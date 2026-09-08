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
    <!-- 百度统计 -->
    <script>
    var _hmt = _hmt || [];
    (function() {
      var hm = document.createElement("script");
      hm.src = "https://hm.baidu.com/hm.js?5949e57aa9d2303fbf9451b06d4df471";
      var s = document.getElementsByTagName("script")[0]; 
      s.parentNode.insertBefore(hm, s);
    })();
    </script>
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
            --bg: #f5f5f5;
            --card: #fff;
            --text: #1a1a2e;
            --muted: #6b7280;
            --primary: #ff6b00;
            --accent: #ffb380;
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
        .city-location-bar { background:linear-gradient(135deg,#fff7f0,#fff3ea); color:#b35400; text-align:center; padding:10px; font-size:14px; border-bottom:1px solid #ffd9c2; }
        .city-location-bar a { color:#ff6b00; font-weight:600; }
        
        /* 字母导航 */
        .letter-nav { background:#fff; position:sticky; top:63px; z-index:99; border-bottom:1px solid #f3f4f6; }
        .letter-nav-container { display:flex; max-width:1200px; margin:0 auto; overflow-x:auto; }
        .letter-link { padding:8px 13px; font-size:13px; color:#9ca3af; font-weight:600; flex-shrink:0; }
        .letter-link:hover { color:var(--primary); }
        
        /* Hero Banner（紧凑横条，与城市门户 hero 同款品牌橙渐变） */
        .banner-section { margin:20px 0 24px; }
        .hero { background:linear-gradient(135deg,#ff6b00 0%,#ff8c33 60%,#ffb380 100%); border-radius:var(--radius); padding:22px 32px; color:#fff; display:flex; align-items:center; justify-content:space-between; gap:24px; box-shadow:0 4px 14px rgba(255,107,0,.22); }
        .hero-left h1 { font-size:24px; font-weight:800; line-height:1.3; margin-bottom:4px; }
        .hero-left p { font-size:14px; opacity:.92; margin-bottom:12px; max-width:520px; }
        .hero-btns { display:flex; gap:12px; }
        .hero-btns a { padding:8px 20px; border-radius:999px; font-size:13px; font-weight:600; transition:all .2s; }
        .btn-primary { background:#fff; color:#ff6b00; }
        .btn-primary:hover { transform:translateY(-1px); box-shadow:0 4px 12px rgba(0,0,0,.15); }
        .btn-outline { border:1.5px solid rgba(255,255,255,.55); color:#fff; }
        .btn-outline:hover { background:rgba(255,255,255,.12); }
        .hero-right { font-size:44px; opacity:.9; }
        
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
        .city-item:hover { background:#fff3ea; color:var(--primary); }
        .city-item.hot-city { font-weight:600; color:#374151; }

        /* 生态入口（子站导航网格） */
        .eco-section { margin:30px 0; }
        .eco-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:14px; }
        .eco-card { display:block; background:var(--card); border:1px solid #f0e3d8; border-radius:var(--radius); padding:18px 16px 14px; text-align:center; box-shadow:var(--shadow); transition:all .2s; }
        .eco-card:hover { transform:translateY(-3px); box-shadow:0 4px 12px rgba(255,107,0,.15); border-color:#ffb380; }
        .eco-icon { font-size:30px; line-height:1; margin-bottom:8px; }
        .eco-name { font-size:15px; font-weight:700; color:var(--text); margin-bottom:3px; }
        .eco-desc { font-size:12px; color:var(--muted); margin-bottom:10px; }
        .eco-go { display:inline-block; font-size:12px; font-weight:600; color:#ff6b00; background:#fff3ea; border-radius:999px; padding:4px 16px; }
        .eco-card:hover .eco-go { background:#ff6b00; color:#fff; }

        /* DAO社区（紧凑条） */
        .dao-community { background:linear-gradient(135deg,#7a3500,#c05c10); color:#fff; padding:24px 32px; border-radius:var(--radius); margin:10px 0 36px; display:flex; align-items:center; justify-content:space-between; gap:24px; flex-wrap:wrap; }
        .dao-title { font-size:18px; font-weight:700; margin-bottom:4px; }
        .dao-text { font-size:13px; line-height:1.7; opacity:.9; max-width:640px; }
        .dao-button { display:inline-block; background:#fff; color:#ff6b00; padding:10px 26px; border-radius:999px; font-size:14px; font-weight:700; transition:all .2s; }
        .dao-button:hover { transform:translateY(-2px); box-shadow:0 4px 15px rgba(0,0,0,.2); }
        
        /* 响应式 */
        @media(max-width:768px){
            .header-container{flex-direction:column;gap:10px}
            .user-actions{justify-content:center}
            .hero{flex-direction:column;text-align:center;padding:24px 20px}
            .hero-btns{justify-content:center}
            .hot-city-grid{grid-template-columns:repeat(3,1fr)}
            .city-grid{grid-template-columns:repeat(4,1fr)}
            .eco-grid{grid-template-columns:repeat(2,1fr)}
            .dao-community{text-align:center;justify-content:center}
            .footer-grid{grid-template-columns:repeat(2,1fr)!important}
        }
        @media(max-width:480px){
            .hot-city-grid{grid-template-columns:repeat(2,1fr)}
            .city-grid{grid-template-columns:repeat(3,1fr)}
            .footer-grid{grid-template-columns:1fr!important}
        }
    </style>
    <!-- Organization 结构化数据 -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "Organization",
      "name": "58区块城市",
      "alternateName": "BlockCity",
      "url": "https://www.58.tl",
      "logo": "https://58.tl/assets/images/logo.png",
      "sameAs": [
        "https://www.58.tl"
      ],
      "description": "基于元宇宙技术的下一代同城生活服务平台，整合BlockCity DAO社区治理"
    }
    </script>
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
				<a href="https://bid.58.tl/" class="nav-button">拍卖</a>
				<a href="https://task.58.tl/" class="nav-button">任务广场</a>
				<?php if (isset($_SESSION['user_id'])): ?>
					<a href="https://block.58.tl/user/dashboard.php" class="nav-button" style="background:#ff6b00;color:#fff;">个人中心</a>
					<a href="auth/logout.php" class="nav-button">退出</a>
				<?php else: ?>
					<a href="auth/login.php" class="nav-button" style="background:#ff6b00;color:#fff;">登录</a>
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
                    <h1>探索元宇宙城市生态</h1>
                    <p>58区块城市 — 基于区块链的虚拟城市平台，发现、认领、交易你的数字领地</p>
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
        
        <!-- 生态入口：全站子服务导航 -->
        <section class="eco-section">
            <h2 class="section-title">区块城市生态 · 一站直达</h2>
            <div class="eco-grid">
                <a href="https://block.58.tl/" class="eco-card">
                    <div class="eco-icon">🏙️</div>
                    <div class="eco-name">区块交易</div>
                    <div class="eco-desc">城市地块认领与买卖</div>
                    <span class="eco-go">进入 →</span>
                </a>
                <a href="https://bct.58.tl/" class="eco-card">
                    <div class="eco-icon">💰</div>
                    <div class="eco-name">BCT交易</div>
                    <div class="eco-desc">平台通证交易市场</div>
                    <span class="eco-go">进入 →</span>
                </a>
                <a href="https://nft.58.tl/" class="eco-card">
                    <div class="eco-icon">🖼️</div>
                    <div class="eco-name">NFT头像</div>
                    <div class="eco-desc">数字头像铸造与收藏</div>
                    <span class="eco-go">进入 →</span>
                </a>
                <a href="https://mall.58.tl/" class="eco-card">
                    <div class="eco-icon">🛍️</div>
                    <div class="eco-name">人气商城</div>
                    <div class="eco-desc">BCT支付优惠购物</div>
                    <span class="eco-go">进入 →</span>
                </a>
                <a href="https://v.58.tl/" class="eco-card">
                    <div class="eco-icon">🤝</div>
                    <div class="eco-name">互访圈</div>
                    <div class="eco-desc">同城互访拓展人脉</div>
                    <span class="eco-go">进入 →</span>
                </a>
                <a href="https://bid.58.tl/" class="eco-card">
                    <div class="eco-icon">🔨</div>
                    <div class="eco-name">拍卖</div>
                    <div class="eco-desc">稀缺资产竞价捡漏</div>
                    <span class="eco-go">进入 →</span>
                </a>
                <a href="https://club.58.tl/" class="eco-card">
                    <div class="eco-icon">👥</div>
                    <div class="eco-name">社区</div>
                    <div class="eco-desc">同城圈子畅聊互动</div>
                    <span class="eco-go">进入 →</span>
                </a>
                <a href="https://task.58.tl/" class="eco-card">
                    <div class="eco-icon">🧩</div>
                    <div class="eco-name">任务广场</div>
                    <div class="eco-desc">悬赏众包 帮做小任务</div>
                    <span class="eco-go">进入 →</span>
                </a>
                <a href="https://www.blockcity.pub/?iclc" class="eco-card">
                    <div class="eco-icon">🏛️</div>
                    <div class="eco-name">DAO治理</div>
                    <div class="eco-desc">社区共建共治共享</div>
                    <span class="eco-go">进入 →</span>
                </a>
            </div>
        </section>

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
            <a href="all-cities.php" style="display:inline-block;background:var(--primary);color:#fff;padding:12px 32px;border-radius:999px;font-size:15px;font-weight:600;box-shadow:var(--shadow-md);">
                查看全部 <?= $city->getTotalCitiesCount() ?> 个城市 →
            </a>
        </div>
        <?php endif; ?>
        
        
        
        
        <!-- DAO社区（紧凑条） -->
        <section class="dao-community">
            <div>
                <h2 class="dao-title">加入 BlockCity DAO 社区</h2>
                <p class="dao-text">持有平台通证，参与社区治理、投票决策、分享收益，共建去中心化城市服务平台。</p>
            </div>
            <a href="https://www.blockcity.pub/?iclc" class="dao-button">立即加入DAO</a>
        </section>
    </main>
    
    <!-- 底部 -->
    <footer style="background:#1a1a2e;color:#94a3b8;padding:48px 0 20px;margin-top:40px;">
        <div class="container">
            <div class="footer-grid" style="display:grid;grid-template-columns:2fr 1fr 1fr 1.2fr 1fr;gap:30px;margin-bottom:30px;">
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
                        <li><a href="https://bid.58.tl/" style="color:#64748b;">拍卖</a></li>
                        <li><a href="https://club.58.tl/" style="color:#64748b;">社区</a></li>
                        <li><a href="https://task.58.tl/" style="color:#64748b;">任务广场</a></li>
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
                    <div style="display:flex;gap:12px;flex-wrap:wrap;">
                        <img src="/images/qr-discount.png" alt="7.5折购地" style="width:80px;height:80px;max-width:80px;max-height:80px;background:#fff;border-radius:6px;padding:3px;display:block;">
                        <img src="/images/qr-customer-service.png" alt="客服微信" style="width:80px;height:80px;max-width:80px;max-height:80px;background:#fff;border-radius:6px;padding:3px;display:block;">
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
	
    <script>
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