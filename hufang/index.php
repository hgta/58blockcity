<?php
// 启动会话
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/database.php';
require_once 'includes/auth.php';
require_once '../classes/Visit.php';
require_once '../classes/Circle.php';
require_once '../classes/City.php';

$circle = new Circle($pdo);

// 扩展城市列表
$city = new City($pdo);
$cities = $city->getAllCities();

// 城市定位优先级：URL参数 > Session缓存 > 默认北京
if (!empty($_GET['city'])) {
    $selectedCity = $_GET['city'];
    $_SESSION['hufang_city'] = $selectedCity;
} elseif (!empty($_SESSION['hufang_city'])) {
    $selectedCity = $_SESSION['hufang_city'];
} else {
    $selectedCity = '北京';
}
$search = trim($_GET['search'] ?? '');
$viewMode = $_GET['view'] ?? 'card'; // card or list

// 首页最多显示100个圈子
$circles = $circle->getCirclesByCity($selectedCity, 100, $search);
$totalCount = $circle->getCircleCountByCity($selectedCity, $search);

// 热门城市（圈子数最多的前20个）
$hotCities = $circle->getHotCities(20);
$hotCityNames = array_column($hotCities, 'city');

// 获取当前用户的访问状态映射
$visitedMap = [];
$unread_count = 0;
if (isset($_SESSION['user_id'])) {
    $userId = $_SESSION['user_id'];
    $visit = new Visit($pdo);
    $pendingVisits = $visit->getCircleVisits($userId, 'pending');
    $unread_count = count($pendingVisits);
    $visitedMap = $visit->getUserVisitedCircleIds($userId);
}

$site_config['title']       = '58互访圈 - 城市间互访交流平台 | 58 BlockCity';
$site_config['description'] = '58互访圈是基于58区块城市的城市间互访交流平台，记录和管理城市间的互访活动。';
$site_config['keywords']    = '58,互访圈,城市互访,区块城市,BlockCity,DAO,同城交流';
$site_config['canonical_url'] = 'https://v.58.tl/';
$site_config['og_image']    = 'https://58.tl/assets/images/og-hufang.jpg';
$site_config['extra_head']  = '<link rel="stylesheet" href="assets/css/main.css"><style>.circle-visit-badge{position:absolute;top:8px;right:8px;padding:3px 10px;border-radius:12px;font-size:11px;font-weight:600;color:#fff;z-index:2}.circle-visit-badge.completed{background:#22c55e}.circle-visit-badge.visited{background:#3b82f6}.circle-visit-badge.pending{background:#f59e0b}.view-switch{display:flex;gap:6px}.view-switch .btn{padding:6px 14px;font-size:13px}@media(max-width:768px){.user-header{flex-direction:column;gap:8px}.user-header>*{width:100%}.city-tags a{font-size:14px;padding:6px 12px}.table th,.table td{font-size:13px;padding:8px 6px}.view-switch .btn{font-size:13px;padding:8px 12px}}@media(max-width:480px){.table{font-size:12px}.city-tags a{font-size:13px;padding:5px 10px}}</style>';

require_once 'includes/header.php';
?>

<div class="container">
    <!-- 城市筛选 -->
    <div class="city-filter">
        <h3><i class="fas fa-map-marker-alt"></i> 选择城市：</h3>
        <div class="city-tags" id="cityTags">
            <?php
            // 显示热门城市（有圈子的）
            $displayedCities = [];
            foreach ($hotCities as $hc):
                $displayedCities[] = $hc['city'];
            ?>
                <a href="?city=<?= urlencode($hc['city']) ?>" class="city-tag <?= $hc['city'] === $selectedCity ? 'active' : '' ?>">
                    <?= e($hc['city']) ?>
                    <small style="opacity:0.6;font-size:10px;"><?= $hc['cnt'] ?></small>
                </a>
            <?php endforeach; ?>

            <?php if (!in_array($selectedCity, $displayedCities)): ?>
                <a href="?city=<?= urlencode($selectedCity) ?>" class="city-tag active"><?= e($selectedCity) ?></a>
            <?php endif; ?>

            <!-- 展开全部城市 -->
            <a href="javascript:void(0)" class="city-tag" style="background:#6366f1;color:#fff;" onclick="toggleAllCities()">
                <i class="fas fa-chevron-down" id="expandIcon"></i> <span id="expandText">展开全部</span>
            </a>
        </div>
        <div class="city-tags" id="allCities" style="display:none;margin-top:8px;">
            <?php foreach ($cities as $c): ?>
                <?php if (!in_array($c['name'], $displayedCities)): ?>
                <a href="?city=<?= urlencode($c['name']) ?>" class="city-tag <?= $c['name'] === $selectedCity ? 'active' : '' ?>">
                    <?= e($c['name']) ?>
                </a>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- 搜索 + 模式切换 -->
    <div class="user-header" style="margin-bottom:20px;">
        <form method="GET" style="display:flex;gap:8px;flex:1;min-width:200px;">
            <input type="hidden" name="city" value="<?= e($selectedCity) ?>">
            <input type="text" name="search" value="<?= e($search) ?>" placeholder="搜索圈子名称或描述..." class="form-control" style="border-radius:20px;">
            <button type="submit" class="btn btn-primary" style="border-radius:20px;padding:8px 16px;">
                <i class="fas fa-search"></i>
            </button>
            <?php if ($search): ?>
                <a href="?city=<?= urlencode($selectedCity) ?>" class="btn btn-outline-secondary" style="border-radius:20px;padding:8px 16px;">清除</a>
            <?php endif; ?>
        </form>
        <div class="view-switch">
            <a href="?city=<?= urlencode($selectedCity) ?><?php echo $search ? '&search=' . urlencode($search) : '' ?>&view=card"
               class="btn <?= $viewMode === 'card' ? 'btn-primary' : 'btn-outline-secondary' ?>">
                <i class="fas fa-th-large"></i> 卡片
            </a>
            <a href="?city=<?= urlencode($selectedCity) ?><?php echo $search ? '&search=' . urlencode($search) : '' ?>&view=list"
               class="btn <?= $viewMode === 'list' ? 'btn-primary' : 'btn-outline-secondary' ?>">
                <i class="fas fa-list"></i> 列表
            </a>
        </div>
    </div>

    <!-- 互访圈列表 -->
    <div class="circle-list">
        <?php if (empty($circles)): ?>
            <?= renderEmptyState('users-slash', '该城市暂无互访圈', '尝试选择其他城市或创建您自己的互访圈',
                (isset($_SESSION['user_id'])
                    ? '<a href="circles/create.php" class="btn btn-primary"><i class="fas fa-plus"></i> 创建互访圈</a>'
                    : '<a href="auth/login.php" class="btn btn-primary"><i class="fas fa-sign-in-alt"></i> 登录后创建互访圈</a>')) ?>
        <?php elseif ($viewMode === 'list'): ?>
            <!-- 列表模式 -->
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
                            <a href="circles/view.php?id=<?= (int)$c['id'] ?>" class="btn btn-primary btn-sm">详情</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <!-- 卡片模式 -->
            <div class="circle-grid">
                <?php foreach ($circles as $c): ?>
                    <?= renderCircleCard($c, $visitedMap[$c['id']] ?? null) ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($totalCount > 100): ?>
        <div class="text-center mt-4">
            <p style="color:#666;margin-bottom:10px;">显示了前 100 个圈子，共 <?= $totalCount ?> 个</p>
            <a href="circles/all.php?city=<?= urlencode($selectedCity) ?><?php echo $search ? '&search=' . urlencode($search) : '' ?>"
               class="btn btn-primary" style="padding:10px 28px;">
                <i class="fas fa-list"></i> 查看全部 <?= $totalCount ?> 个圈子
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
<script>
function toggleAllCities() {
    var allCities = document.getElementById('allCities');
    var icon = document.getElementById('expandIcon');
    var text = document.getElementById('expandText');
    if (allCities.style.display === 'none') {
        allCities.style.display = 'flex';
        allCities.style.flexWrap = 'wrap';
        allCities.style.gap = '6px';
        icon.className = 'fas fa-chevron-up';
        text.textContent = ' 收起';
    } else {
        allCities.style.display = 'none';
        icon.className = 'fas fa-chevron-down';
        text.textContent = ' 展开全部';
    }
}
</script>
