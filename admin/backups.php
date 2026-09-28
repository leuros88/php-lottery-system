<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

// Backup directory comes from includes/config.php (BACKUP_DIR).
// Edit it there to point outside public_html in production.
$backup_dir = BACKUP_DIR;

$backups = [];
$total_size = 0;
$dir_exists = is_dir($backup_dir);

if ($dir_exists) {
    $files = glob($backup_dir . '/backup_*.tar.gz') ?: [];
    usort($files, 'strnatcmp');
    $files = array_reverse($files);

    foreach ($files as $f) {
        $size = filesize($f);
        $total_size += $size;
        $backups[] = [
            'name'   => basename($f),
            'path'   => $f,
            'size'   => $size,
            'size_hr' => formatBytes($size),
            'date'   => date('d/m/Y H:i:s', filemtime($f)),
        ];
    }
}

function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    return round($bytes / pow(1024, $pow), $precision) . ' ' . $units[$pow];
}

require_once __DIR__ . '/header.php';
?>

<div class="page-header">
    <h1><?= htmlspecialchars(t('bk.title')) ?></h1>
</div>

<?php if (!$dir_exists): ?>
<div class="alert alert-warning">
    <?= htmlspecialchars(t('bk.no_dir')) ?> <code><?= htmlspecialchars($backup_dir) ?></code><br>
    <?= htmlspecialchars(t('bk.run_once')) ?>
</div>
<?php endif; ?>

<div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
    <div class="stat-card">
        <div class="stat-number"><?= count($backups) ?></div>
        <div class="stat-label"><?= htmlspecialchars(t('bk.total')) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-number"><?= formatBytes($total_size) ?></div>
        <div class="stat-label"><?= htmlspecialchars(t('bk.size')) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-number"><?= (int)MAX_BACKUPS ?></div>
        <div class="stat-label"><?= htmlspecialchars(t('bk.max')) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-number" style="font-size:0.85rem; word-break:break-all;">
            <?= htmlspecialchars($backup_dir) ?>
        </div>
        <div class="stat-label"><?= htmlspecialchars(t('bk.dir')) ?></div>
    </div>
</div>

<div class="form-card">
    <h2><?= htmlspecialchars(t('bk.files')) ?></h2>
    <p style="margin:0 0 15px; font-size:0.9rem; color:#555;">
        <?= htmlspecialchars(t('bk.desc')) ?>
    </p>
    <table class="data-table">
        <thead>
            <tr>
                <th><?= htmlspecialchars(t('bk.th_file')) ?></th>
                <th><?= htmlspecialchars(t('bk.th_date')) ?></th>
                <th><?= htmlspecialchars(t('bk.th_size')) ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($backups)): ?>
                <tr><td colspan="3" class="empty"><?= htmlspecialchars(t('bk.empty')) ?></td></tr>
            <?php else: ?>
                <?php foreach ($backups as $b): ?>
                <tr>
                    <td><?= htmlspecialchars($b['name']) ?></td>
                    <td><?= $b['date'] ?></td>
                    <td><?= $b['size_hr'] ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
