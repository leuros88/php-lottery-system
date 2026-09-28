<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $current = $_POST['current_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (empty($current) || empty($new) || empty($confirm)) {
        $message = t('pw.msg_req');
        $message_type = 'error';
    } elseif ($new !== $confirm) {
        $message = t('pw.msg_mismatch');
        $message_type = 'error';
    } elseif (strlen($new) < 6) {
        $message = t('pw.msg_min');
        $message_type = 'error';
    } else {
        $stmt = mysqli_prepare($conn, 'SELECT password FROM administrators WHERE id = ?');
        mysqli_stmt_bind_param($stmt, 'i', $_SESSION['admin_id']);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $admin = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if ($admin && password_verify($current, $admin['password'])) {
            $hashed = password_hash($new, PASSWORD_DEFAULT);
            $stmt2 = mysqli_prepare($conn, 'UPDATE administrators SET password = ? WHERE id = ?');
            mysqli_stmt_bind_param($stmt2, 'si', $hashed, $_SESSION['admin_id']);
            if (mysqli_stmt_execute($stmt2)) {
                $message = t('pw.msg_ok');
                $message_type = 'success';
            }
            mysqli_stmt_close($stmt2);
        } else {
            $message = t('pw.msg_current_bad');
            $message_type = 'error';
        }
    }
}

require_once __DIR__ . '/header.php';
?>

<div class="page-header">
    <h1><?= htmlspecialchars(t('pw.title')) ?></h1>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?= $message_type ?>"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<div class="form-card">
    <h2><?= htmlspecialchars(t('pw.subtitle')) ?></h2>
    <form method="post">
        <?= csrfField() ?>
        <div class="form-group">
            <label for="current_password"><?= htmlspecialchars(t('pw.current')) ?></label>
            <input type="password" id="current_password" name="current_password" required autocomplete="current-password">
        </div>
        <div class="form-group">
            <label for="new_password"><?= htmlspecialchars(t('pw.new')) ?></label>
            <input type="password" id="new_password" name="new_password" required minlength="6" autocomplete="new-password">
        </div>
        <div class="form-group">
            <label for="confirm_password"><?= htmlspecialchars(t('pw.confirm')) ?></label>
            <input type="password" id="confirm_password" name="confirm_password" required minlength="6" autocomplete="new-password">
        </div>
        <button type="submit" class="btn btn-primary"><?= htmlspecialchars(t('pw.button')) ?></button>
    </form>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
