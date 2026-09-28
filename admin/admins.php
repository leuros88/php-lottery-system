<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$message = '';
$message_type = '';

// Handle delete (prevent self-deletion)
if (isset($_POST['delete'])) {
    requireCsrf();
    $id = (int)$_POST['delete'];
    if ($id === (int)$_SESSION['admin_id']) {
        $message = t('adm.msg_no_self');
        $message_type = 'error';
    } else {
        $stmt = mysqli_prepare($conn, 'DELETE FROM administrators WHERE id = ?');
        mysqli_stmt_bind_param($stmt, 'i', $id);
        if (mysqli_stmt_execute($stmt)) {
            $message = t('adm.msg_removed');
            $message_type = 'success';
        }
        mysqli_stmt_close($stmt);
    }
}

// Handle add
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $message = t('adm.msg_req');
        $message_type = 'error';
    } elseif (strlen($password) < 6) {
        $message = t('adm.msg_min');
        $message_type = 'error';
    } else {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $stmt = mysqli_prepare($conn, 'INSERT INTO administrators (username, password) VALUES (?, ?)');
        mysqli_stmt_bind_param($stmt, 'ss', $username, $hashed);
        if (mysqli_stmt_execute($stmt)) {
            $message = t('adm.msg_added');
            $message_type = 'success';
        } else {
            $message = t('adm.msg_exists');
            $message_type = 'error';
        }
        mysqli_stmt_close($stmt);
    }
}

$result = mysqli_query($conn, 'SELECT id, username, created_at FROM administrators ORDER BY id');
require_once __DIR__ . '/header.php';
?>

<div class="page-header">
    <h1><?= htmlspecialchars(t('adm.title')) ?></h1>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?= $message_type ?>"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<div class="form-card">
    <h2><?= htmlspecialchars(t('adm.add_title')) ?></h2>
    <form method="post" class="inline-form">
        <?= csrfField() ?>
        <div class="form-row">
            <div class="form-group">
                <label for="username"><?= htmlspecialchars(t('adm.username')) ?></label>
                <input type="text" id="username" name="username" required>
            </div>
            <div class="form-group">
                <label for="password"><?= htmlspecialchars(t('adm.password')) ?></label>
                <input type="password" id="password" name="password" required minlength="6">
            </div>
            <div class="form-group form-group-submit">
                <label>&nbsp;</label>
                <button type="submit" class="btn btn-primary"><?= htmlspecialchars(t('adm.add_btn')) ?></button>
            </div>
        </div>
    </form>
</div>

<div class="table-card">
    <table class="data-table">
        <thead>
            <tr>
                <th><?= htmlspecialchars(t('adm.th_id')) ?></th>
                <th><?= htmlspecialchars(t('adm.th_user')) ?></th>
                <th><?= htmlspecialchars(t('adm.th_created')) ?></th>
                <th><?= htmlspecialchars(t('adm.th_actions')) ?></th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = mysqli_fetch_assoc($result)): ?>
                <tr>
                    <td><?= $row['id'] ?></td>
                    <td><?= htmlspecialchars($row['username']) ?></td>
                    <td><?= date('d/m/Y H:i', strtotime($row['created_at'])) ?></td>
                    <td class="actions-cell">
                        <?php if ((int)$row['id'] !== (int)$_SESSION['admin_id']): ?>
                            <form method="post" style="display:inline">
                                <?= csrfField() ?>
                                <input type="hidden" name="delete" value="<?= $row['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm(<?= htmlspecialchars(json_encode(t('adm.delete_confirm', ['name' => $row['username']])), ENT_QUOTES) ?>)"><?= htmlspecialchars(t('adm.delete')) ?></button>
                            </form>
                        <?php else: ?>
                            <span class="badge badge-success"><?= htmlspecialchars(t('adm.you')) ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
