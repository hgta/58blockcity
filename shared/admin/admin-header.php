<?php
/**
 * 统一后台头部模板
 *
 * 用法:
 *   $admin_site_config = [
 *       'site'       => 'main'|'block'|'mall'|'nft'|'hufang'|'bct',
 *       'page_title' => '当前页面标题',
 *       'extra_head' => '额外CSS/JS（可选）',
 *   ];
 *   require_once '../../shared/admin/admin-header.php';
 *
 * 前置条件: 已 require 当前子站的 includes/auth.php
 */

if (!isset($admin_site_config)) {
    die('缺少 $admin_site_config 配置');
}

require_once __DIR__ . '/admin-auth.php';
require_once __DIR__ . '/admin-menu-config.php';

$site = $admin_site_config['site'] ?? 'main';
$pageTitle = $admin_site_config['page_title'] ?? '管理后台';
$extraHead = $admin_site_config['extra_head'] ?? '';

checkAdminAccess($site);

$currentUser = getCurrentAdminUser();
$allSites = getAllAdminSites();
$siteConfig = getAdminSiteConfig($site);
$sidebarMenus = getAdminMenus($site);

// 当前页面文件名（用于高亮菜单）
$currentFile = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> — <?= htmlspecialchars($siteConfig['name'] ?? '管理后台') ?></title>
    <link rel="icon" href="https://58.tl/assets/images/favicon.ico">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/admin.css">
    <?= $extraHead ?>
</head>
<body class="admin-body">

<div class="admin-wrapper">

    <!-- 顶部导航栏 -->
    <header class="admin-topbar">
        <div class="admin-topbar-left">
            <button class="admin-mobile-toggle" onclick="document.querySelector('.admin-sidebar').classList.toggle('open')">
                <i class="fas fa-bars"></i>
            </button>
            <a href="/" class="admin-topbar-logo">
                <span class="logo-icon">58</span>
                <span>管理后台</span>
            </a>
            <span class="admin-topbar-divider"></span>
            <span class="admin-topbar-site-name"><?= htmlspecialchars($siteConfig['name'] ?? '') ?></span>
        </div>

        <div class="admin-topbar-right">
            <!-- 站点切换器 -->
            <div class="admin-site-switcher">
                <div class="admin-site-switcher-btn">
                    <i class="fas fa-exchange-alt"></i>
                    <span>站点切换</span>
                    <i class="fas fa-chevron-down" style="font-size:10px;"></i>
                </div>
                <div class="admin-site-dropdown">
                    <div class="admin-site-dropdown-header">切换管理后台</div>
                    <?php foreach ($allSites as $key => $s): ?>
                        <?php
                        $isCurrent = ($key === $site);
                        $isDisabled = false; // 后续可按权限控制
                        $class = $isCurrent ? 'current' : ($isDisabled ? 'disabled' : '');
                        ?>
                        <a href="<?= $s['url'] ?>" class="<?= $class ?>">
                            <i class="fas <?= $s['icon'] ?>"></i>
                            <?= htmlspecialchars($s['name']) ?>
                            <?php if ($isCurrent): ?>
                                <i class="fas fa-check" style="margin-left:auto;font-size:10px;opacity:0.6;"></i>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- 用户信息 -->
            <div class="admin-user-menu" onclick="if(confirm('确定要退出登录吗？'))location.href='../auth/logout.php'">
                <div class="admin-user-avatar">
                    <?= strtoupper(substr($currentUser['username'], 0, 1)) ?>
                </div>
                <span class="admin-user-name"><?= htmlspecialchars($currentUser['username']) ?></span>
                <i class="fas fa-sign-out-alt" style="font-size:12px;color:var(--admin-text-muted);margin-left:4px;"></i>
            </div>
        </div>
    </header>

    <!-- 侧边栏 -->
    <aside class="admin-sidebar">
        <ul class="admin-sidebar-menu">
            <?php if (!empty($sidebarMenus)): ?>
                <?php foreach ($sidebarMenus as $menu): ?>
                    <?php $isActive = ($currentFile === $menu['url']); ?>
                    <li>
                        <a href="<?= $menu['url'] ?>" class="<?= $isActive ? 'active' : '' ?>">
                            <i class="fas <?= $menu['icon'] ?>"></i>
                            <span><?= htmlspecialchars($menu['text']) ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            <?php endif; ?>

            <div class="admin-sidebar-divider"></div>

            <li>
                <a href="/">
                    <i class="fas fa-globe"></i>
                    <span>返回前台</span>
                </a>
            </li>
        </ul>
    </aside>

    <!-- 主内容区 -->
    <main class="admin-main">
        <div class="admin-page-header">
            <h1 class="admin-page-title"><?= htmlspecialchars($pageTitle) ?></h1>
        </div>
