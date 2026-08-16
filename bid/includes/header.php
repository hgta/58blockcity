<?php
$site_config['title']       = $site_config['title'] ?? '58拍卖 - 区块/NFT头像拍卖平台 | 58 Bid';
$site_config['description'] = $site_config['description'] ?? '58拍卖是基于区块城市BlockCity的拍卖平台，支持区块和NFT头像拍卖，出价竞拍，价高者得。';
$site_config['keywords']    = $site_config['keywords'] ?? '58,拍卖,区块,NFT,头像,区块城市,BlockCity,竞拍,价高者得';
$site_config['canonical_url'] = $site_config['canonical_url'] ?? 'https://bid.58.tl/';
$site_config['og_image']    = $site_config['og_image'] ?? 'https://58.tl/assets/images/og-bid.jpg';
$site_config['logo_main']   = $site_config['logo_main'] ?? '58';
$site_config['logo_sub']    = $site_config['logo_sub'] ?? '拍卖';
$site_config['logo_tag']    = $site_config['logo_tag'] ?? 'BlockCity 拍卖平台';
$site_config['nav_links']   = $site_config['nav_links'] ?? [
    ['url'=>'/index.php',            'icon'=>'gavel',           'text'=>'拍卖大厅'],
    ['url'=>'/create.php',           'icon'=>'plus-circle',     'text'=>'发起拍卖'],
    ['url'=>'/my.php',               'icon'=>'user',            'text'=>'我的拍卖'],
];
// 登录/个人中心等共享链接改用域名根绝对路径，避免 user/ 子目录页面相对路径错乱
$site_config['url_dashboard'] = '/user/dashboard.php';
$site_config['url_logout']    = '/auth/logout.php';
$site_config['url_login']     = '/auth/login.php';
$site_config['url_register']  = '/auth/register.php';
require_once __DIR__ . '/../../shared/header.php';
