<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'lottery_example');

// Site configuration
define('SITE_NAME', 'Lottery System');
define('NUMBERS_TOTAL', 1000);

// Fixed author attribution (hardcoded: NOT editable from the admin panel).
// The admin can still set their own custom footer via custom_texts.footer_info.
// To change the attribution, edit these two lines in code.
if (!defined('AUTHOR_NAME')) {
    define('AUTHOR_NAME', 'xxxx');
}
if (!defined('AUTHOR_GITHUB_URL')) {
    define('AUTHOR_GITHUB_URL', 'https://google.es');
}

// Admin panel directory (security-by-obscurity: rename this folder to hide the panel URL).
// If you rename /admin/ to something else, update this value to match.
// Only letters, numbers, dashes and underscores. No slashes.
if (!defined('ADMIN_DIR')) {
    define('ADMIN_DIR', 'admin');
}

// Project root (directory containing index.php)
if (!defined('PROJECT_ROOT')) {
    define('PROJECT_ROOT', dirname(__DIR__));
}

// Backup configuration — edit these paths for your server.
// For production, point BACKUP_DIR outside public_html (e.g. /home/user/private/backups).
// Default keeps backups inside the project (cron/backups/ is git-ignored).
if (!defined('BACKUP_DIR')) {
    define('BACKUP_DIR', PROJECT_ROOT . '/cron/backups');
}
if (!defined('MAX_BACKUPS')) {
    define('MAX_BACKUPS', 10);
}
// Full path to the dump binary (run `which mysqldump` / `which mariadb-dump` on your server)
if (!defined('MYSQLDUMP_BIN')) {
    define('MYSQLDUMP_BIN', '/usr/bin/mariadb-dump');
}

// Session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Create connection
try {
    $conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
} catch (mysqli_sql_exception $e) {
    die('Database connection failed: ' . $e->getMessage());
}

if (!$conn) {
    die('Database connection failed: ' . mysqli_connect_error());
}

mysqli_set_charset($conn, 'utf8');

// Internationalization (UI languages). Resolves current language from
// ?lang= > session > cookie > browser (public) > admin default (DB) > 'en'.
// See includes/lang.php and includes/lang/*.php to add more languages.
require_once __DIR__ . '/lang.php';
resolveAppLang(isset($conn) ? $conn : null);
