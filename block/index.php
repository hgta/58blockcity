<?php
require_once '../config/database.php';
require_once '../classes/City.php';

/*
// 获取热门城市数据
$hot_cities = [];
$hot_cities_query = "SELECT * FROM cities WHERE is_hot = 1 ORDER BY rank LIMIT 18";
$hot_result = $pdo->query($hot_cities_query);
if ($hot_result && $hot_result->num_rows > 0) {
    while ($row = $hot_result->fetch_assoc()) {
        $hot_cities[] = $row;
    }
}*/


// 初始化城市类
$city = new City($pdo);

// 获取热门城市数据
$hot_cities = $city->getHotCitiesList(18);

$cities_by_letter = $city->getCitiesByLetter();
$letters = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'J', 'K', 'L', 'M', 'N', 'P', 'Q', 'R', 'S', 'T', 'W', 'X', 'Y', 'Z']; 
?>

<?php require_once 'includes/header.php'; ?>
    
    <!-- 城市定位提示条 -->
    <div class="city-location-bar" id="cityLocationBar">
        欢迎您,来自于<span id="userCity">未知城市</span>的朋友，<a href="https://www.blockcity.pub/?iclc" id="cityLink">点击进入您所在城市的区块</a>
    </div>
    
    <!-- 字母导航 -->
    <nav class="letter-nav">
        <div class="letter-nav-container">
            <?php foreach ($letters as $letter): ?>
                <a href="#<?= $letter ?>" class="letter-link"><?= $letter ?></a>
            <?php endforeach; ?>
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
                <?php foreach ($hot_cities as $city): ?>
                    <a href="/city.php?name=<?= $city['pinyin'] ?>" class="hot-city-item"><?= $city['name'] ?></a>
                <?php endforeach; ?>
            </div>
        </section>
        
        <!-- 城市列表 -->
        <div class="city-list-container">
            <?php foreach ($cities_by_letter as $letter => $cities): ?>
                <section id="<?= $letter ?>" class="city-section">
                    <div class="city-letter"><?= $letter ?></div>
                    <div class="city-grid">
                        <?php foreach ($cities as $city): ?>
                            <a href="/city.php?name=<?= $city['pinyin'] ?>" class="city-item <?= $city['is_hot'] ? 'hot-city' : '' ?>">
                                <?= $city['name'] ?>
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
    
    <?php include 'includes/footer.php'; ?>
        

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