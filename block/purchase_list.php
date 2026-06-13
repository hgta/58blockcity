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
$where = ["pr.status = 'active'"];
$params = [];
if ($filterCity) {
    $where[] = "c.name LIKE ?";
    $params[] = "%$filterCity%";
}
if ($filterZone && preg_match('/^[A-HZ]$/', $filterZone)) {
    $where[] = "pr.zone = ?";
    $params[] = $filterZone;
}
$whereSql = implode(' AND ', $where);

$stmt = $pdo->prepare("SELECT pr.*, c.name as city_name, u.username 
    FROM purchase_requests pr 
    JOIN cities c ON pr.city_id = c.id 
    LEFT JOIN users u ON pr.user_id = u.id 
    WHERE $whereSql 
    ORDER BY pr.created_at DESC LIMIT ? OFFSET ?");
// 显式绑定字符串参数
for ($i = 0; $i < count($params); $i++) {
    $stmt->bindValue($i + 1, $params[$i]);
}
$stmt->bindValue(count($params) + 1, (int)$perPage, PDO::PARAM_INT);
$stmt->bindValue(count($params) + 2, (int)$offset, PDO::PARAM_INT);
$stmt->execute();
$requests = $stmt->fetchAll();

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM purchase_requests pr JOIN cities c ON pr.city_id = c.id WHERE $whereSql");
$countStmt->execute($params);
$total = $countStmt->fetchColumn();
$totalPages = ceil($total / $perPage);

$hotCities = $city->getHotCitiesList(20);
?>
<?php require_once 'includes/header.php'; ?>

<div class="container page-container">
    <div class="page-header-with-action">
        <h1 class="page-title"><i class="fas fa-hand-holding-usd"></i> 求购列表</h1>
        <a href="purchase_create.php" class="btn-primary"><i class="fas fa-plus"></i> 发布求购</a>
    </div>

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
            <a href="purchase_list.php" class="btn-reset">重置</a>
        </div>
    </form>

    <div class="result-count">共 <?= number_format($total) ?> 条记录</div>

    <?php if (empty($requests)): ?>
        <div class="empty-state">
            <i class="fas fa-search"></i>
            <p>暂无求购请求</p>
            <a href="purchase_create.php" class="btn-primary" style="margin-top:15px;display:inline-block;">发布第一条求购</a>
        </div>
    <?php else: ?>
        <div class="req-list">
            <?php foreach ($requests as $r): ?>
                <div class="req-card">
                    <h3>
                        <span class="zone-tag"><?= htmlspecialchars($r['zone'] ?? '?') ?>区</span>
                        <?= htmlspecialchars($r['city_name']) ?>
                        <?= $r['block_number'] ? ' #'.$r['block_number'] : ' (任意区块)' ?>
                    </h3>
                    <div class="meta">
                        <span>求购者: <?= htmlspecialchars($r['username'] ?? '匿名') ?></span>
                        <span class="price">最高出价: <?= $r['max_price'] ? '¥'.number_format($r['max_price'],2) : '面议' ?></span>
                    </div>
                </div>
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
