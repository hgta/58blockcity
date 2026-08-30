<?php
$site_config['title']       = $site_config['title'] ?? '58区块社区 - 区块城市社区交流 | 58 Club';
$site_config['description'] = $site_config['description'] ?? '58区块社区是区块城市BlockCity的社区交流平台，发布帖子、分享心情，聊区块、聊头像、聊人气值、聊城市。';
$site_config['keywords']    = $site_config['keywords'] ?? '58,社区,帖子,心情,区块,头像,人气值,城市,区块城市,BlockCity';
$site_config['canonical_url'] = $site_config['canonical_url'] ?? 'https://club.58.tl/';
$site_config['og_image']    = $site_config['og_image'] ?? 'https://58.tl/assets/images/og-club.jpg';
$site_config['logo_main']   = $site_config['logo_main'] ?? '58';
$site_config['logo_sub']    = $site_config['logo_sub'] ?? '社区';
$site_config['logo_tag']    = $site_config['logo_tag'] ?? 'BlockCity 社区交流平台';
$site_config['nav_links']   = $site_config['nav_links'] ?? [
    ['url'=>'/index.php',   'icon'=>'home',        'text'=>'社区首页'],
    ['url'=>'/create.php',  'icon'=>'plus-circle', 'text'=>'发帖'],
    ['url'=>'/my.php',      'icon'=>'user',        'text'=>'我的'],
];
// 登录/个人中心等共享链接改用域名根绝对路径，避免 user/ 子目录页面相对路径错乱
$site_config['url_dashboard'] = '/user/dashboard.php';
$site_config['url_logout']    = '/auth/logout.php';
$site_config['url_login']     = '/auth/login.php';
$site_config['url_register']  = '/auth/register.php';
// WebSite JSON-LD 注入 SearchAction（搜索结构化）
$site_config['schema_search'] = $site_config['schema_search'] ?? 'https://club.58.tl/search.php?q={search_term_string}';
// 引入 club 专属 V2EX 风格样式
$site_config['extra_head'] = ($site_config['extra_head'] ?? '') . '<link rel="stylesheet" href="/assets/css/club.css">';
require_once __DIR__ . '/../../shared/header.php';
