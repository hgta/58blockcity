<?php
require_once __DIR__ . '/functions.php';
$site_config['title']       = $site_config['title'] ?? '58互访圈 - 城市间互访交流平台 | 58 Hufang';
$site_config['description'] = $site_config['description'] ?? '58互访圈是基于区块城市BlockCity的城市间互访交流平台，支持创建互访圈、跨城互访、访问记录管理，打造城市社交新体验。';
$site_config['keywords']    = $site_config['keywords'] ?? '58,互访圈,区块城市,BlockCity,DAO,同城交流,互访,城市社交';
// canonical：按请求 host 决定（主域 hufangquan.com 指向自身；子域 v.58.tl 收口到主域）
// 页面可传 $site_config['canonical_url'] 覆盖（如详情页需固定路径），否则用当前 URL 推导
if (empty($site_config['canonical_url'])) {
    require_once __DIR__ . '/../../classes/SeoHelper.php';
    $site_config['canonical_url'] = SeoHelper::canonicalTargetUrl();
}
$site_config['og_image']    = $site_config['og_image'] ?? 'https://58.tl/assets/images/og-hufang.jpg';
$site_config['logo_main']   = $site_config['logo_main'] ?? '58';
$site_config['logo_sub']    = $site_config['logo_sub'] ?? '互访圈';
$site_config['logo_tag']    = $site_config['logo_tag'] ?? '城市间互访交流平台';
$site_config['nav_links']   = $site_config['nav_links'] ?? [
    ['url'=>'../','icon'=>'home','text'=>'返回首页'],
    ['url'=>'../index.php','icon'=>'users','text'=>'浏览互访圈'],
    ['url'=>'../circles/create.php','icon'=>'plus-circle','text'=>'创建互访圈'],
    ['url'=>'../rankings/index.php','icon'=>'trophy','text'=>'排行榜'],
    ['url'=>'https://help.58.tl/','icon'=>'circle-question','text'=>'帮助'],
];
$site_config['extra_head'] = ($site_config['extra_head'] ?? '') . '<link rel="stylesheet" href="/assets/css/main.css">';


// 计算当前用户未读通知数与最近通知列表（用于头部下拉）
// 用 isLoggedIn() 而非直接读 $_SESSION，以支持 remember_me 自动登录
if (!isset($isLoggedIn)) {
    $isLoggedIn = function_exists('isLoggedIn') ? isLoggedIn() : isset($_SESSION['user_id']);
}
$notification_count = 0;
$notifications = [];
if ($isLoggedIn) {
    if (!isset($pdo)) {
        require_once __DIR__ . '/../../config/database.php';
    }
    require_once __DIR__ . '/../../classes/Notification.php';
    $notification = new Notification($pdo);
    $notification_count = $notification->getUnreadCount($_SESSION['user_id']);
    $notifications = $notification->getUserNotifications($_SESSION['user_id'], 5);
}

require_once __DIR__ . '/../../shared/header.php';
