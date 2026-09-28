<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(currentLang()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body>
    <div class="admin-layout">
        <nav class="sidebar">
            <div class="sidebar-header">
                <h2><?= htmlspecialchars(t('nav.panel')) ?></h2>
                <p class="user-name"><?= htmlspecialchars($_SESSION['admin_username']) ?></p>
            </div>
            <ul class="nav">
                <li><a href="<?= adminUrl('dashboard.php') ?>" class="<?= $current_page === 'dashboard.php' ? 'active' : '' ?>"><?= htmlspecialchars(t('nav.dashboard')) ?></a></li>
                <li><a href="<?= adminUrl('preview.php') ?>" class="<?= $current_page === 'preview.php' ? 'active' : '' ?>"><?= htmlspecialchars(t('nav.preview')) ?></a></li>
                <li><a href="<?= adminUrl('participants.php') ?>" class="<?= $current_page === 'participants.php' ? 'active' : '' ?>"><?= htmlspecialchars(t('nav.participants')) ?></a></li>
                <li><a href="<?= adminUrl('blacklist.php') ?>" class="<?= $current_page === 'blacklist.php' ? 'active' : '' ?>"><?= htmlspecialchars(t('nav.blacklist')) ?></a></li>
                <li><a href="<?= adminUrl('news.php') ?>" class="<?= $current_page === 'news.php' ? 'active' : '' ?>"><?= htmlspecialchars(t('nav.news')) ?></a></li>
                <li><a href="<?= adminUrl('texts.php') ?>" class="<?= $current_page === 'texts.php' ? 'active' : '' ?>"><?= htmlspecialchars(t('nav.texts')) ?></a></li>
                <li><a href="<?= adminUrl('sponsors.php') ?>" class="<?= $current_page === 'sponsors.php' ? 'active' : '' ?>"><?= htmlspecialchars(t('nav.sponsors')) ?></a></li>
                <li><a href="<?= adminUrl('prizes.php') ?>" class="<?= $current_page === 'prizes.php' ? 'active' : '' ?>"><?= htmlspecialchars(t('nav.prizes')) ?></a></li>
                <li><a href="<?= adminUrl('draw.php') ?>" class="<?= $current_page === 'draw.php' ? 'active' : '' ?>"><?= htmlspecialchars(t('nav.draw')) ?></a></li>
                <li><a href="<?= adminUrl('winners.php') ?>" class="<?= $current_page === 'winners.php' ? 'active' : '' ?>"><?= htmlspecialchars(t('nav.winners')) ?></a></li>
                <li><a href="<?= adminUrl('admins.php') ?>" class="<?= $current_page === 'admins.php' ? 'active' : '' ?>"><?= htmlspecialchars(t('nav.admins')) ?></a></li>
                <li><a href="<?= adminUrl('change_password.php') ?>" class="<?= $current_page === 'change_password.php' ? 'active' : '' ?>"><?= htmlspecialchars(t('nav.password')) ?></a></li>
                <li><a href="<?= adminUrl('backups.php') ?>" class="<?= $current_page === 'backups.php' ? 'active' : '' ?>"><?= htmlspecialchars(t('nav.backups')) ?></a></li>
                <li><a href="<?= adminUrl('reset.php') ?>" class="<?= $current_page === 'reset.php' ? 'active' : '' ?>" style="color:#ef9a9a;"><?= htmlspecialchars(t('nav.reset')) ?></a></li>
                <li><a href="<?= adminUrl('logout.php') ?>" class="logout-link"><?= htmlspecialchars(t('nav.logout')) ?></a></li>
            </ul>
        </nav>
        <main class="main-content">
