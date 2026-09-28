<?php
require_once __DIR__ . '/config.php';

// URL to a page inside the (renameable) admin directory.
// ADMIN_DIR is defined in includes/config.php and must match the real folder name.
function adminUrl($path = '') {
    $dir = defined('ADMIN_DIR') ? ADMIN_DIR : 'admin';
    $base = '/' . trim($dir, '/');
    if ($path === '' || $path === null) {
        return $base . '/';
    }
    return $base . '/' . ltrim($path, '/');
}

function isLoggedIn() {
    return isset($_SESSION['admin_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . adminUrl('index.php'));
        exit;
    }
}

function login($conn, $username, $password) {
    $stmt = mysqli_prepare($conn, 'SELECT id, username, password FROM administrators WHERE username = ?');
    mysqli_stmt_bind_param($stmt, 's', $username);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $admin = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if ($admin && password_verify($password, $admin['password'])) {
        session_regenerate_id(true);
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin_username'] = $admin['username'];
        return true;
    }
    return false;
}

function logout() {
    $_SESSION = [];
    session_destroy();
}

function getClientIp() {
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function isIpBlocked($conn) {
    $ip = getClientIp();
    $stmt = mysqli_prepare($conn, 'SELECT COUNT(*) AS cnt FROM login_attempts WHERE ip_address = ? AND attempted_at > DATE_SUB(NOW(), INTERVAL 48 HOUR)');
    mysqli_stmt_bind_param($stmt, 's', $ip);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    return $row['cnt'] >= 5;
}

function recordFailedAttempt($conn) {
    $ip = getClientIp();
    $stmt = mysqli_prepare($conn, 'INSERT INTO login_attempts (ip_address) VALUES (?)');
    mysqli_stmt_bind_param($stmt, 's', $ip);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

function getFailedAttemptCount($conn) {
    $ip = getClientIp();
    $stmt = mysqli_prepare($conn, 'SELECT COUNT(*) AS cnt FROM login_attempts WHERE ip_address = ? AND attempted_at > DATE_SUB(NOW(), INTERVAL 48 HOUR)');
    mysqli_stmt_bind_param($stmt, 's', $ip);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    return (int)$row['cnt'];
}

function clearFailedAttempts($conn) {
    $ip = getClientIp();
    $stmt = mysqli_prepare($conn, 'DELETE FROM login_attempts WHERE ip_address = ?');
    mysqli_stmt_bind_param($stmt, 's', $ip);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

function csrfToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function csrfField() {
    return '<input type="hidden" name="_token" value="' . csrfToken() . '">';
}

function requireCsrf() {
    $token = $_REQUEST['_token'] ?? '';
    if (!verifyCsrfToken($token)) {
        http_response_code(403);
        echo 'Invalid or missing CSRF token. Please go back and try again.';
        exit;
    }
}
