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
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

// 加载通知数据（全站通用）
$notification_count = 0;
$notifications = [];
if (isset($_SESSION['user_id']) && isset($pdo)) {
    if (!class_exists('Notification')) {
        $notifyClassPath = __DIR__ . '/../classes/Notification.php';
        if (file_exists($notifyClassPath)) {
            require_once $notifyClassPath;
        }
    }
    if (class_exists('Notification')) {
        $notificationObj = new Notification($pdo);
        $notification_count = $notificationObj->getUnreadCount($_SESSION['user_id']);
        $notifications = $notificationObj->getUserNotifications($_SESSION['user_id'], 5);
    }
}

// 加载站内信未读数
$message_unread = 0;
if (isset($_SESSION['user_id']) && isset($pdo) && !isset($message_unread_computed)) {
    if (!class_exists('Message')) {
        $msgClassPath = __DIR__ . '/../classes/Message.php';
        if (file_exists($msgClassPath)) { require_once $msgClassPath; }
    }
    if (class_exists('Message')) {
        $messageObj = new Message($pdo);
        $message_unread = $messageObj->getUnreadCount($_SESSION['user_id']);
    }
    $message_unread_computed = true;
}

$nav_links = $site_config['nav_links'] ?? [];
$extra_head = $site_config['extra_head'] ?? '';
$theme = $site_config['theme_color'] ?? '#ff6b00';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- DNS 预解析 + 预连接（加速 CDN 资源加载） -->
    <link rel="dns-prefetch" href="//sdk.51.la">
    <link rel="dns-prefetch" href="//hm.baidu.com">
    <link rel="dns-prefetch" href="//cdnjs.cloudflare.com">
    <link rel="dns-prefetch" href="//cdn.jsdelivr.net">
    <link rel="dns-prefetch" href="//code.jquery.com">
    <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <link rel="preconnect" href="https://code.jquery.com" crossorigin>
    <title><?= htmlspecialchars($site_config['title']) ?></title>
    <meta name="description" content="<?= htmlspecialchars($site_config['description']) ?>">
    <meta name="keywords" content="<?= htmlspecialchars($site_config['keywords']) ?>">
    <link rel="canonical" href="<?= htmlspecialchars($site_config['canonical_url'] ?? '') ?>">
    <!-- Open Graph (Facebook / X / LinkedIn / Discord 通用) -->
    <meta property="og:title" content="<?= htmlspecialchars($site_config['og_title'] ?? $site_config['title']) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($site_config['og_description'] ?? $site_config['description']) ?>">
    <meta property="og:type" content="<?= htmlspecialchars($site_config['og_type'] ?? 'website') ?>">
    <meta property="og:url" content="<?= htmlspecialchars($site_config['og_url'] ?? ($site_config['canonical_url'] ?? '')) ?>">
    <?php if (!empty($site_config['og_image'])): ?>
    <meta property="og:image" content="<?= htmlspecialchars($site_config['og_image']) ?>">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <?php endif; ?>
    <link rel="icon" href="https://58.tl/assets/images/favicon.ico">
    <?php if (!empty($_SESSION['csrf_token'])): ?>
    <meta name="csrf-token" content="<?= $_SESSION['csrf_token'] ?>">
    <?php endif; ?>
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "WebSite",
      "name": "<?= htmlspecialchars($site_config['og_title'] ?? $site_config['title']) ?>",
      "url": "<?= htmlspecialchars($site_config['og_url'] ?? ($site_config['canonical_url'] ?? '')) ?>",
      "description": "<?= htmlspecialchars($site_config['og_description'] ?? $site_config['description']) ?>"
      <?php if (!empty($site_config['schema_search'])): ?>,
      "potentialAction": {
        "@type": "SearchAction",
        "target": "<?= htmlspecialchars($site_config['schema_search']) ?>",
        "query-input": "required name=search_term_string"
      }
      <?php endif; ?>
    }
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://58.tl/assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://58.tl/assets/css/main.css">
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://58.tl/assets/js/main.js"></script>
    <script src="https://58.tl/assets/js/message-modal.js"></script>
    <?= $extra_head ?>
    <!-- 51.LA 统计 -->
    <script charset="UTF-8" id="LA_COLLECT" src="//sdk.51.la/js-sdk-pro.min.js"></script>
    <script>LA.init({id:"Km945dEjfme2S7Eg",ck:"Km945dEjfme2S7Eg"})</script>
    <!-- 百度统计 -->
    <script>
    var _hmt = _hmt || [];
    (function() {
      var hm = document.createElement("script");
      hm.src = "https://hm.baidu.com/hm.js?5949e57aa9d2303fbf9451b06d4df471";
      var s = document.getElementsByTagName("script")[0]; 
      s.parentNode.insertBefore(hm, s);
    })();
    </script>
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
.notification-badge {
    position: absolute; top: -2px; right: -2px;
    background: #ef4444; color: #fff; font-size: 10px; font-weight: 700;
    min-width: 16px; height: 16px; border-radius: 8px;
    display: flex; align-items: center; justify-content: center; padding: 0 4px;
}
.dropdown { position: relative; }
.dropdown-menu { display: none; position: absolute; top: 100%; right: 0; z-index: 1000; background: #fff; border-radius: 10px; box-shadow: 0 4px 20px rgba(0,0,0,0.15); padding: 8px 0; margin-top: 6px; min-width: 280px; max-width: 360px; width: max-content; }
.dropdown-menu.show { display: block; }
.dropdown-item { display: block; padding: 10px 16px; font-size: 13px; color: #333; text-decoration: none; line-height: 1.5; }
.dropdown-item:hover { background: #f5f5f5; color: #1e293b; text-decoration: none; }
.dropdown-divider { height: 1px; background: #e9ecef; margin: 4px 0; }
.city-location-bar { background:#fff3e0; color:#e65100; text-align:center; padding:8px; font-size:13px; }
.city-location-bar a { color:#e65100; font-weight:bold; }
main.container { max-width:1200px; margin:0 auto; padding:0 15px; }

/* 汉堡菜单按钮 */
.menu-toggle {
    display: none;
    background: none; border: none; color: white; font-size: 24px;
    cursor: pointer; padding: 8px 12px; border-radius: 8px;
    line-height: 1;
}
.menu-toggle:hover { background: rgba(255,255,255,0.15); }

@media(max-width:768px){
    .header-container{flex-wrap:wrap}
    .menu-toggle { display: block; }
    .header-actions {
        display: none;
        width: 100%;
        flex-direction: column;
        padding-top: 12px;
        gap: 2px;
    }
    .header-actions.open { display: flex; }
    .header-actions .nav-button {
        display: flex; width: 100%; margin: 0;
        padding: 12px 16px; font-size: 15px; border-radius: 10px;
        justify-content: flex-start;
    }
    .header-actions .nav-button i { font-size: 16px; width: 24px; text-align: center; }
    /* 菜单遮罩 */
    .menu-overlay {
        display: none;
        position: fixed; top: 0; left: 0; right: 0; bottom: 0;
        background: rgba(0,0,0,0.3); z-index: 99;
    }
    .menu-overlay.open { display: block; }
}
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
        <button class="menu-toggle" id="menuToggle" aria-label="导航菜单">☰</button>
        <div class="header-actions" id="headerActions">
            <?php foreach ($nav_links as $nav): ?>
                <a href="<?= $nav['url'] ?>" class="nav-button">
                    <?php if (!empty($nav['icon'])): ?><i class="fas fa-<?= $nav['icon'] ?>"></i><?php endif; ?>
                    <?= htmlspecialchars($nav['text']) ?>
                </a>
            <?php endforeach; ?>
            <?php if (isset($_SESSION['user_id'])): ?>
                <div class="dropdown d-inline-block">
                    <a href="#" class="nav-button" id="notificationDropdown" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <i class="fas fa-bell"></i>
                        <?php if (!empty($notification_count)): ?>
                            <span class="notification-badge"><?= $notification_count > 9 ? '9+' : $notification_count ?></span>
                        <?php endif; ?>
                    </a>
                    <div class="dropdown-menu dropdown-menu-right" aria-labelledby="notificationDropdown">
                        <?php if (empty($notifications)): ?>
                            <span class="dropdown-item-text text-muted">暂无通知</span>
                        <?php else: ?>
                            <?php foreach ($notifications as $n): ?>
                                <?php
                                $link = $n['related_url'] ?? '../user/notifications.php';
                                if ($n['type'] === 'visit_request') {
                                    $link = "https://v.58.tl/user/visits.php?circle_id=" . intval($n['related_id']);
                                } elseif (in_array($n['type'], ['visit_confirm', 'return_confirm'])) {
                                    $link = "https://v.58.tl/user/visit_detail.php?id=" . intval($n['related_id']);
                                } elseif ($n['type'] === 'order_paid') {
                                    $link = 'https://mall.58.tl/shop/orders.php?id=' . intval($n['related_id']);
                                } elseif ($n['type'] === 'order_shipped' || $n['type'] === 'order_done') {
                                    $link = 'https://mall.58.tl/user/order_detail.php?id=' . intval($n['related_id']);
                                } elseif ($n['type'] === 'new_review') {
                                    $link = 'https://mall.58.tl/product/detail.php?id=' . intval($n['related_id']);
                                }
                                $bold = empty($n['is_read']) ? 'font-weight-bold' : 'font-weight-normal';
                                ?>
                                <a class="dropdown-item <?= $bold ?> text-truncate" href="<?= $link ?>">
                                    <?= htmlspecialchars($n['content']) ?>
                                </a>
                            <?php endforeach; ?>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item text-center text-primary" href="../user/messages.php">查看全部通知</a>
                        <?php endif; ?>
                    </div>
                </div>
                <a href="/messages/" class="nav-button" style="position:relative;">
                    <i class="fas fa-envelope"></i> 站内信
                    <?php if ($message_unread > 0): ?>
                    <span class="notification-badge"><?= $message_unread > 9 ? '9+' : $message_unread ?></span>
                    <?php endif; ?>
                </a>
                <a href="../user/dashboard.php" class="nav-button"><i class="fas fa-user"></i> 个人中心</a>
                <a href="../auth/logout.php" class="nav-button"><i class="fas fa-sign-out-alt"></i> 退出</a>
            <?php else: ?>
                <a href="../auth/login.php" class="nav-button"><i class="fas fa-sign-in-alt"></i> 登录</a>
                <a href="../auth/register.php" class="nav-button"><i class="fas fa-user-plus"></i> 注册</a>
            <?php endif; ?>
        </div>
    </div>
</header>

<div class="menu-overlay" id="menuOverlay"></div>

<div class="city-location-bar" id="cityLocationBar">
    欢迎您，来自于<a href="https://www.58.tl/city/beijing.html" id="cityLink" style="color:inherit;"><span id="userCity">未知城市</span></a>的朋友，<a href="https://www.58.tl/city/beijing.html" id="cityLink2">点击进入您的区块城市</a>
</div>

<main class="container">
