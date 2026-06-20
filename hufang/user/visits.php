<?php
require_once '../../config/database.php';
require_once '../includes/auth.php';
require_once '../../classes/Circle.php';
require_once '../../classes/Visit.php';

checkLogin();

$userId = $_SESSION['user_id'];
$visit = new Visit($pdo);
$circle = new Circle($pdo);

$circleId = isset($_GET['circle_id']) ? intval($_GET['circle_id']) : 0;
$search = trim($_GET['search'] ?? '');
$status = $_GET['status'] ?? 'all';
$sort = ($_GET['sort'] ?? 'newest') === 'oldest' ? 'oldest' : 'newest';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 10;

if ($circleId) {
    $circleInfo = $circle->getCircleById($circleId);
    $visits = $visit->getCircleVisitsById($circleId);
    $pageTitle = "互访圈: " . htmlspecialchars($circleInfo['name'] ?? '') . " 的访问记录";
    $total = count($visits);
    $totalPages = 1;
} else {
    $statusFilter = in_array($status, ['pending', 'confirmed', 'completed']) ? $status : null;
    $visits = $visit->getUserVisits($userId, $statusFilter, $search, $page, $perPage, $sort);
    $total = $visit->getUserVisitsCount($userId, $statusFilter, $search);
    $totalPages = (int) ceil($total / $perPage);
    $pageTitle = "我的所有访问记录";
}

// 各状态计数用于 tab 标签
$statusCounts = ['all' => 0, 'pending' => 0, 'confirmed' => 0, 'completed' => 0];
if (!$circleId) {
    $statusCounts['all'] = $visit->getUserVisitsCount($userId, null, $search);
    foreach (['pending', 'confirmed', 'completed'] as $s) {
        $statusCounts[$s] = $visit->getUserVisitsCount($userId, $s, $search);
    }
}

$statusLabels = [
    'all' => '全部记录',
    'pending' => '待处理',
    'confirmed' => '已确认',
    'completed' => '已完成'
];

function buildQuery($overrides) {
    $params = array_merge($_GET, $overrides);
    if (isset($params['page'])) {
        unset($params['page']);
    }
    return http_build_query($params);
}

function renderVisitList($list, $circleId) {
    if (empty($list)) {
        echo renderEmptyState('exchange-alt', '暂无访问记录', '这里还没有任何访问记录');
        return;
    }
    echo '<div class="visit-list">';
    foreach ($list as $v) {
        $actions = '<a href="visit_detail.php?id=' . (int)$v['id'] . '" class="btn btn-sm btn-secondary"><i class="fas fa-info-circle"></i> 详情</a>';
        if ($v['status'] == 'pending' && $circleId) {
            $actions = '<a href="confirm_visit.php?id=' . (int)$v['id'] . '" class="btn btn-sm btn-primary mr-2"><i class="fas fa-check"></i> 确认</a>' . $actions;
        } elseif ($v['status'] == 'confirmed' && !$circleId) {
            $actions = '<a href="record_return.php?id=' . (int)$v['id'] . '" class="btn btn-sm btn-success mr-2"><i class="fas fa-check-double"></i> 记录回访</a>' . $actions;
        }
        echo renderVisitItem($v, true, $actions);
    }
    echo '</div>';
}
?>

<?php require_once '../includes/header.php'; ?>

<div class="container user-container">
    <div class="user-header">
        <h2><i class="fas fa-exchange-alt"></i> <?= $pageTitle ?></h2>

        <?php if ($circleId): ?>
            <a href="circles.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> 返回我的互访圈
            </a>
        <?php endif; ?>
    </div>

    <?php if (!$circleId): ?>
    <div class="card mb-3">
        <div class="card-body">
            <form method="get" class="form-row align-items-end">
                <div class="col-md-5">
                    <label for="search">搜索</label>
                    <input type="text" class="form-control" id="search" name="search"
                           value="<?= htmlspecialchars($search) ?>" placeholder="互访圈名称、城市、圈主">
                </div>
                <div class="col-md-3">
                    <label for="sort">排序</label>
                    <select class="form-control" id="sort" name="sort">
                        <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>最新优先</option>
                        <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>最早优先</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> 查询</button>
                    <a href="visits.php" class="btn btn-outline-secondary">重置</a>
                </div>
            </form>
        </div>
    </div>

    <div class="visit-tabs">
        <div class="tabs-header">
            <?php foreach ($statusLabels as $key => $label): ?>
                <?php $active = ($status === $key || ($key === 'all' && !in_array($status, ['pending','confirmed','completed']))) ? 'active' : ''; ?>
                <a href="?<?= buildQuery(['status' => $key]) ?>" class="tab-item <?= $active ?>">
                    <?= $label ?> (<?= $statusCounts[$key] ?>)
                </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="tabs-content mt-3">
        <?php renderVisitList($visits, $circleId); ?>
    </div>

    <?php if (!$circleId && $totalPages > 1): ?>
    <nav aria-label="访问记录分页" class="mt-4">
        <ul class="pagination justify-content-center">
            <?php if ($page > 1): ?>
                <li class="page-item">
                    <a class="page-link" href="?<?= buildQuery(['page' => $page - 1]) ?>">上一页</a>
                </li>
            <?php endif; ?>

            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <?php $active = ($i === $page) ? 'active' : ''; ?>
                <li class="page-item <?= $active ?>">
                    <a class="page-link" href="?<?= buildQuery(['page' => $i]) ?>"><?= $i ?></a>
                </li>
            <?php endfor; ?>

            <?php if ($page < $totalPages): ?>
                <li class="page-item">
                    <a class="page-link" href="?<?= buildQuery(['page' => $page + 1]) ?>">下一页</a>
                </li>
            <?php endif; ?>
        </ul>
    </nav>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
