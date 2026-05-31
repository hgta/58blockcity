<?php
$site_config['title']       = '58头像市场 - BlockCity NFT头像交易平台 | 58 NFT';
$site_config['description'] = '58头像市场是基于区块城市BlockCity的NFT头像交易平台。';
$site_config['keywords']    = '58,头像,NFT,区块城市,BlockCity,数字收藏,Avatar';
$site_config['logo_main']   = '58';
$site_config['logo_sub']    = '头像';
$site_config['logo_tag']    = 'BlockCity NFT头像交易平台';
$site_config['nav_links']   = [
    ['url'=>'../','icon'=>'home','text'=>'返回首页'],
    ['url'=>'../nft/claim_list.php','icon'=>'hand-holding-heart','text'=>'认领'],
    ['url'=>'../nft/sale_list.php','icon'=>'tag','text'=>'售卖'],
    ['url'=>'../nft/purchase_list.php','icon'=>'hand-holding-usd','text'=>'求购'],
];
require_once __DIR__ . '/../../shared/header.php';
