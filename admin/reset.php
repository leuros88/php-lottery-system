<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_reset'])) {
    requireCsrf();
    $confirm = trim($_POST['confirm'] ?? '');

    if (strtoupper($confirm) !== 'RESET') {
        $message = t('res.msg_type');
        $message_type = 'error';
    } else {
        mysqli_begin_transaction($conn);
        try {
            mysqli_query($conn, 'DELETE FROM winners');
            mysqli_query($conn, 'DELETE FROM participants');
            mysqli_query($conn, 'DELETE FROM prizes');
            mysqli_query($conn, 'DELETE FROM blacklist');
            mysqli_query($conn, 'DELETE FROM news');
            mysqli_query($conn, "UPDATE custom_texts SET content = 'open' WHERE `key` = 'lottery_status'");
            mysqli_query($conn, "UPDATE custom_texts SET content = 'off' WHERE `key` = 'maintenance_mode'");
            mysqli_commit($conn);
            $message = t('res.msg_ok');
            $message_type = 'success';
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $message = $e->getMessage();
            $message_type = 'error';
        }
    }
}

require_once __DIR__ . '/header.php';
?>

<div class="page-header">
    <h1><?= htmlspecialchars(t('res.title')) ?></h1>
    <p class="page-description"><?= htmlspecialchars(t('res.desc')) ?></p>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?= $message_type ?>"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<div class="form-card" style="border-left: 4px solid #e53935;">
    <h2 style="color: #c62828;"><?= htmlspecialchars(t('res.danger')) ?></h2>
    <p style="color: #555; line-height: 1.6;">
        <?= htmlspecialchars(t('res.will_delete')) ?>
    </p>
    <ul style="color: #555; line-height: 1.8; margin-bottom: 20px;">
        <li><?= htmlspecialchars(t('res.li_participants')) ?></li>
        <li><?= htmlspecialchars(t('res.li_winners')) ?></li>
        <li><?= htmlspecialchars(t('res.li_prizes')) ?></li>
        <li><?= htmlspecialchars(t('res.li_blacklist')) ?></li>
        <li><?= htmlspecialchars(t('res.li_news')) ?></li>
        <li><?= htmlspecialchars(t('res.li_status')) ?></li>
        <li><?= htmlspecialchars(t('res.li_maint')) ?></li>
    </ul>
    <p style="color: #c62828; font-weight: 600;"><?= htmlspecialchars(t('res.cannot_undo')) ?></p>
    <form method="post" onsubmit="return confirm(<?= htmlspecialchars(json_encode(t('res.confirm_js')), ENT_QUOTES) ?>)">
        <?= csrfField() ?>
        <div class="form-group">
            <label for="confirm"><?= htmlspecialchars(t('res.type_reset')) ?></label>
            <input type="text" id="confirm" name="confirm" required style="max-width: 200px;" oninput="this.value = this.value.toUpperCase()">
        </div>
        <button type="submit" name="confirm_reset" class="btn btn-danger"><?= htmlspecialchars(t('res.button')) ?></button>
    </form>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
