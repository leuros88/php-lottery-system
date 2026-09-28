<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$message = '';
$message_type = '';

// Handle undo winner
if (isset($_POST['undo'])) {
    requireCsrf();
    $id = (int)$_POST['undo'];
    $stmt = mysqli_prepare($conn, 'DELETE FROM winners WHERE id = ?');
    mysqli_stmt_bind_param($stmt, 'i', $id);
    if (mysqli_stmt_execute($stmt)) {
        $message = t('win.msg_undone');
        $message_type = 'success';
    }
    mysqli_stmt_close($stmt);
}

$winners = getWinnersWithDetails($conn);

require_once __DIR__ . '/header.php';
?>

<div class="page-header">
    <h1><?= htmlspecialchars(t('win.title')) ?></h1>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?= $message_type ?>"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<div class="table-card">
    <table class="data-table">
        <thead>
            <tr>
                <th><?= htmlspecialchars(t('win.th_prize')) ?></th>
                <th><?= htmlspecialchars(t('win.th_participant')) ?></th>
                <th><?= htmlspecialchars(t('win.th_number')) ?></th>
                <th><?= htmlspecialchars(t('win.th_draw')) ?></th>
                <th><?= htmlspecialchars(t('win.th_date')) ?></th>
                <th><?= htmlspecialchars(t('win.th_actions')) ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($winners)): ?>
                <tr><td colspan="6" class="empty"><?= htmlspecialchars(t('win.empty')) ?> <a href="<?= adminUrl('draw.php') ?>"><?= htmlspecialchars(t('win.live_draw')) ?></a></td></tr>
            <?php else: ?>
                <?php foreach ($winners as $w): ?>
                    <?php
                    $audit = null;
                    if (drawTableExists($conn) && isset($w['prize_id'], $w['participant_id'])) {
                        $audit = getDrawAuditForWinner($conn, (int)$w['prize_id'], (int)$w['participant_id']);
                    }
                    ?>
                    <tr>
                        <td><strong>#<?= $w['position'] ?></strong> <?= htmlspecialchars($w['prize_name']) ?></td>
                        <td><?= htmlspecialchars($w['participant_name']) ?></td>
                        <td class="number-cell"><?= htmlspecialchars($w['winning_number']) ?></td>
                        <td style="font-size:0.85rem;">
                            <?php if ($audit): ?>
                                <strong><?= htmlspecialchars(drawModeLabel($audit['mode'])) ?></strong><br>
                                <span style="color:#666;"><?= htmlspecialchars($audit['provider'] ?: t('win.manual_entry')) ?> &middot; <code><?= htmlspecialchars(substr($audit['proof_hash'], 0, 10)) ?></code></span>
                            <?php else: ?>
                                <span style="color:#888;"><?= htmlspecialchars(t('win.manual')) ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?= date('d/m/Y H:i', strtotime($w['created_at'])) ?></td>
                        <td class="actions-cell">
                            <form method="post" style="display:inline">
                                <?= csrfField() ?>
                                <input type="hidden" name="undo" value="<?= $w['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm(<?= htmlspecialchars(json_encode(t('win.undo_confirm')), ENT_QUOTES) ?>)"><?= htmlspecialchars(t('win.undo')) ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
