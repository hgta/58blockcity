<?php
$site_config['title']       = $site_config['title'] ?? '58人气值市场 - BCT(BlockCity Token)大宗交易平台 | 58 BCT';
$site_config['description'] = $site_config['description'] ?? '人气值市场是基于区块城市BlockCity的BCT人气值大宗交易平台，支持百万级交易、低至0%手续费、平台/中介/直接三种交易方式。';
$site_config['keywords']    = $site_config['keywords'] ?? '58,人气值,BCT,区块城市,BlockCity,DAO,同城交流,BCT交易,人气值交易';
$site_config['canonical_url'] = $site_config['canonical_url'] ?? 'https://bct.58.tl/';
$site_config['og_image']    = $site_config['og_image'] ?? 'https://58.tl/assets/images/og-bct.jpg';
$site_config['logo_main']   = $site_config['logo_main'] ?? '58';
$site_config['logo_sub']    = $site_config['logo_sub'] ?? '人气值';
$site_config['logo_tag']    = $site_config['logo_tag'] ?? 'BCT(BlockCity Token)大宗交易平台';
$site_config['nav_links']   = $site_config['nav_links'] ?? [
    ['url'=>'../','icon'=>'home','text'=>'返回首页'],
    ['url'=>'../market.php','icon'=>'chart-line','text'=>'行情'],
    ['url'=>'../trade.php','icon'=>'exchange-alt','text'=>'交易'],
];
$site_config['schema_search'] = $site_config['schema_search'] ?? 'https://bct.58.tl/search?q={search_term_string}';
require_once __DIR__ . '/../../shared/header.php';

