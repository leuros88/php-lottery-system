<?php
/**
 * Lottery System - One-shot migration for existing installs.
 *
 * Ensures structures added after the first release exist:
 *   - draw_audits table
 *   - number columns widened to CHAR(4) (3- and 4-digit modes)
 *   - custom_texts: draw_mode (default 'manual')
 *   - custom_texts: number_digits (default '3' = 000-999)
 *   - custom_texts: unique_winners (default '0' = repeat wins allowed)
 *   - custom_texts: site_title (default 'Lottery System', editable in dashboard)
 *   - custom_texts: admin_lang_default (default 'en' = panel UI language)
 *
 * Usage: log in to your admin folder first (see ADMIN_DIR in
 * includes/config.php), then hit /migrate.php in the
 * browser. Safe to run multiple times (all statements are idempotent).
 *
 * IMPORTANT: delete this file after running it.
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$steps = [];

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

if (mysqli_query($conn, $sql_table)) {
    $steps[] = ['ok' => true, 'msg' => 'Table draw_audits is ready.'];
} else {
    $steps[] = ['ok' => false, 'msg' => 'Could not create draw_audits: ' . mysqli_error($conn)];
}

// Widen number columns to CHAR(4) so both 3- and 4-digit modes fit.
$widens = [
    'participants.number' => 'ALTER TABLE participants MODIFY number CHAR(4) NOT NULL',
    'participants.number2' => 'ALTER TABLE participants MODIFY number2 CHAR(4) DEFAULT NULL',
    'participants.number3' => 'ALTER TABLE participants MODIFY number3 CHAR(4) DEFAULT NULL',
    'winners.number' => 'ALTER TABLE winners MODIFY number CHAR(4) DEFAULT NULL',
    'draw_audits.drawn_number' => 'ALTER TABLE draw_audits MODIFY drawn_number CHAR(4) NOT NULL',
    'draw_audits.winning_number' => 'ALTER TABLE draw_audits MODIFY winning_number CHAR(4) DEFAULT NULL',
];
foreach ($widens as $label => $sql) {
    if (mysqli_query($conn, $sql)) {
        $steps[] = ['ok' => true, 'msg' => "Column $label is CHAR(4)."];
    } else {
        $steps[] = ['ok' => false, 'msg' => "Could not widen $label: " . mysqli_error($conn)];
    }
}

if (mysqli_query($conn, "INSERT IGNORE INTO custom_texts (`key`, content) VALUES ('draw_mode', 'manual')")) {
    $steps[] = ['ok' => true, 'msg' => 'Setting draw_mode is ready (default manual).'];
} else {
    $steps[] = ['ok' => false, 'msg' => 'Could not set draw_mode: ' . mysqli_error($conn)];
}

if (mysqli_query($conn, "INSERT IGNORE INTO custom_texts (`key`, content) VALUES ('number_digits', '3')")) {
    $steps[] = ['ok' => true, 'msg' => 'Setting number_digits is ready (default 3 = 000-999).'];
} else {
    $steps[] = ['ok' => false, 'msg' => 'Could not set number_digits: ' . mysqli_error($conn)];
}

if (mysqli_query($conn, "INSERT IGNORE INTO custom_texts (`key`, content) VALUES ('unique_winners', '0')")) {
    $steps[] = ['ok' => true, 'msg' => 'Setting unique_winners is ready (default 0 = repeat wins allowed).'];
} else {
    $steps[] = ['ok' => false, 'msg' => 'Could not set unique_winners: ' . mysqli_error($conn)];
}

if (mysqli_query($conn, "INSERT IGNORE INTO custom_texts (`key`, content) VALUES ('site_title', 'Lottery System')")) {
    $steps[] = ['ok' => true, 'msg' => 'Setting site_title is ready (default Lottery System, editable in dashboard).'];
} else {
    $steps[] = ['ok' => false, 'msg' => 'Could not set site_title: ' . mysqli_error($conn)];
}

if (mysqli_query($conn, "INSERT IGNORE INTO custom_texts (`key`, content) VALUES ('admin_lang_default', 'en')")) {
    $steps[] = ['ok' => true, 'msg' => 'Setting admin_lang_default is ready (default en, editable in dashboard).'];
} else {
    $steps[] = ['ok' => false, 'msg' => 'Could not set admin_lang_default: ' . mysqli_error($conn)];
}

// Read back current values for confirmation.
$current = [];
foreach (['draw_mode', 'number_digits', 'unique_winners', 'site_title', 'admin_lang_default'] as $key) {
    $stmt = mysqli_prepare($conn, 'SELECT content FROM custom_texts WHERE `key` = ?');
    mysqli_stmt_bind_param($stmt, 's', $key);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    $current[$key] = $row ? $row['content'] : '(missing)';
}

$all_ok = true;
foreach ($steps as $s) {
    if (!$s['ok']) {
        $all_ok = false;
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Migration &mdash; Lottery System</title>
    <style>
        body { font-family: -apple-system, "Segoe UI", Roboto, Arial, sans-serif; background: #13233C; color: #333; margin: 0; padding: 40px 15px; }
        .box { max-width: 640px; margin: 0 auto; background: #fff; border-radius: 12px; padding: 28px 30px; }
        h1 { margin: 0 0 6px; color: #13233C; font-size: 1.4rem; }
        p.sub { margin: 0 0 18px; color: #666; font-size: .92rem; }
        .step { border-radius: 7px; padding: 10px 13px; margin-bottom: 8px; font-size: .92rem; }
        .ok { background: #e8f5e9; color: #1b5e20; }
        .fail { background: #ffebee; color: #7f1d1d; }
        code { background: #eaecef; padding: 1px 6px; border-radius: 4px; }
        .current { margin: 16px 0; font-size: .92rem; }
        .warn { background: #fff8e1; border-left: 4px solid #f9a825; color: #6d4c00; padding: 12px 15px; border-radius: 8px; margin-top: 16px; font-size: .9rem; }
        a.btn { display: inline-block; margin-top: 16px; background: #13233C; color: #fff; padding: 11px 24px; border-radius: 8px; text-decoration: none; font-weight: 700; }
    </style>
</head>
<body>
    <div class="box">
        <h1><?= $all_ok ? '&#x2713; Migration complete' : 'Migration finished with errors' ?></h1>
        <p class="sub">Idempotent: safe to re-run. Delete <code>migrate.php</code> when done.</p>
        <?php foreach ($steps as $s): ?>
            <div class="step <?= $s['ok'] ? 'ok' : 'fail' ?>"><?= $s['ok'] ? '&#x2713;' : '&#x2717;' ?> <?= htmlspecialchars($s['msg']) ?></div>
        <?php endforeach; ?>
        <div class="current">
            Current <code>draw_mode</code>: <strong><?= htmlspecialchars($current['draw_mode']) ?></strong><br>
            Current <code>number_digits</code>: <strong><?= htmlspecialchars($current['number_digits']) ?></strong>
            (3 = 000-999, 4 = 0000-9999)<br>
            Current <code>unique_winners</code>: <strong><?= htmlspecialchars($current['unique_winners']) ?></strong>
            (0 = repeat wins allowed, 1 = unique winner)<br>
            Current <code>site_title</code>: <strong><?= htmlspecialchars($current['site_title']) ?></strong><br>
            Current <code>admin_lang_default</code>: <strong><?= htmlspecialchars($current['admin_lang_default']) ?></strong>
            (en = English, es = Español, de = Deutsch, pt = Português, fr = Français)
        </div>
        <a class="btn" href="<?= adminUrl('dashboard.php') ?>">Go to Dashboard &rarr;</a>
        <div class="warn"><strong>&#x26A0; Important:</strong> delete <code>migrate.php</code> now, just like <code>install.php</code>.</div>
    </div>
</body>
</html>
