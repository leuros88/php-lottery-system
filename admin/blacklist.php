<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$message = '';
$message_type = '';

// Handle delete
if (isset($_POST['delete'])) {
    requireCsrf();
    $id = (int)$_POST['delete'];
    $stmt = mysqli_prepare($conn, 'DELETE FROM blacklist WHERE id = ?');
    mysqli_stmt_bind_param($stmt, 'i', $id);
    if (mysqli_stmt_execute($stmt)) {
        $message = t('bl.msg_removed');
        $message_type = 'success';
    }
    mysqli_stmt_close($stmt);
}

// Handle add
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add'])) {
    requireCsrf();
    $name = trim($_POST['name'] ?? '');
    $reason = trim($_POST['reason'] ?? '');

    if (empty($name)) {
        $message = t('bl.msg_name_req');
        $message_type = 'error';
    } else {
        $stmt = mysqli_prepare($conn, 'INSERT INTO blacklist (name, reason) VALUES (?, ?)');
        mysqli_stmt_bind_param($stmt, 'ss', $name, $reason);
        if (mysqli_stmt_execute($stmt)) {
            $message = t('bl.msg_added');
            $message_type = 'success';
        } else {
            $message = t('bl.msg_exists');
            $message_type = 'error';
        }
        mysqli_stmt_close($stmt);
    }
}

$result = mysqli_query($conn, 'SELECT id, name, reason, created_at FROM blacklist ORDER BY name');
require_once __DIR__ . '/header.php';
?>

<div class="page-header">
    <h1><?= htmlspecialchars(t('bl.title')) ?></h1>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?= $message_type ?>"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<div class="form-card">
    <h2><?= htmlspecialchars(t('bl.add_title')) ?></h2>
    <form method="post" class="inline-form">
        <?= csrfField() ?>
        <div class="form-row">
            <div class="form-group">
                <label for="name"><?= htmlspecialchars(t('bl.name')) ?></label>
                <input type="text" id="name" name="name" required>
            </div>
            <div class="form-group form-group-wide">
                <label for="reason"><?= htmlspecialchars(t('bl.reason')) ?></label>
                <input type="text" id="reason" name="reason">
            </div>
            <div class="form-group form-group-submit">
                <label>&nbsp;</label>
                <button type="submit" name="add" class="btn btn-primary"><?= htmlspecialchars(t('bl.add_btn')) ?></button>
            </div>
        </div>
    </form>
</div>

<div class="table-card">
    <table class="data-table">
        <thead>
            <tr>
                <th><?= htmlspecialchars(t('bl.th_name')) ?></th>
                <th><?= htmlspecialchars(t('bl.th_reason')) ?></th>
                <th><?= htmlspecialchars(t('bl.th_added')) ?></th>
                <th><?= htmlspecialchars(t('bl.th_actions')) ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (mysqli_num_rows($result) === 0): ?>
                <tr><td colspan="4" class="empty"><?= htmlspecialchars(t('bl.empty')) ?></td></tr>
            <?php else: ?>
                <?php while ($row = mysqli_fetch_assoc($result)): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['name']) ?></td>
                        <td><?= htmlspecialchars($row['reason'] ?: '-') ?></td>
                        <td><?= date('d/m/Y H:i', strtotime($row['created_at'])) ?></td>
                        <td class="actions-cell">
                            <form method="post" style="display:inline">
                                <?= csrfField() ?>
                                <input type="hidden" name="delete" value="<?= $row['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm(<?= htmlspecialchars(json_encode(t('bl.remove_confirm', ['name' => $row['name']])), ENT_QUOTES) ?>)"><?= htmlspecialchars(t('bl.remove')) ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
