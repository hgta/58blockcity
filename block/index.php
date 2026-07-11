<?php
require_once '../config/database.php';

// 统计数据
$totalCities = $pdo->query("SELECT COUNT(*) FROM cities")->fetchColumn();
$totalSold = $pdo->query("SELECT COUNT(*) FROM blocks WHERE status='sold'")->fetchColumn();
$totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();

// 热门城市
$hotCities = [];
$stmt = $pdo->query("SELECT name, pinyin, rank, resident_count FROM cities WHERE is_hot=1 ORDER BY rank LIMIT 8");
$hotCities = $stmt->fetchAll();

// 每个热门城市的 A 区区块统计数据（取前12行12列的样本）
// block_number 格式: 0305 = col=03, row=05
foreach ($hotCities as &$c) {
    $stmt = $pdo->prepare("
        SELECT b.block_number, b.status 
        FROM blocks b JOIN cities c ON b.city_id = c.id 
        WHERE c.id = (SELECT id FROM cities WHERE name = ?) AND b.zone = 'A'
    ");
    $stmt->execute([$c['name']]);
    $allBlocks = $stmt->fetchAll();
    $grid = [];
    $soldCount = 0;
    $totalCount = 0;
    foreach ($allBlocks as $b) {
        $col = intval(substr($b['block_number'], 0, 2));
        $row = intval(substr($b['block_number'], 2, 2));
        if ($row >= 1 && $row <= 12 && $col >= 1 && $col <= 12) {
            $grid[$row][$col] = $b['status'];
            if ($b['status'] === 'sold') $soldCount++;
            $totalCount++;
        }
    }
    $c['grid'] = $grid;
    $c['sold_count'] = $soldCount;
    $c['total'] = $totalCount;
    $c['percent'] = $totalCount > 0 ? round($soldCount / $totalCount * 100) : 0;
}
unset($c);

// 最近认领
$stmt = $pdo->query("SELECT b.block_number, b.zone, c.name AS city_name, u.username, b.updated_at FROM blocks b JOIN users u ON b.owner_id = u.id JOIN cities c ON b.city_id = c.id WHERE b.status = 'sold' ORDER BY b.updated_at DESC LIMIT 8");
$recentClaims = $stmt->fetchAll();

// A-Z 城市列表（折叠用）
$citiesByLetter = [];
$otherCities = []; // 拼音异常的城市放这里
$stmt = $pdo->query("SELECT name, pinyin, rank FROM cities ORDER BY pinyin");
$allCities = $stmt->fetchAll();
$letters = range('A', 'Z');
foreach ($allCities as $city) {
    $fl = strtoupper(substr($city['pinyin'] ?? '', 0, 1));
    if ($fl && in_array($fl, $letters)) {
        $citiesByLetter[$fl][] = $city;
    } else {
        $otherCities[] = $city;
    }
}

require_once 'includes/header.php';
?>

<style>
.block-hero{text-align:center;padding:30px 0 20px}
.block-hero h1{font-size:28px;font-weight:800;margin-bottom:6px}
.block-hero p{color:#888;font-size:15px;margin-bottom:20px}
.block-stats{display:flex;justify-content:center;gap:30px;margin-bottom:16px;flex-wrap:wrap}
.block-stat{text-align:center}
.block-stat .val{font-size:26px;font-weight:800;color:#ff6b00}
.block-stat .lbl{font-size:13px;color:#999}

.block-search{display:flex;justify-content:center;margin-bottom:10px}
.block-search input{padding:12px 16px;border:2px solid #e0e0e0;border-radius:8px 0 0 8px;font-size:15px;width:300px;outline:none;transition:.2s}
.block-search input:focus{border-color:#ff6b00}
.block-search button{padding:12px 24px;background:#ff6b00;color:#fff;border:none;border-radius:0 8px 8px 0;font-size:15px;cursor:pointer;font-weight:700}
.block-hot-links{display:flex;justify-content:center;gap:10px;flex-wrap:wrap;margin-bottom:30px}
.block-hot-links a{display:inline-block;padding:5px 14px;background:#fff;border:1px solid #ddd;border-radius:20px;font-size:13px;color:#666;text-decoration:none;transition:.15s}
.block-hot-links a:hover{border-color:#ff6b00;color:#ff6b00}

.block-grids{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:16px;margin-bottom:30px}
.block-city-card{background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 10px rgba(0,0,0,0.04);transition:.2s}
.block-city-card:hover{transform:translateY(-2px);box-shadow:0 6px 20px rgba(0,0,0,0.08)}
.block-city-card-inner{padding:16px}
.block-city-name{font-size:16px;font-weight:700;margin-bottom:12px;display:flex;align-items:center;justify-content:space-between}
.block-city-name a{color:#333;text-decoration:none}
.block-city-name a:hover{color:#ff6b00}

.block-mini-grid{width:100%;border-spacing:1px;background:#ccc;border-radius:2px;overflow:hidden}
.block-mini-grid td{padding:4px 2px;text-align:center;font-size:9px;color:#999;border-radius:1px;line-height:1.1;height:26px;width:8.3%}
.block-mini-grid td.available{background:#fff}
.block-mini-grid td.sold{background:#ff6b00;color:#fff;font-weight:700}
.block-mini-grid td.reserved{background:#fff3e0;color:#e65100}

.block-city-meta{display:flex;justify-content:space-between;align-items:center;margin-top:10px;font-size:12px;color:#888}
.block-city-meta .progress{flex:1;margin:0 10px;height:4px;background:#eee;border-radius:2px;overflow:hidden}
.block-city-meta .progress-bar{height:100%;background:linear-gradient(90deg,#ff6b00,#ff9500);border-radius:2px}
.block-city-btn{display:block;text-align:center;padding:8px;margin-top:10px;background:#fff9f0;color:#ff6b00;border-radius:6px;font-size:13px;font-weight:600;text-decoration:none;transition:.15s}
.block-city-btn:hover{background:#ff6b00;color:#fff}

.block-recent{background:#fff;border-radius:12px;padding:20px;box-shadow:0 2px 10px rgba(0,0,0,0.04);margin-bottom:30px}
.block-recent h3{font-size:16px;font-weight:700;margin-bottom:14px}
.block-recent-list{display:flex;flex-wrap:wrap;gap:8px 20px}
.block-recent-item{font-size:13px;color:#888;white-space:nowrap}
.block-recent-item strong{color:#333}

.block-features{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:14px;margin-bottom:30px}
.block-feature{background:#fff;border-radius:12px;padding:24px;text-align:center;box-shadow:0 2px 10px rgba(0,0,0,0.04);transition:.2s}
.block-feature:hover{transform:translateY(-2px)}
.block-feature .icon{font-size:32px;margin-bottom:10px;display:block}
.block-feature h4{font-size:15px;margin-bottom:4px;color:#333}
.block-feature p{font-size:13px;color:#888}

.block-all-cities{background:#fff;border-radius:12px;padding:20px;box-shadow:0 2px 10px rgba(0,0,0,0.04)}
.block-all-cities h3{font-size:16px;font-weight:700;margin-bottom:14px}
.block-letter-nav{display:flex;flex-wrap:wrap;gap:4px;margin-bottom:16px}
.block-letter-nav a{display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:4px;font-size:12px;font-weight:600;color:#888;text-decoration:none;border:1px solid #e0e0e0;transition:.15s}
.block-letter-nav a:hover,.block-letter-nav a.active{background:#ff6b00;color:#fff;border-color:#ff6b00}
.block-city-group{margin-bottom:12px}
.block-city-group h4{font-size:14px;color:#ff6b00;margin-bottom:6px;font-weight:700}
.block-city-group-inner{display:flex;flex-wrap:wrap;gap:4px 14px}
.block-city-group-inner a{font-size:13px;color:#666;text-decoration:none;transition:.15s}
.block-city-group-inner a:hover{color:#ff6b00}

@media(max-width:768px){
    .block-hero h1{font-size:22px}
    .block-grids{grid-template-columns:repeat(2,1fr);gap:10px}
    .block-search input{width:200px}
}
@media(max-width:480px){
    .block-grids{grid-template-columns:1fr}
}
</style>

<div class="block-hero">
    <h1>🔲 BlockCity 区块交易市场</h1>
    <p>200+城市 · 9区布局 · 实时可视 · 一键认领</p>
    <div class="block-stats">
        <div class="block-stat"><div class="val"><?= number_format($totalCities) ?></div><div class="lbl">城市数</div></div>
        <div class="block-stat"><div class="val"><?= number_format($totalSold) ?></div><div class="lbl">已售区块</div></div>
        <div class="block-stat"><div class="val"><?= number_format($totalUsers) ?></div><div class="lbl">注册用户</div></div>
    </div>
    <form class="block-search" action="city.php" method="get">
        <input type="text" name="name" placeholder="搜索城市名称或拼音...">
        <button type="submit">🔍 查看区块地图</button>
    </form>
    <div class="block-hot-links">
        <a href="city.php?name=beijing">北京</a>
        <a href="city.php?name=shanghai">上海</a>
        <a href="city.php?name=shenzhen">深圳</a>
        <a href="city.php?name=hangzhou">杭州</a>
        <a href="city.php?name=guangzhou">广州</a>
        <a href="city.php?name=chengdu">成都</a>
        <a href="city.php?name=nanjing">南京</a>
        <a href="city.php?name=wuhan">武汉</a>
    </div>
</div>

<!-- 热门城市区块实况 -->
<div class="block-grids">
    <?php foreach ($hotCities as $c): ?>
    <div class="block-city-card">
        <div class="block-city-card-inner">
            <div class="block-city-name">
                <a href="city.php?name=<?= $c['pinyin'] ?>"><?= htmlspecialchars($c['name']) ?> A区</a>
                <span style="font-size:12px;color:#ff6b00;">#<?= $c['rank'] ?></span>
            </div>
            <table class="block-mini-grid">
                <?php for ($r = 1; $r <= 12; $r++): ?>
                <tr>
                <?php for ($col = 1; $col <= 12; $col++): 
                    $status = $c['grid'][$r][$col] ?? 'available';
                    $bn = str_pad($col, 2, '0', STR_PAD_LEFT) . str_pad($r, 2, '0', STR_PAD_LEFT);
                ?>
                    <td class="<?= $status ?>"><?= $bn ?></td>
                <?php endfor; ?>
                </tr>
                <?php endfor; ?>
            </table>
            <div class="block-city-meta">
                <span>已售 <?= $c['sold_count'] ?>/<?= $c['total'] ?></span>
                <div class="progress"><div class="progress-bar" style="width:<?= $c['percent'] ?>%"></div></div>
                <span><strong><?= $c['percent'] ?>%</strong></span>
            </div>
            <a href="city.php?name=<?= $c['pinyin'] ?>" class="block-city-btn">查看 101×99 完整区块地图 →</a>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- 最近动态 -->
<?php if ($recentClaims): ?>
<div class="block-recent">
    <h3>📢 最近认领动态</h3>
    <div class="block-recent-list">
    <?php foreach ($recentClaims as $r): ?>
        <span class="block-recent-item">
            <strong><?= htmlspecialchars($r['username']) ?></strong>
            认领了 <?= htmlspecialchars($r['city_name']) ?> <?= $r['zone'] ?>区 <?= $r['block_number'] ?>
        </span>
    <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- 核心功能 -->
<div class="block-features">
    <div class="block-feature">
        <span class="icon">🗺️</span>
        <h4>九区全景</h4>
        <p>A-H 区 + Z区，3×3 网格全览</p>
    </div>
    <div class="block-feature">
        <span class="icon">🔲</span>
        <h4>合并查看</h4>
        <p>跨区相邻区块 2×2 3×3 合并</p>
    </div>
    <div class="block-feature">
        <span class="icon">💰</span>
        <h4>透明定价</h4>
        <p>每个区块实时价格，按区定价</p>
    </div>
    <div class="block-feature">
        <span class="icon">📱</span>
        <h4>一键认领</h4>
        <p>登录后选中区块直接认领</p>
    </div>
</div>

<!-- A-Z 城市列表 -->
<div class="block-all-cities">
    <h3>🏙 全部城市 A-Z</h3>
    <div class="block-letter-nav">
        <?php foreach ($letters as $l): ?>
        <a href="#city-<?= $l ?>" onclick="document.getElementById('city-<?= $l ?>').scrollIntoView()"><?= $l ?></a>
        <?php endforeach; ?>
    </div>
    <?php foreach ($citiesByLetter as $letter => $cities): ?>
    <div class="block-city-group" id="city-<?= $letter ?>">
        <h4><?= $letter ?></h4>
        <div class="block-city-group-inner">
            <?php foreach ($cities as $city): ?>
            <a href="city.php?name=<?= $city['pinyin'] ?>"><?= htmlspecialchars($city['name']) ?></a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>
    <?php if (!empty($otherCities)): ?>
    <div class="block-city-group">
        <h4>其他</h4>
        <div class="block-city-group-inner">
            <?php foreach ($otherCities as $city): ?>
            <a href="city.php?name=<?= $city['pinyin'] ?>"><?= htmlspecialchars($city['name']) ?></a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
