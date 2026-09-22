<?php
$site_config['title']       = $site_config['title'] ?? '58人气值购物商城 - BCT商城平台 | 58 Mall';
$site_config['description'] = $site_config['description'] ?? '人气值商城是基于区块城市BlockCity的BCT商城交易平台，支持BCT人气值支付、多种商品分类、免费开店上架商品。';
$site_config['keywords']    = $site_config['keywords'] ?? '58,人气值,BCT,区块城市,BlockCity,商城,购物,BCT支付,人气值购物';
// canonical：按当前请求 URL 生成（此前默认写死为首页，导致内页 canonical 都指向首页）
if (empty($site_config['canonical_url'])) {
    require_once __DIR__ . '/../../classes/SeoHelper.php';
    $site_config['canonical_url'] = SeoHelper::canonicalTargetUrl();
}
$site_config['og_image']    = $site_config['og_image'] ?? 'https://www.58.tl/assets/images/og-mall.jpg';
$site_config['logo_main']   = $site_config['logo_main'] ?? '58';
$site_config['logo_sub']    = $site_config['logo_sub'] ?? '人气值';
$site_config['logo_tag']    = $site_config['logo_tag'] ?? '商城交易平台';
$site_config['nav_links']   = $site_config['nav_links'] ?? [
    ['url'=>'../index.php','icon'=>'home','text'=>'首页'],
    ['url'=>'../product/list.php','icon'=>'shopping-bag','text'=>'商品浏览'],
    ['url'=>'../rankings/','icon'=>'trophy','text'=>'排行榜'],
    ['url'=>'../author/list.php','icon'=>'palette','text'=>'作者库'],
    ['url'=>'../shop/list.php','icon'=>'store','text'=>'店铺列表'],
    ['url'=>'https://help.58.tl/','icon'=>'circle-question','text'=>'帮助'],
];
// 站内信从顶栏隐藏（个人中心内仍可访问）；购物车移到右侧用户区
$site_config['show_message'] = false;
$site_config['show_cart']    = true;
require_once __DIR__ . '/../../shared/header.php';

