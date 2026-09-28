<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';

if (isLoggedIn()) {
    header('Location: ' . adminUrl('dashboard.php'));
    exit;
}

$lang = currentLang($conn);
$error = '';

if (isIpBlocked($conn)) {
    $error = t('login.blocked');
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if (login($conn, $username, $password)) {
        clearFailedAttempts($conn);
        header('Location: ' . adminUrl('dashboard.php'));
        exit;
    } else {
        recordFailedAttempt($conn);
        $remaining = 5 - getFailedAttemptCount($conn);
        if ($remaining > 0) {
            $error = t('login.invalid', ['remaining' => $remaining]);
        } else {
            $error = t('login.blocked');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(t('login.title')) ?> - <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-box">
            <h1><?= htmlspecialchars(t('login.title')) ?></h1>
            <div class="login-lang-switcher" aria-label="<?= htmlspecialchars(t('lang.label')) ?>">
                <?php foreach (supportedLangs() as $code => $meta): ?>
                <a href="?lang=<?= $code ?>" class="lang-link<?= $code === $lang ? ' active' : '' ?>"><?= strtoupper($code) ?></a>
                <?php endforeach; ?>
            </div>
            <?php if ($error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <form method="post">
                <div class="form-group">
                    <label for="username"><?= htmlspecialchars(t('login.username')) ?></label>
                    <input type="text" id="username" name="username" required autocomplete="username">
                </div>
                <div class="form-group">
                    <label for="password"><?= htmlspecialchars(t('login.password')) ?></label>
                    <input type="password" id="password" name="password" required autocomplete="current-password">
                </div>
                <button type="submit" class="btn btn-primary btn-block"><?= htmlspecialchars(t('login.button')) ?></button>
            </form>
        </div>
    </div>
</body>
</html>
