<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$isLoggedIn = isset($_SESSION['user_id']);

$site_config['title']       = 'BlockCity区块市场 - 区块交易平台 | 58 BlockCity';
$site_config['description'] = 'BlockCity区块城市区块交易平台，支持200+城市区块地图浏览、跨区相邻区块合并认领、区块买卖交易。';
$site_config['keywords']    = '58,区块,区块城市,BlockCity,DAO,区块交易,区块认领';
$site_config['canonical_url'] = 'https://block.58.tl/';
$site_config['og_image']    = 'https://58.tl/assets/images/og-block.jpg';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($site_config['title']) ?></title>
    <meta name="description" content="<?= htmlspecialchars($site_config['description']) ?>">
    <meta name="keywords" content="<?= htmlspecialchars($site_config['keywords']) ?>">
    <link rel="canonical" href="<?= htmlspecialchars($site_config['canonical_url']) ?>" />
    <link rel="icon" type="image/png" href="/favicon-96x96.png" sizes="96x96" />
    <link rel="icon" type="image/svg+xml" href="/favicon.svg" />
    <link rel="shortcut icon" href="/favicon.ico" />
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png" />
    <meta property="og:title" content="BlockCity区块市场">
    <meta property="og:description" content="<?= htmlspecialchars($site_config['description']) ?>">
    <meta property="og:image" content="<?= $site_config['og_image'] ?>">
    <link rel="icon" type="image/png" href="/favicon-96x96.png" sizes="96x96" />
    <link rel="shortcut icon" href="/favicon.ico" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://www.58.tl/assets/css/main.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://www.58.tl/assets/js/main.js"></script>
    <script charset="UTF-8" id="LA_COLLECT" src="//sdk.51.la/js-sdk-pro.min.js"></script>
    <script>LA.init({id:"KplyYLrcc6uYhdjv",ck:"KplyYLrcc6uYhdjv"})</script>
    <script>var _hmt=_hmt||[];(function(){var hm=document.createElement("script");hm.src="https://hm.baidu.com/hm.js?5949e57aa9d2303fbf9451b06d4df471";var s=document.getElementsByTagName("script")[0];s.parentNode.insertBefore(hm,s);})();</script>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI","PingFang SC","Microsoft YaHei",sans-serif;background:#f5f6fa;color:#333;line-height:1.6}

.block-header{background:#fff;border-bottom:1px solid #e8e8e8;position:sticky;top:0;z-index:100}
.block-header-inner{max-width:1200px;margin:0 auto;padding:0 20px;display:flex;align-items:center;justify-content:space-between;height:56px}
.block-logo{display:flex;align-items:center;gap:10px;text-decoration:none}
.block-logo-icon{width:34px;height:34px;background:linear-gradient(135deg,#ff6b00,#e55a00);border-radius:6px;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:800;font-size:15px;flex-shrink:0}
.block-logo-text{display:flex;flex-direction:column;line-height:1.1}
.block-logo-text strong{font-size:16px;color:#333;font-weight:700}
.block-logo-text span{font-size:11px;color:#999}

.block-nav{display:flex;align-items:center;gap:4px}
.block-nav a{display:inline-flex;align-items:center;gap:5px;padding:7px 14px;border-radius:6px;font-size:14px;color:#666;text-decoration:none;transition:.15s;white-space:nowrap}
.block-nav a:hover{background:#fff9f0;color:#ff6b00}
.block-nav a i{font-size:13px}
.block-mobile-toggle{display:none;background:none;border:none;font-size:22px;color:#666;cursor:pointer;padding:8px}

@media(max-width:768px){
    .block-nav{display:none;position:absolute;top:56px;left:0;right:0;background:#fff;flex-direction:column;padding:8px;border-bottom:1px solid #e8e8e8}
    .block-nav.open{display:flex}
    .block-mobile-toggle{display:block}
}

.block-container{max-width:1200px;margin:0 auto;padding:20px}
.block-footer{background:#2d3748;color:#a0aec0;margin-top:40px}
.block-footer-inner{max-width:1200px;margin:0 auto;padding:30px 20px;display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:20px}
.footer-col h4{color:#fff;font-size:14px;margin-bottom:10px;font-weight:600}
.footer-col a{display:block;color:#a0aec0;font-size:13px;text-decoration:none;padding:3px 0;transition:color .15s}
.footer-col a:hover{color:#ff6b00}
.block-copyright{text-align:center;padding:16px 20px;font-size:12px;color:#718096;border-top:1px solid #4a5568}
@media(max-width:768px){.block-footer-inner{grid-template-columns:repeat(2,1fr)}}
</style>
</head>
<body>
<header class="block-header">
    <div class="block-header-inner">
        <a href="/" class="block-logo">
            <div class="block-logo-icon">58</div>
            <div class="block-logo-text">
                <strong>区块市场</strong>
                <span>BlockCity</span>
            </div>
        </a>
        <button class="block-mobile-toggle" onclick="document.querySelector('.block-nav').classList.toggle('open')">☰</button>
        <nav class="block-nav">
            <a href="/"><i class="fas fa-home"></i>首页</a>
            <a href="/sale_list.php"><i class="fas fa-tag"></i>已售区块</a>
            <a href="/purchase_list.php"><i class="fas fa-hand-holding-usd"></i>求购</a>
            <a href="/claim_list.php"><i class="fas fa-hand-holding-heart"></i>认领</a>
            <a href="/top200city.php"><i class="fas fa-trophy"></i>排行</a>
            <?php if ($isLoggedIn): ?>
            <a href="/messages/"><i class="fas fa-envelope"></i>站内信</a>
            <a href="/user/dashboard.php"><i class="fas fa-user"></i><?= htmlspecialchars(mb_substr($_SESSION['username'] ?? '我', 0, 4)) ?></a>
            <a href="/auth/logout.php" style="color:#e74c3c"><i class="fas fa-sign-out-alt"></i>退出</a>
            <?php else: ?>
            <a href="/auth/login.php"><i class="fas fa-sign-in-alt"></i>登录</a>
            <a href="/auth/register.php" style="background:#ff6b00;color:#fff;border-radius:6px;"><i class="fas fa-user-plus"></i>注册</a>
            <?php endif; ?>
        </nav>
    </div>
</header>
<div class="block-container">
