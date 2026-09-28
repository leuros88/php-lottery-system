<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    foreach ($_POST['texts'] as $key => $content) {
        $stmt = mysqli_prepare($conn, 'UPDATE custom_texts SET content = ? WHERE `key` = ?');
        mysqli_stmt_bind_param($stmt, 'ss', $content, $key);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
    $message = t('texts.updated');
    $message_type = 'success';
}

$result = mysqli_query($conn, 'SELECT `key`, content FROM custom_texts');
$texts = [];
while ($row = mysqli_fetch_assoc($result)) {
    $texts[$row['key']] = $row['content'];
}

$placeholders = [
    'participant_create_success' => '{name}, {numbers}',
    'participant_update_success' => '{name}, {numbers}',
    'participant_auto_assign' => '{requested}, {assigned}',
    'participant_duplicate' => '{name}',
    'participant_blacklisted' => '{name}',
    'participant_error' => '{reason}',
    'status_open_message' => '',
    'status_closed_message' => '',
];

require_once __DIR__ . '/header.php';
?>

<div class="page-header">
    <h1><?= htmlspecialchars(t('texts.title')) ?></h1>
    <p class="page-description"><?= htmlspecialchars(t('texts.desc')) ?></p>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?= $message_type ?>"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<div class="form-card">
    <form method="post">
        <?= csrfField() ?>
        <?php foreach ($texts as $key => $content): ?>
            <div class="form-group">
                <label for="text_<?= $key ?>"><?= ucwords(str_replace('_', ' ', $key)) ?></label>
                <textarea id="text_<?= $key ?>" name="texts[<?= $key ?>]" rows="6"><?= htmlspecialchars($content) ?></textarea>
                <?php if (!empty($placeholders[$key])): ?>
                <small style="color:#888;display:block;margin-top:4px;"><?= htmlspecialchars(t('texts.vars', ['vars' => $placeholders[$key]])) ?></small>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        <div class="form-group">
            <button type="submit" class="btn btn-primary"><?= htmlspecialchars(t('texts.save')) ?></button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
