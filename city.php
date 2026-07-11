<?php
/**
 * 城市页面动态路由
 * .htaccess: RewriteRule ^city/([a-z]+)\.html$ city.php?pinyin=$1 [L,QSA]
 *
 * 用 City::getCityByPinyin() 直接查 cities 表（含 pinyin 字段）
 * 有静态 HTML → 读文件+注入DB数据；无静态 → 动态生成
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/classes/City.php';

$pinyin = trim($_GET['pinyin'] ?? '');

if (!preg_match('/^[a-z]+$/', $pinyin)) {
    header('HTTP/1.0 404 Not Found');
    include __DIR__ . '/404.php';
    exit;
}

$cityObj = new City($pdo);
$city = $cityObj->getCityByPinyin($pinyin);

if (!$city) {
    header('HTTP/1.0 404 Not Found');
    include __DIR__ . '/404.php';
    exit;
}

$cityName   = $city['name'];
$rank       = $city['rank'] ?? '—';
$residents  = number_format((int)($city['resident_count'] ?? 0));
$blocks     = number_format((int)($city['activated_blocks'] ?? 0));
$fund       = number_format((float)($city['current_balance'] ?? ($city['total_fund'] ?? 0)), 1);
$areaCode   = $city['area_code'] ?? '';

$staticFile = __DIR__ . "/city/{$pinyin}.html";

// ===== 模式 A：有静态 HTML → 注入动态数据 =====
if (file_exists($staticFile)) {
    $html = file_get_contents($staticFile);

    if ($rank && $rank !== '—') {
        $html = preg_replace('/<div class="stat-value">第\d+名<\/div>/', '<div class="stat-value">第' . $rank . '名</div>', $html);
    }
    $html = preg_replace('/<div class="stat-label">现有居民<\/div>\s*<div class="stat-value">[^<]+<\/div>/s', '<div class="stat-label">现有居民</div><div class="stat-value">' . $residents . '人</div>', $html);
    $html = preg_replace('/<div class="stat-label">开启区块数<\/div>\s*<div class="stat-value">[^<]+<\/div>/s', '<div class="stat-label">开启区块数</div><div class="stat-value">' . $blocks . '</div>', $html);
    $html = preg_replace('/<div class="stat-label">基金余额<\/div>\s*<div class="stat-value">[^<]+<\/div>/s', '<div class="stat-label">基金余额</div><div class="stat-value">¥' . $fund . '</div>', $html);

    echo $html;
    exit;
}

// ===== 模式 B：无静态 HTML → 动态生成 =====
$title    = "{$cityName}区块城市 - 58区块城市 | 元宇宙同城平台";
$desc     = "{$cityName}区块城市详情页，展示{$cityName}城市排名、居民信息、基金余额等关键数据，是了解{$cityName}数字经济与元宇宙发展的重要门户。";
$keywords = "{$cityName}区块城市,{$cityName}元宇宙,58同城{$cityName},BlockCity,DAO";
$enterUrl = $areaCode ? "https://www.blockcity.pub/{$areaCode}?iclc" : "https://www.blockcity.pub/?iclc";
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?></title>
    <meta name="description" content="<?= htmlspecialchars($desc) ?>">
    <meta name="keywords" content="<?= htmlspecialchars($keywords) ?>">
    <link rel="canonical" href="https://58.tl/city/<?= $pinyin ?>.html" />
    <meta property="og:title" content="<?= htmlspecialchars($cityName) ?>区块城市 - 58区块城市">
    <meta property="og:description" content="<?= htmlspecialchars($desc) ?>">
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://58.tl/city/<?= $pinyin ?>.html">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= htmlspecialchars($cityName) ?>区块城市 - 58区块城市">
    <meta name="twitter:description" content="<?= htmlspecialchars($desc) ?>">
    <script type="application/ld+json">
    {"@context":"https://schema.org","@type":"City","name":"<?= htmlspecialchars($cityName) ?>区块城市","url":"https://58.tl/city/<?= $pinyin ?>.html","address":{"@type":"PostalAddress","addressRegion":"<?= htmlspecialchars($cityName) ?>市","addressCountry":"CN"}}
    </script>
    <script type="application/ld+json">
    {"@context":"https://schema.org","@type":"BreadcrumbList","itemListElement":[{"@type":"ListItem","position":1,"name":"58区块城市","item":"https://www.58.tl/"},{"@type":"ListItem","position":2,"name":"城市列表","item":"https://www.58.tl/all-cities.php"},{"@type":"ListItem","position":3,"name":"<?= htmlspecialchars($cityName) ?>区块城市"}]}
    </script>
    <script charset="UTF-8" id="LA_COLLECT" src="//sdk.51.la/js-sdk-pro.min.js"></script>
    <script>LA.init({id:"Km945dEjfme2S7Eg",ck:"Km945dEjfme2S7Eg"})</script>
    <link rel="icon" type="image/png" href="/favicon-96x96.png" sizes="96x96" />
    <link rel="icon" type="image/svg+xml" href="/favicon.svg" />
    <link rel="shortcut icon" href="/favicon.ico" />
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png" />
    <link rel="stylesheet" href="/city/city.css" type="text/css" media="all" />
    <script>
    var _hmt=_hmt||[];
    (function(){var hm=document.createElement("script");hm.src="https://hm.baidu.com/hm.js?5949e57aa9d2303fbf9451b06d4df471";var s=document.getElementsByTagName("script")[0];s.parentNode.insertBefore(hm,s);})();
    </script>
</head>
<body>
    <div class="container breadcrumb">
        <a href="/index.php">首页</a> &gt;
        <a href="/top200city.php">TOP200城市</a> &gt;
        <span><?= htmlspecialchars($cityName) ?>区块城市</span>
    </div>
    <header>
        <div class="container header-container">
            <div class="logo">
                <div class="logo-img">58</div>
                <div class="logo-text">区块城市<span>元宇宙同城生活服务平台</span></div>
            </div>
            <div class="user-actions">
                <a href="/index.php" class="nav-button">返回首页</a>
                <a href="https://nft.58.tl/" class="nav-button">NFT交易</a>
                <a href="https://v.58.tl/" class="nav-button">互访圈</a>
                <a href="/top200city.php" class="nav-button">TOP200城市</a>
                <a href="https://www.blockcity.vip/pages/user/user/?iclc" class="nav-button">我的区块</a>
            </div>
        </div>
    </header>
    <div class="city-location-bar" id="cityLocationBar">
        欢迎您,来自于<span id="userCity">未知城市</span>的朋友，<a href="https://www.blockcity.pub/?iclc" id="cityLink">点击进入您所在城市的区块</a>
    </div>
    <div class="container city-detail-container">
        <div class="city-header">
            <div class="city-avatar">
                <img src="data:image/svg+xml;charset=UTF-8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect width='100' height='100' fill='%23fff8f0'/><text x='50' y='60' font-size='40' text-anchor='middle' fill='%23cc0000'><?= htmlspecialchars(mb_substr($cityName,0,1)) ?></text></svg>" alt="<?= htmlspecialchars($cityName) ?>城市">
            </div>
            <div class="city-info">
                <h1 class="city-name"><?= htmlspecialchars($cityName) ?>区块城市</h1>
                <p class="city-slogan">共建<?= htmlspecialchars($cityName) ?>元宇宙</p>
                <div class="city-stats">
                    <div class="stat-card"><div class="stat-label">全国排名</div><div class="stat-value">第<?= $rank ?>名</div></div>
                    <div class="stat-card"><div class="stat-label">现有居民</div><div class="stat-value"><?= $residents ?>人</div></div>
                    <div class="stat-card"><div class="stat-label">开启区块数</div><div class="stat-value"><?= $blocks ?></div></div>
                    <div class="stat-card"><div class="stat-label">基金余额</div><div class="stat-value">¥<?= $fund ?></div></div>
                </div>
                <a href="<?= htmlspecialchars($enterUrl) ?>" class="enter-city-btn">进入<?= htmlspecialchars($cityName) ?>区块城市</a>
            </div>
        </div>
    </div>
    <footer>
        <div class="container">
            <div class="footer-container">
                <div class="footer-column"><h3>关于58区块城市</h3><ul>
                    <li><a href="https://www.blockcity.vip/pages/index/company/?iclc=1">公司简介</a></li>
                    <li><a href="https://www.blockcity.vip/zt/pages/invest/plan/?iclc=1">元宇宙愿景</a></li>
                    <li><a href="https://www.blockcity.vip/pages/index/help3?iclc=1&id=72&type=7">产品介绍</a></li>
                    <li><a href="https://www.blockcity.pub/pages/index/book/?iclc=1">元宇宙白皮书</a></li>
                </ul></div>
                <div class="footer-column"><h3>帮助中心</h3><ul>
                    <li><a href="/help/help.html">新手指南</a></li>
                    <li><a href="#">元宇宙入门</a></li>
                    <li><a href="https://mp.weixin.qq.com/s/KWoNXzeldh3GxI9uS2O80g">用户答疑</a></li>
                    <li><a href="https://www.blockcity.vip/pages/index/help/?iclc=1">常见问题</a></li>
                </ul></div>
                <div class="footer-column"><h3>商家服务</h3><ul>
                    <li><a href="/news.html">区块新闻</a></li>
                    <li><a href="https://www.blockcity.biz/naquba/">元宇宙店铺</a></li>
                    <li><a href="https://www.blockcity.pub/pages/index/block/?iclc=1">9区价格表</a></li>
                    <li><a href="http://blockcity.pub/zc/?iclc">营销推广</a></li>
                </ul></div>
                <div class="footer-column"><h3>关注我们</h3><ul>
                    <li><a href="#">BlockCity微信公众号</a></li>
                    <li><a href="#">BlockCity微博</a></li>
                    <li><a href="#">BlockCity小红书</a></li>
                    <li><a href="https://work.weixin.qq.com/kfid/kfc5e3b38b343460881">BlockCity在线客服</a></li>
                </ul></div>
            </div>
            <div class="copyright">© 2025 58区块城市 | BlockCity DAO 版权所有 | 基于元宇宙技术的下一代同城服务平台</div>
        </div>
    </footer>
    <div class="promotion-floating" id="promotionFloating">
        <div class="promotion-close" onclick="document.getElementById('promotionFloating').style.display='none'">×</div>
        <div class="promotion-header"><i>🎉</i> 限时优惠</div>
        <div class="promotion-content">凡通过本站购买各城市新区块，一律享<strong style="color:#ff6b00;">7.5折优惠</strong>！<br>详情请扫描下方二维码添加客服微信咨询。</div>
        <div class="promotion-qrcode"><img src="/qr.jpg" alt="<?= htmlspecialchars($cityName) ?>区块城市客服微信二维码" loading="lazy"></div>
        <div style="text-align:center;font-size:12px;color:#999;">扫码添加客服微信</div>
    </div>
    <script src="/city/city.js"></script>
    <script>
        window.onload=getCityInfo;
        setTimeout(function(){document.getElementById('promotionFloating').style.display='block';},3000);
    </script>
</body>
</html>
