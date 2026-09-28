<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$digits = getNumberDigits($conn);
$num_min = str_repeat('0', $digits);
$num_max = str_repeat('9', $digits);
$edit_participant = null;
$auto_assigned = [];
$result_modal = null;

// Handle delete
if (isset($_POST['delete'])) {
    requireCsrf();
    $id = (int)$_POST['delete'];
    $stmt = mysqli_prepare($conn, 'DELETE FROM participants WHERE id = ?');
    mysqli_stmt_bind_param($stmt, 'i', $id);
    if (mysqli_stmt_execute($stmt)) {
        $result_modal = [
            'title' => t('part.modal_deleted'),
            'message' => t('part.modal_deleted_msg'),
            'auto_assigned' => []
        ];
    }
    mysqli_stmt_close($stmt);
}

// Handle edit get
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $stmt = mysqli_prepare($conn, 'SELECT id, name, number, number2, number3 FROM participants WHERE id = ?');
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $edit_participant = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
}

// Handle add/edit post
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $name = trim($_POST['name'] ?? '');
    $edit_id = isset($_POST['edit_id']) ? (int)$_POST['edit_id'] : 0;

    $raw_numbers = [];
    $n1 = trim($_POST['number1'] ?? '');
    $n2 = trim($_POST['number2'] ?? '');
    $n3 = trim($_POST['number3'] ?? '');
    if ($n1 !== '') $raw_numbers[] = $n1;
    if ($n2 !== '') $raw_numbers[] = $n2;
    if ($n3 !== '') $raw_numbers[] = $n3;

    $error_reason = '';

    if (empty($name)) {
        $error_reason = t('part.err_name_req');
    } elseif (empty($raw_numbers)) {
        $error_reason = t('part.err_number_req', ['min' => $num_min, 'max' => $num_max]);
    } else {
        $valid = true;
        foreach ($raw_numbers as $n) {
            if (!preg_match('/^\d{' . $digits . '}$/', $n)) {
                $error_reason = t('part.err_digits', ['digits' => $digits, 'min' => $num_min, 'max' => $num_max]);
                $valid = false;
                break;
            }
        }

        if ($valid && count($raw_numbers) !== count(array_unique($raw_numbers))) {
            $error_reason = t('part.err_dup_numbers');
            $valid = false;
        }

        if ($valid) {
            if ($edit_id) {
                $stmt = mysqli_prepare($conn, 'SELECT id FROM participants WHERE name = ? AND id != ?');
                mysqli_stmt_bind_param($stmt, 'si', $name, $edit_id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_store_result($stmt);
                if (mysqli_stmt_num_rows($stmt) > 0) {
                    $error_reason = 'duplicate';
                    $valid = false;
                }
                mysqli_stmt_close($stmt);
            } elseif (isParticipantRegistered($conn, $name)) {
                $error_reason = 'duplicate';
                $valid = false;
            }
        }

        if ($valid && isBlacklisted($conn, $name)) {
            $error_reason = 'blacklisted';
            $valid = false;
        }

        if ($valid) {
            $final_numbers = [];

            foreach ($raw_numbers as $n) {
                if ($edit_id) {
                    $taken = isNumberTakenExcluding($conn, $n, $edit_id);
                } else {
                    $taken = isNumberTaken($conn, $n);
                }

                if ($taken) {
                    $new_n = getRandomAvailableNumber($conn);
                    if ($new_n) {
                        $final_numbers[] = $new_n;
                        $auto_assigned[] = ['requested' => $n, 'assigned' => $new_n];
                    } else {
                        $error_reason = t('part.err_no_numbers');
                        $valid = false;
                        break;
                    }
                } else {
                    $final_numbers[] = $n;
                }
            }
        }

        if ($valid) {
            $fn1 = $final_numbers[0] ?? null;
            $fn2 = $final_numbers[1] ?? null;
            $fn3 = $final_numbers[2] ?? null;

            if ($edit_id) {
                $stmt = mysqli_prepare($conn, 'UPDATE participants SET name = ?, number = ?, number2 = ?, number3 = ? WHERE id = ?');
                mysqli_stmt_bind_param($stmt, 'ssssi', $name, $fn1, $fn2, $fn3, $edit_id);
                $executed = mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            } else {
                $stmt = mysqli_prepare($conn, 'INSERT INTO participants (name, number, number2, number3) VALUES (?, ?, ?, ?)');
                mysqli_stmt_bind_param($stmt, 'ssss', $name, $fn1, $fn2, $fn3);
                $executed = mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }

            if ($executed) {
                $numbers_str = implode(', ', array_filter([$fn1, $fn2, $fn3]));
                $template_key = $edit_id ? 'participant_update_success' : 'participant_create_success';
                $default_tpl = $edit_id
                    ? "✅ Participant {name} has been updated successfully!\nNumbers: {numbers}"
                    : "✅ Participant {name} has been registered successfully!\nNumbers: {numbers}";
                $success_msg = renderTemplate($conn, $template_key, ['name' => $name, 'numbers' => $numbers_str], $default_tpl);

                $result_modal = [
                    'title' => t('part.modal_success'),
                    'message' => $success_msg,
                    'auto_assigned' => $auto_assigned
                ];
            }
        }
    }

    if (!empty($error_reason) && !$result_modal) {
        if ($error_reason === 'duplicate') {
            $result_modal = [
                'title' => t('part.modal_duplicate'),
                'message' => renderTemplate($conn, 'participant_duplicate', ['name' => $name], "❌ The name '{name}' is already registered."),
                'auto_assigned' => []
            ];
        } elseif ($error_reason === 'blacklisted') {
            $result_modal = [
                'title' => t('part.modal_blacklisted'),
                'message' => renderTemplate($conn, 'participant_blacklisted', ['name' => $name], "❌ The name '{name}' is blacklisted and cannot be added."),
                'auto_assigned' => []
            ];
        } else {
            $result_modal = [
                'title' => t('part.modal_error'),
                'message' => renderTemplate($conn, 'participant_error', ['reason' => $error_reason], "❌ {reason}"),
                'auto_assigned' => []
            ];
        }
    }
}

$result = mysqli_query($conn, 'SELECT id, name, number, number2, number3, created_at FROM participants ORDER BY name ASC');
require_once __DIR__ . '/header.php';
?>

<div class="page-header">
    <h1><?= htmlspecialchars(t('part.title')) ?></h1>
</div>

<div class="form-card">
    <h2><?= $edit_participant ? htmlspecialchars(t('part.edit')) : htmlspecialchars(t('part.add')) ?></h2>
    <form method="post" class="inline-form" id="participant-form">
        <?= csrfField() ?>
        <?php if ($edit_participant): ?>
            <input type="hidden" name="edit_id" value="<?= $edit_participant['id'] ?>">
        <?php endif; ?>
        <div class="form-row">
            <div class="form-group">
                <label for="name"><?= htmlspecialchars(t('part.name')) ?></label>
                <input type="text" id="name" name="name" value="<?= htmlspecialchars($edit_participant['name'] ?? '') ?>" required>
            </div>
            <div class="form-group form-group-number">
                <label for="number1"><?= htmlspecialchars(t('part.num1', ['min' => $num_min, 'max' => $num_max])) ?></label>
                <div class="input-group">
                    <input type="text" id="number1" name="number1" value="<?= htmlspecialchars($edit_participant['number'] ?? '') ?>" maxlength="<?= $digits ?>" pattern="\d{<?= $digits ?>}" placeholder="<?= $num_min ?>" required>
                    <button type="button" class="btn btn-secondary" onclick="randomNumber('number1', this)">&#9852;</button>
                </div>
            </div>
            <div class="form-group form-group-number">
                <label for="number2"><?= htmlspecialchars(t('part.num2')) ?> <span class="label-opt"><?= htmlspecialchars(t('part.optional')) ?></span></label>
                <div class="input-group">
                    <input type="text" id="number2" name="number2" value="<?= htmlspecialchars($edit_participant['number2'] ?? '') ?>" maxlength="<?= $digits ?>" pattern="\d{<?= $digits ?>}" placeholder="<?= $num_min ?>">
                    <button type="button" class="btn btn-secondary" onclick="randomNumber('number2', this)">&#9852;</button>
                </div>
            </div>
            <div class="form-group form-group-number">
                <label for="number3"><?= htmlspecialchars(t('part.num3')) ?> <span class="label-opt"><?= htmlspecialchars(t('part.optional')) ?></span></label>
                <div class="input-group">
                    <input type="text" id="number3" name="number3" value="<?= htmlspecialchars($edit_participant['number3'] ?? '') ?>" maxlength="<?= $digits ?>" pattern="\d{<?= $digits ?>}" placeholder="<?= $num_min ?>">
                    <button type="button" class="btn btn-secondary" onclick="randomNumber('number3', this)">&#9852;</button>
                </div>
            </div>
            <div class="form-group form-group-submit">
                <label>&nbsp;</label>
                <button type="submit" class="btn btn-primary">
                    <?= $edit_participant ? htmlspecialchars(t('part.update_btn')) : htmlspecialchars(t('part.add_btn')) ?>
                </button>
                <?php if ($edit_participant): ?>
                    <a href="<?= adminUrl('participants.php') ?>" class="btn btn-secondary"><?= htmlspecialchars(t('part.cancel')) ?></a>
                <?php endif; ?>
            </div>
        </div>
    </form>
</div>

<div class="table-card">
    <table class="data-table">
        <thead>
            <tr>
                <th><?= htmlspecialchars(t('part.th_numbers')) ?></th>
                <th><?= htmlspecialchars(t('part.th_name')) ?></th>
                <th><?= htmlspecialchars(t('part.th_registered')) ?></th>
                <th><?= htmlspecialchars(t('part.th_actions')) ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (mysqli_num_rows($result) === 0): ?>
                <tr><td colspan="4" class="empty"><?= htmlspecialchars(t('part.empty')) ?></td></tr>
            <?php else: ?>
                <?php while ($row = mysqli_fetch_assoc($result)): ?>
                    <?php
                    $nums = array_filter([$row['number'], $row['number2'], $row['number3']]);
                    ?>
                    <tr>
                        <td class="number-cell"><?= htmlspecialchars(implode(', ', $nums)) ?></td>
                        <td><?= htmlspecialchars($row['name']) ?></td>
                        <td><?= date('d/m/Y H:i', strtotime($row['created_at'])) ?></td>
                        <td class="actions-cell">
                            <a href="?edit=<?= $row['id'] ?>" class="btn btn-sm btn-secondary"><?= htmlspecialchars(t('part.edit_btn')) ?></a>
                            <form method="post" style="display:inline">
                                <?= csrfField() ?>
                                <input type="hidden" name="delete" value="<?= $row['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm(<?= htmlspecialchars(json_encode(t('part.delete_confirm', ['name' => $row['name']])), ENT_QUOTES) ?>)"><?= htmlspecialchars(t('part.delete')) ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php if ($result_modal): ?>
<div class="modal-overlay" id="result-modal" style="display:flex;">
    <div class="modal-box">
        <div class="modal-header">
            <h2><?= htmlspecialchars($result_modal['title']) ?></h2>
        </div>
        <div class="modal-body">
            <div id="modal-message"><?= nl2br(htmlspecialchars($result_modal['message'])) ?></div>
            <?php if (!empty($result_modal['auto_assigned'])): ?>
            <hr>
            <p><strong><?= htmlspecialchars(t('part.auto_assigned')) ?></strong></p>
            <ul class="auto-assign-list">
                <?php foreach ($result_modal['auto_assigned'] as $a): ?>
                <li><span class="num-requested"><?= htmlspecialchars($a['requested']) ?></span> &rarr; <span class="num-assigned"><?= htmlspecialchars($a['assigned']) ?></span></li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="copyModalMessage()"><?= htmlspecialchars(t('common.copy')) ?></button>
            <button type="button" class="btn btn-primary" onclick="this.closest('.modal-overlay').style.display='none'"><?= htmlspecialchars(t('common.close')) ?></button>
        </div>
    </div>
</div>
<script>
function closeModal() {
    var m = document.getElementById('result-modal');
    if (m) m.style.display = 'none';
}
function copyModalMessage() {
    var el = document.getElementById('modal-message');
    if (!el) return;
    var text = el.innerText.trim();
    var btn = document.querySelector('.modal-footer .btn-secondary');
    function done() { if (btn) { btn.textContent = <?= json_encode(t('common.copied')) ?>; setTimeout(function () { btn.textContent = <?= json_encode(t('common.copy')) ?>; }, 2000); } }
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(done).catch(done);
    } else {
        var ta = document.createElement('textarea');
        ta.value = text;
        ta.style.position = 'fixed';
        ta.style.left = '-9999px';
        document.body.appendChild(ta);
        ta.select();
        try { document.execCommand('copy'); } catch(e) {}
        document.body.removeChild(ta);
        done();
    }
}
</script>
<?php endif; ?>

<script src="/assets/js/admin.js"></script>
<?php require_once __DIR__ . '/footer.php'; ?>
