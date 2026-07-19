<?php
require_once '../config/database.php';
require_once 'includes/auth.php';
require_once '../classes/Block.php';
require_once '../classes/City.php';
require_once '../classes/BlockListing.php';

$block = new Block($pdo);
$city = new City($pdo);
$listing = new BlockListing($pdo);

// 筛选参数
$filterCity = trim($_GET['city'] ?? '');
$filterZone = trim($_GET['zone'] ?? '');
$filterCurrency = trim($_GET['currency'] ?? '');
$filterMinPrice = isset($_GET['min_price']) && $_GET['min_price'] !== '' ? floatval($_GET['min_price']) : null;
$filterMaxPrice = isset($_GET['max_price']) && $_GET['max_price'] !== '' ? floatval($_GET['max_price']) : null;

$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$where = ["l.status = 'listed'"];
$params = [];
if ($filterCity) {
    $where[] = "c.name LIKE ?";
    $params[] = "%$filterCity%";
}
if ($filterZone && preg_match('/^[A-HZ]$/', $filterZone)) {
    $where[] = "b.zone = ?";
    $params[] = $filterZone;
}
if ($filterCurrency && in_array($filterCurrency, ['popularity', 'cny'])) {
    $where[] = "l.currency = ?";
    $params[] = $filterCurrency;
}
if ($filterMinPrice !== null && $filterMinPrice >= 0) {
    $where[] = "l.price >= ?";
    $params[] = $filterMinPrice;
}
if ($filterMaxPrice !== null && $filterMaxPrice > 0) {
    $where[] = "l.price <= ?";
    $params[] = $filterMaxPrice;
}
$whereSql = implode(' AND ', $where);

$sql = "SELECT l.*, c.name as city_name, u.username as seller_name,
               b.zone, b.block_number, b.display_type, b.display_image, b.display_text, b.display_color,
               mb.merge_size, mb.merged_blocks as merged_nums
        FROM block_listings l
        LEFT JOIN cities c ON l.city_id = c.id
        LEFT JOIN blocks b ON l.block_id = b.id
        LEFT JOIN users u ON l.seller_id = u.id
        LEFT JOIN merged_blocks mb ON l.merged_block_id = mb.id
        WHERE $whereSql
        ORDER BY l.created_at DESC
        LIMIT ? OFFSET ?";
$stmt = $pdo->prepare($sql);
for ($i = 0; $i < count($params); $i++) {
    $stmt->bindValue($i + 1, $params[$i]);
}
$stmt->bindValue(count($params) + 1, (int)$perPage, PDO::PARAM_INT);
$stmt->bindValue(count($params) + 2, (int)$offset, PDO::PARAM_INT);
$stmt->execute();
$listings = $stmt->fetchAll();

$countSql = "SELECT COUNT(*) FROM block_listings l LEFT JOIN cities c ON l.city_id = c.id LEFT JOIN blocks b ON l.block_id = b.id LEFT JOIN merged_blocks mb ON l.merged_block_id = mb.id WHERE $whereSql";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$total = $countStmt->fetchColumn();
$totalPages = ceil($total / $perPage);

$hotCities = $city->getHotCitiesList(20);

$skinColors = ['red' => '#ff6060', 'green' => '#35cc2d', 'blue' => '#337be6'];

// 渲染卡片缩略（皮肤）
function renderThumb($l, $skinColors) {
    $dt = $l['display_type'] ?? 'none';
    if ($dt === 'image' && !empty($l['display_image'])) {
        return '<div class="card-skin"><img src="/' . htmlspecialchars($l['display_image']) . '" alt="" onerror="this.style.display=\'none\'"></div>';
    }
    if ($dt === 'text' && !empty($l['display_text'])) {
        $c = $skinColors[$l['display_color'] ?? 'blue'] ?? '#337be6';
        return '<div class="card-skin" style="background:' . $c . ';display:flex;align-items:center;justify-content:center;color:#fff;font-weight:bold;font-size:14px;text-align:center;padding:6px;">' . htmlspecialchars($l['display_text']) . '</div>';
    }
    return '<div class="card-skin card-skin-default"><i class="fas fa-map-marker-alt"></i></div>';
}
function listingTitle($l) {
    if (!empty($l['merged_block_id'])) {
        $m = $l['merged_size'] ?? '1x1';
        $min = $l['merged_nums'] ? min(array_map('trim', explode(',', $l['merged_nums']))) : '';
        return $l['zone'] . '区 · ' . $m . ' 合并区块' . ($min ? '（' . $min . '）' : '');
    }
    return $l['zone'] . '区 #' . $l['block_number'];
}
?>
<?php require_once 'includes/header.php'; ?>

<div class="container page-container">
    <h1 class="page-title"><i class="fas fa-store"></i> 区块出售市场</h1>

    <form class="filter-bar" method="get">
        <div class="filter-group">
            <label>城市</label>
            <select name="city">
                <option value="">全部城市</option>
                <?php foreach ($hotCities as $hc): ?>
                <option value="<?= htmlspecialchars($hc['name']) ?>" <?= $filterCity===$hc['name']?'selected':'' ?>><?= htmlspecialchars($hc['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-group">
            <label>区域</label>
            <select name="zone">
                <option value="">全部区域</option>
                <?php foreach (['A','B','C','D','E','F','G','H','Z'] as $z): ?>
                <option value="<?= $z ?>" <?= $filterZone===$z?'selected':'' ?>><?= $z ?>区</option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-group">
            <label>货币</label>
            <select name="currency">
                <option value="">全部</option>
                <option value="cny" <?= $filterCurrency==='cny'?'selected':'' ?>>人民币 ¥</option>
                <option value="popularity" <?= $filterCurrency==='popularity'?'selected':'' ?>>人气值 Ⓟ</option>
            </select>
        </div>
        <div class="filter-group">
            <label>最低价格</label>
            <input type="number" name="min_price" value="<?= $filterMinPrice !== null ? $filterMinPrice : '' ?>" placeholder="¥" min="0">
        </div>
        <div class="filter-group">
            <label>最高价格</label>
            <input type="number" name="max_price" value="<?= $filterMaxPrice !== null ? $filterMaxPrice : '' ?>" placeholder="¥" min="0">
        </div>
        <div class="filter-actions">
            <button type="submit" class="btn-filter">筛选</button>
            <a href="sale_list.php" class="btn-reset">重置</a>
        </div>
    </form>

    <div class="result-count">共 <?= number_format($total) ?> 个在售区块</div>

    <?php if (empty($listings)): ?>
        <div class="empty-state">
            <i class="fas fa-box-open"></i>
            <p>暂无在售区块</p>
            <a href="city.php?name=beijing" class="btn-primary">浏览区块城市</a>
        </div>
    <?php else: ?>
        <div class="block-grid">
            <?php foreach ($listings as $l): ?>
                <a href="block/buy.php?listing=<?= $l['id'] ?>" class="block-card">
                    <?= renderThumb($l, $skinColors) ?>
                    <h3>
                        <span class="zone-tag"><?= htmlspecialchars(listingTitle($l)) ?></span>
                    </h3>
                    <div class="card-meta">
                        <span class="owner">卖家: <?= htmlspecialchars($l['seller_name'] ?? '匿名') ?></span>
                    </div>
                    <div class="price" style="color:<?= $l['currency']==='popularity' ? '#e74c3c' : '#ff6b00' ?>">
                        <?= $l['currency']==='popularity' ? 'Ⓟ ' : '¥ ' ?><?= number_format($l['price'], 2) ?>
                    </div>
                    <div class="city"><?= htmlspecialchars($l['city_name'] ?? '') ?></div>
                </a>
            <?php endforeach; ?>
        </div>

        <?php if ($totalPages > 1): ?>
        <div class="pagination-clean">
            <?php if ($page > 1): ?>
                <a href="?page=<?= $page-1 ?>&city=<?= urlencode($filterCity) ?>&zone=<?= $filterZone ?>&currency=<?= $filterCurrency ?>&min_price=<?= $filterMinPrice ?? '' ?>&max_price=<?= $filterMaxPrice ?? '' ?>" class="page-link">上一页</a>
            <?php endif; ?>
            <?php for ($i = max(1, $page-2); $i <= min($totalPages, $page+2); $i++): ?>
                <a href="?page=<?= $i ?>&city=<?= urlencode($filterCity) ?>&zone=<?= $filterZone ?>&currency=<?= $filterCurrency ?>&min_price=<?= $filterMinPrice ?? '' ?>&max_price=<?= $filterMaxPrice ?? '' ?>" class="page-link <?= $i==$page?'active':'' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($page < $totalPages): ?>
                <a href="?page=<?= $page+1 ?>&city=<?= urlencode($filterCity) ?>&zone=<?= $filterZone ?>&currency=<?= $filterCurrency ?>&min_price=<?= $filterMinPrice ?? '' ?>&max_price=<?= $filterMaxPrice ?? '' ?>" class="page-link">下一页</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<style>
.card-skin { height:90px; border-radius:8px 8px 0 0; overflow:hidden; background:#f3f4f6; display:flex; align-items:center; justify-content:center; color:#bbb; font-size:24px; }
.card-skin img { width:100%; height:100%; object-fit:cover; }
.card-skin-default { background:linear-gradient(135deg,#eef1f5,#dfe4ea); }
</style>

<?php require_once 'includes/footer.php'; ?>
