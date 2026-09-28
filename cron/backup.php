<?php
/**
 * Lottery Backup Script
 *
 * Run via cron:
 *   php /ruta/completa/hacia/cron/backup.php
 *
 * All paths are configured in includes/config.php (BACKUP_DIR,
 * PROJECT_ROOT, MAX_BACKUPS, MYSQLDUMP_BIN). Edit them there.
 *
 * Creates a .tar.gz with DB dump + project files.
 * Keeps max MAX_BACKUPS backups, auto-removes oldest.
 */

// --- Load DB credentials + backup configuration ---
// When run from cron, __DIR__ resolves to the cron/ directory wherever
// the project lives, so no hardcoded absolute paths are needed.
require_once __DIR__ . '/../includes/config.php';

// --- Ensure backup directory exists ---
if (!is_dir(BACKUP_DIR)) {
    if (!mkdir(BACKUP_DIR, 0755, true)) {
        exit("ERROR: Cannot create backup directory: " . BACKUP_DIR . "\n");
    }
}
if (!is_writable(BACKUP_DIR)) {
    exit("ERROR: Backup directory is not writable: " . BACKUP_DIR . "\n");
}

// --- Timestamp ---
$timestamp = date('Y-m-d_Hi');
$archive_name = 'backup_' . $timestamp . '.tar.gz';
$archive_path = BACKUP_DIR . '/' . $archive_name;

// --- 1. Database dump ---
$sql_file = tempnam(sys_get_temp_dir(), 'lottery_sql_');
$dump_cmd = sprintf(
    '%s --host=%s --user=%s --password=%s --routines --single-transaction %s > %s 2>&1',
    MYSQLDUMP_BIN,
    escapeshellarg(DB_HOST),
    escapeshellarg(DB_USER),
    escapeshellarg(DB_PASS),
    escapeshellarg(DB_NAME),
    escapeshellarg($sql_file)
);
exec($dump_cmd, $dump_out, $dump_rc);

if ($dump_rc !== 0 || !file_exists($sql_file) || filesize($sql_file) === 0) {
    @unlink($sql_file);
    exit("ERROR: Database dump failed (exit code $dump_rc).\n");
}

// --- 2. Stage files in temp dir ---
$staging = tempnam(sys_get_temp_dir(), 'lottery_stg_');
@unlink($staging);
mkdir($staging, 0755, true);

// Rsync project files into staging (exclude .git and backup artifacts)
$excludes = ['.git', 'cron/backups', 'cron/*.tar.gz'];
$rsync_cmd = 'rsync -a --delete';
foreach ($excludes as $ex) {
    $rsync_cmd .= ' --exclude=' . escapeshellarg($ex);
}
$rsync_cmd .= ' ' . escapeshellarg(PROJECT_ROOT . '/') . ' ' . escapeshellarg($staging . '/');
exec($rsync_cmd, $rsync_out, $rsync_rc);

if ($rsync_rc !== 0) {
    @unlink($sql_file);
    exec('rm -rf ' . escapeshellarg($staging));
    exit("ERROR: File copy failed (rsync exit code $rsync_rc).\n");
}

// Copy SQL dump into staging
copy($sql_file, $staging . '/lottery_db.sql');

// --- 3. Create tar.gz from staging ---
$tar_cmd = sprintf(
    'tar -czf %s -C %s . 2>&1',
    escapeshellarg($archive_path),
    escapeshellarg($staging)
);
exec($tar_cmd, $tar_out, $tar_rc);

// Cleanup temp files
@unlink($sql_file);
exec('rm -rf ' . escapeshellarg($staging));

if ($tar_rc !== 0 || !file_exists($archive_path) || filesize($archive_path) === 0) {
    @unlink($archive_path);
    exit("ERROR: Archive creation failed (tar exit code $tar_rc).\n");
}

// --- 4. Rotate old backups (keep max MAX_BACKUPS) ---
$backups = glob(BACKUP_DIR . '/backup_*.tar.gz') ?: [];
usort($backups, 'strnatcmp');

while (count($backups) >= MAX_BACKUPS) {
    $oldest = array_shift($backups);
    @unlink($oldest);
}

echo "OK: Backup created: " . $archive_path . "\n";
