<?php
/**
 * 任务广场子站头部代理
 * 页面在 require 本文件前：require config/database.php + includes/auth.php，
 * 并可先设置 $site_config['title'] 等覆盖项。
 */
$site_config['title']       = $site_config['title'] ?? '任务广场 - 58 区块城市 | 58 Task';
$site_config['description'] = $site_config['description'] ?? '任务广场：发布悬赏找代互访、代打卡、代做市长等线下小任务，众包领取赚人气值/现金，验收结算并双向评价沉淀信誉。';
$site_config['keywords']    = $site_config['keywords'] ?? '任务,悬赏,代互访,代打卡,代做市长,众包,人气值,现金,58,区块城市,BlockCity,互访圈';
$site_config['canonical_url'] = $site_config['canonical_url'] ?? 'https://task.58.tl/';
$site_config['logo_main']   = $site_config['logo_main'] ?? '58';
$site_config['logo_sub']    = $site_config['logo_sub'] ?? '任务';
$site_config['logo_tag']    = $site_config['logo_tag'] ?? '悬赏众包 · 任务广场';
$site_config['nav_links']   = $site_config['nav_links'] ?? [
    ['url' => '/index.php',   'icon' => 'store',               'text' => '任务广场'],
    ['url' => '/create.php',  'icon' => 'plus-circle',         'text' => '发布任务'],
    ['url' => '/find.php',    'icon' => 'hand-holding-heart',  'text' => '找承接人'],
    ['url' => '/my.php',      'icon' => 'user',                'text' => '我的任务'],
];
// 登录/个人中心等共享链接用域名根绝对路径，避免 user/ 等子目录相对路径错乱
$site_config['url_dashboard'] = '/user/dashboard.php';
$site_config['url_logout']    = '/auth/logout.php';
$site_config['url_login']     = '/auth/login.php';
$site_config['url_register']  = '/auth/register.php';
// 根共享函数库（compressImage/e/...），页面与 hufang 同源互不冲突
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../shared/header.php';
