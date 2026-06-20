<?php
require_once __DIR__ . '/functions.php';
$site_config['title']       = '58互访圈 - 城市间互访交流平台 | 58 Hufang';
$site_config['description'] = '58互访圈是基于区块城市BlockCity的城市间互访交流平台，支持创建互访圈、跨城互访、访问记录管理，打造城市社交新体验。';
$site_config['keywords']    = '58,互访圈,区块城市,BlockCity,DAO,同城交流,互访,城市社交';
$site_config['canonical_url'] = 'https://v.58.tl/';
$site_config['og_image']    = 'https://58.tl/assets/images/og-hufang.jpg';
$site_config['logo_main']   = '58';
$site_config['logo_sub']    = '互访圈';
$site_config['logo_tag']    = '城市间互访交流平台';
$site_config['nav_links']   = [
    ['url'=>'../','icon'=>'home','text'=>'返回首页'],
    ['url'=>'../index.php','icon'=>'users','text'=>'浏览互访圈'],
    ['url'=>'../circles/create.php','icon'=>'plus-circle','text'=>'创建互访圈'],
    ['url'=>'../rankings/index.php','icon'=>'trophy','text'=>'排行榜'],
];
$site_config['extra_head'] = ($site_config['extra_head'] ?? '') . '<link rel="stylesheet" href="/assets/css/main.css">';

// 计算当前用户未读通知数与最近通知列表（用于头部下拉）
$notification_count = 0;
$notifications = [];
if (isset($_SESSION['user_id'])) {
    if (!isset($pdo)) {
        require_once __DIR__ . '/../../config/database.php';
    }
    require_once __DIR__ . '/../../classes/Notification.php';
    $notification = new Notification($pdo);
    $notification_count = $notification->getUnreadCount($_SESSION['user_id']);
    $notifications = $notification->getUserNotifications($_SESSION['user_id'], 5);
}

require_once __DIR__ . '/../../shared/header.php';
