<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$message = '';
$message_type = '';
$edit_prize = null;

// Handle delete
if (isset($_POST['delete'])) {
    requireCsrf();
    $id = (int)$_POST['delete'];
    $stmt = mysqli_prepare($conn, 'DELETE FROM prizes WHERE id = ?');
    mysqli_stmt_bind_param($stmt, 'i', $id);
    if (mysqli_stmt_execute($stmt)) {
        $message = t('prize.msg_deleted');
        $message_type = 'success';
    }
    mysqli_stmt_close($stmt);
}

// Handle edit get
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $stmt = mysqli_prepare($conn, 'SELECT * FROM prizes WHERE id = ?');
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $edit_prize = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
}

// Handle add/edit post
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $name = trim($_POST['name'] ?? '');
    $position = (int)($_POST['position'] ?? 0);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $edit_id = isset($_POST['edit_id']) ? (int)$_POST['edit_id'] : 0;

    if (empty($name)) {
        $message = t('prize.msg_name_req');
        $message_type = 'error';
    } elseif ($position < 1) {
        $message = t('prize.msg_pos_req');
        $message_type = 'error';
    } else {
        if ($edit_id) {
            $stmt = mysqli_prepare($conn, 'UPDATE prizes SET name = ?, position = ?, is_active = ? WHERE id = ?');
            mysqli_stmt_bind_param($stmt, 'siii', $name, $position, $is_active, $edit_id);
            mysqli_stmt_execute($stmt);
            $message = t('prize.msg_updated');
            $message_type = 'success';
            mysqli_stmt_close($stmt);
        } else {
            $stmt = mysqli_prepare($conn, 'INSERT INTO prizes (name, position, is_active) VALUES (?, ?, ?)');
            mysqli_stmt_bind_param($stmt, 'sii', $name, $position, $is_active);
            mysqli_stmt_execute($stmt);
            $message = t('prize.msg_added');
            $message_type = 'success';
            mysqli_stmt_close($stmt);
        }
    }
}

$result = mysqli_query($conn, 'SELECT * FROM prizes ORDER BY position ASC');
require_once __DIR__ . '/header.php';
?>

<div class="page-header">
    <h1><?= htmlspecialchars(t('prize.title')) ?></h1>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?= $message_type ?>"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<div class="form-card">
    <h2><?= $edit_prize ? htmlspecialchars(t('prize.edit')) : htmlspecialchars(t('prize.add')) ?></h2>
    <form method="post" class="inline-form">
        <?= csrfField() ?>
        <?php if ($edit_prize): ?>
            <input type="hidden" name="edit_id" value="<?= $edit_prize['id'] ?>">
        <?php endif; ?>
        <div class="form-row">
            <div class="form-group">
                <label for="name"><?= htmlspecialchars(t('prize.name_label')) ?></label>
                <input type="text" id="name" name="name" value="<?= htmlspecialchars($edit_prize['name'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label for="position"><?= htmlspecialchars(t('prize.pos_label')) ?></label>
                <input type="number" id="position" name="position" value="<?= htmlspecialchars($edit_prize['position'] ?? '') ?>" min="1" required>
            </div>
            <div class="form-group form-group-submit">
                <label>&nbsp;</label>
                <button type="submit" class="btn btn-primary"><?= $edit_prize ? htmlspecialchars(t('prize.update_btn')) : htmlspecialchars(t('prize.add_btn')) ?></button>
                <?php if ($edit_prize): ?>
                    <a href="<?= adminUrl('prizes.php') ?>" class="btn btn-secondary"><?= htmlspecialchars(t('prize.cancel')) ?></a>
                <?php endif; ?>
            </div>
        </div>
        <div class="form-group form-checkbox">
            <label>
                <input type="checkbox" name="is_active" <?= (isset($edit_prize) && $edit_prize['is_active']) || !isset($edit_prize) ? 'checked' : '' ?>>
                <?= htmlspecialchars(t('prize.active_label')) ?>
            </label>
        </div>
    </form>
</div>

<div class="table-card">
    <table class="data-table">
        <thead>
            <tr>
                <th><?= htmlspecialchars(t('prize.th_pos')) ?></th>
                <th><?= htmlspecialchars(t('prize.th_prize')) ?></th>
                <th><?= htmlspecialchars(t('prize.th_status')) ?></th>
                <th><?= htmlspecialchars(t('prize.th_actions')) ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (mysqli_num_rows($result) === 0): ?>
                <tr><td colspan="4" class="empty"><?= htmlspecialchars(t('prize.empty')) ?></td></tr>
            <?php else: ?>
                <?php while ($row = mysqli_fetch_assoc($result)): ?>
                    <tr>
                        <td><?= $row['position'] ?></td>
                        <td><?= htmlspecialchars($row['name']) ?></td>
                        <td><?= $row['is_active'] ? '<span class="badge badge-success">' . htmlspecialchars(t('common.active')) . '</span>' : '<span class="badge badge-inactive">' . htmlspecialchars(t('common.inactive')) . '</span>' ?></td>
                        <td class="actions-cell">
                            <a href="?edit=<?= $row['id'] ?>" class="btn btn-sm btn-secondary"><?= htmlspecialchars(t('prize.edit_btn')) ?></a>
                            <form method="post" style="display:inline">
                                <?= csrfField() ?>
                                <input type="hidden" name="delete" value="<?= $row['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm(<?= htmlspecialchars(json_encode(t('prize.delete_confirm', ['name' => $row['name']])), ENT_QUOTES) ?>)"><?= htmlspecialchars(t('prize.delete')) ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
