<?php
/**
 * 城市页面动态路由
 * .htaccess: RewriteRule ^city/([a-z]+)\.html$ city.php?pinyin=$1 [L,QSA]
 *
 * 两种模式：
 *   1. city/{pinyin}.html 存在 → 读取静态 HTML + 用 DB 数据替换统计数字
 *   2. 静态文件不存在 → 从 DB 动态生成完整页面
 */

require_once __DIR__ . '/config/database.php';

$pinyin = trim($_GET['pinyin'] ?? '');

// 安全校验
if (!preg_match('/^[a-z]+$/', $pinyin)) {
    header('HTTP/1.0 404 Not Found');
    include __DIR__ . '/404.php';
    exit;
}

// --- 拼音→中文名 映射表 ---
$pinyinToName = [
    'aba' => '阿坝', 'akesu' => '阿克苏', 'alashanmeng' => '阿拉善盟', 'aletai' => '阿勒泰',
    'ankang' => '安康', 'anqing' => '安庆', 'anshan' => '鞍山', 'anshun' => '安顺',
    'anyang' => '安阳', 'baise' => '百色', 'baishan' => '白山', 'baoding' => '保定',
    'baoji' => '宝鸡', 'baotou' => '包头', 'beihai' => '北海', 'beijing' => '北京',
    'bengbu' => '蚌埠', 'bijie' => '毕节', 'binzhou' => '滨州', 'bozhou' => '亳州',
    'changsha' => '长沙', 'changzhou' => '常州', 'chaozhou' => '潮州', 'chengdu' => '成都',
    'chongqing' => '重庆', 'dalian' => '大连', 'daqing' => '大庆', 'dongguan' => '东莞', 'foshan' => '佛山',
    'fuzhou' => '福州', 'guangzhou' => '广州', 'guiyang' => '贵阳', 'haerbin' => '哈尔滨',
    'haikou' => '海口', 'hangzhou' => '杭州', 'hefei' => '合肥', 'huizhou' => '惠州',
    'huzhou' => '湖州', 'jiaxing' => '嘉兴', 'jinan' => '济南', 'jinhua' => '金华',
    'jining' => '济宁', 'kaifeng' => '开封', 'kunming' => '昆明', 'linyi' => '临沂',
    'luoyang' => '洛阳', 'nanjing' => '南京', 'ningbo' => '宁波', 'ningde' => '宁德',
    'qingdao' => '青岛', 'shanghai' => '上海', 'shenyang' => '沈阳', 'shenzhen' => '深圳',
    'suzhou' => '苏州', 'taiyuan' => '太原', 'tianjin' => '天津', 'wuhan' => '武汉',
    'wuxi' => '无锡', 'xiamen' => '厦门', 'xian' => '西安', 'yantai' => '烟台',
    'zhengzhou' => '郑州', 'zhoukou' => '周口', 'zhuhai' => '珠海',
];
$cityName = $pinyinToName[$pinyin] ?? null;

// --- 查数据库 ---
$dbStats = null;
if ($cityName && isset($pdo)) {
    try {
        $stmt = $pdo->prepare("SELECT id, name, rank, resident_count, activated_blocks, total_fund, current_balance, popularity FROM cities WHERE name = ?");
        $stmt->execute([$cityName]);
        $dbStats = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}
}

// 如果 DB 没查到，尝试用 cities-data.json
if (!$dbStats && $cityName) {
    $jsonPath = __DIR__ . '/cities-data.json';
    if (file_exists($jsonPath)) {
        $jsonData = json_decode(file_get_contents($jsonPath), true);
        foreach (($jsonData['cities'] ?? []) as $c) {
            if ($c['name'] === $cityName) {
                $dbStats = [
                    'name' => $c['name'],
                    'rank' => $c['rank'] ?? null,
                    'resident_count' => $c['population'] ?? 0,
                    'activated_blocks' => $c['blocks'] ?? 0,
                    'area_no' => $c['areaNo'] ?? '',
                ];
                break;
            }
        }
    }
}

$staticFile = __DIR__ . "/city/{$pinyin}.html";

// ===== 模式 1：有静态 HTML → 注入动态数据 =====
if (file_exists($staticFile)) {
    $html = file_get_contents($staticFile);
    
    if ($dbStats) {
        if ($dbStats['rank']) {
            $html = preg_replace('/<div class="stat-value">第\d+名<\/div>/', '<div class="stat-value">第' . $dbStats['rank'] . '名</div>', $html);
        }
        if (isset($dbStats['resident_count'])) {
            $html = preg_replace('/<div class="stat-label">现有居民<\/div>\s*<div class="stat-value">[^<]+<\/div>/s', '<div class="stat-label">现有居民</div><div class="stat-value">' . number_format((int)$dbStats['resident_count']) . '人</div>', $html);
        }
        if (isset($dbStats['activated_blocks'])) {
            $html = preg_replace('/<div class="stat-label">开启区块数<\/div>\s*<div class="stat-value">[^<]+<\/div>/s', '<div class="stat-label">开启区块数</div><div class="stat-value">' . number_format((int)$dbStats['activated_blocks']) . '</div>', $html);
        }
        if (isset($dbStats['current_balance'])) {
            $html = preg_replace('/<div class="stat-label">基金余额<\/div>\s*<div class="stat-value">[^<]+<\/div>/s', '<div class="stat-label">基金余额</div><div class="stat-value">¥' . number_format((float)$dbStats['current_balance'], 1) . '</div>', $html);
        }
    }
    
    echo $html;
    exit;
}

// ===== 模式 2：无静态 HTML → 动态生成 =====
if (!$dbStats) {
    header('HTTP/1.0 404 Not Found');
    include __DIR__ . '/404.php';
    exit;
}

$rank = $dbStats['rank'] ?? '—';
$residents = number_format((int)($dbStats['resident_count'] ?? 0));
$blocks = number_format((int)($dbStats['activated_blocks'] ?? 0));
$fund = number_format((float)($dbStats['current_balance'] ?? ($dbStats['total_fund'] ?? 0)), 1);
$areaNo = $dbStats['area_no'] ?? '';

// SEO
$title = "{$cityName}区块城市 - 58区块城市 | 元宇宙同城平台";
$desc = "{$cityName}区块城市详情页，展示{$cityName}城市排名、居民信息、基金余额等关键数据，是了解{$cityName}数字经济与元宇宙发展的重要门户。";
$keywords = "{$cityName}区块城市,{$cityName}元宇宙,58同城{$cityName},BlockCity,DAO";
?><!DOCTYPE html>
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
    {
      "@context": "https://schema.org",
      "@type": "City",
      "name": "<?= htmlspecialchars($cityName) ?>区块城市",
      "url": "https://58.tl/city/<?= $pinyin ?>.html",
      "address": {
        "@type": "PostalAddress",
        "addressRegion": "<?= htmlspecialchars($cityName) ?>市",
        "addressCountry": "CN"
      }
    }
    </script>
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "BreadcrumbList",
      "itemListElement": [
        {"@type": "ListItem", "position": 1, "name": "58区块城市", "item": "https://www.58.tl/"},
        {"@type": "ListItem", "position": 2, "name": "城市列表", "item": "https://www.58.tl/all-cities.php"},
        {"@type": "ListItem", "position": 3, "name": "<?= htmlspecialchars($cityName) ?>区块城市"}
      ]
    }
    </script>
    <script charset="UTF-8" id="LA_COLLECT" src="//sdk.51.la/js-sdk-pro.min.js"></script>
    <script>LA.init({id:"Km945dEjfme2S7Eg",ck:"Km945dEjfme2S7Eg"})</script>
    <link rel="icon" type="image/png" href="/favicon-96x96.png" sizes="96x96" />
    <link rel="icon" type="image/svg+xml" href="/favicon.svg" />
    <link rel="shortcut icon" href="/favicon.ico" />
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png" />
    <link rel="stylesheet" href="/city/city.css" type="text/css" media="all" />
    <script>
    var _hmt = _hmt || [];
    (function() {
      var hm = document.createElement("script");
      hm.src = "https://hm.baidu.com/hm.js?5949e57aa9d2303fbf9451b06d4df471";
      var s = document.getElementsByTagName("script")[0];
      s.parentNode.insertBefore(hm, s);
    })();
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
                <div class="logo-text">
                    区块城市
                    <span>元宇宙同城生活服务平台</span>
                </div>
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
                <img src="data:image/svg+xml;charset=UTF-8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect width='100' height='100' fill='%23fff8f0'/><text x='50' y='60' font-size='40' text-anchor='middle' fill='%23cc0000'><?= htmlspecialchars(mb_substr($cityName, 0, 1)) ?></text></svg>" alt="<?= htmlspecialchars($cityName) ?>城市">
            </div>
            <div class="city-info">
                <h1 class="city-name"><?= htmlspecialchars($cityName) ?>区块城市</h1>
                <p class="city-slogan">共建<?= htmlspecialchars($cityName) ?>元宇宙</p>
                <div class="city-stats">
                    <div class="stat-card">
                        <div class="stat-label">全国排名</div>
                        <div class="stat-value">第<?= $rank ?>名</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">现有居民</div>
                        <div class="stat-value"><?= $residents ?>人</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">开启区块数</div>
                        <div class="stat-value"><?= $blocks ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">基金余额</div>
                        <div class="stat-value">¥<?= $fund ?></div>
                    </div>
                </div>
                <a href="https://www.blockcity.pub/?iclc<?= $areaNo ? '&areaNo=' . $areaNo : '' ?>" class="enter-city-btn">进入<?= htmlspecialchars($cityName) ?>区块城市</a>
            </div>
        </div>
    </div>
    
    <footer>
        <div class="container">
            <div class="footer-container">
                <div class="footer-column">
                    <h3>关于58区块城市</h3>
                    <ul>
                        <li><a href="https://www.blockcity.vip/pages/index/company/?iclc=1">公司简介</a></li>
                        <li><a href="https://www.blockcity.vip/zt/pages/invest/plan/?iclc=1">元宇宙愿景</a></li>
                        <li><a href="https://www.blockcity.vip/pages/index/help3?iclc=1&id=72&type=7">产品介绍</a></li>
                        <li><a href="https://www.blockcity.pub/pages/index/book/?iclc=1">元宇宙白皮书</a></li>
                    </ul>
                </div>
                <div class="footer-column">
                    <h3>帮助中心</h3>
                    <ul>
                        <li><a href="/help/help.html">新手指南</a></li>
                        <li><a href="#">元宇宙入门</a></li>
                        <li><a href="https://mp.weixin.qq.com/s/KWoNXzeldh3GxI9uS2O80g">用户答疑</a></li>
                        <li><a href="https://www.blockcity.vip/pages/index/help/?iclc=1">常见问题</a></li>
                    </ul>
                </div>
                <div class="footer-column">
                    <h3>商家服务</h3>
                    <ul>
                        <li><a href="/news.html">区块新闻</a></li>
                        <li><a href="https://www.blockcity.biz/naquba/">元宇宙店铺</a></li>
                        <li><a href="https://www.blockcity.pub/pages/index/block/?iclc=1">9区价格表</a></li>
                        <li><a href="http://blockcity.pub/zc/?iclc">营销推广</a></li>
                    </ul>
                </div>
                <div class="footer-column">
                    <h3>关注我们</h3>
                    <ul>
                        <li><a href="#">BlockCity微信公众号</a></li>
                        <li><a href="#">BlockCity微博</a></li>
                        <li><a href="#">BlockCity小红书</a></li>
                        <li><a href="https://work.weixin.qq.com/kfid/kfc5e3b38b343460881">BlockCity在线客服</a></li>
                    </ul>
                </div>
            </div>
            <div class="copyright">
                © 2025 58区块城市 | BlockCity DAO 版权所有 | 基于元宇宙技术的下一代同城服务平台
            </div>
        </div>
    </footer>
    
    <div class="promotion-floating" id="promotionFloating">
        <div class="promotion-close" onclick="document.getElementById('promotionFloating').style.display='none'">×</div>
        <div class="promotion-header"><i>🎉</i> 限时优惠</div>
        <div class="promotion-content">
            凡通过本站购买各城市新区块，一律享<strong style="color:#ff6b00;">7.7折优惠</strong>！<br>
            详情请扫描下方二维码添加客服微信咨询。
        </div>
        <div class="promotion-qrcode">
            <img src="/qr.jpg" alt="<?= htmlspecialchars($cityName) ?>区块城市客服微信二维码" loading="lazy">
        </div>
        <div style="text-align:center;font-size:12px;color:#999;">扫码添加客服微信</div>
    </div>
    <script src="/city/city.js"></script>
    <script>
        setTimeout(function() {
            document.getElementById('promotionFloating').style.display = 'block';
        }, 3000);
    </script>
</body>
</html>
