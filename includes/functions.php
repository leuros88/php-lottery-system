<?php
require_once __DIR__ . '/config.php';

// Fixed author attribution (hardcoded in config.php: AUTHOR_NAME / AUTHOR_GITHUB_URL).
// Intentionally NOT stored in custom_texts nor in lang files, so it cannot be
// edited or removed from the admin panel. Returns a safe HTML link.
function renderAuthorCredit() {
    $name = defined('AUTHOR_NAME') ? AUTHOR_NAME : 'xxxx';
    $url = defined('AUTHOR_GITHUB_URL') ? AUTHOR_GITHUB_URL : 'https://google.es';
    return '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener">'
        . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '</a>';
}

// Full attribution line in the current UI language (prefix is translatable,
// author name + URL stay fixed from config.php). The link HTML comes from
// renderAuthorCredit() (already escaped); the prefix comes from our own
// lang files (trusted). Safe to echo without further escaping.
function renderAuthorCreditLine() {
    return t('common.developed_by', ['author' => renderAuthorCredit()]);
}

function isBlacklisted($conn, $name) {
    $stmt = mysqli_prepare($conn, 'SELECT id FROM blacklist WHERE name = ?');
    mysqli_stmt_bind_param($stmt, 's', $name);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);
    $count = mysqli_stmt_num_rows($stmt);
    mysqli_stmt_close($stmt);
    return $count > 0;
}

function isParticipantRegistered($conn, $name) {
    $stmt = mysqli_prepare($conn, 'SELECT id FROM participants WHERE name = ?');
    mysqli_stmt_bind_param($stmt, 's', $name);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);
    $count = mysqli_stmt_num_rows($stmt);
    mysqli_stmt_close($stmt);
    return $count > 0;
}

function isNumberTaken($conn, $number) {
    $stmt = mysqli_prepare($conn, 'SELECT id FROM participants WHERE number = ? OR number2 = ? OR number3 = ?');
    mysqli_stmt_bind_param($stmt, 'sss', $number, $number, $number);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);
    $count = mysqli_stmt_num_rows($stmt);
    mysqli_stmt_close($stmt);
    return $count > 0;
}

function isNumberTakenExcluding($conn, $number, $exclude_id) {
    $stmt = mysqli_prepare($conn, 'SELECT id FROM participants WHERE (number = ? OR number2 = ? OR number3 = ?) AND id != ?');
    mysqli_stmt_bind_param($stmt, 'sssi', $number, $number, $number, $exclude_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);
    $count = mysqli_stmt_num_rows($stmt);
    mysqli_stmt_close($stmt);
    return $count > 0;
}

function getAvailableNumbers($conn) {
    $digits = getNumberDigits($conn);
    $max = getNumberRangeMax($digits);
    $result = mysqli_query($conn, 'SELECT number, number2, number3 FROM participants');
    $taken = [];
    while ($row = mysqli_fetch_assoc($result)) {
        if ($row['number']) $taken[] = $row['number'];
        if ($row['number2']) $taken[] = $row['number2'];
        if ($row['number3']) $taken[] = $row['number3'];
    }

    $available = [];
    for ($i = 0; $i <= $max; $i++) {
        $num = str_pad($i, $digits, '0', STR_PAD_LEFT);
        if (!in_array($num, $taken)) {
            $available[] = $num;
        }
    }
    return $available;
}

function getRandomAvailableNumber($conn) {
    $available = getAvailableNumbers($conn);
    if (empty($available)) {
        return false;
    }
    return $available[array_rand($available)];
}

function getNumbersByRange($conn) {
    $digits = getNumberDigits($conn);
    $max = getNumberRangeMax($digits);
    $result = mysqli_query($conn, 'SELECT name, number, number2, number3 FROM participants ORDER BY name');
    $participants = [];
    while ($row = mysqli_fetch_assoc($result)) {
        if ($row['number']) $participants[$row['number']] = $row['name'];
        if ($row['number2']) $participants[$row['number2']] = $row['name'];
        if ($row['number3']) $participants[$row['number3']] = $row['name'];
    }

    // 3 digits -> 10 blocks of 100 (000-099, ...). 4 digits -> 10 blocks of 1000 (0000-0999, ...).
    $block_size = ($digits === 4) ? 1000 : 100;
    $ranges = [];
    for ($start = 0; $start <= $max; $start += $block_size) {
        $end = min($start + $block_size - 1, $max);
        $numbers = [];
        for ($j = $start; $j <= $end; $j++) {
            $num = str_pad($j, $digits, '0', STR_PAD_LEFT);
            $numbers[$num] = isset($participants[$num]) ? $participants[$num] : null;
        }
        $ranges[] = [
            'start' => str_pad($start, $digits, '0', STR_PAD_LEFT),
            'end' => str_pad($end, $digits, '0', STR_PAD_LEFT),
            'numbers' => $numbers,
        ];
    }
    return $ranges;
}

function getParticipantCount($conn) {
    $result = mysqli_query($conn, 'SELECT COUNT(*) AS count FROM participants');
    $row = mysqli_fetch_assoc($result);
    return $row['count'];
}

function getBlacklistCount($conn) {
    $result = mysqli_query($conn, 'SELECT COUNT(*) AS count FROM blacklist');
    $row = mysqli_fetch_assoc($result);
    return $row['count'];
}

function getWinnerCount($conn) {
    $result = mysqli_query($conn, 'SELECT COUNT(*) AS count FROM winners');
    $row = mysqli_fetch_assoc($result);
    return $row['count'];
}

function getActiveNews($conn) {
    $result = mysqli_query($conn, 'SELECT * FROM news WHERE is_active = 1 ORDER BY created_at DESC LIMIT 10');
    $news = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $news[] = $row;
    }
    return $news;
}

function getCustomText($conn, $key) {
    $stmt = mysqli_prepare($conn, 'SELECT content FROM custom_texts WHERE `key` = ?');
    mysqli_stmt_bind_param($stmt, 's', $key);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    return $row ? $row['content'] : '';
}

// ---- Site title (editable header name, e.g. "Loteria pepe", "Mi loteria") ----
// Stored in custom_texts as site_title. Falls back to SITE_NAME when empty/missing.

function getSiteTitle($conn) {
    $title = trim((string)getCustomText($conn, 'site_title'));
    if ($title === '') {
        return defined('SITE_NAME') ? SITE_NAME : 'Lottery System';
    }
    return $title;
}

function setSiteTitle($conn, $title) {
    $title = trim((string)$title);
    if ($title === '' || mb_strlen($title) > 200) {
        return false;
    }
    $stmt = mysqli_prepare($conn, "REPLACE INTO custom_texts (`key`, content) VALUES ('site_title', ?)");
    mysqli_stmt_bind_param($stmt, 's', $title);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $ok;
}

function getWinnersWithDetails($conn) {
    $sql = 'SELECT w.id, w.prize_id, w.participant_id, COALESCE(w.number, p.number) AS winning_number, p.name AS participant_name, p.number, p.number2, p.number3, pr.name AS prize_name, pr.position, w.created_at
            FROM winners w
            JOIN participants p ON w.participant_id = p.id
            JOIN prizes pr ON w.prize_id = pr.id
            ORDER BY pr.position ASC';
    $result = mysqli_query($conn, $sql);
    $winners = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $winners[] = $row;
    }
    return $winners;
}

function getAvailableParticipantsForDraw($conn) {
    if (isUniqueWinnersOnly($conn)) {
        $sql = 'SELECT p.id, p.name, p.number, p.number2, p.number3
                FROM participants p
                LEFT JOIN winners w ON p.id = w.participant_id
                WHERE w.id IS NULL
                ORDER BY p.name ASC';
    } else {
        $sql = 'SELECT p.id, p.name, p.number, p.number2, p.number3
                FROM participants p
                ORDER BY p.name ASC';
    }
    $result = mysqli_query($conn, $sql);
    $participants = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $participants[] = $row;
    }
    return $participants;
}

function searchParticipants($conn, $search) {
    $searchTerm = '%' . $search . '%';
    if (isUniqueWinnersOnly($conn)) {
        $sql = 'SELECT p.id, p.name, p.number, p.number2, p.number3
                FROM participants p
                LEFT JOIN winners w ON p.id = w.participant_id
                WHERE w.id IS NULL AND p.name LIKE ?
                ORDER BY p.name ASC
                LIMIT 50';
    } else {
        $sql = 'SELECT p.id, p.name, p.number, p.number2, p.number3
                FROM participants p
                WHERE p.name LIKE ?
                ORDER BY p.name ASC
                LIMIT 50';
    }
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 's', $searchTerm);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $participants = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $participants[] = $row;
    }
    mysqli_stmt_close($stmt);
    return $participants;
}

function renderTemplate($conn, $key, $placeholders, $default) {
    $template = getCustomText($conn, $key);
    if (empty($template)) {
        $template = $default;
    }
    foreach ($placeholders as $k => $v) {
        $template = str_replace('{' . $k . '}', $v, $template);
    }
    return $template;
}

function getActivePrizes($conn) {
    $result = mysqli_query($conn, 'SELECT * FROM prizes WHERE is_active = 1 ORDER BY position ASC');
    $prizes = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $prizes[] = $row;
    }
    return $prizes;
}

function getPendingPrizes($conn) {
    $sql = 'SELECT pr.*
            FROM prizes pr
            WHERE pr.is_active = 1
            AND pr.id NOT IN (SELECT prize_id FROM winners)
            ORDER BY pr.position ASC';
    $result = mysqli_query($conn, $sql);
    $prizes = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $prizes[] = $row;
    }
    return $prizes;
}

// ---- Number digits (3 or 4) ----
// Stored in custom_texts as number_digits = '3' (default, 000-999)
// or '4' (0000-9999). Number columns are CHAR(4) so both modes fit.

function getValidNumberDigits() {
    return [3, 4];
}

function getNumberDigits($conn) {
    $v = (int)getCustomText($conn, 'number_digits');
    if (!in_array($v, getValidNumberDigits(), true)) {
        return 3;
    }
    return $v;
}

function getNumberRangeMax($digits) {
    return ((int)$digits === 4) ? 9999 : 999;
}

function numberDigitsLabel($digits) {
    if ((int)$digits === 4) {
        return t('func.digits_4');
    }
    return t('func.digits_3');
}

function formatLotteryNumber($n, $digits = 3) {
    $digits = ((int)$digits === 4) ? 4 : 3;
    return str_pad(abs((int)$n), $digits, '0', STR_PAD_LEFT);
}

// Widen number columns to CHAR(4) so both modes fit. Idempotent.
function ensureNumberColumns($conn) {
    $alters = [
        'ALTER TABLE participants MODIFY number CHAR(4) NOT NULL',
        'ALTER TABLE participants MODIFY number2 CHAR(4) DEFAULT NULL',
        'ALTER TABLE participants MODIFY number3 CHAR(4) DEFAULT NULL',
        'ALTER TABLE winners MODIFY number CHAR(4) DEFAULT NULL',
    ];
    foreach ($alters as $sql) {
        if (!mysqli_query($conn, $sql)) {
            return false;
        }
    }
    if (drawTableExists($conn)) {
        if (!mysqli_query($conn, 'ALTER TABLE draw_audits MODIFY drawn_number CHAR(4) NOT NULL')) {
            return false;
        }
        if (!mysqli_query($conn, 'ALTER TABLE draw_audits MODIFY winning_number CHAR(4) DEFAULT NULL')) {
            return false;
        }
    }
    return true;
}

// Count stored numbers above 999 (blocks a 4 -> 3 switch).
function countNumbersAbove999($conn) {
    $total = 0;
    $checks = [
        'participants|number', 'participants|number2', 'participants|number3',
        'winners|number',
    ];
    foreach ($checks as $check) {
        list($table, $col) = explode('|', $check);
        $result = mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM `$table` WHERE `$col` IS NOT NULL AND `$col` != '' AND CAST(`$col` AS UNSIGNED) > 999");
        if (!$result) {
            return -1;
        }
        $row = mysqli_fetch_assoc($result);
        $total += (int)($row['cnt'] ?? 0);
    }
    if (drawTableExists($conn)) {
        foreach (['drawn_number', 'winning_number'] as $col) {
            $result = mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM draw_audits WHERE `$col` IS NOT NULL AND `$col` != '' AND CAST(`$col` AS UNSIGNED) > 999");
            if (!$result) {
                return -1;
            }
            $row = mysqli_fetch_assoc($result);
            $total += (int)($row['cnt'] ?? 0);
        }
    }
    return $total;
}

// Switch digit mode, migrating stored numbers. Returns [bool, message].
// 3 -> 4 pads with a leading zero (042 -> 0042, always safe).
// 4 -> 3 strips the leading zero but is blocked if any number is > 999.
function setNumberDigits($conn, $digits) {
    $digits = (int)$digits;
    if (!in_array($digits, getValidNumberDigits(), true)) {
        return [false, t('func.digits_invalid')];
    }
    $from = getNumberDigits($conn);
    if ($from === $digits) {
        return [true, t('func.digits_already', ['label' => numberDigitsLabel($digits)])];
    }
    if (!ensureNumberColumns($conn)) {
        return [false, t('func.digits_no_widen', ['error' => mysqli_error($conn)])];
    }
    if ($digits === 4) {
        $updates = [
            'participants|number', 'participants|number2', 'participants|number3',
            'winners|number',
        ];
        foreach ($updates as $u) {
            list($table, $col) = explode('|', $u);
            if (!mysqli_query($conn, "UPDATE `$table` SET `$col` = LPAD(`$col`, 4, '0') WHERE `$col` IS NOT NULL AND `$col` != ''")) {
                return [false, t('func.digits_migr_fail', ['table' => $table, 'col' => $col, 'error' => mysqli_error($conn)])];
            }
        }
        if (drawTableExists($conn)) {
            foreach (['drawn_number', 'winning_number'] as $col) {
                if (!mysqli_query($conn, "UPDATE draw_audits SET `$col` = LPAD(`$col`, 4, '0') WHERE `$col` IS NOT NULL AND `$col` != ''")) {
                    return [false, 'Migration failed on draw_audits.' . $col . ': ' . mysqli_error($conn)];
                }
            }
        }
    } else {
        $above = countNumbersAbove999($conn);
        if ($above < 0) {
            return [false, t('func.digits_check_fail', ['error' => mysqli_error($conn)])];
        }
        if ($above > 0) {
            return [false, t('func.digits_blocked', ['count' => $above])];
        }
        $updates = [
            'participants|number', 'participants|number2', 'participants|number3',
            'winners|number',
        ];
        foreach ($updates as $u) {
            list($table, $col) = explode('|', $u);
            if (!mysqli_query($conn, "UPDATE `$table` SET `$col` = RIGHT(`$col`, 3) WHERE `$col` IS NOT NULL AND `$col` != ''")) {
                return [false, t('func.digits_migr_fail', ['table' => $table, 'col' => $col, 'error' => mysqli_error($conn)])];
            }
        }
        if (drawTableExists($conn)) {
            foreach (['drawn_number', 'winning_number'] as $col) {
                if (!mysqli_query($conn, "UPDATE draw_audits SET `$col` = RIGHT(`$col`, 3) WHERE `$col` IS NOT NULL AND `$col` != ''")) {
                    return [false, 'Migration failed on draw_audits.' . $col . ': ' . mysqli_error($conn)];
                }
            }
        }
    }
    $value = (string)$digits;
    $stmt = mysqli_prepare($conn, "REPLACE INTO custom_texts (`key`, content) VALUES ('number_digits', ?)");
    mysqli_stmt_bind_param($stmt, 's', $value);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    if (!$ok) {
        return [false, t('func.digits_saved_fail', ['error' => mysqli_error($conn)])];
    }
    return [true, t('func.digits_ok', ['label' => numberDigitsLabel($digits)])];
}

// Lottery is "in progress" while it holds data: participants or winners exist.
// Structural settings (number digits, draw mode) are locked in that state
// and can only be changed after a full reset.
function isLotteryInProgress($conn) {
    return getParticipantCount($conn) > 0 || getWinnerCount($conn) > 0;
}

function lotteryLockedMessage() {
    return t('func.locked');
}

// ---- Draw mode (manual / random_org / random_int) ----

function getValidDrawModes() {
    return ['manual', 'random_org', 'random_int'];
}

function getDrawMode($conn) {
    $mode = getCustomText($conn, 'draw_mode');
    if (!in_array($mode, getValidDrawModes(), true)) {
        return 'manual';
    }
    return $mode;
}

function setDrawMode($conn, $mode) {
    if (!in_array($mode, getValidDrawModes(), true)) {
        return false;
    }
    $stmt = mysqli_prepare($conn, "REPLACE INTO custom_texts (`key`, content) VALUES ('draw_mode', ?)");
    mysqli_stmt_bind_param($stmt, 's', $mode);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $ok;
}

function drawModeLabel($mode) {
    $labels = [
        'manual' => t('func.mode_manual'),
        'random_org' => t('func.mode_org'),
        'random_int' => t('func.mode_local'),
    ];
    return $labels[$mode] ?? $mode;
}

// ---- Winner rule: unique (1 prize per person) vs repeat allowed ----
// Stored in custom_texts as unique_winners = '1' (unique) / '0' (repeat allowed).
// Default '0' so existing installs keep working and a participant can win
// several prizes unless the admin enables unique mode in the dashboard.

function isUniqueWinnersOnly($conn) {
    return getCustomText($conn, 'unique_winners') === '1';
}

function setUniqueWinnersOnly($conn, $unique) {
    $value = $unique ? '1' : '0';
    $stmt = mysqli_prepare($conn, "REPLACE INTO custom_texts (`key`, content) VALUES ('unique_winners', ?)");
    mysqli_stmt_bind_param($stmt, 's', $value);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $ok;
}

// Participants still eligible for the draw under the current winner rule.
function getRemainingDrawParticipantCount($conn) {
    if (!isUniqueWinnersOnly($conn)) {
        return (int)getParticipantCount($conn);
    }
    $result = mysqli_query($conn, 'SELECT COUNT(*) AS cnt FROM participants p LEFT JOIN winners w ON p.id = w.participant_id WHERE w.id IS NULL');
    if (!$result) {
        return 0;
    }
    $row = mysqli_fetch_assoc($result);
    return (int)($row['cnt'] ?? 0);
}

// Explanatory message for draw.php / ajax.php when findClosestParticipant() is null.
function getDrawExhaustedMessage($conn) {
    $total = (int)getParticipantCount($conn);
    if ($total === 0) {
        return t('func.draw_none');
    }
    if (isUniqueWinnersOnly($conn) && getRemainingDrawParticipantCount($conn) === 0) {
        return t('func.draw_unique_done');
    }
    return t('func.draw_default');
}

function drawTableExists($conn) {
    $result = @mysqli_query($conn, "SHOW TABLES LIKE 'draw_audits'");
    if (!$result) {
        return false;
    }
    $exists = mysqli_num_rows($result) > 0;
    mysqli_free_result($result);
    return $exists;
}

// Fetch one integer from random.org (atmospheric noise), zero-padded to $digits.
// Returns ['number' => '0042', 'raw' => '42'] or ['error' => 'reason'].
function fetchRandomOrgNumber($digits = 3) {
    $digits = ((int)$digits === 4) ? 4 : 3;
    $max = getNumberRangeMax($digits);
    $url = 'https://www.random.org/integers/?num=1&min=0&max=' . $max . '&col=1&base=10&format=plain&rnd=new';
    $raw = false;
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        $raw = curl_exec($ch);
        $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($raw === false || $http !== 200) {
            return ['error' => 'random.org did not respond (HTTP ' . $http . ').'];
        }
    } else {
        $ctx = stream_context_create(['http' => ['timeout' => 5]]);
        $raw = @file_get_contents($url, false, $ctx);
        if ($raw === false) {
            return ['error' => 'random.org did not respond.'];
        }
    }
    $raw = trim($raw);
    if (!preg_match('/^\d{1,4}$/', $raw)) {
        return ['error' => 'random.org returned an invalid value.'];
    }
    $int = (int)$raw;
    if ($int < 0 || $int > $max) {
        return ['error' => 'random.org returned an out-of-range value.'];
    }
    return ['number' => str_pad($int, $digits, '0', STR_PAD_LEFT), 'raw' => $raw];
}

function generateLocalRandomNumber($digits = 3) {
    $digits = ((int)$digits === 4) ? 4 : 3;
    return str_pad(random_int(0, getNumberRangeMax($digits)), $digits, '0', STR_PAD_LEFT);
}

// Shared rule: closest participant number >= drawn number, wrapping around to 000 (0000 in 4-digit mode).
// Circular distance: (candidate - drawn + total) % total. Exact match wins (distance 0).
// Returns ['participant' => $row, 'winning_number' => '042', 'wrapped' => bool] or null
// (null only when no participants remain available for the draw).
function findClosestParticipant($conn, $drawn_number) {
    $digits = getNumberDigits($conn);
    $total = getNumberRangeMax($digits) + 1;
    $drawn_int = (int)$drawn_number;
    if (isUniqueWinnersOnly($conn)) {
        $result = mysqli_query($conn, "SELECT id, name, number, number2, number3 FROM participants WHERE id NOT IN (SELECT participant_id FROM winners)");
    } else {
        $result = mysqli_query($conn, 'SELECT id, name, number, number2, number3 FROM participants');
    }
    if (!$result) {
        return null;
    }
    $best_participant = null;
    $best_number = null;
    $best_dist = null;
    while ($row = mysqli_fetch_assoc($result)) {
        foreach (['number', 'number2', 'number3'] as $col) {
            if (isset($row[$col]) && $row[$col] !== '' && $row[$col] !== null) {
                $num_int = (int)$row[$col];
                $dist = ($num_int - $drawn_int + $total) % $total;
                if ($best_dist === null || $dist < $best_dist) {
                    $best_dist = $dist;
                    $best_number = $row[$col];
                    $best_participant = $row;
                }
            }
        }
    }
    if (!$best_participant) {
        return null;
    }
    return ['participant' => $best_participant, 'winning_number' => $best_number, 'wrapped' => ($best_dist > 0 && (int)$best_number < $drawn_int)];
}

function createDrawAudit($conn, $prize_id, $mode, $drawn_number, $provider, $external_raw, $admin_id) {
    $drawn_number = formatLotteryNumber($drawn_number, getNumberDigits($conn));
    $stamp = date('Y-m-d H:i:s');
    $proof = hash('sha256', $drawn_number . '|' . (int)$prize_id . '|' . $stamp . '|' . (int)$admin_id . '|' . $mode);
    $stmt = mysqli_prepare($conn, 'INSERT INTO draw_audits (prize_id, mode, drawn_number, provider, external_raw, proof_hash, admin_id) VALUES (?, ?, ?, ?, ?, ?, ?)');
    if (!$stmt) {
        return false;
    }
    // NULL provider/raw handling: bind empty string as NULL-safe fallback
    $provider_param = $provider ?: null;
    $raw_param = $external_raw ?: null;
    $admin_param = $admin_id ? (int)$admin_id : null;
    mysqli_stmt_bind_param($stmt, 'isssssi', $prize_id, $mode, $drawn_number, $provider_param, $raw_param, $proof, $admin_param);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return false;
    }
    $id = mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);
    return $id;
}

function getDrawAuditById($conn, $id) {
    $id = (int)$id;
    $result = mysqli_query($conn, "SELECT * FROM draw_audits WHERE id = $id");
    if (!$result) {
        return null;
    }
    $row = mysqli_fetch_assoc($result);
    return $row ?: null;
}

function confirmDrawAudit($conn, $audit_id, $participant_id, $winning_number) {
    $audit_id = (int)$audit_id;
    $participant_id = (int)$participant_id;
    $stmt = mysqli_prepare($conn, 'UPDATE draw_audits SET participant_id = ?, winning_number = ?, confirmed_at = NOW() WHERE id = ? AND confirmed_at IS NULL');
    if (!$stmt) {
        return false;
    }
    mysqli_stmt_bind_param($stmt, 'isi', $participant_id, $winning_number, $audit_id);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $ok;
}

// Latest confirmed audit for a given winner (linked by prize + participant).
function getDrawAuditForWinner($conn, $prize_id, $participant_id) {
    $prize_id = (int)$prize_id;
    $participant_id = (int)$participant_id;
    $result = mysqli_query($conn, "SELECT * FROM draw_audits WHERE prize_id = $prize_id AND participant_id = $participant_id AND confirmed_at IS NOT NULL ORDER BY confirmed_at DESC LIMIT 1");
    if (!$result) {
        return null;
    }
    $row = mysqli_fetch_assoc($result);
    return $row ?: null;
}
