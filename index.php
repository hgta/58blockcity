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
    <link rel="canonical" href="https://www.58.tl">
    <script charset="UTF-8" id="LA_COLLECT" src="//sdk.51.la/js-sdk-pro.min.js"></script>
    <script>LA.init({id:"Km945dEjfme2S7Eg",ck:"Km945dEjfme2S7Eg"})</script>
<?php
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
        /* 全局样式 */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Microsoft YaHei', Arial, sans-serif;
        }
        
        body {
            background-color: #f5f5f5;
            color: #333;
            line-height: 1.6;
        }
        
        a {
            text-decoration: none;
            color: #333;
        }
        
        ul {
            list-style: none;
        }
        
        .container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 15px;
        }
        
        /* 头部样式 */
        header {
            background-color: #ff6b00;
            color: white;
            padding: 15px 0;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .header-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            display: flex;
            align-items: center;
        }
        
        .logo-img {
            width: 70px;
            height: 50px;
            margin-right: 10px;
            background-color: white;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 24px;
            color: #ff6b00;
        }
        
        .logo-text {
            font-size: 24px;
            font-weight: bold;
        }
        
        .logo-text span {
            font-size: 16px;
            margin-left: 10px;
            opacity: 0.8;
        }
        
        .user-actions {
            display: flex;
            gap: 15px;
        }
        
        .nav-button {
            background-color: rgba(255,255,255,0.2);
            color: white;
            padding: 10px 20px;
            border-radius: 30px;
            font-size: 16px;
            font-weight: bold;
            transition: all 0.3s;
            white-space: nowrap;
        }
        
        .nav-button:hover {
            background-color: rgba(255,255,255,0.3);
            transform: translateY(-2px);
        }
        
        /* 字母导航 */
        .letter-nav {
            background-color: white;
            padding: 10px 0;
            position: sticky;
            top: 80px;
            z-index: 90;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .letter-nav-container {
            display: flex;
            overflow-x: auto;
            white-space: nowrap;
            -webkit-overflow-scrolling: touch;
            padding: 0 15px;
            justify-content: center;
        }
        
        .letter-nav-container::-webkit-scrollbar {
            display: none;
        }
        
        .letter-link {
            padding: 5px 12px;
            font-size: 16px;
            color: #666;
            border-radius: 15px;
            margin-right: 5px;
        }
        
        .letter-link.active, .letter-link:hover {
            background-color: #ff6b00;
            color: white;
        }
        
        /* 城市列表 */
        .city-list-container {
            padding: 20px 0;
        }
        
        .city-section {
            background-color: white;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .city-letter {
            background-color: #f5f5f5;
            padding: 10px 15px;
            font-size: 18px;
            font-weight: bold;
            color: #ff6b00;
            border-bottom: 1px solid #eee;
        }
        
        .city-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 1px;
            background-color: #eee;
        }
        
        .city-item {
            background-color: white;
            padding: 15px;
            text-align: center;
            transition: all 0.3s;
        }
        
        .city-item:hover {
            background-color: #fff8f5;
            color: #ff6b00;
        }
        
        .hot-city {
            color: #ff6b00;
            font-weight: bold;
        }
        
        /* 热门城市 */
        .hot-cities {
            background-color: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            position: relative;
        }
        
        .section-title {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .more-cities {
            font-size: 14px;
            color: #ff6b00;
            font-weight: normal;
        }
        
        .hot-city-grid {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 15px;
        }
        
        .hot-city-item {
            background-color: #fff8f5;
            padding: 15px;
            text-align: center;
            border-radius: 5px;
            color: #ff6b00;
            font-weight: bold;
            transition: all 0.3s;
        }
        
        .hot-city-item:hover {
            background-color: #ff6b00;
            color: white;
            transform: translateY(-3px);
        }
        
        /* 元宇宙特色区块 */
        .metaverse-features {
            background-color: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .feature-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }
        
        .feature-card {
            border: 1px solid #eee;
            border-radius: 8px;
            padding: 20px;
            transition: all 0.3s;
        }
        
        .feature-card:hover {
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transform: translateY(-5px);
        }
        
        .feature-icon {
            font-size: 30px;
            color: #ff6b00;
            margin-bottom: 15px;
        }
        
        .feature-title {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 10px;
            color: #333;
        }
        
        .feature-desc {
            font-size: 14px;
            color: #666;
        }
        
        /* DAO社区区块 */
        .dao-community {
            background-color: #1a1a1a;
            color: white;
            border-radius: 8px;
            padding: 30px;
            margin-bottom: 20px;
        }
        
        .dao-title {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 15px;
            color: #ff6b00;
        }
        
        .dao-content {
            display: flex;
            align-items: center;
        }
        
        .dao-text {
            flex: 1;
            padding-right: 20px;
        }
        
        .dao-image {
            width: 200px;
            height: 150px;
            background-color: #333;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #ff6b00;
            font-weight: bold;
        }
        
        .dao-button {
            display: inline-block;
            background-color: #ff6b00;
            color: white;
            padding: 10px 20px;
            border-radius: 5px;
            margin-top: 15px;
            transition: all 0.3s;
        }
        
        .dao-button:hover {
            background-color: #e05d00;
        }
        
        /* 底部 */
        footer {
            background-color: #333;
            color: #999;
            padding: 30px 0;
            font-size: 14px;
        }
        
        .footer-container {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 30px;
        }
        
        .footer-column h3 {
            color: white;
            font-size: 16px;
            margin-bottom: 15px;
        }
        
        .footer-column ul li {
            margin-bottom: 10px;
        }
        
        .footer-column a {
            color: #999;
            transition: color 0.3s;
        }
        
        .footer-column a:hover {
            color: white;
        }
        
        .copyright {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #444;
            font-size: 12px;
        }
        
        /* 移动端适配 */
        @media (max-width: 992px) {
            .city-grid {
                grid-template-columns: repeat(4, 1fr);
            }
            
            .hot-city-grid {
                grid-template-columns: repeat(4, 1fr);
            }
            
            .feature-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .footer-container {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .dao-content {
                flex-direction: column;
            }
            
            .dao-text {
                padding-right: 0;
                margin-bottom: 20px;
            }
            
            .dao-image {
                width: 100%;
            }
        }
        
        @media (max-width: 768px) {
            .header-container {
                flex-direction: column;
                gap: 15px;
            }
            
            .logo {
                margin-bottom: 0;
            }
            
            .user-actions {
                width: 100%;
                justify-content: center;
                flex-wrap: wrap;
            }
            
            .city-grid {
                grid-template-columns: repeat(3, 1fr);
            }
            
            .hot-city-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }
        
        @media (max-width: 576px) {
            .city-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .hot-city-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .feature-grid {
                grid-template-columns: 1fr;
            }
            
            .footer-container {
                grid-template-columns: 1fr;
            }
            
            .logo-img {
                width: 40px;
                height: 40px;
                font-size: 20px;
            }
            
            .logo-text {
                font-size: 20px;
            }
            
            .logo-text span {
                font-size: 14px;
            }
            
            .nav-button {
                padding: 8px 15px;
                font-size: 14px;
            }
        }
    </style>
	<style>
        /* 在原有样式基础上新增以下样式 */
        .domain-sale {
            background-color: #fff8e6;
            border: 1px solid #ffd700;
            border-radius: 8px;
            padding: 15px;
            margin: 20px 0;
            text-align: center;
            position: relative;
        }
        
        .domain-sale-text {
            font-size: 16px;
            color: #ff6b00;
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .domain-sale-link {
            display: inline-block;
            background-color: #ff6b00;
            color: white;
            padding: 8px 20px;
            border-radius: 30px;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .domain-sale-link:hover {
            background-color: #e05d00;
            transform: translateY(-2px);
        }
        
        .domain-sale-english {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
		
		/* 新增：优惠悬浮窗口样式 */
        .promotion-floating {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 300px;
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            padding: 15px;
            z-index: 999;
            transform: translateY(20px);
            opacity: 0;
            animation: floatIn 0.5s forwards;
            border: 2px solid #ff6b00;
        }

        @keyframes floatIn {
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .promotion-header {
            color: #ff6b00;
            font-weight: bold;
            font-size: 18px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
        }

        .promotion-header i {
            margin-right: 8px;
            font-size: 20px;
        }

        .promotion-content {
            font-size: 14px;
            color: #666;
            margin-bottom: 15px;
        }

        .promotion-qrcode {
            width: 100%;
            text-align: center;
            margin: 10px 0;
        }

        .promotion-qrcode img {
            width: 150px;
            height: 150px;
            border: 1px solid #eee;
        }

        .promotion-close {
            position: absolute;
            top: 10px;
            right: 10px;
            width: 20px;
            height: 20px;
            cursor: pointer;
            color: #999;
            font-size: 16px;
            text-align: center;
            line-height: 20px;
        }

        .promotion-close:hover {
            color: #ff6b00;
        }

        @media (max-width: 768px) {
            .promotion-floating {
                width: 250px;
                right: 15px;
                bottom: 15px;
            }
        }
		
		/* 新增：城市定位提示条 */
        .city-location-bar {
            background-color: #ff6b00;
            color: white;
            padding: 8px 0;
            text-align: center;
            font-size: 14px;
        }
        
        .city-location-bar a {
            color: white;
            text-decoration: underline;
            margin-left: 5px;
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
				<a href="https://nft.58.tl/" class="nav-button">NFT交易</a>
				<a href="https://v.58.tl/" class="nav-button">互访圈</a>
				<a href="/rankings/rankings.html" class="nav-button">排行榜</a>
                <a href="top200city.php" class="nav-button">TOP200城市</a>
				<a href="hongbao.php" class="nav-button">红包时间表</a>
                <a href="https://www.blockcity.pub/pages/user/user/?iclc" class="nav-button">我的区块</a>
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
                    <a href="/block/city.php?name=<?= urlencode($c['pinyin']) ?>" class="hot-city-item"><?= htmlspecialchars($c['name']) ?></a>
                <?php endforeach; ?>
            </div>
        </section>
        
        <!-- 城市列表 -->
        <div class="city-list-container">
            <?php foreach ($letters as $letter): 
                if (empty($citiesByLetter[$letter])) continue;
            ?>
            <section id="<?= $letter ?>" class="city-section">
                <div class="city-letter"><?= $letter ?></div>
                <div class="city-grid">
                    <?php foreach ($citiesByLetter[$letter] as $c): 
                        if (strtoupper(substr($c['pinyin'], 0, 1)) !== $letter) continue;
                    ?>
                        <a href="/block/city.php?name=<?= urlencode($c['pinyin']) ?>" 
                           class="city-item <?= $c['is_hot'] ? 'hot-city' : '' ?>">
                            <?= htmlspecialchars($c['name']) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endforeach; ?>
        </div>
        
        
        
        
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
    <footer>
		<!-- 原有footer内容前添加 -->
		<!--
        <div class="container">
            <div class="domain-sale" style="margin-bottom: 30px;">
                <div class="domain-sale-text">💎 本网站域名诚意出售 | Domain Name For Sale</div>
                <a href="https://domainbatch.com/name/58.tl" class="domain-sale-link">联系购买 | Contact Now</a>
                <div class="domain-sale-english">Premium domain "58.tl" available for blockchain/metaverse projects</div>
            </div>
        </div>
		-->
		
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
                        <li><a href="https://www.blockcity.pub/pages/city/learn/?iclc=1">元宇宙入门</a></li>
                        <li><a href="https://mp.weixin.qq.com/s/KWoNXzeldh3GxI9uS2O80g">用户答疑</a></li>
                        <li><a href="https://www.blockcity.vip/pages/index/help/?iclc=1">常见问题</a></li>
                    </ul>
                </div>
                <div class="footer-column">
                    <h3>商家服务</h3>
                    <ul>
                        <li><a href="news.php">区块新闻</a></li>
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
            getCityInfo();
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