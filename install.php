<?php
/**
 * Lottery System - Installer
 *
 * Creates the database, imports sql/schema.sql, creates the admin user
 * and saves the DB credentials into includes/config.php.
 * DB defaults shown in the form are read from includes/config.php.
 *
 * PREFER NOT TO USE THIS INSTALLER?
 * Import the schema manually instead:
 *   mysql -u YOUR_USER -p < sql/schema.sql
 * (or via phpMyAdmin -> Import). The schema already includes a test admin
 * user (admin / admin2026): change its password after logging in.
 * Then edit includes/config.php with your credentials.
 *
 * IMPORTANT: delete this file (or uncomment the install.php rule in
 * .htaccess) after the installation.
 */

// --- Read DB defaults from includes/config.php (without connecting) ---
function configDefault($key, $fallback) {
    $file = __DIR__ . '/includes/config.php';
    if (!is_readable($file)) {
        return $fallback;
    }
    $content = file_get_contents($file);
    if (preg_match("/define\(\s*'" . preg_quote($key, '/') . "'\s*,\s*'((?:[^'\\\\]|\\\\.)*)'\s*\)/", $content, $m)) {
        return stripcslashes($m[1]);
    }
    if (preg_match('/define\(\s*\'' . preg_quote($key, '/') . '\'\s*,\s*"((?:[^"\\\\]|\\\\.)*)"\s*\)/', $content, $m)) {
        return stripcslashes($m[1]);
    }
    return $fallback;
}

// --- Split a SQL dump into statements, ignoring semicolons that appear
// inside single/double-quoted strings and inside comments.
// Handles '' (doubled quote) and backslash escapes.
function splitSqlStatements($sql) {
    $statements = [];
    $current = '';
    $len = strlen($sql);
    $quote = null; // null, "'" or '"'
    $i = 0;
    while ($i < $len) {
        $ch = $sql[$i];
        $next = ($i + 1 < $len) ? $sql[$i + 1] : '';

        if ($quote !== null) {
            $current .= $ch;
            if ($ch === '\\' && $i + 1 < $len) {
                // Backslash escape: keep the escaped char literally
                $current .= $next;
                $i += 2;
                continue;
            }
            if ($ch === $quote) {
                if ($next === $quote) {
                    // Doubled quote '' inside string: keep both, stay in string
                    $current .= $next;
                    $i += 2;
                    continue;
                }
                $quote = null; // end of quoted string
            }
            $i++;
            continue;
        }

        // Not inside a string: check for comments
        if ($ch === '-' && $next === '-' && isset($sql[$i + 2]) && ($sql[$i + 2] === ' ' || $sql[$i + 2] === "\t" || $sql[$i + 2] === "\n" || $sql[$i + 2] === "\r")) {
            // Line comment: skip until end of line
            while ($i < $len && $sql[$i] !== "\n") {
                $i++;
            }
            continue;
        }
        if ($ch === '/' && $next === '*') {
            // Block comment: skip until */
            $i += 2;
            while ($i < $len && !($sql[$i] === '*' && isset($sql[$i + 1]) && $sql[$i + 1] === '/')) {
                $i++;
            }
            $i += 2;
            continue;
        }
        if ($ch === '#' && ($current === '' || substr(rtrim($current), -1) === "\n" || trim($current) === '')) {
            while ($i < $len && $sql[$i] !== "\n") {
                $i++;
            }
            continue;
        }

        if ($ch === "'" || $ch === '"') {
            $quote = $ch;
            $current .= $ch;
            $i++;
            continue;
        }

        if ($ch === ';') {
            $stmt = trim($current);
            if ($stmt !== '') {
                $statements[] = $stmt;
            }
            $current = '';
            $i++;
            continue;
        }

        $current .= $ch;
        $i++;
    }
    $stmt = trim($current);
    if ($stmt !== '') {
        $statements[] = $stmt;
    }
    return $statements;
}

// --- Write DB credentials into includes/config.php ---
// Replaces the DB_HOST/DB_USER/DB_PASS/DB_NAME define lines, keeping
// everything else in the file untouched.
// Returns [true, ''] on success or [false, $reason] on failure.
function updateConfigDb($file, $host, $user, $pass, $name) {
    if (!is_file($file) || !is_readable($file)) {
        return [false, 'includes/config.php not found or not readable.'];
    }
    if (!is_writable($file)) {
        return [false, 'includes/config.php is not writable by the web server. '
            . 'Make it writable (e.g. chmod 666 includes/config.php), re-run the installer, '
            . 'then restore strict permissions.'];
    }
    $content = file_get_contents($file);
    $map = [
        'DB_HOST' => $host,
        'DB_USER' => $user,
        'DB_PASS' => $pass,
        'DB_NAME' => $name,
    ];
    foreach ($map as $key => $value) {
        $escaped = addcslashes($value, "\\'");
        $replacement = "define('$key', '$escaped');";
        $pattern = "/define\(\s*'" . preg_quote($key, '/') . "'\s*,\s*('(?:[^'\\\\]|\\\\.)*'|\"(?:[^\"\\\\]|\\\\.)*\")\s*\)\s*;/";
        $content = preg_replace($pattern, $replacement, $content, 1, $count);
        if ($count === 0) {
            return [false, "could not find define('$key', ...) in includes/config.php."];
        }
    }
    if (file_put_contents($file, $content, LOCK_EX) === false) {
        return [false, 'could not write to includes/config.php.'];
    }
    return [true, ''];
}

// --- Ensure draw-mode structures exist (idempotent upgrade step) ---
// Creates draw_audits + draw_mode default on both fresh installs and
// upgrades of existing databases. Safe to run multiple times.
// Returns [true, $message] on success or [false, $reason] on failure.
function ensureDrawModeTables($conn) {
    $sql_table = "CREATE TABLE IF NOT EXISTS draw_audits (
        id INT AUTO_INCREMENT PRIMARY KEY,
        prize_id INT NOT NULL,
        mode VARCHAR(20) NOT NULL,
        drawn_number CHAR(4) NOT NULL,
        winning_number CHAR(4) DEFAULT NULL,
        participant_id INT DEFAULT NULL,
        provider VARCHAR(20) DEFAULT NULL,
        external_raw VARCHAR(255) DEFAULT NULL,
        proof_hash CHAR(64) NOT NULL,
        admin_id INT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        confirmed_at TIMESTAMP NULL,
        FOREIGN KEY (prize_id) REFERENCES prizes(id) ON DELETE CASCADE,
        INDEX idx_draw_prize (prize_id),
        INDEX idx_draw_confirmed (confirmed_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8";
    if (!mysqli_query($conn, $sql_table)) {
        return [false, 'could not create draw_audits: ' . mysqli_error($conn)];
    }
    // Widen number columns to CHAR(4) so both 3- and 4-digit modes fit
    // (CREATE TABLE IF NOT EXISTS above does not alter pre-existing tables).
    $widens = [
        'ALTER TABLE participants MODIFY number CHAR(4) NOT NULL',
        'ALTER TABLE participants MODIFY number2 CHAR(4) DEFAULT NULL',
        'ALTER TABLE participants MODIFY number3 CHAR(4) DEFAULT NULL',
        'ALTER TABLE winners MODIFY number CHAR(4) DEFAULT NULL',
        'ALTER TABLE draw_audits MODIFY drawn_number CHAR(4) NOT NULL',
        'ALTER TABLE draw_audits MODIFY winning_number CHAR(4) DEFAULT NULL',
    ];
    foreach ($widens as $sql) {
        if (!mysqli_query($conn, $sql)) {
            return [false, 'could not widen number columns (' . $sql . '): ' . mysqli_error($conn)];
        }
    }
    if (!mysqli_query($conn, "INSERT IGNORE INTO custom_texts (`key`, content) VALUES ('draw_mode', 'manual')")) {
        return [false, 'could not set draw_mode: ' . mysqli_error($conn)];
    }
    if (!mysqli_query($conn, "INSERT IGNORE INTO custom_texts (`key`, content) VALUES ('number_digits', '3')")) {
        return [false, 'could not set number_digits: ' . mysqli_error($conn)];
    }
    if (!mysqli_query($conn, "INSERT IGNORE INTO custom_texts (`key`, content) VALUES ('unique_winners', '0')")) {
        return [false, 'could not set unique_winners: ' . mysqli_error($conn)];
    }
    if (!mysqli_query($conn, "INSERT IGNORE INTO custom_texts (`key`, content) VALUES ('admin_lang_default', 'en')")) {
        return [false, 'could not set admin_lang_default: ' . mysqli_error($conn)];
    }
    if (!mysqli_query($conn, "INSERT IGNORE INTO custom_texts (`key`, content) VALUES ('site_title', 'Lottery System')")) {
        return [false, 'could not set site_title: ' . mysqli_error($conn)];
    }
    return [true, 'Draw mode structures ready (draw_audits + draw_mode + number_digits + unique_winners + admin_lang_default).'];
}

$defaults = [
    'host' => configDefault('DB_HOST', 'localhost'),
    'user' => configDefault('DB_USER', 'root'),
    'pass' => configDefault('DB_PASS', ''),
    'name' => configDefault('DB_NAME', 'lottery_example'),
    'admin_dir' => configDefault('ADMIN_DIR', 'admin'),
];

$schema_file = __DIR__ . '/sql/schema.sql';
$errors = [];
$success = [];
$ran = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ran = true;
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    $db_host = trim($_POST['db_host'] ?? '');
    $db_user = trim($_POST['db_user'] ?? '');
    $db_pass = $_POST['db_pass'] ?? ''; // password: do not trim
    $db_name = trim($_POST['db_name'] ?? '');
    $admin_user = trim($_POST['admin_user'] ?? '');
    $admin_pass = $_POST['admin_pass'] ?? '';
    $site_title = trim($_POST['site_title'] ?? 'Lottery System');

    if ($db_host === '' || $db_user === '' || $db_name === '') {
        $errors[] = 'Database host, user and name are required.';
    }
    if (!preg_match('/^[A-Za-z0-9_]+$/', $db_name)) {
        $errors[] = 'Database name may only contain letters, numbers and underscores.';
    }
    if ($admin_user === '' || $admin_pass === '') {
        $errors[] = 'Admin username and password are required.';
    } elseif (strlen($admin_pass) < 6) {
        $errors[] = 'Admin password must be at least 6 characters.';
    }
    if ($site_title === '') {
        $errors[] = 'Lottery name (site title) is required.';
    } elseif (mb_strlen($site_title) > 200) {
        $errors[] = 'Lottery name must be 200 characters or fewer.';
    }
    if (!is_readable($schema_file)) {
        $errors[] = 'Schema file not found: sql/schema.sql';
    }

    if (empty($errors)) {
        try {
            // 1. Connect to the MySQL server (no database selected yet)
            $conn = mysqli_connect($db_host, $db_user, $db_pass);
            mysqli_set_charset($conn, 'utf8');

            // 2. Create database
            mysqli_query($conn, "CREATE DATABASE IF NOT EXISTS `$db_name` DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci");
            $success[] = "Database `$db_name` is ready.";
            mysqli_select_db($conn, $db_name);

            // 3. Import sql/schema.sql statement by statement.
            // NOTE: cannot simply explode(';') because data contains
            // semicolons inside quoted strings (e.g. '&copy;').
            $sql = file_get_contents($schema_file);
            $statements = splitSqlStatements($sql);

            // Skip USE / CREATE DATABASE from the file: we already handle the DB above
            $executed = 0;
            foreach ($statements as $stmt) {
                if ($stmt === '' || preg_match('/^(USE|CREATE\s+DATABASE)\b/i', $stmt)) {
                    continue;
                }
                mysqli_query($conn, $stmt);
                $executed++;
            }
            // Make sure we are on the right DB (in case the file contained USE)
            mysqli_select_db($conn, $db_name);
            $success[] = "Schema imported ($executed statements executed).";

            // 3b. Ensure draw-mode structures (also upgrades existing DBs
            // whose schema.sql predates the draw feature).
            [$draw_ok, $draw_msg] = ensureDrawModeTables($conn);
            if ($draw_ok) {
                $success[] = $draw_msg;
            } else {
                $errors[] = 'Draw mode migration failed: ' . $draw_msg;
            }

            // 4. Create or update the admin user (hashed with password_hash)
            $hash = password_hash($admin_pass, PASSWORD_DEFAULT);
            $stmt = mysqli_prepare($conn, 'SELECT id FROM administrators WHERE username = ?');
            mysqli_stmt_bind_param($stmt, 's', $admin_user);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_store_result($stmt);
            $exists = mysqli_stmt_num_rows($stmt) > 0;
            mysqli_stmt_close($stmt);

            if ($exists) {
                $stmt = mysqli_prepare($conn, 'UPDATE administrators SET password = ? WHERE username = ?');
                mysqli_stmt_bind_param($stmt, 'ss', $hash, $admin_user);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                $success[] = "Admin user '$admin_user' already existed: password updated.";
            } else {
                $stmt = mysqli_prepare($conn, 'INSERT INTO administrators (username, password) VALUES (?, ?)');
                mysqli_stmt_bind_param($stmt, 'ss', $admin_user, $hash);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                $success[] = "Admin user '$admin_user' created.";
            }

            // 4b. Save the lottery name (site title shown in the public header).
            $stmt = mysqli_prepare($conn, "REPLACE INTO custom_texts (`key`, content) VALUES ('site_title', ?)");
            mysqli_stmt_bind_param($stmt, 's', $site_title);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            $success[] = "Lottery name set to '$site_title' (editable later in the dashboard).";

            // 5. Sync includes/config.php with the installed credentials
            $config_file = __DIR__ . '/includes/config.php';
            $mismatch = ($db_host !== $defaults['host'] || $db_user !== $defaults['user']
                || $db_pass !== $defaults['pass'] || $db_name !== $defaults['name']);
            if (!$mismatch) {
                $success[] = 'includes/config.php already matches the installed credentials.';
            } else {
                [$ok, $msg] = updateConfigDb($config_file, $db_host, $db_user, $db_pass, $db_name);
                if ($ok) {
                    $success[] = 'includes/config.php updated with the installed credentials.';
                    $defaults = ['host' => $db_host, 'user' => $db_user, 'pass' => $db_pass, 'name' => $db_name, 'admin_dir' => $defaults['admin_dir']];
                } else {
                    $errors[] = 'Installation used credentials that DIFFER from includes/config.php '
                        . 'and the file could not be updated automatically (' . $msg . ') '
                        . 'Update DB_HOST/DB_USER/DB_PASS/DB_NAME in includes/config.php manually or the site will not connect.';
                }
            }

            mysqli_close($conn);
        } catch (mysqli_sql_exception $e) {
            $errors[] = 'MySQL error: ' . $e->getMessage();
        }
    }
}

$finished_ok = ($ran && empty($errors));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Install &mdash; Lottery System</title>
    <style>
        *, *:before, *:after { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 40px 15px 60px;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            background: #13233C;
            background: linear-gradient(160deg, #0d1929 0%, #13233C 45%, #1e3a5f 100%);
            min-height: 100vh;
        }
        .installer {
            max-width: 680px;
            margin: 0 auto;
            background: #fff;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0, 0, 0, .45);
        }
        .inst-header {
            background: linear-gradient(135deg, #13233C 0%, #1e3a5f 100%);
            color: #fff;
            padding: 34px 34px 28px;
            text-align: center;
            border-bottom: 4px solid #ffd600;
        }
        .inst-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 64px;
            height: 64px;
            font-size: 2rem;
            background: #ffd600;
            border-radius: 50%;
            margin-bottom: 12px;
            box-shadow: 0 4px 14px rgba(255, 214, 0, .35);
        }
        .inst-header h1 {
            margin: 0 0 4px;
            font-size: 1.7rem;
            letter-spacing: .3px;
        }
        .inst-header p {
            margin: 0;
            opacity: .8;
            font-size: .95rem;
        }
        .steps {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0;
            padding: 20px 20px 4px;
            list-style: none;
            margin: 0;
        }
        .steps li {
            display: flex;
            align-items: center;
            font-size: .8rem;
            font-weight: 600;
            color: #999;
        }
        .steps .dot {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: #eaecef;
            color: #888;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: .8rem;
            margin-right: 7px;
            flex-shrink: 0;
        }
        .steps li.active { color: #13233C; }
        .steps li.active .dot { background: #13233C; color: #ffd600; }
        .steps li.done { color: #2e7d32; }
        .steps li.done .dot { background: #2e7d32; color: #fff; }
        .steps .sep {
            width: 44px;
            height: 2px;
            background: #e0e0e0;
            margin: 0 14px;
        }
        .inst-body { padding: 22px 34px 30px; }
        .alert {
            border-radius: 8px;
            padding: 12px 15px;
            margin: 0 0 14px;
            font-size: .92rem;
        }
        .alert ul { margin: 6px 0 0 18px; padding: 0; }
        .alert-error { background: #ffebee; border-left: 4px solid #c62828; color: #7f1d1d; }
        .alert-success { background: #e8f5e9; border-left: 4px solid #2e7d32; color: #1b5e20; }
        .alert-warning { background: #fff8e1; border-left: 4px solid #f9a825; color: #6d4c00; }
        .alert-danger { background: #ffebee; border: 1px solid #ef9a9a; border-left: 4px solid #c62828; color: #7f1d1d; }
        .section-card {
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            padding: 18px 20px 20px;
            margin: 0 0 18px;
            background: #fafbfc;
        }
        .section-card h2 {
            margin: 0 0 4px;
            font-size: 1.05rem;
            color: #13233C;
        }
        .section-card .hint {
            margin: 0 0 12px;
            font-size: .85rem;
            color: #777;
        }
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 0 16px; }
        .field { margin-bottom: 12px; }
        .field label {
            display: block;
            margin-bottom: 4px;
            font-weight: 600;
            font-size: .88rem;
            color: #13233C;
        }
        .field input {
            width: 100%;
            padding: 10px 12px;
            font-size: .95rem;
            font-family: inherit;
            border: 1px solid #cfd6dd;
            border-radius: 7px;
            background: #fff;
            transition: border-color .15s, box-shadow .15s;
        }
        .field input:focus {
            outline: none;
            border-color: #13233C;
            box-shadow: 0 0 0 3px rgba(19, 35, 60, .12);
        }
        code {
            background: #eaecef;
            padding: 1px 6px;
            border-radius: 4px;
            font-size: .86em;
        }
        .alert code { background: rgba(0, 0, 0, .07); }
        pre {
            background: #13233C;
            color: #ffd600;
            padding: 12px 14px;
            border-radius: 7px;
            overflow-x: auto;
            font-size: .85rem;
            margin: 8px 0 0;
        }
        details.manual {
            border: 1px dashed #b0b8c1;
            border-radius: 8px;
            padding: 10px 15px;
            margin-bottom: 20px;
            font-size: .9rem;
            color: #555;
            background: #fff;
        }
        details.manual summary {
            cursor: pointer;
            font-weight: 600;
            color: #13233C;
        }
        .btn {
            display: inline-block;
            padding: 13px 34px;
            font-size: 1rem;
            font-weight: 700;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            transition: transform .1s, box-shadow .15s, background .15s;
        }
        .btn-primary { background: #13233C; color: #fff; width: 100%; }
        .btn-primary:hover { background: #1e3a5f; box-shadow: 0 6px 18px rgba(19, 35, 60, .35); }
        .btn-primary:active { transform: translateY(1px); }
        .btn-success { background: #2e7d32; color: #fff; }
        .btn-success:hover { background: #388e3c; }
        .success-hero { text-align: center; padding: 8px 0 4px; }
        .success-hero .check {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 72px;
            height: 72px;
            font-size: 2.4rem;
            color: #fff;
            background: #2e7d32;
            border-radius: 50%;
            box-shadow: 0 6px 20px rgba(46, 125, 50, .4);
            margin-bottom: 10px;
        }
        .success-hero h2 { margin: 0 0 4px; color: #1b5e20; font-size: 1.4rem; }
        .success-hero p { margin: 0 0 6px; color: #555; }
        .done-list { list-style: none; margin: 14px 0; padding: 0; }
        .done-list li {
            background: #e8f5e9;
            border-radius: 7px;
            padding: 9px 13px;
            margin-bottom: 8px;
            font-size: .9rem;
            color: #1b5e20;
        }
        .done-list li:before { content: "\2713  "; font-weight: 700; }
        .center { text-align: center; margin-top: 16px; }
        .inst-footer {
            text-align: center;
            padding: 14px;
            font-size: .8rem;
            color: #8a97a5;
            background: #f4f6f8;
            border-top: 1px solid #e0e0e0;
        }
        @media (max-width: 560px) {
            body { padding: 20px 10px 40px; }
            .inst-body { padding: 18px 18px 24px; }
            .inst-header { padding: 26px 20px 22px; }
            .grid-2 { grid-template-columns: 1fr; }
            .steps .sep { width: 20px; margin: 0 8px; }
            .steps li span.lbl { display: none; }
        }
    </style>
</head>
<body>
    <div class="installer">
        <div class="inst-header">
            <div class="inst-badge">&#x1F3AB;</div>
            <h1>Lottery System</h1>
            <p>Installation wizard &mdash; up and running in a minute</p>
        </div>

        <ul class="steps">
            <li class="<?= $finished_ok ? 'done' : 'active' ?>"><span class="dot"><?= $finished_ok ? '&#x2713;' : '1' ?></span><span class="lbl">Configure</span></li>
            <li class="sep"></li>
            <li class="<?= $finished_ok ? 'done' : '' ?>"><span class="dot"><?= $finished_ok ? '&#x2713;' : '2' ?></span><span class="lbl">Install</span></li>
            <li class="sep"></li>
            <li class="<?= $finished_ok ? 'active' : '' ?>"><span class="dot">3</span><span class="lbl">Done</span></li>
        </ul>

        <div class="inst-body">
        <?php if ($finished_ok): ?>
            <div class="success-hero">
                <div class="check">&#x2713;</div>
                <h2>Installation complete!</h2>
                <p>Your lottery site is ready to use.</p>
            </div>
            <ul class="done-list">
                <?php foreach ($success as $s): ?>
                    <li><?= htmlspecialchars($s) ?></li>
                <?php endforeach; ?>
            </ul>
            <div class="center">
                <a href="/<?= htmlspecialchars($defaults['admin_dir']) ?>/" class="btn btn-success">Go to Admin Panel &rarr;</a>
            </div>
            <div class="alert alert-danger" style="margin-top:18px;">
                <strong>&#x26A0; Important:</strong> delete <code>install.php</code> now,
                or uncomment the <code>install.php</code> rule in <code>.htaccess</code> to block it.
            </div>
        <?php else: ?>
            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <strong>Something went wrong:</strong>
                    <ul>
                        <?php foreach ($errors as $e): ?>
                            <li><?= htmlspecialchars($e) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            <?php if (!empty($success)): ?>
                <div class="alert alert-success">
                    <ul>
                        <?php foreach ($success as $s): ?>
                            <li><?= htmlspecialchars($s) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <details class="manual">
                <summary>Prefer a manual install?</summary>
                <p style="margin:10px 0 0;">
                    You don't need this page: import <code>sql/schema.sql</code> into MySQL yourself, e.g.
                </p>
                <pre>mysql -u YOUR_USER -p &lt; sql/schema.sql</pre>
                <p style="margin:8px 0 0;">
                    (or phpMyAdmin &rarr; Import). The schema already includes a test admin
                    user (<code>admin</code> / <code>admin2026</code>): change its password
                    after logging in. Then edit <code>includes/config.php</code>
                    (DB credentials + <code>BACKUP_DIR</code>) and delete this file.
                </p>
            </details>

            <form method="post">
                <div class="section-card">
                    <h2>&#x1F5C4;&#xFE0F; Database</h2>
                    <p class="hint">Defaults come from <code>includes/config.php</code>. The installer will save these values there automatically.</p>
                    <div class="grid-2">
                        <div class="field">
                            <label for="db_host">Host</label>
                            <input type="text" id="db_host" name="db_host" value="<?= htmlspecialchars($_POST['db_host'] ?? $defaults['host']) ?>" required autocomplete="off">
                        </div>
                        <div class="field">
                            <label for="db_name">Database name</label>
                            <input type="text" id="db_name" name="db_name" value="<?= htmlspecialchars($_POST['db_name'] ?? $defaults['name']) ?>" required autocomplete="off">
                        </div>
                    </div>
                    <div class="grid-2">
                        <div class="field">
                            <label for="db_user">User</label>
                            <input type="text" id="db_user" name="db_user" value="<?= htmlspecialchars($_POST['db_user'] ?? $defaults['user']) ?>" required autocomplete="off">
                        </div>
                        <div class="field">
                            <label for="db_pass">Password</label>
                            <input type="password" id="db_pass" name="db_pass" value="<?= htmlspecialchars($_POST['db_pass'] ?? $defaults['pass']) ?>" autocomplete="new-password">
                        </div>
                    </div>
                </div>

                <div class="section-card">
                    <h2>&#x1F3AB; Lottery</h2>
                    <p class="hint">Name shown in the public page header (<code>&lt;title&gt;</code> + banner). You can change it later in the dashboard.</p>
                    <div class="field">
                        <label for="site_title">Lottery name</label>
                        <input type="text" id="site_title" name="site_title" value="<?= htmlspecialchars($_POST['site_title'] ?? 'Lottery System') ?>" required maxlength="200" autocomplete="off" placeholder="Mi loteria">
                    </div>
                </div>

                <div class="section-card">
                    <h2>&#x1F464; Administrator</h2>
                    <p class="hint">Account to access <code>/<?= htmlspecialchars($defaults['admin_dir']) ?>/</code>. You can rename that folder for extra security (see README).</p>
                    <div class="grid-2">
                        <div class="field">
                            <label for="admin_user">Username</label>
                            <input type="text" id="admin_user" name="admin_user" value="<?= htmlspecialchars($_POST['admin_user'] ?? 'admin') ?>" required autocomplete="off">
                        </div>
                        <div class="field">
                            <label for="admin_pass">Password (min. 6 characters)</label>
                            <input type="password" id="admin_pass" name="admin_pass" value="<?= htmlspecialchars($_POST['admin_pass'] ?? 'admin2026') ?>" required minlength="6" autocomplete="new-password">
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">Run Installation &rarr;</button>
            </form>
        <?php endif; ?>
        </div>

        <div class="inst-footer">Lottery System installer &mdash; delete this file after installation</div>
    </div>
</body>
</html>
