<?php
require_once '../config/database.php';
require_once 'includes/auth.php';
require_once '../classes/City.php';

$city = new City($pdo);

// 排序方式
$sort = $_GET['sort'] ?? 'activated';
$allowedSorts = ['activated' => 'activated_blocks DESC', 'resident' => 'resident_count DESC', 'popularity' => 'popularity DESC', 'name' => 'name ASC'];
$orderBy = $allowedSorts[$sort] ?? $allowedSorts['activated'];

$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$stmt = $pdo->prepare("SELECT * FROM cities ORDER BY $orderBy LIMIT ? OFFSET ?");
$stmt->execute([$perPage, $offset]);
$cities = $stmt->fetchAll();

$totalStmt = $pdo->query("SELECT COUNT(*) FROM cities");
$total = $totalStmt->fetchColumn();
$totalPages = ceil($total / $perPage);
?>
<?php require_once 'includes/header.php'; ?>

<div class="container page-container">
    <h1 class="page-title"><i class="fas fa-trophy"></i> 城市排行</h1>

    <div class="sort-bar">
        <span>排序：</span>
        <a href="?sort=activated" class="<?= $sort==='activated'?'active':'' ?>">已激活区块</a>
        <a href="?sort=resident" class="<?= $sort==='resident'?'active':'' ?>">居民数</a>
        <a href="?sort=popularity" class="<?= $sort==='popularity'?'active':'' ?>">人气</a>
        <a href="?sort=name" class="<?= $sort==='name'?'active':'' ?>">名称</a>
    </div>

    <div class="result-count">共 <?= number_format($total) ?> 个城市</div>

    <?php if (empty($cities)): ?>
        <div class="empty-state">
            <i class="fas fa-city"></i>
            <p>暂无城市数据</p>
        </div>
    <?php else: ?>
        <div class="city-rank-list">
            <?php foreach ($cities as $index => $c): ?>
                <?php $rank = $offset + $index + 1; ?>
                <a href="city.php?name=<?= urlencode($c['pinyin']) ?>" class="city-rank-item">
                    <span class="rank-number <?= $rank<=3?'top3':'' ?>"><?= $rank ?></span>
                    <span class="city-name"><?= htmlspecialchars($c['name']) ?></span>
                    <span class="city-stats-bar">
                        <span title="已激活"><i class="fas fa-cubes"></i> <?= number_format($c['activated_blocks'] ?? 0) ?></span>
                        <span title="居民"><i class="fas fa-users"></i> <?= number_format($c['resident_count'] ?? 0) ?></span>
                        <span title="人气"><i class="fas fa-fire"></i> <?= number_format($c['popularity'] ?? 0) ?></span>
                    </span>
                </a>
            <?php endforeach; ?>
        </div>

        <?php if ($totalPages > 1): ?>
        <div class="pagination-clean">
            <?php if ($page > 1): ?>
                <a href="?page=<?= $page-1 ?>&sort=<?= $sort ?>" class="page-link">上一页</a>
            <?php endif; ?>
            <?php for ($i = max(1, $page-2); $i <= min($totalPages, $page+2); $i++): ?>
                <a href="?page=<?= $i ?>&sort=<?= $sort ?>" class="page-link <?= $i==$page?'active':'' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($page < $totalPages): ?>
                <a href="?page=<?= $page+1 ?>&sort=<?= $sort ?>" class="page-link">下一页</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
