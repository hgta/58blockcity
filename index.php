<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>58区块城市 - 元宇宙同城生活服务平台 | 58 BlockCity </title>
    <meta name="description" content="58区块城市是基于元宇宙技术的下一代同城生活服务平台，整合BlockCity DAO社区治理，为您提供全新的本地生活体验。">
    <meta name="keywords" content="58,区块城市,区块同城,元宇宙,BlockCity,DAO,同城服务,本地生活,区块链城市">
    <meta property="og:title" content="58区块城市 - 元宇宙同城生活服务平台">
    <meta property="og:description" content="基于元宇宙技术的下一代同城生活服务平台">
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://www.58.tl">
    <meta property="og:image" content="https://58.tl/assets/images/og-main.jpg">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <link rel="canonical" href="https://www.58.tl">
    <script charset="UTF-8" id="LA_COLLECT" src="//sdk.51.la/js-sdk-pro.min.js"></script>
    <script>LA.init({id:"Km945dEjfme2S7Eg",ck:"Km945dEjfme2S7Eg"})</script>
<?php
session_start();
require_once 'config/database.php';
require_once 'classes/City.php';
$city = new City($pdo);
$hotCities = $city->getHotCitiesList(18);
$citiesByLetter = $city->getCitiesByLetter();
$letters = range('A', 'Z');
?>
    <link rel="icon" type="image/png" href="/favicon-96x96.png" sizes="96x96" />
	<link rel="icon" type="image/svg+xml" href="/favicon.svg" />
	<link rel="shortcut icon" href="/favicon.ico" />
	<link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png" />
	<link rel="manifest" href="/site.webmanifest" />
	<script src="/city/city.js"></script>
    <style>
        :root {
            --bg: #f0f2f5;
            --card: #fff;
            --text: #1a1a2e;
            --muted: #6b7280;
            --primary: #2563eb;
            --accent: #f59e0b;
            --shadow: 0 1px 3px rgba(0,0,0,.06), 0 1px 2px rgba(0,0,0,.04);
            --shadow-md: 0 4px 6px rgba(0,0,0,.07), 0 2px 4px rgba(0,0,0,.04);
            --radius: 12px;
        }
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family: 'PingFang SC','Microsoft YaHei','Helvetica Neue',sans-serif; background:var(--bg); color:var(--text); line-height:1.6; -webkit-font-smoothing:antialiased; }
        a { text-decoration:none; color:inherit; }
        .container { max-width:1200px; margin:0 auto; padding:0 20px; }
        
        /* 头部 */
        header { background:#fff; padding:0; box-shadow:0 1px 0 rgba(0,0,0,.06); position:sticky; top:0; z-index:100; }
        .header-container { display:flex; justify-content:space-between; align-items:center; padding:12px 20px; max-width:1200px; margin:0 auto; }
        .logo { display:flex; align-items:center; gap:10px; }
        .logo-img { width:38px; height:38px; background:var(--primary); color:#fff; border-radius:8px; display:flex; align-items:center; justify-content:center; font-size:17px; font-weight:700; }
        .logo-text { font-size:18px; font-weight:700; color:var(--text); }
        .logo-text span { display:block; font-size:11px; font-weight:400; color:var(--muted); }
        .user-actions { display:flex; gap:4px; flex-wrap:wrap; }
        .nav-button { display:inline-flex; align-items:center; padding:6px 14px; border-radius:6px; font-size:13px; color:#4b5563; transition:all .2s; white-space:nowrap; }
        .nav-button:hover { background:#f3f4f6; color:var(--primary); }
        
        /* 城市定位条 */
        .city-location-bar { background:linear-gradient(135deg,#eff6ff,#f0f9ff); color:#1e40af; text-align:center; padding:10px; font-size:14px; border-bottom:1px solid #dbeafe; }
        .city-location-bar a { color:#2563eb; font-weight:600; }
        
        /* 字母导航 */
        .letter-nav { background:#fff; position:sticky; top:63px; z-index:99; border-bottom:1px solid #f3f4f6; }
        .letter-nav-container { display:flex; max-width:1200px; margin:0 auto; overflow-x:auto; }
        .letter-link { padding:8px 13px; font-size:13px; color:#9ca3af; font-weight:600; flex-shrink:0; }
        .letter-link:hover { color:var(--primary); }
        
        /* Hero Banner */
        .banner-section { margin:30px 0 40px; }
        .hero { background:linear-gradient(135deg,#1e3a5f 0%,#2563eb 50%,#7c3aed 100%); border-radius:var(--radius); padding:60px 50px; color:#fff; display:flex; align-items:center; justify-content:space-between; gap:40px; }
        .hero-left h1 { font-size:36px; font-weight:800; line-height:1.2; margin-bottom:12px; }
        .hero-left p { font-size:16px; opacity:.85; margin-bottom:24px; max-width:420px; }
        .hero-btns { display:flex; gap:12px; }
        .hero-btns a { padding:10px 24px; border-radius:8px; font-size:14px; font-weight:600; transition:all .2s; }
        .btn-primary { background:#fff; color:#1e3a5f; }
        .btn-primary:hover { transform:translateY(-1px); box-shadow:0 4px 12px rgba(0,0,0,.15); }
        .btn-outline { border:1.5px solid rgba(255,255,255,.4); color:#fff; }
        .btn-outline:hover { background:rgba(255,255,255,.1); }
        .hero-right { font-size:80px; opacity:.15; }
        
        /* 热门城市 */
        .hot-cities { padding:0 0 30px; }
        .section-title { font-size:22px; font-weight:700; color:var(--text); margin-bottom:20px; display:flex; justify-content:space-between; align-items:center; }
        .more-cities { font-size:13px; color:var(--primary); font-weight:500; }
        .more-cities:hover { text-decoration:underline; }
        .hot-city-grid { display:grid; grid-template-columns:repeat(6,1fr); gap:12px; }
        .hot-city-item { background:var(--card); padding:20px 12px; text-align:center; border-radius:var(--radius); font-size:15px; font-weight:600; color:var(--text); box-shadow:var(--shadow); transition:all .25s; }
        .hot-city-item:hover { transform:translateY(-3px); box-shadow:var(--shadow-md); color:var(--primary); }
        
        /* 城市列表 */
        .city-list-container { margin:20px 0 40px; }
        .city-section { margin-bottom:28px; }
        .city-letter { font-size:18px; font-weight:700; color:var(--primary); padding:8px 0; border-bottom:2px solid #e5e7eb; margin-bottom:12px; }
        .city-grid { display:grid; grid-template-columns:repeat(8,1fr); gap:6px; }
        .city-item { background:var(--card); padding:7px 4px; text-align:center; border-radius:6px; font-size:12px; color:#6b7280; transition:all .2s; }
        .city-item:hover { background:#eff6ff; color:var(--primary); }
        .city-item.hot-city { font-weight:600; color:#374151; }
        
        /* 特色促销区 */
        .featured-section { display:grid; grid-template-columns:1fr 1fr; gap:16px; margin:30px 0; }
        .promo-banner { padding:30px; border-radius:var(--radius); color:#fff; text-align:center; }
        .promo-banner.shop { background:linear-gradient(135deg,#059669,#10b981); }
        .promo-banner.red { background:linear-gradient(135deg,#dc2626,#ef4444); }
        .promo-title { font-size:20px; font-weight:700; margin-bottom:6px; }
        .promo-desc { font-size:13px; opacity:.9; margin-bottom:16px; }
        .promo-btn { display:inline-block; background:#fff; padding:8px 22px; border-radius:20px; font-size:13px; font-weight:600; }
        
        /* 元宇宙特色 */
        .metaverse-features { padding:40px 0; text-align:center; }
        .feature-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:20px; margin-top:20px; }
        .feature-card { background:var(--card); padding:30px 20px; border-radius:var(--radius); box-shadow:var(--shadow); transition:all .3s; }
        .feature-card:hover { transform:translateY(-4px); box-shadow:var(--shadow-md); }
        .feature-icon { font-size:44px; margin-bottom:12px; }
        .feature-title { font-size:16px; font-weight:700; margin-bottom:8px; color:var(--text); }
        .feature-desc { font-size:13px; color:var(--muted); line-height:1.6; }
        
        /* DAO社区 */
        .dao-community { background:linear-gradient(135deg,#1e3a5f,#312e81); color:#fff; padding:44px 40px; border-radius:var(--radius); margin:30px 0 40px; text-align:center; }
        .dao-title { font-size:26px; font-weight:700; margin-bottom:12px; }
        .dao-text { font-size:15px; line-height:1.7; opacity:.9; margin-bottom:20px; max-width:600px; margin-left:auto; margin-right:auto; }
        .dao-button { display:inline-block; background:#fff; color:#1e3a5f; padding:12px 30px; border-radius:24px; font-size:14px; font-weight:700; transition:all .2s; }
        .dao-button:hover { transform:translateY(-2px); box-shadow:0 4px 15px rgba(0,0,0,.2); }
        
        /* 响应式 */
        @media(max-width:768px){
            .header-container{flex-direction:column;gap:10px}
            .user-actions{justify-content:center}
            .hero{flex-direction:column;text-align:center;padding:40px 24px}
            .hero-btns{justify-content:center}
            .hot-city-grid{grid-template-columns:repeat(3,1fr)}
            .city-grid{grid-template-columns:repeat(4,1fr)}
            .feature-grid{grid-template-columns:1fr}
            .featured-section{grid-template-columns:1fr}
        }
        @media(max-width:480px){
            .hot-city-grid{grid-template-columns:repeat(2,1fr)}
            .city-grid{grid-template-columns:repeat(3,1fr)}
        }
    </style>
</head>
<body>
    <!-- 头部区域 -->
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
				<a href="https://block.58.tl/" class="nav-button">区块交易</a>
				<a href="https://bct.58.tl/" class="nav-button">BCT交易</a>
				<a href="https://nft.58.tl/" class="nav-button">NFT头像</a>
				<a href="https://mall.58.tl/" class="nav-button">人气商城</a>
				<a href="https://v.58.tl/" class="nav-button">互访圈</a>
				<a href="top200city.php" class="nav-button">TOP200</a>
				<a href="hongbao.php" class="nav-button">红包</a>
				<?php if (isset($_SESSION['user_id'])): ?>
					<a href="https://block.58.tl/user/dashboard.php" class="nav-button" style="background:#2563eb;color:#fff;">个人中心</a>
					<a href="auth/logout.php" class="nav-button">退出</a>
				<?php else: ?>
					<a href="auth/login.php" class="nav-button" style="background:#2563eb;color:#fff;">登录</a>
					<a href="auth/register.php" class="nav-button">注册</a>
				<?php endif; ?>
            </div>
        </div>
    </header>
	
	<!-- 城市定位提示条 -->
    <div class="city-location-bar" id="cityLocationBar">
        欢迎您,来自于<span id="userCity">未知城市</span>的朋友，<a href="https://www.blockcity.pub/?iclc" id="cityLink">点击进入您所在城市的区块</a>
    </div>
	
    <!--
	<div class="container">
        <div class="domain-sale">
            <div class="domain-sale-text">🔥 优质域名出售中 | Premium Domain For Sale</div>
            <a href="https://domainbatch.com/name/58.tl" class="domain-sale-link">立即询价 | Make an Offer</a>
            <div class="domain-sale-english">www.58.tl - 优质短域名，适合区块链/元宇宙项目</div>
        </div>
    </div>
    -->	
    <!-- 字母导航 -->
    <nav class="letter-nav">
        <div class="letter-nav-container">
            <a href="#A" class="letter-link">A</a>
            <a href="#B" class="letter-link">B</a>
            <a href="#C" class="letter-link">C</a>
            <a href="#D" class="letter-link">D</a>
            <a href="#E" class="letter-link">E</a>
            <a href="#F" class="letter-link">F</a>
            <a href="#G" class="letter-link">G</a>
            <a href="#H" class="letter-link">H</a>
            <a href="#J" class="letter-link">J</a>
            <a href="#K" class="letter-link">K</a>
            <a href="#L" class="letter-link">L</a>
            <a href="#M" class="letter-link">M</a>
            <a href="#N" class="letter-link">N</a>
            <a href="#P" class="letter-link">P</a>
            <a href="#Q" class="letter-link">Q</a>
            <a href="#R" class="letter-link">R</a>
            <a href="#S" class="letter-link">S</a>
            <a href="#T" class="letter-link">T</a>
            <a href="#W" class="letter-link">W</a>
            <a href="#X" class="letter-link">X</a>
            <a href="#Y" class="letter-link">Y</a>
            <a href="#Z" class="letter-link">Z</a>
        </div>
    </nav>
    
    <!-- Hero Banner -->
    <div class="banner-section">
        <div class="container">
            <div class="hero">
                <div class="hero-left">
                    <h1>探索元宇宙<br>城市生态</h1>
                    <p>58区块城市 — 基于区块链的虚拟城市交易平台，发现、认领、交易你的数字领地</p>
                    <div class="hero-btns">
                        <a href="https://block.58.tl/" class="btn-primary">开始探索</a>
                        <a href="https://bct.58.tl/" class="btn-outline">了解BCT</a>
                    </div>
                </div>
                <div class="hero-right">🏙️</div>
            </div>
        </div>
    </div>

    <!-- 主要内容 -->
    <main class="container">
        <!-- 热门城市 -->
        <section class="hot-cities">
            <h2 class="section-title">
                热门区块城市
                <a href="top200city.php" class="more-cities">更多热门城市 →</a>
            </h2>
            <div class="hot-city-grid">
                <?php foreach ($hotCities as $c): ?>
                    <a href="/city/<?= urlencode($c['pinyin']) ?>.html" class="hot-city-item"><?= htmlspecialchars($c['name']) ?></a>
                <?php endforeach; ?>
            </div>
        </section>
        
        <!-- 促销区 -->
        <div class="featured-section">
            <div class="promo-banner shop">
                <div class="promo-title">🛍️ 人气商城</div>
                <div class="promo-desc">BCT支付购物，区块城市专属商城</div>
                <a href="https://mall.58.tl/" class="promo-btn">立即购物</a>
            </div>
            <div class="promo-banner red">
                <div class="promo-title">🧧 红包时间表</div>
                <div class="promo-desc">各城市红包发放时间，定好闹钟来抢</div>
                <a href="hongbao.php" class="promo-btn">查看时间表</a>
            </div>
        </div>

        <!-- 城市列表（仅展示前200个） -->
        <div class="city-list-container">
            <?php
            $displayedCount = 0;
            $maxCities = 200;
            foreach ($letters as $letter):
                if (empty($citiesByLetter[$letter])) continue;
            ?>
            <section id="<?= $letter ?>" class="city-section">
                <div class="city-letter"><?= $letter ?></div>
                <div class="city-grid">
                    <?php foreach ($citiesByLetter[$letter] as $c):
                        if ($displayedCount >= $maxCities) break;
                        if (strtoupper(substr($c['pinyin'], 0, 1)) !== $letter) continue;
                        $displayedCount++;
                    ?>
                        <a href="/city/<?= urlencode($c['pinyin']) ?>.html"
                           class="city-item <?= $c['is_hot'] ? 'hot-city' : '' ?>">
                            <?= htmlspecialchars($c['name']) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php
                if ($displayedCount >= $maxCities) break;
            endforeach;
            ?>
        </div>

        <?php if ($displayedCount >= $maxCities): ?>
        <div class="more-cities-bar" style="text-align:center;padding:24px 0 40px;">
            <a href="all-cities.php" class="btn-primary" style="display:inline-block;padding:12px 32px;border-radius:8px;font-size:15px;">
                查看全部 <?= count($allCities) ?> 个城市 →
            </a>
        </div>
        <?php endif; ?>
        
        
        
        
        <!-- 元宇宙特色 -->
        <section class="metaverse-features">
            <h2 class="section-title">BlockCity元宇宙特色</h2>
            <div class="feature-grid">
                <div class="feature-card">
                    <div class="feature-icon">🌐</div>
                    <h3 class="feature-title">虚拟城市探索</h3>
                    <p class="feature-desc">通过元宇宙技术，58区块城市为您提供沉浸式的虚拟城市探索体验，足不出户逛遍全城。</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">🪙</div>
                    <h3 class="feature-title">数字资产交易</h3>
                    <p class="feature-desc">基于区块链技术的数字资产交易平台，安全可靠地交易您的虚拟商品和服务。</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">👥</div>
                    <h3 class="feature-title">DAO社区治理</h3>
                    <p class="feature-desc">58区块城市采用DAO(去中心化自治组织)模式，让用户参与平台治理和决策。</p>
                </div>
            </div>
        </section>
        
        <!-- DAO社区 -->
        <section class="dao-community">
            <h2 class="dao-title">加入BlockCity DAO社区</h2>
            <div class="dao-content">
                <div class="dao-text">
                    <p>58区块城市正在构建全球最大的元宇宙同城DAO社区。通过持有平台通证，您可以参与社区治理、投票决策、分享收益，共同打造下一代去中心化城市服务平台。</p>
                    <a href="https://www.blockcity.pub/?iclc" class="dao-button">立即加入DAO</a>
                </div>
                <div class="dao-image">
                    BlockCity DAO
                </div>
            </div>
        </section>
    </main>
    
    <!-- 底部 -->
    <footer style="background:#1a1a2e;color:#94a3b8;padding:48px 0 20px;margin-top:40px;">
        <div class="container">
            <div style="display:grid;grid-template-columns:2fr 1fr 1fr 1.2fr 1fr;gap:30px;margin-bottom:30px;">
                <div>
                    <h4 style="color:#fff;margin-bottom:12px;font-size:15px;">关于58区块城市</h4>
                    <p style="font-size:13px;line-height:1.8;color:#64748b;">
                        58区块城市是基于元宇宙技术的下一代同城生活服务平台，整合BlockCity DAO社区治理，为用户提供虚拟城市探索、数字资产交易的一站式体验。
                    </p>
                </div>
                <div>
                    <h4 style="color:#fff;margin-bottom:12px;font-size:15px;">快速链接</h4>
                    <ul style="list-style:none;padding:0;font-size:13px;line-height:2.2;">
                        <li><a href="https://block.58.tl/" style="color:#64748b;">区块交易</a></li>
                        <li><a href="https://bct.58.tl/" style="color:#64748b;">BCT交易</a></li>
                        <li><a href="https://mall.58.tl/" style="color:#64748b;">人气商城</a></li>
                        <li><a href="https://nft.58.tl/" style="color:#64748b;">NFT头像</a></li>
                    </ul>
                </div>
                <div>
                    <h4 style="color:#fff;margin-bottom:12px;font-size:15px;">帮助支持</h4>
                    <ul style="list-style:none;padding:0;font-size:13px;line-height:2.2;">
                        <li><a href="https://www.blockcity.vip/pages/index/help/?iclc=1" style="color:#64748b;">使用指南</a></li>
                        <li><a href="https://www.blockcity.pub/?iclc=1" style="color:#64748b;">加入DAO</a></li>
                        <li><a href="https://www.blockcity.biz/naquba/" style="color:#64748b;">元宇宙店铺</a></li>
                        <li><a href="news.php" style="color:#64748b;">区块新闻</a></li>
                    </ul>
                </div>
                <div>
                    <h4 style="color:#fff;margin-bottom:12px;font-size:15px;">关注我们</h4>
                    <div style="display:flex;gap:12px;">
                        <img src="/images/qr-discount.png" alt="7.5折购地" style="width:80px;height:80px;background:#fff;border-radius:6px;padding:3px;">
                        <img src="/images/qr-customer-service.png" alt="客服微信" style="width:80px;height:80px;background:#fff;border-radius:6px;padding:3px;">
                    </div>
                    <div style="font-size:10px;color:#64748b;margin-top:6px;display:flex;gap:12px;">
                        <span style="width:80px;text-align:center;">7.5折购地</span>
                        <span style="width:80px;text-align:center;">客服微信</span>
                    </div>
                </div>
                <div>
                    <h4 style="color:#fff;margin-bottom:12px;font-size:15px;">联系我们</h4>
                    <p style="font-size:13px;color:#64748b;line-height:2;">
                        📧 support@58.tl<br>
                        🌐 www.58.tl<br>
                        📍 元宇宙同城生态
                    </p>
                </div>
            </div>
            <div style="border-top:1px solid #1e293b;padding-top:20px;text-align:center;font-size:12px;color:#475569;">
                © 2025 58区块城市 | BlockCity 版权所有 | 基于元宇宙技术的下一代同城服务平台
            </div>
        </div>
    </footer>
	
	<!-- 新增：优惠悬浮窗口 -->
    <!--<div class="promotion-floating" id="promotionFloating">
        <div class="promotion-close" onclick="document.getElementById('promotionFloating').style.display='none'">×</div>
        <div class="promotion-header">
            <i>🎉</i> 限时优惠
        </div>
        <div class="promotion-content">
            凡通过本站购买各城市新区块，一律享<strong style="color:#ff6b00;">7折优惠</strong>！<br>
            详情请扫描下方二维码添加客服微信咨询。
        </div>
        <div class="promotion-qrcode">
            <img src="../qr.jpg" alt="客服微信二维码">
        </div>
        <div style="text-align:center;font-size:12px;color:#999;">扫码添加客服微信</div>
    </div>-->

    <script>
        // 3秒后显示悬浮窗口
        //setTimeout(function() {
        //    document.getElementById('promotionFloating').style.display = 'block';
        //}, 3000);
		
		// 页面加载时获取城市信息
        window.onload = function() {
            if (typeof getCityInfo === 'function') getCityInfo();
        };
    </script>
    
    <!-- JSON-LD结构化数据 -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "WebSite",
      "name": "58区块城市",
      "url": "https://www.58.tl",
      "potentialAction": {
        "@type": "SearchAction",
        "target": "https://www.58.tl/search?q={search_term_string}",
        "query-input": "required name=search_term_string"
      },
      "description": "基于元宇宙技术的下一代同城生活服务平台，整合BlockCity DAO社区治理。",
      "keywords": "58,区块城市,元宇宙,BlockCity,DAO,同城服务,本地生活,区块链城市"
    }
    </script>
</body>
</html>