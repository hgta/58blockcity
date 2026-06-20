<?php
require_once '../../config/database.php';
require_once '../includes/auth.php';
require_once '../../classes/Circle.php';
require_once '../../classes/City.php';
require_once '../../classes/Visit.php';

$circle = new Circle($pdo);
$city = new City($pdo);

$selectedCity = $_GET['city'] ?? '北京';
$search = trim($_GET['search'] ?? '');
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 30;

$circles = $circle->getCirclesByCityPaginated($selectedCity, $page, $perPage, $search);
$totalCount = $circle->getCircleCountByCity($selectedCity, $search);
$totalPages = ceil($totalCount / $perPage);

// 获取当前用户的访问状态
$visitedMap = [];
if (isset($_SESSION['user_id'])) {
    $visit = new Visit($pdo);
    $visitedMap = $visit->getUserVisitedCircleIds($_SESSION['user_id']);
}

$site_config['title']       = e($selectedCity) . '全部互访圈 - 58互访圈';
$site_config['description'] = '浏览 ' . e($selectedCity) . ' 的全部互访圈，发现城市间互访交流的机会。';
$site_config['keywords']    = '58,互访圈,' . e($selectedCity) . ',城市互访,BlockCity';
$site_config['canonical_url'] = 'https://v.58.tl/circles/all.php';
$site_config['extra_head']  = '<link rel="stylesheet" href="../assets/css/main.css">';

require_once '../includes/header.php';
?>

<div class="container">
    <div class="user-header">
        <h2><i class="fas fa-map-marker-alt"></i> <?= e($selectedCity) ?> 的互访圈 <small class="text-muted">(<?= $totalCount ?>)</small></h2>
        <a href="../index.php?city=<?= urlencode($selectedCity) ?>" class="btn btn-outline-secondary"><i class="fas fa-arrow-left"></i> 返回首页</a>
    </div>

    <form method="GET" class="search-form" style="margin-bottom:16px;">
        <input type="hidden" name="city" value="<?= e($selectedCity) ?>">
        <div class="input-group">
            <input type="text" name="search" value="<?= e($search) ?>" class="form-control" placeholder="搜索圈子名称或描述...">
            <div class="input-group-append">
                <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> 搜索</button>
            </div>
        </div>
    </form>

    <?php if (empty($circles)): ?>
        <?= renderEmptyState('users-slash', '暂无互访圈', '该城市还没有任何互访圈，成为第一个创建者吧！',
            '<a href="create.php" class="btn btn-primary"><i class="fas fa-plus"></i> 创建互访圈</a>') ?>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover bg-white rounded shadow-sm">
            <thead class="thead-light">
                <tr>
                    <th>互访圈</th>
                    <th>城市</th>
                    <th>区块数</th>
                    <th>圈主</th>
                    <th>状态</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($circles as $c):
                    $visitStatus = $visitedMap[$c['id']] ?? null;
                    $statusInfo = getVisitStatusLabel($visitStatus ?: '');
                ?>
                <tr>
                    <td><strong><?= e($c['name']) ?></strong></td>
                    <td><?= e($c['city']) ?></td>
                    <td><?= $c['block_count'] ?></td>
                    <td><?= e($c['username']) ?></td>
                    <td>
                        <?php if ($visitStatus): ?>
                            <span class="status-badge badge-<?= $statusInfo['class'] ?>"><?= $statusInfo['label'] ?></span>
                        <?php else: ?>
                            <span style="color:#ccc;">-</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="view.php?id=<?= (int)$c['id'] ?>" class="btn btn-primary btn-sm">详情</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
    <div class="d-flex justify-content-center">
        <ul class="pagination">
            <?php if ($page > 1): ?>
                <li class="page-item"><a class="page-link" href="?city=<?= urlencode($selectedCity) ?>&page=<?= $page - 1 ?><?= $search ? '&search=' . urlencode($search) : '' ?>">上一页</a></li>
            <?php endif; ?>
            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                <li class="page-item <?= $i == $page ? 'active' : '' ?>"><a class="page-link" href="?city=<?= urlencode($selectedCity) ?>&page=<?= $i ?><?= $search ? '&search=' . urlencode($search) : '' ?>"><?= $i ?></a></li>
            <?php endfor; ?>
            <?php if ($page < $totalPages): ?>
                <li class="page-item"><a class="page-link" href="?city=<?= urlencode($selectedCity) ?>&page=<?= $page + 1 ?><?= $search ? '&search=' . urlencode($search) : '' ?>">下一页</a></li>
            <?php endif; ?>
        </ul>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
