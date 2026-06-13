<?php
require_once '../config/database.php';
require_once 'includes/auth.php';
require_once '../classes/Block.php';
require_once '../classes/City.php';

$block = new Block($pdo);
$city = new City($pdo);

// 筛选参数
$filterCity = $_GET['city'] ?? '';
$filterZone = $_GET['zone'] ?? '';
$filterMinPrice = isset($_GET['min_price']) ? floatval($_GET['min_price']) : null;
$filterMaxPrice = isset($_GET['max_price']) ? floatval($_GET['max_price']) : null;

$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

// 构建查询
$where = ["b.status = 'sold'"];
$params = [];
if ($filterCity) {
    $where[] = "c.name LIKE ?";
    $params[] = "%$filterCity%";
}
if ($filterZone && preg_match('/^[A-HZ]$/', $filterZone)) {
    $where[] = "b.zone = ?";
    $params[] = $filterZone;
}
if ($filterMinPrice !== null && $filterMinPrice >= 0) {
    $where[] = "b.price >= ?";
    $params[] = $filterMinPrice;
}
if ($filterMaxPrice !== null && $filterMaxPrice > 0) {
    $where[] = "b.price <= ?";
    $params[] = $filterMaxPrice;
}

$whereSql = implode(' AND ', $where);

// 数据查询（使用参数绑定 LIMIT）
$sql = "SELECT b.*, c.name as city_name, u.username as owner_name
    FROM blocks b
    JOIN cities c ON b.city_id = c.id
    LEFT JOIN users u ON b.owner_id = u.id
    WHERE $whereSql
    ORDER BY b.updated_at DESC
    LIMIT ? OFFSET ?";
$stmt = $pdo->prepare($sql);
$stmt->execute(array_merge($params, [$perPage, $offset]));
$blocks = $stmt->fetchAll();

// 总数查询
$countSql = "SELECT COUNT(*) FROM blocks b JOIN cities c ON b.city_id = c.id WHERE $whereSql";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$total = $countStmt->fetchColumn();
$totalPages = ceil($total / $perPage);

// 热门城市列表（用于筛选下拉）
$hotCities = $city->getHotCitiesList(20);
?>
<?php require_once 'includes/header.php'; ?>

<div class="container page-container">
    <h1 class="page-title"><i class="fas fa-tag"></i> 已售区块</h1>

    <!-- 筛选栏 -->
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

    <div class="result-count">共 <?= number_format($total) ?> 条记录</div>

    <?php if (empty($blocks)): ?>
        <div class="empty-state">
            <i class="fas fa-box-open"></i>
            <p>暂无记录</p>
            <a href="city.php?name=beijing" class="btn-primary">浏览区块城市</a>
        </div>
    <?php else: ?>
        <div class="block-grid">
            <?php foreach ($blocks as $b): ?>
                <a href="block/view.php?id=<?= $b['id'] ?>" class="block-card">
                    <h3>
                        <span class="zone-tag"><?= $b['zone'] ?>区</span>
                        <?= htmlspecialchars($b['city_name']) ?> #<?= $b['block_number'] ?>
                    </h3>
                    <div class="card-meta">
                        <span class="owner">拥有者: <?= htmlspecialchars($b['owner_name'] ?? '匿名') ?></span>
                    </div>
                    <div class="price">¥<?= number_format($b['price'] ?? 0, 2) ?></div>
                    <div class="city"><?= htmlspecialchars($b['city_name']) ?></div>
                </a>
            <?php endforeach; ?>
        </div>

        <?php if ($totalPages > 1): ?>
        <div class="pagination-clean">
            <?php if ($page > 1): ?>
                <a href="?page=<?= $page-1 ?>&city=<?= urlencode($filterCity) ?>&zone=<?= $filterZone ?>&min_price=<?= $filterMinPrice ?? '' ?>&max_price=<?= $filterMaxPrice ?? '' ?>" class="page-link">上一页</a>
            <?php endif; ?>
            <?php for ($i = max(1, $page-2); $i <= min($totalPages, $page+2); $i++): ?>
                <a href="?page=<?= $i ?>&city=<?= urlencode($filterCity) ?>&zone=<?= $filterZone ?>&min_price=<?= $filterMinPrice ?? '' ?>&max_price=<?= $filterMaxPrice ?? '' ?>" class="page-link <?= $i==$page?'active':'' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($page < $totalPages): ?>
                <a href="?page=<?= $page+1 ?>&city=<?= urlencode($filterCity) ?>&zone=<?= $filterZone ?>&min_price=<?= $filterMinPrice ?? '' ?>&max_price=<?= $filterMaxPrice ?? '' ?>" class="page-link">下一页</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
