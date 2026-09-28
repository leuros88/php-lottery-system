<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$message = '';
$message_type = '';
$edit_news = null;

// Handle delete
if (isset($_POST['delete'])) {
    requireCsrf();
    $id = (int)$_POST['delete'];
    $stmt = mysqli_prepare($conn, 'DELETE FROM news WHERE id = ?');
    mysqli_stmt_bind_param($stmt, 'i', $id);
    if (mysqli_stmt_execute($stmt)) {
        $message = t('news.msg_deleted');
        $message_type = 'success';
    }
    mysqli_stmt_close($stmt);
}

// Handle toggle active
if (isset($_POST['toggle'])) {
    requireCsrf();
    $id = (int)$_POST['toggle'];
    $result = mysqli_query($conn, "SELECT is_active FROM news WHERE id = $id");
    $row = mysqli_fetch_assoc($result);
    $new_status = $row['is_active'] ? 0 : 1;
    $stmt = mysqli_prepare($conn, 'UPDATE news SET is_active = ? WHERE id = ?');
    mysqli_stmt_bind_param($stmt, 'ii', $new_status, $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

// Handle edit get
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $stmt = mysqli_prepare($conn, 'SELECT * FROM news WHERE id = ?');
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $edit_news = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
}

// Handle add/edit post
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $title = trim($_POST['title'] ?? '');
    $content = $_POST['content'] ?? '';
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $edit_id = isset($_POST['edit_id']) ? (int)$_POST['edit_id'] : 0;

    if (empty($title)) {
        $message = t('news.msg_title_req');
        $message_type = 'error';
    } elseif (empty($content)) {
        $message = t('news.msg_content_req');
        $message_type = 'error';
    } else {
        if ($edit_id) {
            $stmt = mysqli_prepare($conn, 'UPDATE news SET title = ?, content = ?, is_active = ? WHERE id = ?');
            mysqli_stmt_bind_param($stmt, 'ssii', $title, $content, $is_active, $edit_id);
            mysqli_stmt_execute($stmt);
            $message = t('news.msg_updated');
            $message_type = 'success';
            mysqli_stmt_close($stmt);
        } else {
            $stmt = mysqli_prepare($conn, 'INSERT INTO news (title, content, is_active) VALUES (?, ?, ?)');
            mysqli_stmt_bind_param($stmt, 'ssi', $title, $content, $is_active);
            mysqli_stmt_execute($stmt);
            $message = t('news.msg_added');
            $message_type = 'success';
            mysqli_stmt_close($stmt);
        }
    }
}

$result = mysqli_query($conn, 'SELECT id, title, is_active, created_at FROM news ORDER BY created_at DESC');
require_once __DIR__ . '/header.php';
?>

<div class="page-header">
    <h1><?= htmlspecialchars(t('news.title')) ?></h1>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?= $message_type ?>"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<div class="form-card">
    <h2><?= $edit_news ? htmlspecialchars(t('news.edit')) : htmlspecialchars(t('news.add')) ?></h2>
    <form method="post">
        <?= csrfField() ?>
        <?php if ($edit_news): ?>
            <input type="hidden" name="edit_id" value="<?= $edit_news['id'] ?>">
        <?php endif; ?>
        <div class="form-group">
            <label for="title"><?= htmlspecialchars(t('news.title_label')) ?></label>
            <input type="text" id="title" name="title" value="<?= htmlspecialchars($edit_news['title'] ?? '') ?>" required>
        </div>
        <div class="form-group">
            <label for="content"><?= htmlspecialchars(t('news.content_label')) ?></label>
            <textarea id="content" name="content" rows="6" required><?= htmlspecialchars($edit_news['content'] ?? '') ?></textarea>
        </div>
        <div class="form-group form-checkbox">
            <label>
                <input type="checkbox" name="is_active" <?= (isset($edit_news) && $edit_news['is_active']) || !isset($edit_news) ? 'checked' : '' ?>>
                <?= htmlspecialchars(t('news.active_label')) ?>
            </label>
        </div>
        <div class="form-group">
            <button type="submit" class="btn btn-primary"><?= $edit_news ? htmlspecialchars(t('news.update_btn')) : htmlspecialchars(t('news.add_btn')) ?></button>
            <?php if ($edit_news): ?>
                <a href="<?= adminUrl('news.php') ?>" class="btn btn-secondary"><?= htmlspecialchars(t('news.cancel')) ?></a>
            <?php endif; ?>
        </div>
    </form>
</div>

<div class="table-card">
    <table class="data-table">
        <thead>
            <tr>
                <th><?= htmlspecialchars(t('news.th_title')) ?></th>
                <th><?= htmlspecialchars(t('news.th_status')) ?></th>
                <th><?= htmlspecialchars(t('news.th_created')) ?></th>
                <th><?= htmlspecialchars(t('news.th_actions')) ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (mysqli_num_rows($result) === 0): ?>
                <tr><td colspan="4" class="empty"><?= htmlspecialchars(t('news.empty')) ?></td></tr>
            <?php else: ?>
                <?php while ($row = mysqli_fetch_assoc($result)): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['title']) ?></td>
                        <td><?= $row['is_active'] ? '<span class="badge badge-success">' . htmlspecialchars(t('common.active')) . '</span>' : '<span class="badge badge-inactive">' . htmlspecialchars(t('common.inactive')) . '</span>' ?></td>
                        <td><?= date('d/m/Y H:i', strtotime($row['created_at'])) ?></td>
                        <td class="actions-cell">
                            <form method="post" style="display:inline">
                                <?= csrfField() ?>
                                <input type="hidden" name="toggle" value="<?= $row['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-secondary"><?= $row['is_active'] ? htmlspecialchars(t('news.deactivate')) : htmlspecialchars(t('news.activate')) ?></button>
                            </form>
                            <a href="?edit=<?= $row['id'] ?>" class="btn btn-sm btn-secondary"><?= htmlspecialchars(t('news.edit_btn')) ?></a>
                            <form method="post" style="display:inline">
                                <?= csrfField() ?>
                                <input type="hidden" name="delete" value="<?= $row['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm(<?= htmlspecialchars(json_encode(t('news.delete_confirm')), ENT_QUOTES) ?>)"><?= htmlspecialchars(t('news.delete')) ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
