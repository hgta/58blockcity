<?php
$site_config['title']       = $site_config['title'] ?? '58头像市场 - BlockCity NFT头像交易平台 | 58 NFT';
$site_config['description'] = $site_config['description'] ?? '58头像市场是基于区块城市BlockCity的NFT头像交易平台，支持NFT头像浏览、认领、买卖交易、求购发布，海量SVG头像等您来收藏。';
$site_config['keywords']    = $site_config['keywords'] ?? '58,头像,NFT,区块城市,BlockCity,数字收藏,Avatar,NFT交易,头像交易';
// canonical：按当前请求 URL 生成（此前默认写死为首页，导致内页 canonical 都指向首页）
if (empty($site_config['canonical_url'])) {
    require_once __DIR__ . '/../../classes/SeoHelper.php';
    $site_config['canonical_url'] = SeoHelper::canonicalTargetUrl();
}
$site_config['og_image']    = $site_config['og_image'] ?? 'https://www.58.tl/assets/images/og-nft.jpg';
$site_config['logo_main']   = $site_config['logo_main'] ?? '58';
$site_config['logo_sub']    = $site_config['logo_sub'] ?? '头像';
$site_config['logo_tag']    = $site_config['logo_tag'] ?? 'BlockCity NFT头像交易平台';
$site_config['nav_links']   = $site_config['nav_links'] ?? [
    ['url'=>'../','icon'=>'home','text'=>'返回首页'],
    ['url'=>'../ranking/index.php','icon'=>'trophy','text'=>'排行榜'],
    ['url'=>'../nft/claim_list.php','icon'=>'hand-holding-heart','text'=>'认领'],
    ['url'=>'../nft/sale_list.php','icon'=>'tag','text'=>'售卖'],
    ['url'=>'../nft/purchase_list.php','icon'=>'hand-holding-usd','text'=>'求购'],
    ['url'=>'https://help.58.tl/','icon'=>'circle-question','text'=>'帮助'],
];
require_once __DIR__ . '/../../shared/header.php';

