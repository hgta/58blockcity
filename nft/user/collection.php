<?php
require_once '../../config/database.php';
require_once '../../classes/NFT.php';
require_once '../../classes/User.php';
require_once '../../classes/City.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// 验证登录状态
checkLogin();

$userId = $_SESSION['user_id'];
$nft = new NFT($pdo);
$user = new User($pdo);
$cityObj = new City($pdo);

// 获取用户收藏的NFT按城市分组
$collection = $nft->getUserCollection($userId);

// 按城市分组并统计数量
$cityGroups = [];
foreach ($collection as $item) {
    $cityId = $item['city_id'];
    if (!isset($cityGroups[$cityId])) {
        $cityGroups[$cityId] = [
            'city_name' => $item['city_name'],
            'city_rank' => $item['city_rank'] ?? 0,
            'items' => []
        ];
    }
    $cityGroups[$cityId]['items'][] = $item;
}

// 按rank从低到高排序
uasort($cityGroups, function($a, $b) {
    return $a['city_rank'] - $b['city_rank'];
});

// 城市分页设置
$citiesPerPage = 5; // 每页显示5个城市
$cityIds = array_keys($cityGroups);
$totalCities = count($cityGroups);
$totalPages = ceil($totalCities / $citiesPerPage);
$page = max(1, min((int)($_GET['page'] ?? 1), $totalPages));
$startCity = ($page - 1) * $citiesPerPage;
$currentPageCities = array_slice($cityIds, $startCity, $citiesPerPage, true);

// 获取用户当前头像信息
$userData = $user->getUserById($userId);
$currentAvatar = $userData['avatar'];
?>

<?php include '../includes/header.php'; ?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0"><i class="fas fa-user-circle me-2"></i>我持有的NFT头像</h2>
        <div class="d-flex align-items-center gap-3">
            <span class="badge bg-primary"><?= count($collection) ?> 个头像</span>
            <span class="badge bg-secondary"><?= $totalCities ?> 个城市</span>
        </div>
    </div>

    <!-- 消息提示 -->
    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?= htmlspecialchars($_SESSION['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['message']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?= htmlspecialchars($_SESSION['error']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <!-- 城市分组展示 -->
    <?php if (empty($currentPageCities)): ?>
        <div class="empty-state text-center py-5">
            <div class="empty-icon text-muted mb-3">
                <i class="fas fa-image fa-3x"></i>
            </div>
            <h3 class="h5">您还没有NFT收藏</h3>
            <p class="text-muted">快去认领或购买NFT头像吧！</p>
            <div class="d-flex justify-content-center gap-2 mt-3">
                <a href="/nft/claim_list.php" class="btn btn-primary">
                    <i class="fas fa-hand-holding-heart me-2"></i>去认领NFT
                </a>
                <a href="/nft/sale_list.php" class="btn btn-outline-primary">
                    <i class="fas fa-store me-2"></i>去购买NFT
                </a>
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($currentPageCities as $cityId): ?>
            <?php $cityGroup = $cityGroups[$cityId]; ?>
            <div class="city-section mb-4">
                <!-- 城市标题 -->
                <div class="city-header d-flex align-items-center mb-3 px-2">
                    <div class="city-icon me-2">
                        <i class="fas fa-city text-primary"></i>
                    </div>
                    <h5 class="city-title mb-0 flex-grow-1"><?= htmlspecialchars($cityGroup['city_name']) ?></h5>
                    <span class="badge bg-light text-dark"><?= count($cityGroup['items']) ?> 个头像</span>
                    <?php if ($cityGroup['city_rank'] > 0): ?>
                        <span class="badge bg-warning text-dark ms-2" title="城市排名">
                            <i class="fas fa-trophy me-1"></i><?= $cityGroup['city_rank'] ?>
                        </span>
                    <?php endif; ?>
                </div>

                <!-- NFT网格 -->
                <div class="row row-cols-2 row-cols-sm-3 row-cols-md-4 row-cols-lg-6 g-3">
                    <?php foreach ($cityGroup['items'] as $item): ?>
                        <div class="col">
                            <div class="nft-collection-card card h-100 border-0 position-relative">
                                <!-- 当前头像标记 -->
                                <?php if ($currentAvatar == $item['base_image']): ?>
                                    <div class="current-avatar-badge position-absolute top-0 end-0 m-1">
                                        <span class="badge bg-success rounded-circle p-1" title="当前头像">
                                            <i class="fas fa-check fa-xs"></i>
                                        </span>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- 头像容器 - 增大头像显示区域 -->
                                <div class="avatar-main-section d-flex flex-column align-items-center p-2">
                                    <div class="avatar-container">
                                        <img src="../avatar/<?= htmlspecialchars($item['base_image']) ?>" 
                                             class="nft-avatar-img" 
                                             alt="NFT <?= htmlspecialchars($item['code']) ?>"
                                             loading="lazy">
                                    </div>
                                    
                                    <!-- NFT编号 - 始终显示 -->
                                    <div class="nft-code mt-2 text-center">
                                        <span class="badge bg-orange text-white"><?= htmlspecialchars($item['code']) ?></span>
                                    </div>
                                </div>
                                
                                <!-- 操作按钮 - 紧凑布局 -->
                                <div class="card-footer border-0 bg-transparent pt-0 pb-2 px-2">
                                    <div class="nft-actions d-flex justify-content-center gap-1">
                                        <a href="/user/set_profile_avatar.php?id=<?= $item['nft_id'] ?>" 
                                           class="btn btn-sm btn-outline-primary rounded-circle"
                                           title="设为头像"
                                           data-bs-toggle="tooltip">
                                            <i class="fas fa-user-circle"></i>
                                        </a>
                                        <a href="/nft/sell.php?id=<?= $item['nft_id'] ?>&city_id=<?= $item['city_id'] ?>" 
                                           class="btn btn-sm btn-outline-success rounded-circle"
                                           title="出售"
                                           data-bs-toggle="tooltip">
                                            <i class="fas fa-tag"></i>
                                        </a>
                                        <a href="/nft/view.php?id=<?= $item['nft_id'] ?>" 
                                           class="btn btn-sm btn-outline-info rounded-circle"
                                           title="查看详情"
                                           data-bs-toggle="tooltip">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- 城市分隔线（除了最后一个） -->
            <?php if ($cityId !== end($currentPageCities)): ?>
                <hr class="my-4">
            <?php endif; ?>
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- 紧凑分页导航 -->
    <?php if ($totalPages > 1): ?>
        <div class="pagination-section mt-4 pt-3 border-top">
            <div class="d-flex justify-content-center align-items-center gap-3">
                <!-- 上一页 -->
                <a href="?page=<?= $page - 1 ?>" class="btn btn-sm btn-outline-primary <?= $page <= 1 ? 'disabled' : '' ?>">
                    <i class="fas fa-chevron-left me-1"></i>上一页
                </a>
                
                <!-- 页码信息 -->
                <div class="d-flex align-items-center gap-2">
                    <span class="text-muted small">第</span>
                    <select class="form-select form-select-sm page-select" style="width: auto;" onchange="location.href='?page='+this.value">
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <option value="<?= $i ?>" <?= $i == $page ? 'selected' : '' ?>><?= $i ?></option>
                        <?php endfor; ?>
                    </select>
                    <span class="text-muted small">页，共 <?= $totalPages ?> 页</span>
                </div>
                
                <!-- 下一页 -->
                <a href="?page=<?= $page + 1 ?>" class="btn btn-sm btn-outline-primary <?= $page >= $totalPages ? 'disabled' : '' ?>">
                    下一页<i class="fas fa-chevron-right ms-1"></i>
                </a>
            </div>
            
            <!-- 城市统计 -->
            <div class="text-center mt-2">
                <small class="text-muted">
                    显示 <?= min(($page - 1) * $citiesPerPage + 1, $totalCities) ?>-<?= min($page * $citiesPerPage, $totalCities) ?> 个城市，共 <?= $totalCities ?> 个城市
                </small>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>

<style>
/* 头像卡片样式 */
.nft-collection-card {
    transition: all 0.2s ease;
    border: 1px solid #e9ecef !important;
    border-radius: 12px;
    background: white;
    height: 100%;
    display: flex;
    flex-direction: column;
}

.nft-collection-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    border-color: #ff6b00 !important;
}

/* 头像主区域 - 占据3/4空间 */
.avatar-main-section {
    flex: 3;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    padding: 1rem 0.5rem;
}

.avatar-container {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    overflow: hidden;
    border: 2px solid #ff6b00;
    background: white;
}

.nft-avatar-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

/* NFT编号样式 */
.nft-code .badge {
    background: linear-gradient(135deg, #ff6b00 0%, #ff8c00 100%);
    color: white;
    font-size: 0.7rem;
    font-weight: 600;
    padding: 0.3rem 0.6rem;
    border-radius: 12px;
}

/* 操作按钮区域 - 占据1/4空间 */
.card-footer {
    flex: 1;
    padding-top: 0 !important;
}

.nft-actions .btn {
    width: 28px;
    height: 28px;
    padding: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0.8;
    transition: all 0.2s ease;
    border-width: 1px;
    font-size: 0.75rem;
}

.nft-actions .btn:hover {
    opacity: 1;
    transform: scale(1.1);
}

.current-avatar-badge .badge {
    box-shadow: 0 1px 3px rgba(0,0,0,0.2);
    font-size: 0.5rem;
    padding: 0.25rem;
}

/* 城市标题样式 */
.city-header {
    background: #f8f9fa;
    padding: 0.5rem 0.75rem;
    border-radius: 6px;
    margin-bottom: 0.75rem;
}

.city-title {
    font-size: 1rem;
    font-weight: 600;
    color: #495057;
}

/* 紧凑分页样式 */
.pagination-section {
    padding: 0.5rem 0;
}

.page-select {
    width: 70px !important;
    display: inline-block;
}

/* 分隔线样式 */
hr {
    margin: 1.5rem 0;
    border-color: #e9ecef;
    opacity: 0.5;
}

/* 橙色主题 */
.bg-orange {
    background-color: #ff6b00 !important;
}

/* 响应式调整 */
@media (max-width: 768px) {
    .pagination-section .d-flex {
        flex-direction: column;
        gap: 1rem;
    }
    
    .avatar-container {
        width: 70px;
        height: 70px;
    }
    
    .nft-actions .btn {
        width: 26px;
        height: 26px;
    }
    
    .city-header {
        padding: 0.4rem 0.6rem;
    }
    
    .city-title {
        font-size: 0.9rem;
    }
    
    .avatar-main-section {
        padding: 0.8rem 0.3rem;
    }
}

@media (max-width: 576px) {
    .avatar-container {
        width: 60px;
        height: 60px;
    }
    
    .nft-code .badge {
        font-size: 0.65rem;
        padding: 0.25rem 0.5rem;
    }
}

/* 城市排名徽章 */
.badge.bg-warning {
    background: linear-gradient(135deg, #ffc107 0%, #ffb300 100%) !important;
    color: #212529 !important;
    font-weight: 600;
    font-size: 0.7rem;
}
</style>

<script>
// 启用工具提示
document.addEventListener('DOMContentLoaded', function() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
</script>