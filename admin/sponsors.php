<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/header.php';

$msg = '';
$error = '';

if (isset($_POST['save']) && isset($_POST['id'])) {
    requireCsrf();
    $id = (int)$_POST['id'];
    $text = trim($_POST['text']);
    $link = trim($_POST['link']);
    $sort_order = (int)$_POST['sort_order'];
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    if ($id === 0) {
        $img = '';
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $filename = 'sponsor_' . time() . '.' . $ext;
            $dest = __DIR__ . '/../assets/img/sponsors/' . $filename;
            if (move_uploaded_file($_FILES['image']['tmp_name'], $dest)) {
                $img = 'assets/img/sponsors/' . $filename;
            }
        }
        $stmt = mysqli_prepare($conn, "INSERT INTO sponsors (image_path, text, link, sort_order, is_active) VALUES (?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, 'sssii', $img, $text, $link, $sort_order, $is_active);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $msg = t('spon.msg_added');
    } else {
        $full_path = '';
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $filename = 'sponsor_' . time() . '.' . $ext;
            $dest = __DIR__ . '/../assets/img/sponsors/' . $filename;
            if (move_uploaded_file($_FILES['image']['tmp_name'], $dest)) {
                $full_path = 'assets/img/sponsors/' . $filename;
            }
        }

        if ($full_path) {
            $stmt = mysqli_prepare($conn, "UPDATE sponsors SET image_path = ?, text = ?, link = ?, sort_order = ?, is_active = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, 'sssiii', $full_path, $text, $link, $sort_order, $is_active, $id);
        } else {
            $stmt = mysqli_prepare($conn, "UPDATE sponsors SET text = ?, link = ?, sort_order = ?, is_active = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, 'ssiii', $text, $link, $sort_order, $is_active, $id);
        }
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $msg = t('spon.msg_updated');
    }
    header('Location: ' . adminUrl('sponsors.php') . '?msg=' . urlencode($msg));
    exit;
}

if (isset($_POST['delete'])) {
    requireCsrf();
    $id = (int)$_POST['delete'];
    $stmt = mysqli_prepare($conn, "DELETE FROM sponsors WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    header('Location: ' . adminUrl('sponsors.php') . '?msg=' . urlencode(t('spon.msg_deleted')));
    exit;
}

if (isset($_POST['toggle'])) {
    requireCsrf();
    $id = (int)$_POST['toggle'];
    $stmt = mysqli_prepare($conn, "UPDATE sponsors SET is_active = NOT is_active WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    header('Location: ' . adminUrl('sponsors.php') . '?msg=' . urlencode(t('spon.msg_toggled')));
    exit;
}

$edit = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $result = mysqli_query($conn, "SELECT * FROM sponsors WHERE id = $id");
    $edit = mysqli_fetch_assoc($result);
}

$sponsors = mysqli_query($conn, "SELECT * FROM sponsors ORDER BY sort_order ASC, id ASC");
?>
<div class="page-header">
    <h1><?= htmlspecialchars(t('spon.title')) ?></h1>
</div>

<?php if (isset($_GET['msg'])): ?>
    <p style="color:green; font-weight:600;"><?= htmlspecialchars($_GET['msg']) ?></p>
<?php endif; ?>

<div class="form-card" style="margin-bottom:20px;">
    <h2><?= $edit ? htmlspecialchars(t('spon.edit')) : htmlspecialchars(t('spon.add')) ?></h2>
    <form method="post" enctype="multipart/form-data">
        <?= csrfField() ?>
        <input type="hidden" name="id" value="<?= $edit ? (int)$edit['id'] : 0 ?>">
        <div class="form-group">
            <label><?= htmlspecialchars(t('spon.image')) ?></label>
            <input type="file" name="image" accept="image/png,image/jpeg,image/gif,image/webp,image/svg+xml" <?= $edit ? '' : 'required' ?>>
            <?php if ($edit && $edit['image_path']): ?>
                <p style="margin:5px 0;"><img src="/<?= htmlspecialchars($edit['image_path']) ?>" alt="" style="max-height:60px;"></p>
            <?php endif; ?>
        </div>
        <div class="form-group">
            <label><?= htmlspecialchars(t('spon.text')) ?></label>
            <input type="text" name="text" value="<?= $edit ? htmlspecialchars($edit['text']) : '' ?>" maxlength="500" style="width:100%;">
        </div>
        <div class="form-group">
            <label><?= htmlspecialchars(t('spon.link')) ?></label>
            <input type="url" name="link" value="<?= $edit ? htmlspecialchars($edit['link']) : '' ?>" maxlength="255" style="width:100%;">
        </div>
        <div class="form-group" style="display:inline-block; margin-right:15px;">
            <label><?= htmlspecialchars(t('spon.order')) ?></label>
            <input type="number" name="sort_order" value="<?= $edit ? (int)$edit['sort_order'] : 0 ?>" style="width:70px;">
        </div>
        <div class="form-group" style="display:inline-block;">
            <label><input type="checkbox" name="is_active" <?= (!$edit || $edit['is_active']) ? 'checked' : '' ?>> <?= htmlspecialchars(t('spon.active_label')) ?></label>
        </div>
        <div style="margin-top:10px;">
            <button type="submit" name="save" class="btn btn-primary"><?= $edit ? htmlspecialchars(t('spon.update_btn')) : htmlspecialchars(t('spon.add_btn')) ?></button>
            <?php if ($edit): ?>
                <a href="<?= adminUrl('sponsors.php') ?>" class="btn btn-secondary"><?= htmlspecialchars(t('spon.cancel')) ?></a>
            <?php endif; ?>
        </div>
    </form>
</div>

<table class="data-table">
    <thead>
        <tr>
            <th><?= htmlspecialchars(t('spon.th_order')) ?></th>
            <th><?= htmlspecialchars(t('spon.th_image')) ?></th>
            <th><?= htmlspecialchars(t('spon.th_text')) ?></th>
            <th><?= htmlspecialchars(t('spon.th_link')) ?></th>
            <th><?= htmlspecialchars(t('spon.th_active')) ?></th>
            <th><?= htmlspecialchars(t('spon.th_actions')) ?></th>
        </tr>
    </thead>
    <tbody>
        <?php while ($row = mysqli_fetch_assoc($sponsors)): ?>
        <tr>
            <td><?= (int)$row['sort_order'] ?></td>
            <td>
                <?php if ($row['image_path']): ?>
                    <img src="/<?= htmlspecialchars($row['image_path']) ?>" alt="" style="max-height:50px; max-width:120px;">
                <?php else: ?>
                    <em><?= htmlspecialchars(t('spon.no_image')) ?></em>
                <?php endif; ?>
            </td>
            <td><?= htmlspecialchars(substr($row['text'], 0, 60)) ?></td>
            <td><?= $row['link'] ? htmlspecialchars(substr($row['link'], 0, 40)) : '—' ?></td>
            <td><?= $row['is_active'] ? htmlspecialchars(t('common.yes')) : htmlspecialchars(t('common.no')) ?></td>
            <td class="actions-cell">
                <a href="?edit=<?= (int)$row['id'] ?>" class="btn btn-primary btn-sm"><?= htmlspecialchars(t('spon.edit_btn')) ?></a>
                <form method="post" style="display:inline">
                    <?= csrfField() ?>
                    <input type="hidden" name="toggle" value="<?= (int)$row['id'] ?>">
                    <button type="submit" class="btn btn-secondary btn-sm"><?= $row['is_active'] ? htmlspecialchars(t('common.deactivate')) : htmlspecialchars(t('common.activate')) ?></button>
                </form>
                <form method="post" style="display:inline">
                    <?= csrfField() ?>
                    <input type="hidden" name="delete" value="<?= (int)$row['id'] ?>">
                    <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm(<?= htmlspecialchars(json_encode(t('spon.delete_confirm')), ENT_QUOTES) ?>)"><?= htmlspecialchars(t('spon.delete')) ?></button>
                </form>
            </td>
        </tr>
        <?php endwhile; ?>
        <?php if (mysqli_num_rows($sponsors) === 0): ?>
        <tr><td colspan="6" style="text-align:center; color:#888;"><?= htmlspecialchars(t('spon.empty')) ?></td></tr>
        <?php endif; ?>
    </tbody>
</table>

<?php require_once __DIR__ . '/footer.php'; ?>
