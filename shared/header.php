<?php
/**
 * 共享头部组件 — 所有子站共用
 * 
 * $site_config = [
 *     'title'       => '页面标题',
 *     'description' => 'SEO描述',
 *     'keywords'    => 'SEO关键词',
 *     'logo_main'   => '58',
 *     'logo_sub'    => '子站名',
 *     'logo_tag'    => '副标题',
 *     'nav_links'   => [ ['url'=>'..','icon'=>'fa-home','text'=>'首页'], ... ],
 *     'extra_head'  => '',  // 额外的CSS/JS
 *     'theme_color' => '#ff6b00',  // 主题色
 * ];
 */

if (!isset($site_config)) { die('缺少站点配置'); }
if (session_status() === PHP_SESSION_NONE) session_start();

$nav_links = $site_config['nav_links'] ?? [];
$extra_head = $site_config['extra_head'] ?? '';
$theme = $site_config['theme_color'] ?? '#ff6b00';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($site_config['title']) ?></title>
    <meta name="description" content="<?= htmlspecialchars($site_config['description']) ?>">
    <meta name="keywords" content="<?= htmlspecialchars($site_config['keywords']) ?>">
    <link rel="icon" href="https://58.tl/assets/images/favicon.ico">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://58.tl/assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://58.tl/assets/css/main.css">
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://58.tl/assets/js/main.js"></script>
    <?= $extra_head ?>
</head>
<body>

<style>
header { background:<?= $theme ?>; color:white; padding:15px 0; box-shadow:0 2px 5px rgba(0,0,0,0.1); position:sticky; top:0; z-index:100; }
.header-container { max-width:1200px; margin:0 auto; padding:0 15px; display:flex; justify-content:space-between; align-items:center; }
.logo { display:flex; align-items:center; text-decoration:none; color:white; }
.logo-img { width:44px; height:44px; background:white; color:<?= $theme ?>; border-radius:8px; display:flex; align-items:center; justify-content:center; font-size:20px; font-weight:bold; margin-right:12px; }
.logo-text { line-height:1.3; }
.logo-text strong { font-size:18px; display:block; }
.logo-text span { font-size:11px; opacity:0.8; }
.nav-button { padding:6px 14px; border-radius:20px; color:white; text-decoration:none; font-size:13px; margin-left:6px; transition:all .3s; display:inline-flex; align-items:center; gap:5px; }
.nav-button:hover { background:rgba(255,255,255,0.2); color:white; text-decoration:none; }
.nav-button i { font-size:13px; }
.city-location-bar { background:#fff3e0; color:#e65100; text-align:center; padding:8px; font-size:13px; }
.city-location-bar a { color:#e65100; font-weight:bold; }
main.container { max-width:1200px; margin:0 auto; padding:0 15px; }
@media(max-width:768px){ .header-container{flex-wrap:wrap} .nav-button{font-size:11px; padding:5px 8px} }
</style>

<header>
    <div class="header-container">
        <div class="logo">
            <div class="logo-img"><?= htmlspecialchars($site_config['logo_main'] ?? '58') ?></div>
            <div class="logo-text">
                <strong><?= htmlspecialchars($site_config['logo_sub'] ?? '') ?></strong>
                <span><?= htmlspecialchars($site_config['logo_tag'] ?? '') ?></span>
            </div>
        </div>
        <div class="header-actions">
            <?php foreach ($nav_links as $nav): ?>
                <a href="<?= $nav['url'] ?>" class="nav-button">
                    <?php if (!empty($nav['icon'])): ?><i class="fas fa-<?= $nav['icon'] ?>"></i><?php endif; ?>
                    <?= htmlspecialchars($nav['text']) ?>
                </a>
            <?php endforeach; ?>
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="../user/dashboard.php" class="nav-button"><i class="fas fa-user"></i> 个人中心</a>
                <a href="../auth/logout.php" class="nav-button"><i class="fas fa-sign-out-alt"></i> 退出</a>
            <?php else: ?>
                <a href="../auth/login.php" class="nav-button"><i class="fas fa-sign-in-alt"></i> 登录</a>
                <a href="../auth/register.php" class="nav-button"><i class="fas fa-user-plus"></i> 注册</a>
            <?php endif; ?>
        </div>
    </div>
</header>

<div class="city-location-bar" id="cityLocationBar">
    欢迎您,来自于<span id="userCity">未知城市</span>的朋友，<a href="https://www.blockcity.pub/?iclc" id="cityLink">点击进入您所在城市的区块</a>
</div>

<main class="container">
