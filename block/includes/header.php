<?php
$site_config['title']       = 'BlockCity区块市场 - 区块交易平台 | 58 BlockCity';
$site_config['description'] = 'BlockCity区块城市区块交易平台，支持200+城市区块地图浏览、跨区相邻区块合并认领、区块买卖交易。';
$site_config['keywords']    = '58,区块,区块城市,BlockCity,DAO,同城交流,Avatar,区块交易,区块认领';
$site_config['canonical_url'] = 'https://block.58.tl/';
$site_config['og_image']    = 'https://58.tl/assets/images/og-block.jpg';
$site_config['logo_main']   = '58';
$site_config['logo_sub']    = '区块';
$site_config['logo_tag']    = 'BlockCity区块交易市场';
$site_config['nav_links']   = [
    ['url'=>'../','icon'=>'home','text'=>'返回首页'],
    ['url'=>'../sale_list.php','icon'=>'tag','text'=>'售卖'],
    ['url'=>'../purchase_list.php','icon'=>'hand-holding-usd','text'=>'求购'],
    ['url'=>'../claim_list.php','icon'=>'hand-holding-heart','text'=>'认领'],
];
$site_config['extra_head']  = '<script charset="UTF-8" id="LA_COLLECT" src="//sdk.51.la/js-sdk-pro.min.js"></script><script>LA.init({id:"KplyYLrcc6uYhdjv",ck:"KplyYLrcc6uYhdjv"})</script>';
require_once __DIR__ . '/../../shared/header.php';
