<?php
require_once '../config/database.php';
require_once '../classes/City.php';
require_once '../classes/User.php';

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
$cityObj = new City($pdo);

// 获取热门城市数据
$hot_cities = $cityObj->getHotCitiesList(18);

$cities_by_letter = $cityObj->getCitiesByLetter();
$letters = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'J', 'K', 'L', 'M', 'N', 'P', 'Q', 'R', 'S', 'T', 'W', 'X', 'Y', 'Z'];

// 平台实时统计
$totalCities = $cityObj->getTotalCitiesCount();
$totalUsers = (new User($pdo))->getUserCount();
$stmtActivated = $pdo->query("SELECT COUNT(*) FROM blocks WHERE status = 'sold'");
$totalActivated = $stmtActivated ? (int)$stmtActivated->fetchColumn() : 0;
?>

<?php require_once 'includes/header.php'; ?>

    <!-- 平台实时统计面板 -->
    <section class="platform-stats">
        <div class="container">
            <div class="stats-grid">
                <div class="stat-card-block">
                    <div class="stat-icon-block">🏙️</div>
                    <div class="stat-info-block">
                        <div class="stat-value-block" data-count="<?= $totalCities ?>">0</div>
                        <div class="stat-label-block">城市总数</div>
                    </div>
                </div>
                <div class="stat-card-block">
                    <div class="stat-icon-block">🧱</div>
                    <div class="stat-info-block">
                        <div class="stat-value-block" data-count="<?= $totalActivated ?>">0</div>
                        <div class="stat-label-block">已激活区块</div>
                    </div>
                </div>
                <div class="stat-card-block">
                    <div class="stat-icon-block">👥</div>
                    <div class="stat-info-block">
                        <div class="stat-value-block" data-count="<?= $totalUsers ?>">0</div>
                        <div class="stat-label-block">注册用户</div>
                    </div>
                </div>
                <div class="stat-card-block">
                    <div class="stat-icon-block">🔗</div>
                    <div class="stat-info-block">
                        <div class="stat-value-block" data-count="<?= number_format($totalActivated / max($totalCities,1), 1) ?>">0</div>
                        <div class="stat-label-block">平均激活/城</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- 城市搜索框 -->
    <section class="city-search-section">
        <div class="container">
            <div class="city-search-box">
                <i class="fas fa-search"></i>
                <input type="text" id="citySearchInput" placeholder="输入城市名或拼音快速定位..." autocomplete="off">
                <span id="citySearchClear" style="display:none;cursor:pointer;color:#999;">✕</span>
            </div>
            <div id="citySearchEmpty" class="city-search-empty" style="display:none;">未找到匹配的城市</div>
        </div>
    </section>

    <!-- 字母导航 -->
    <nav class="letter-nav">
        <div class="letter-nav-container">
            <?php foreach ($letters as $letter): ?>
                <a href="#<?= $letter ?>" class="letter-link" data-letter="<?= $letter ?>"><?= $letter ?></a>
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
        // 页面加载时获取城市信息
        window.onload = function() {
            getCityInfo();
        };

        // 平台统计数字动画
        (function() {
            function animateValue(el, target, duration) {
                var start = 0;
                var startTime = null;
                function step(timestamp) {
                    if (!startTime) startTime = timestamp;
                    var progress = Math.min((timestamp - startTime) / duration, 1);
                    var value = Math.floor(progress * target);
                    el.textContent = value.toLocaleString();
                    if (progress < 1) {
                        requestAnimationFrame(step);
                    } else {
                        el.textContent = target.toLocaleString();
                    }
                }
                requestAnimationFrame(step);
            }
            document.querySelectorAll('.stat-value-block[data-count]').forEach(function(el) {
                var target = parseFloat(el.getAttribute('data-count').toString().replace(/,/g, ''));
                if (!isNaN(target)) animateValue(el, target, 1200);
            });
        })();

        // 城市搜索
        (function() {
            var input = document.getElementById('citySearchInput');
            var clearBtn = document.getElementById('citySearchClear');
            var emptyMsg = document.getElementById('citySearchEmpty');
            if (!input) return;

            var allCities = [];
            document.querySelectorAll('.city-item').forEach(function(item) {
                var name = item.textContent.trim();
                var pinyin = item.getAttribute('href').replace('/city.php?name=', '');
                allCities.push({ el: item, name: name, pinyin: pinyin, letter: item.closest('.city-section').id });
            });

            function doSearch() {
                var query = input.value.trim().toLowerCase();
                clearBtn.style.display = query ? 'inline' : 'none';
                var hasMatch = false;
                var matchedLetters = {};

                allCities.forEach(function(c) {
                    var match = c.name.indexOf(query) !== -1 || c.pinyin.indexOf(query) !== -1;
                    c.el.style.display = match ? '' : 'none';
                    c.el.classList.toggle('highlighted', match && query.length >= 1);
                    if (match) {
                        hasMatch = true;
                        matchedLetters[c.letter] = true;
                    }
                });

                document.querySelectorAll('.city-section').forEach(function(sec) {
                    sec.classList.toggle('hidden', query.length > 0 && !matchedLetters[sec.id]);
                });

                document.querySelectorAll('.letter-link').forEach(function(link) {
                    link.classList.toggle('active', !!matchedLetters[link.getAttribute('data-letter')]);
                });

                emptyMsg.style.display = (query.length > 0 && !hasMatch) ? 'block' : 'none';

                if (hasMatch && query.length > 0) {
                    var first = document.querySelector('.city-item.highlighted');
                    if (first) first.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            }

            input.addEventListener('input', doSearch);
            clearBtn.addEventListener('click', function() {
                input.value = '';
                doSearch();
                input.focus();
            });
        })();
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