<?php
require_once '../config/database.php';
require_once 'includes/auth.php';
require_once '../classes/City.php';

$city = new City($pdo);

// 筛选参数
$filterCity = $_GET['city'] ?? '';
$filterZone = $_GET['zone'] ?? '';

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
$whereSql = implode(' AND ', $where);

$stmt = $pdo->prepare("SELECT b.*, c.name as city_name, u.username 
    FROM blocks b 
    JOIN cities c ON b.city_id = c.id 
    LEFT JOIN users u ON b.owner_id = u.id 
    WHERE $whereSql 
    ORDER BY b.created_at DESC LIMIT ? OFFSET ?");
$stmt->execute(array_merge($params, [$perPage, $offset]));
$blocks = $stmt->fetchAll();

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM blocks b JOIN cities c ON b.city_id = c.id WHERE $whereSql");
$countStmt->execute($params);
$total = $countStmt->fetchColumn();
$totalPages = ceil($total / $perPage);

$hotCities = $city->getHotCitiesList(20);
?>
<?php require_once 'includes/header.php'; ?>

<div class="container page-container">
    <h1 class="page-title"><i class="fas fa-hand-holding-heart"></i> 最近认领</h1>

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
        <div class="filter-actions">
            <button type="submit" class="btn-filter">筛选</button>
            <a href="claim_list.php" class="btn-reset">重置</a>
        </div>
    </form>

    <div class="result-count">共 <?= number_format($total) ?> 条记录</div>

    <?php if (empty($blocks)): ?>
        <div class="empty-state">
            <i class="fas fa-map-marked-alt"></i>
            <p>暂无认领记录</p>
        </div>
    <?php else: ?>
        <div class="block-grid compact">
            <?php foreach ($blocks as $b): ?>
                <a href="block/view.php?id=<?= $b['id'] ?>" class="block-card compact">
                    <div class="card-info">
                        <span class="zone-tag"><?= $b['zone'] ?>区</span>
                        <?= htmlspecialchars($b['city_name']) ?> #<?= $b['block_number'] ?>
                        <div class="card-owner"><?= htmlspecialchars($b['username'] ?? '匿名') ?></div>
                    </div>
                    <span class="card-price">¥<?= number_format($b['price'] ?? 0, 2) ?></span>
                </a>
            <?php endforeach; ?>
        </div>

        <?php if ($totalPages > 1): ?>
        <div class="pagination-clean">
            <?php if ($page > 1): ?>
                <a href="?page=<?= $page-1 ?>&city=<?= urlencode($filterCity) ?>&zone=<?= $filterZone ?>" class="page-link">上一页</a>
            <?php endif; ?>
            <?php for ($i = max(1, $page-2); $i <= min($totalPages, $page+2); $i++): ?>
                <a href="?page=<?= $i ?>&city=<?= urlencode($filterCity) ?>&zone=<?= $filterZone ?>" class="page-link <?= $i==$page?'active':'' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($page < $totalPages): ?>
                <a href="?page=<?= $page+1 ?>&city=<?= urlencode($filterCity) ?>&zone=<?= $filterZone ?>" class="page-link">下一页</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
