<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

// Global default panel language (all admins, stored in DB).
if (isset($_POST['admin_lang_default'])) {
    requireCsrf();
    $new_lang = strtolower(trim($_POST['admin_lang_default'] ?? ''));
    if (isSupportedLang($new_lang)) {
        $stmt = mysqli_prepare($conn, "REPLACE INTO custom_texts (`key`, content) VALUES ('admin_lang_default', ?)");
        mysqli_stmt_bind_param($stmt, 's', $new_lang);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
    header('Location: ' . adminUrl('dashboard.php'));
    exit;
}

// Personal session language (only this browser).
if (isset($_POST['session_lang'])) {
    requireCsrf();
    $new_lang = strtolower(trim($_POST['session_lang'] ?? ''));
    if (isSupportedLang($new_lang)) {
        setAppLang($new_lang);
    }
    header('Location: ' . adminUrl('dashboard.php'));
    exit;
}

$total_participants = getParticipantCount($conn);
$total_blacklisted = getBlacklistCount($conn);
$total_winners = getWinnerCount($conn);
$available_list = getAvailableNumbers($conn);
$total_available = count($available_list);
$lottery_status = getCustomText($conn, 'lottery_status');
$status_open = ($lottery_status !== 'closed');
$maintenance_mode = getCustomText($conn, 'maintenance_mode');
$maintenance_on = ($maintenance_mode === 'on');
$draw_mode = getDrawMode($conn);
$unique_only = isUniqueWinnersOnly($conn);
$number_digits = getNumberDigits($conn);
$lottery_locked = isLotteryInProgress($conn);
$admin_lang_default = getDefaultAdminLang($conn);
$session_lang = currentLang($conn);
$digits_msg = '';
$digits_msg_type = '';
$draw_mode_msg = '';
$draw_mode_msg_type = '';

if (isset($_POST['toggle_status'])) {
    requireCsrf();
    $new_status = $status_open ? 'closed' : 'open';
    $stmt = mysqli_prepare($conn, "UPDATE custom_texts SET content = ? WHERE `key` = 'lottery_status'");
    mysqli_stmt_bind_param($stmt, 's', $new_status);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    header('Location: ' . adminUrl('dashboard.php'));
    exit;
}

if (isset($_POST['toggle_maintenance'])) {
    requireCsrf();
    $new_mode = $maintenance_on ? 'off' : 'on';
    $stmt = mysqli_prepare($conn, "UPDATE custom_texts SET content = ? WHERE `key` = 'maintenance_mode'");
    mysqli_stmt_bind_param($stmt, 's', $new_mode);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    header('Location: ' . adminUrl('dashboard.php'));
    exit;
}

if (isset($_POST['draw_mode'])) {
    requireCsrf();
    if ($lottery_locked) {
        // No redirect: the lock message must stay visible.
        $draw_mode_msg = lotteryLockedMessage();
        $draw_mode_msg_type = 'error';
    } else {
        $new_draw_mode = trim($_POST['draw_mode']);
        if (in_array($new_draw_mode, getValidDrawModes(), true)) {
            setDrawMode($conn, $new_draw_mode);
        }
        header('Location: ' . adminUrl('dashboard.php'));
        exit;
    }
}

if (isset($_POST['unique_winners'])) {
    requireCsrf();
    setUniqueWinnersOnly($conn, trim($_POST['unique_winners']) === '1');
    header('Location: ' . adminUrl('dashboard.php'));
    exit;
}

if (isset($_POST['number_digits'])) {
    requireCsrf();
    if ($lottery_locked) {
        $digits_msg = lotteryLockedMessage();
        $digits_msg_type = 'error';
    } else {
        list($digits_ok, $digits_msg) = setNumberDigits($conn, (int)$_POST['number_digits']);
        $digits_msg_type = $digits_ok ? 'success' : 'error';
        // Refresh stats shown below (no redirect: the migration result must stay visible).
        $number_digits = getNumberDigits($conn);
        $available_list = getAvailableNumbers($conn);
        $total_available = count($available_list);
    }
}

if (isset($_POST['site_title_save'])) {
    requireCsrf();
    $new_title = trim($_POST['site_title'] ?? '');
    if ($new_title !== '' && mb_strlen($new_title) <= 200) {
        setSiteTitle($conn, $new_title);
    }
    header('Location: ' . adminUrl('dashboard.php'));
    exit;
}

$logo_upload_msg = '';
if (isset($_POST['upload_logo']) && isset($_FILES['logo_file'])) {
    requireCsrf();
    $file = $_FILES['logo_file'];
    $allowed = ['image/png', 'image/jpeg', 'image/gif', 'image/webp', 'image/svg+xml'];
    if ($file['error'] === UPLOAD_ERR_OK && in_array($file['type'], $allowed)) {
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'logo.' . $ext;
        $dest = __DIR__ . '/../assets/img/' . $filename;
        if (move_uploaded_file($file['tmp_name'], $dest)) {
            $logo_path_db = 'assets/img/' . $filename;
            $stmt = mysqli_prepare($conn, "REPLACE INTO custom_texts (`key`, content) VALUES ('logo_path', ?)");
            mysqli_stmt_bind_param($stmt, 's', $logo_path_db);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            $logo_upload_msg = t('dash.logo_saved');
        } else {
            $logo_upload_msg = t('dash.logo_save_error');
        }
    } else {
        $logo_upload_msg = t('dash.logo_invalid');
    }
}

if (isset($_POST['remove_logo'])) {
    requireCsrf();
    $stmt = mysqli_prepare($conn, "REPLACE INTO custom_texts (`key`, content) VALUES ('logo_path', '')");
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    header('Location: ' . adminUrl('dashboard.php'));
    exit;
}

$logo_path = getCustomText($conn, 'logo_path');
$site_title = getSiteTitle($conn);

require_once __DIR__ . '/header.php';
?>

<div class="page-header">
    <h1><?= htmlspecialchars(t('dash.title')) ?></h1>
</div>

<?php if ($maintenance_on): ?>
<div class="alert alert-warning" style="font-weight:600;">
    <?= htmlspecialchars(t('dash.maintenance_on')) ?>
</div>
<?php endif; ?>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-number"><?= $total_participants ?></div>
        <div class="stat-label"><?= htmlspecialchars(t('dash.participants')) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-number"><?= $total_available ?></div>
        <div class="stat-label"><?= htmlspecialchars(t('dash.available')) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-number"><?= $total_blacklisted ?></div>
        <div class="stat-label"><?= htmlspecialchars(t('dash.blacklisted')) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-number"><?= $total_winners ?></div>
        <div class="stat-label"><?= htmlspecialchars(t('dash.winners')) ?></div>
    </div>
</div>

<div class="quick-actions">
    <h2><?= htmlspecialchars(t('dash.quick')) ?></h2>
    <div class="actions-grid">
        <a href="<?= adminUrl('participants.php') ?>" class="action-card">
            <span class="action-icon">+</span>
            <span class="action-text"><?= htmlspecialchars(t('dash.add_participant')) ?></span>
        </a>
        <a href="<?= adminUrl('draw.php') ?>" class="action-card">
            <span class="action-icon">&#9879;</span>
            <span class="action-text"><?= htmlspecialchars(t('dash.live_draw')) ?></span>
        </a>
        <a href="<?= adminUrl('blacklist.php') ?>" class="action-card">
            <span class="action-icon">&#10007;</span>
            <span class="action-text"><?= htmlspecialchars(t('dash.manage_blacklist')) ?></span>
        </a>
        <a href="<?= adminUrl('prizes.php') ?>" class="action-card">
            <span class="action-icon">&#9733;</span>
            <span class="action-text"><?= htmlspecialchars(t('dash.manage_prizes')) ?></span>
        </a>
    </div>
</div>

<div style="display: flex; gap: 20px; flex-wrap: wrap; margin-top: 25px;">
    <div class="form-card" style="flex: 1; min-width: 280px;">
        <h2><?= htmlspecialchars(t('dash.status_title')) ?></h2>
        <p style="margin: 0 0 15px; font-size: 0.95rem; color: #555;">
            <?= htmlspecialchars(t('dash.current')) ?>
            <strong style="color: <?= $status_open ? '#2e7d32' : '#c62828' ?>">
                <?= $status_open ? htmlspecialchars(t('dash.open')) : htmlspecialchars(t('dash.closed')) ?>
            </strong>
        </p>
        <form method="post" style="display:inline">
            <?= csrfField() ?>
            <input type="hidden" name="toggle_status" value="1">
            <button type="submit" class="btn <?= $status_open ? 'btn-danger' : 'btn-primary' ?>">
                <?= $status_open ? htmlspecialchars(t('dash.close_btn')) : htmlspecialchars(t('dash.open_btn')) ?>
            </button>
        </form>
    </div>

    <div class="form-card" style="flex: 1; min-width: 280px;">
        <h2><?= htmlspecialchars(t('dash.maint_title')) ?></h2>
        <p style="margin: 0 0 15px; font-size: 0.95rem; color: #555;">
            <?= htmlspecialchars(t('dash.maint_status')) ?>
            <strong style="color: <?= $maintenance_on ? '#c62828' : '#2e7d32' ?>">
                <?= $maintenance_on ? htmlspecialchars(t('dash.maint_on')) : htmlspecialchars(t('dash.maint_off')) ?>
            </strong>
            <?= htmlspecialchars(t('dash.maint_hint')) ?>
        </p>
        <form method="post" style="display:inline">
            <?= csrfField() ?>
            <input type="hidden" name="toggle_maintenance" value="1">
            <button type="submit" class="btn <?= $maintenance_on ? 'btn-primary' : 'btn-danger' ?>">
                <?= $maintenance_on ? htmlspecialchars(t('dash.maint_disable')) : htmlspecialchars(t('dash.maint_enable')) ?>
            </button>
        </form>
        <a href="<?= adminUrl('texts.php') ?>" class="btn btn-secondary" style="margin-left: 5px;"><?= htmlspecialchars(t('dash.edit_message')) ?></a>
    </div>
</div>

<div class="config-section">
    <h2 class="config-title"><?= htmlspecialchars(t('dash.config')) ?></h2>
    <p class="config-desc"><?= htmlspecialchars(t('dash.config_desc')) ?></p>

<div class="form-card">
    <h2><?= htmlspecialchars(t('lang.panel_title')) ?></h2>
    <p style="margin: 0 0 15px; font-size: 0.95rem; color: #555;">
        <?= htmlspecialchars(t('lang.panel_desc')) ?>
    </p>
    <form method="post" style="display:flex; gap:10px; flex-wrap:wrap; align-items:flex-end;">
        <?= csrfField() ?>
        <div style="flex:1; min-width:220px;">
            <label for="admin_lang_default" style="display:block; margin-bottom:6px; font-size:0.88rem; font-weight:600; color:#13233C;"><?= htmlspecialchars(t('lang.panel_title')) ?></label>
            <select id="admin_lang_default" name="admin_lang_default" style="width:100%; padding:10px 12px; font-size:.95rem; border:1px solid #cfd6dd; border-radius:7px;">
                <?php foreach (supportedLangs() as $code => $meta): ?>
                <option value="<?= $code ?>" <?= $admin_lang_default === $code ? 'selected' : '' ?>><?= htmlspecialchars($meta['flag'] . ' ' . $meta['label']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-primary"><?= htmlspecialchars(t('lang.save_global')) ?></button>
    </form>
</div>

<div class="form-card">
    <h2><?= htmlspecialchars(t('lang.session_title')) ?></h2>
    <p style="margin: 0 0 15px; font-size: 0.95rem; color: #555;">
        <?= htmlspecialchars(t('lang.session_desc')) ?>
    </p>
    <form method="post" style="display:flex; gap:10px; flex-wrap:wrap; align-items:flex-end;">
        <?= csrfField() ?>
        <div style="flex:1; min-width:220px;">
            <label for="session_lang" style="display:block; margin-bottom:6px; font-size:0.88rem; font-weight:600; color:#13233C;"><?= htmlspecialchars(t('lang.label')) ?></label>
            <select id="session_lang" name="session_lang" style="width:100%; padding:10px 12px; font-size:.95rem; border:1px solid #cfd6dd; border-radius:7px;">
                <?php foreach (supportedLangs() as $code => $meta): ?>
                <option value="<?= $code ?>" <?= $session_lang === $code ? 'selected' : '' ?>><?= htmlspecialchars($meta['flag'] . ' ' . $meta['label']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-primary"><?= htmlspecialchars(t('lang.save_session')) ?></button>
    </form>
</div>

<div class="form-card">
    <h2><?= htmlspecialchars(t('dash.name_title')) ?></h2>
    <p style="margin: 0 0 15px; font-size: 0.95rem; color: #555;">
        <?= htmlspecialchars(t('dash.name_desc')) ?>
        <?= htmlspecialchars(t('dash.name_current')) ?> <strong style="color: #13233C"><?= htmlspecialchars($site_title) ?></strong>
    </p>
    <form method="post" style="display:flex; gap:10px; flex-wrap:wrap; align-items:flex-end;">
        <?= csrfField() ?>
        <input type="hidden" name="site_title_save" value="1">
        <div style="flex:1; min-width:220px;">
            <label for="site_title" style="display:block; margin-bottom:6px; font-size:0.88rem; font-weight:600; color:#13233C;"><?= htmlspecialchars(t('dash.name_label')) ?></label>
            <input type="text" id="site_title" name="site_title" value="<?= htmlspecialchars($site_title) ?>" required maxlength="200" placeholder="Mi loteria" style="width:100%; padding:10px 12px; font-size:.95rem; border:1px solid #cfd6dd; border-radius:7px;">
        </div>
        <button type="submit" class="btn btn-primary"><?= htmlspecialchars(t('dash.name_save')) ?></button>
    </form>
</div>

<div class="form-card">
    <h2><?= htmlspecialchars(t('dash.draw_title')) ?></h2>
    <?php if ($draw_mode_msg): ?>
        <div class="alert alert-<?= $draw_mode_msg_type ?>" style="margin: 0 0 15px;"><?= htmlspecialchars($draw_mode_msg) ?></div>
    <?php endif; ?>
    <?php if ($lottery_locked): ?>
        <div class="alert alert-warning" style="margin: 0 0 15px;"><?= htmlspecialchars(t('dash.locked', ['participants' => $total_participants, 'winners' => $total_winners])) ?> <a href="<?= adminUrl('reset.php') ?>"><?= htmlspecialchars(t('nav.reset')) ?></a></div>
    <?php endif; ?>
    <p style="margin: 0 0 15px; font-size: 0.95rem; color: #555;">
        <?= htmlspecialchars(t('dash.current')) ?>
        <strong style="color: #13233C"><?= htmlspecialchars(drawModeLabel($draw_mode)) ?></strong>
        <?= htmlspecialchars(t('dash.draw_hint_live')) ?>
        <?php if (!drawTableExists($conn)): ?>
            <br><span style="color:#c62828;"><?= htmlspecialchars(t('dash.draw_db_pending')) ?></span>
        <?php endif; ?>
    </p>
    <form method="post">
        <?= csrfField() ?>
        <label style="display:block; margin-bottom:8px; font-size:0.92rem;">
            <input type="radio" name="draw_mode" value="manual" <?= $draw_mode === 'manual' ? 'checked' : '' ?> <?= $lottery_locked ? 'disabled' : '' ?>>
            <strong><?= htmlspecialchars(t('dash.draw_manual')) ?></strong> <?= htmlspecialchars(t('dash.draw_manual_desc')) ?>
        </label>
        <label style="display:block; margin-bottom:8px; font-size:0.92rem;">
            <input type="radio" name="draw_mode" value="random_org" <?= $draw_mode === 'random_org' ? 'checked' : '' ?> <?= $lottery_locked ? 'disabled' : '' ?>>
            <strong><?= htmlspecialchars(t('dash.draw_org')) ?></strong> <?= htmlspecialchars(t('dash.draw_org_desc', ['min' => str_repeat('0', $number_digits)])) ?>
        </label>
        <label style="display:block; margin-bottom:12px; font-size:0.92rem;">
            <input type="radio" name="draw_mode" value="random_int" <?= $draw_mode === 'random_int' ? 'checked' : '' ?> <?= $lottery_locked ? 'disabled' : '' ?>>
            <strong><?= htmlspecialchars(t('dash.draw_local')) ?></strong> <?= htmlspecialchars(t('dash.draw_local_desc', ['max' => getNumberRangeMax($number_digits), 'min' => str_repeat('0', $number_digits)])) ?>
        </label>
        <button type="submit" class="btn btn-primary" <?= $lottery_locked ? 'disabled title="Locked while the lottery is in progress"' : '' ?>><?= htmlspecialchars(t('dash.draw_save')) ?></button>
    </form>
</div>

<div class="form-card">
    <h2><?= htmlspecialchars(t('dash.digits_title')) ?></h2>
    <?php if ($digits_msg): ?>
        <div class="alert alert-<?= $digits_msg_type ?>" style="margin: 0 0 15px;"><?= htmlspecialchars($digits_msg) ?></div>
    <?php endif; ?>
    <?php if ($lottery_locked): ?>
        <div class="alert alert-warning" style="margin: 0 0 15px;"><?= htmlspecialchars(t('dash.locked', ['participants' => $total_participants, 'winners' => $total_winners])) ?> <a href="<?= adminUrl('reset.php') ?>"><?= htmlspecialchars(t('nav.reset')) ?></a></div>
    <?php endif; ?>
    <p style="margin: 0 0 15px; font-size: 0.95rem; color: #555;">
        <?= htmlspecialchars(t('dash.current')) ?>
        <strong style="color: #13233C"><?= htmlspecialchars(numberDigitsLabel($number_digits)) ?></strong>
        <?= htmlspecialchars(t('dash.digits_hint')) ?>
    </p>
    <form method="post">
        <?= csrfField() ?>
        <label style="display:block; margin-bottom:8px; font-size:0.92rem;">
            <input type="radio" name="number_digits" value="3" <?= $number_digits === 3 ? 'checked' : '' ?> <?= $lottery_locked ? 'disabled' : '' ?>>
            <strong><?= htmlspecialchars(t('dash.digits_3')) ?></strong> <?= htmlspecialchars(t('dash.digits_3_desc')) ?>
        </label>
        <label style="display:block; margin-bottom:8px; font-size:0.92rem;">
            <input type="radio" name="number_digits" value="4" <?= $number_digits === 4 ? 'checked' : '' ?> <?= $lottery_locked ? 'disabled' : '' ?>>
            <strong><?= htmlspecialchars(t('dash.digits_4')) ?></strong> <?= htmlspecialchars(t('dash.digits_4_desc')) ?>
        </label>
        <p style="margin: 0 0 12px; font-size:0.85rem; color:#666;">
            <?= htmlspecialchars(t('dash.digits_note')) ?>
        </p>
        <button type="submit" class="btn btn-primary" <?= $lottery_locked ? 'disabled title="Locked while the lottery is in progress"' : '' ?>><?= htmlspecialchars(t('dash.digits_save')) ?></button>
    </form>
</div>

<div class="form-card">
    <h2><?= htmlspecialchars(t('dash.winner_title')) ?></h2>
    <p style="margin: 0 0 15px; font-size: 0.95rem; color: #555;">
        <?= htmlspecialchars(t('dash.current')) ?>
        <strong style="color: #13233C"><?= $unique_only ? htmlspecialchars(t('dash.winner_unique_now')) : htmlspecialchars(t('dash.winner_repeat_now')) ?></strong>
        <?= $unique_only ? htmlspecialchars(t('dash.winner_unique_hint')) : htmlspecialchars(t('dash.winner_repeat_hint')) ?>
    </p>
    <form method="post">
        <?= csrfField() ?>
        <label style="display:block; margin-bottom:8px; font-size:0.92rem;">
            <input type="radio" name="unique_winners" value="0" <?= !$unique_only ? 'checked' : '' ?>>
            <strong><?= htmlspecialchars(t('dash.winner_repeat')) ?></strong> <?= htmlspecialchars(t('dash.winner_repeat_desc')) ?>
        </label>
        <label style="display:block; margin-bottom:12px; font-size:0.92rem;">
            <input type="radio" name="unique_winners" value="1" <?= $unique_only ? 'checked' : '' ?>>
            <strong><?= htmlspecialchars(t('dash.winner_unique')) ?></strong> <?= htmlspecialchars(t('dash.winner_unique_desc')) ?>
        </label>
        <button type="submit" class="btn btn-primary"><?= htmlspecialchars(t('dash.winner_save')) ?></button>
    </form>
</div>

<div class="form-card">
    <h2><?= htmlspecialchars(t('dash.logo_title')) ?></h2>
    <?php if ($logo_upload_msg): ?>
        <p style="color:green; font-weight:600;"><?= htmlspecialchars($logo_upload_msg) ?></p>
    <?php endif; ?>
    <?php if ($logo_path): ?>
        <p><img src="/<?= htmlspecialchars($logo_path) ?>" alt="Logo" style="max-height:80px;"></p>
        <p>
            <form method="post" style="display:inline">
                <?= csrfField() ?>
                <input type="hidden" name="remove_logo" value="1">
                <button type="submit" class="btn btn-danger" style="font-size:0.85rem;"><?= htmlspecialchars(t('dash.logo_remove')) ?></button>
            </form>
        </p>
    <?php else: ?>
        <p style="font-size:0.9rem; color:#555;"><?= htmlspecialchars(t('dash.logo_none')) ?></p>
    <?php endif; ?>
    <form method="post" enctype="multipart/form-data" style="margin-top:10px;">
        <?= csrfField() ?>
        <input type="file" name="logo_file" accept="image/png,image/jpeg,image/gif,image/webp,image/svg+xml" required>
        <button type="submit" name="upload_logo" class="btn btn-primary" style="margin-top:8px;"><?= htmlspecialchars(t('dash.logo_upload')) ?></button>
    </form>
</div>

</div><!-- /config-section -->

<div class="form-card" style="margin-top: 0; border-left: 4px solid #e53935;">
    <h2 style="color: #c62828;"><?= htmlspecialchars(t('dash.reset_title')) ?></h2>
    <p style="margin: 0 0 15px; font-size: 0.95rem; color: #555;">
        <?= htmlspecialchars(t('dash.reset_desc')) ?>
    </p>
    <a href="<?= adminUrl('reset.php') ?>" class="btn btn-danger"><?= htmlspecialchars(t('dash.reset_btn')) ?></a>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
