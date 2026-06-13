<?php
$site_config['title']       = '58人气值购物商城 - BCT商城平台 | 58 Mall';
$site_config['description'] = '人气值商城是基于区块城市BlockCity的BCT商城交易平台，支持BCT人气值支付、多种商品分类、免费开店上架商品。';
$site_config['keywords']    = '58,人气值,BCT,区块城市,BlockCity,商城,购物,BCT支付,人气值购物';
$site_config['canonical_url'] = 'https://mall.58.tl/';
$site_config['og_image']    = 'https://58.tl/assets/images/og-mall.jpg';
$site_config['logo_main']   = '58';
$site_config['logo_sub']    = '人气值';
$site_config['logo_tag']    = '商城交易平台';
$site_config['nav_links']   = [
    ['url'=>'../index.php','icon'=>'home','text'=>'首页'],
    ['url'=>'../product/list.php','icon'=>'shopping-bag','text'=>'商品浏览'],
    ['url'=>'../shop/list.php','icon'=>'store','text'=>'店铺列表'],
    ['url'=>'../cart/index.php','icon'=>'shopping-cart','text'=>'购物车'],
    ['url'=>'../user/orders.php','icon'=>'clipboard-list','text'=>'我的订单'],
];
require_once __DIR__ . '/../../shared/header.php';
