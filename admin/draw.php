<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$message = '';
$message_type = '';

$draw_mode = getDrawMode($conn);
$is_random_mode = ($draw_mode === 'random_org' || $draw_mode === 'random_int');
$unique_only = isUniqueWinnersOnly($conn);
$digits = getNumberDigits($conn);
$num_min = str_repeat('0', $digits);
$num_max = str_repeat('9', $digits);
$admin_id = isset($_SESSION['admin_id']) ? (int)$_SESSION['admin_id'] : null;

// Handle winner assignment via POST
$just_assigned = false;
if (isset($_POST['assign']) && isset($_POST['prize_id'])) {
    requireCsrf();
    $prize_id = (int)$_POST['prize_id'];
    $participant_id = (int)$_POST['assign'];
    $audit_id = isset($_POST['audit_id']) ? (int)$_POST['audit_id'] : 0;

    $winning_number = isset($_POST['number']) ? formatLotteryNumber($_POST['number'], $digits) : null;

    // In random modes the assignment must reference a certified unconfirmed audit
    $audit = null;
    if ($is_random_mode) {
        if ($audit_id <= 0) {
            $message = t('draw.err_random_active');
            $message_type = 'error';
        } elseif (!drawTableExists($conn)) {
            $message = t('draw.err_db_pending');
            $message_type = 'error';
        } else {
            $audit = getDrawAuditById($conn, $audit_id);
            if (!$audit || (int)$audit['prize_id'] !== $prize_id || !empty($audit['confirmed_at'])) {
                $message = t('draw.err_invalid_draw');
                $message_type = 'error';
                $audit = null;
                $audit_id = 0;
            }
        }
        if ($message_type === 'error' && $message !== '') {
            // skip assignment below
            $prize_id = 0;
        }
    }

    if ($prize_id > 0) {
        $check = mysqli_query($conn, "SELECT id FROM winners WHERE prize_id = $prize_id");
        if (mysqli_num_rows($check) > 0) {
            $message = t('draw.err_prize_taken');
            $message_type = 'error';
        } else {
            $already_won = false;
            if ($unique_only) {
                $check2 = mysqli_query($conn, "SELECT id FROM winners WHERE participant_id = $participant_id");
                $already_won = mysqli_num_rows($check2) > 0;
            }
            if ($already_won) {
                $message = t('draw.err_already_won');
                $message_type = 'error';
            } else {
                if ($is_random_mode && $audit) {
                    mysqli_begin_transaction($conn);
                    $stmt = mysqli_prepare($conn, 'INSERT INTO winners (participant_id, prize_id, number) VALUES (?, ?, ?)');
                    mysqli_stmt_bind_param($stmt, 'iis', $participant_id, $prize_id, $winning_number);
                    $ok = mysqli_stmt_execute($stmt);
                    mysqli_stmt_close($stmt);
                    $ok = $ok && confirmDrawAudit($conn, $audit_id, $participant_id, $winning_number);
                    if ($ok) {
                        mysqli_commit($conn);
                        $p_result = mysqli_query($conn, "SELECT name FROM participants WHERE id = $participant_id");
                        $p_row = mysqli_fetch_assoc($p_result);
                        $message = t('draw.ok_assigned_mode', ['name' => htmlspecialchars($p_row['name']), 'mode' => htmlspecialchars(drawModeLabel($draw_mode))]);
                        $message_type = 'success';
                        $just_assigned = true;
                    } else {
                        mysqli_rollback($conn);
                        $message = t('draw.err_confirm');
                        $message_type = 'error';
                    }
                } else {
                    $stmt = mysqli_prepare($conn, 'INSERT INTO winners (participant_id, prize_id, number) VALUES (?, ?, ?)');
                    mysqli_stmt_bind_param($stmt, 'iis', $participant_id, $prize_id, $winning_number);
                    if (mysqli_stmt_execute($stmt)) {
                        $p_result = mysqli_query($conn, "SELECT name FROM participants WHERE id = $participant_id");
                        $p_row = mysqli_fetch_assoc($p_result);
                        $message = t('draw.ok_assigned', ['name' => htmlspecialchars($p_row['name'])]);
                        $message_type = 'success';
                        $just_assigned = true;
                        // Log manual draws for traceability (no IP stored)
                        if (!$is_random_mode && drawTableExists($conn)) {
                            $log_id = createDrawAudit($conn, $prize_id, 'manual', $winning_number ?: $num_min, null, null, $admin_id);
                            if ($log_id) {
                                confirmDrawAudit($conn, $log_id, $participant_id, $winning_number);
                            }
                        }
                    }
                    mysqli_stmt_close($stmt);
                }
            }
        }
    }
}

// Handle random number generation via POST (random modes only)
$preview_participant = null;
$preview_number = '';
$preview_actual_number = '';
$preview_wrapped = false;
$preview_prize_id = null;
$preview_audit_id = 0;
$preview_audit = null;

if (!$just_assigned && $is_random_mode && isset($_POST['generate_random']) && isset($_POST['prize_id'])) {
    requireCsrf();
    $gen_prize_id = (int)$_POST['prize_id'];

    $prize_check = mysqli_query($conn, "SELECT id FROM prizes WHERE id = $gen_prize_id AND is_active = 1 AND id NOT IN (SELECT prize_id FROM winners)");
    if (mysqli_num_rows($prize_check) === 0) {
        $message = t('draw.err_assigned_short');
        $message_type = 'error';
    } elseif (!drawTableExists($conn)) {
            $message = t('draw.err_db_pending');
        $message_type = 'error';
    } else {
        $drawn = null;
        $provider = null;
        $raw = null;
        if ($draw_mode === 'random_org') {
            $fetched = fetchRandomOrgNumber($digits);
            if (isset($fetched['error'])) {
                $message = t('draw.err_random_failed', ['error' => $fetched['error']]);
                $message_type = 'error';
            } else {
                $drawn = $fetched['number'];
                $provider = 'random.org';
                $raw = $fetched['raw'];
            }
        } else {
            $drawn = generateLocalRandomNumber($digits);
            $provider = 'local-csprng';
            $raw = $drawn;
        }

        if ($drawn !== null) {
            $new_audit_id = createDrawAudit($conn, $gen_prize_id, $draw_mode, $drawn, $provider, $raw, $admin_id);
            if (!$new_audit_id) {
                $message = t('draw.err_store_failed');
                $message_type = 'error';
            } else {
                $found = findClosestParticipant($conn, $drawn);
                if (!$found) {
                    $message = getDrawExhaustedMessage($conn);
                    $message_type = 'error';
                } else {
                    $preview_participant = $found['participant'];
                    $preview_number = $drawn;
                    $preview_actual_number = $found['winning_number'];
                    $preview_wrapped = !empty($found['wrapped']);
                    $preview_prize_id = $gen_prize_id;
                    $preview_audit_id = $new_audit_id;
                    $preview_audit = getDrawAuditById($conn, $new_audit_id);
                }
            }
        }
    }
}

// Reload a pending random preview via GET (allows refresh without regenerating)
if (!$just_assigned && $is_random_mode && !$preview_participant && isset($_GET['audit_id'])) {
    $reload_id = (int)$_GET['audit_id'];
    if (drawTableExists($conn)) {
        $reload = getDrawAuditById($conn, $reload_id);
        if ($reload && empty($reload['confirmed_at'])) {
            $rp = (int)$reload['prize_id'];
            $prize_check = mysqli_query($conn, "SELECT id FROM prizes WHERE id = $rp AND is_active = 1 AND id NOT IN (SELECT prize_id FROM winners)");
            if (mysqli_num_rows($prize_check) > 0) {
                $found = findClosestParticipant($conn, $reload['drawn_number']);
                if ($found) {
                    $preview_participant = $found['participant'];
                    $preview_number = $reload['drawn_number'];
                    $preview_actual_number = $found['winning_number'];
                    $preview_wrapped = !empty($found['wrapped']);
                    $preview_prize_id = $rp;
                    $preview_audit_id = $reload_id;
                    $preview_audit = $reload;
                }
            }
        }
    }
}

// Handle manual number lookup for preview (manual mode only)
if (!$just_assigned && !$preview_participant && isset($_GET['number']) && isset($_GET['prize_id'])) {
    if ($is_random_mode) {
        $message = t('draw.err_manual_disabled', ['mode' => drawModeLabel($draw_mode)]);
        $message_type = 'error';
    } else {
        $number = formatLotteryNumber($_GET['number'], $digits);
        $preview_number = $number;
        $preview_prize_id = (int)$_GET['prize_id'];

        $prize_check = mysqli_query($conn, "SELECT id FROM prizes WHERE id = $preview_prize_id AND is_active = 1 AND id NOT IN (SELECT prize_id FROM winners)");
        if (mysqli_num_rows($prize_check) === 0) {
            $message = t('draw.err_assigned_short');
            $message_type = 'error';
        } else {
            $found = findClosestParticipant($conn, $number);
            if ($found) {
                $preview_participant = $found['participant'];
                $preview_actual_number = $found['winning_number'];
                $preview_wrapped = !empty($found['wrapped']);
            } else {
                $message = getDrawExhaustedMessage($conn);
                $message_type = 'error';
            }
        }
    }
}

$pending_prizes = getPendingPrizes($conn);

require_once __DIR__ . '/header.php';
?>

<div class="page-header">
    <h1><?= htmlspecialchars(t('draw.title')) ?></h1>
    <p class="page-description"><?= htmlspecialchars(t('draw.numbers_line', ['label' => numberDigitsLabel($digits)])) ?> — <a href="<?= adminUrl('dashboard.php') ?>"><?= htmlspecialchars(t('draw.change_dash')) ?></a></p>
    <p class="page-description"><?= $unique_only ? htmlspecialchars(t('draw.winners_unique')) : htmlspecialchars(t('draw.winners_repeat')) ?> — <a href="<?= adminUrl('dashboard.php') ?>"><?= htmlspecialchars(t('draw.change_dash')) ?></a></p>
    <?php if ($draw_mode === 'manual'): ?>
        <p class="page-description"><?= htmlspecialchars(t('draw.mode_manual')) ?> <a href="<?= adminUrl('dashboard.php') ?>"><?= htmlspecialchars(t('draw.change_mode')) ?></a></p>
    <?php elseif ($draw_mode === 'random_org'): ?>
        <p class="page-description"><?= htmlspecialchars(t('draw.mode_org')) ?> <a href="<?= adminUrl('dashboard.php') ?>"><?= htmlspecialchars(t('draw.change_mode')) ?></a></p>
    <?php else: ?>
        <p class="page-description"><?= htmlspecialchars(t('draw.mode_local', ['max' => getNumberRangeMax($digits)])) ?> <a href="<?= adminUrl('dashboard.php') ?>"><?= htmlspecialchars(t('draw.change_mode')) ?></a></p>
    <?php endif; ?>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?= $message_type ?>"><?= $message ?></div>
<?php endif; ?>

<?php if ($preview_participant): ?>
    <div class="draw-card" style="border: 2px solid #2e7d32;">
        <div class="draw-card-header" style="background:#2e7d32;">
            <h2><?= htmlspecialchars(t('draw.found')) ?></h2>
        </div>
        <div class="draw-card-body" style="text-align:center;padding:30px;">
            <div style="margin-bottom:15px;">
                <span style="font-size:0.9rem;color:#888;"><?= htmlspecialchars(t('draw.drawn')) ?></span>
                <div style="font-size:2.5rem;font-weight:700;font-family:'Courier New',monospace;color:#888;text-decoration:line-through;">
                    <?= htmlspecialchars($preview_number) ?>
                </div>
            </div>
            <div style="margin-bottom:20px;">
                <span style="font-size:0.9rem;color:#2e7d32;"><?= htmlspecialchars(t('draw.winning_number')) ?></span>
                <div style="font-size:3.5rem;font-weight:700;font-family:'Courier New',monospace;color:#13233C;">
                    <?= htmlspecialchars($preview_actual_number) ?>
                </div>
                <?php if (!empty($preview_wrapped)): ?>
                    <div style="font-size:0.85rem;color:#8d6e00;margin-top:8px;"><?= htmlspecialchars(t('draw.wrapped', ['drawn' => $preview_number, 'min' => $num_min])) ?></div>
                <?php elseif ($preview_actual_number !== $preview_number && $preview_number !== ''): ?>
                    <div style="font-size:0.85rem;color:#666;margin-top:8px;"><?= htmlspecialchars(t('draw.taken_next', ['drawn' => $preview_number])) ?></div>
                <?php endif; ?>
            </div>
            <div style="font-size:1.4rem;margin-bottom:5px;">
                <strong><?= htmlspecialchars($preview_participant['name']) ?></strong>
            </div>
            <?php
            $nums = array_filter([$preview_participant['number'], $preview_participant['number2'], $preview_participant['number3']]);
            ?>
            <div style="margin-bottom:20px;color:#666;font-size:0.9rem;">
                <?= htmlspecialchars(t('draw.numbers_of', ['numbers' => implode(', ', $nums)])) ?>
            </div>
            <?php if ($preview_audit): ?>
                <div style="margin-bottom:20px;color:#666;font-size:0.85rem;">
                    <?= htmlspecialchars(t('draw.source')) ?> <strong><?= htmlspecialchars($preview_audit['provider'] ?: $preview_audit['mode']) ?></strong>
                    &middot; Proof: <code><?= htmlspecialchars(substr($preview_audit['proof_hash'], 0, 12)) ?></code>
                    &middot; <?= htmlspecialchars($preview_audit['created_at']) ?>
                </div>
            <?php else: ?>
                <div style="margin-bottom:20px;color:#666;font-size:0.85rem;">
                    Source: <strong><?= htmlspecialchars(t('draw.entered_manually')) ?></strong>
                </div>
            <?php endif; ?>
            <form method="post" style="display:inline">
                <?= csrfField() ?>
                <input type="hidden" name="assign" value="<?= $preview_participant['id'] ?>">
                <input type="hidden" name="prize_id" value="<?= $preview_prize_id ?>">
                <input type="hidden" name="number" value="<?= htmlspecialchars($preview_actual_number) ?>">
                <?php if ($preview_audit_id): ?>
                    <input type="hidden" name="audit_id" value="<?= $preview_audit_id ?>">
                <?php endif; ?>
                <button type="submit" class="btn btn-primary" style="padding:12px 30px;font-size:1rem;"
                   onclick="return confirm(<?= htmlspecialchars(json_encode(t('draw.confirm_question', ['name' => $preview_participant['name']])), ENT_QUOTES) ?>)"><?= htmlspecialchars(t('draw.confirm')) ?></button>
            </form>
        </div>
    </div>
<?php endif; ?>

<?php if (empty($pending_prizes)): ?>
    <div class="alert alert-success"><?= htmlspecialchars(t('draw.all_assigned')) ?> <a href="<?= adminUrl('winners.php') ?>"><?= htmlspecialchars(t('draw.view_winners')) ?></a></div>
<?php else: ?>
    <?php foreach ($pending_prizes as $prize): ?>
        <div class="draw-card">
            <div class="draw-card-header">
                <h2><?= htmlspecialchars(t('draw.prize_prefix', ['pos' => $prize['position'], 'name' => $prize['name']])) ?></h2>
            </div>
            <div class="draw-card-body">
                <?php if ($draw_mode === 'manual'): ?>
                    <form method="get" class="inline-form">
                        <input type="hidden" name="prize_id" value="<?= $prize['id'] ?>">
                        <div class="form-row">
                            <div class="form-group form-group-number">
                                <label for="number_<?= $prize['id'] ?>"><?= htmlspecialchars(t('draw.enter_number', ['min' => $num_min, 'max' => $num_max])) ?></label>
                                <div class="input-group">
                                    <input type="text" id="number_<?= $prize['id'] ?>" name="number"
                                           placeholder="e.g. <?= $num_min === '000' ? '042' : '0042' ?>" maxlength="<?= $digits ?>" pattern="[0-9]{1,<?= $digits ?>}"
                                           autocomplete="off" required>
                                    <button type="submit" class="btn btn-primary btn-sm"><?= htmlspecialchars(t('draw.find')) ?></button>
                                </div>
                            </div>
                        </div>
                    </form>
                <?php else: ?>
                    <form method="post" class="inline-form">
                        <?= csrfField() ?>
                        <input type="hidden" name="prize_id" value="<?= $prize['id'] ?>">
                        <input type="hidden" name="generate_random" value="1">
                        <div class="form-row">
                            <div class="form-group">
                                <label><?= htmlspecialchars(t('draw.random_label', ['provider' => $draw_mode === 'random_org' ? 'random.org' : 'local random_int'])) ?></label>
                                <button type="submit" class="btn btn-primary">
                                    <?= $draw_mode === 'random_org' ? htmlspecialchars(t('draw.generate_org')) : htmlspecialchars(t('draw.generate_local')) ?>
                                </button>
                            </div>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<script src="/assets/js/admin.js"></script>
<?php require_once __DIR__ . '/footer.php'; ?>
