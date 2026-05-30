<?php require_once 'includes/header.php'; ?>

<?php
require_once '../config/database.php';
require_once '../classes/NFT.php';
require_once '../classes/UserPopularity.php';
require_once '../classes/City.php';
require_once '../classes/PurchaseRequest.php';

$nft = new NFT($pdo);
$popularity = new UserPopularity($pdo);
$cityObj = new City($pdo);
$purchaseRequest = new PurchaseRequest($pdo);

// 分页参数
$page = $_GET['page'] ?? 1;
$perPage = 200; // 与claim_list.php保持一致

// 获取统计数据
$totalClaimed = $pdo->query("SELECT COUNT(*) FROM nft_city_user")->fetchColumn();
$totalListed = $nft->getTotalSaleCount('', '', '', '', 'all');
$totalWanted = $purchaseRequest->getTotalPurchaseRequestsCount('pending'); // 仅统计待响应的求购;  

// 获取前20个热门城市
$cities = $cityObj->getPopularCities(100);
$selectedCity = $_GET['cityid'] ?? ($cities[0]['id'] ?? '497');

// 获取当前城市的NFT列表
$avatars = $nft->getAvatarsByCity($selectedCity, $perPage, 'listed', ($page - 1) * $perPage);
$totalAvatars = $nft->getTotalAvatarsByCity($selectedCity);

// 获取用户在该城市的人气值
$userPopularity = isset($_SESSION['user_id']) ? $popularity->getUserPopularity($_SESSION['user_id'], $selectedCity) : 0;
?>

<div class="container">
    <!-- 统计数据汇总 -->
    <div class="stats-summary">
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-user-check"></i>
            </div>
            <div class="stat-content">
                <h3><?= number_format($totalClaimed) ?></h3>
                <p>已认领头像</p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-store"></i>
            </div>
            <div class="stat-content">
                <h3><?= number_format($totalListed) ?></h3>
                <p>售卖中头像</p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-hand-holding-usd"></i>
            </div>
            <div class="stat-content">
                <h3><?= number_format($totalWanted) ?></h3>
                <p>求购中头像</p>
            </div>
        </div>
    </div>

    <!-- 城市筛选 -->
    <div class="city-filter">
        <h3><i class="fas fa-map-marker-alt"></i> 选择城市：</h3>
        <div class="city-tags">
            <?php foreach ($cities as $city): ?>
                <a href="?cityid=<?= $city['id'] ?>" class="city-tag <?= 
                    $city['id'] == $selectedCity ? 'active' : '' ?>">
                    <?= htmlspecialchars($city['name']) ?>
                </a>
            <?php endforeach; ?>
            <a href="nft/list.php" class="city-tag more-cities">
                <i class="fas fa-ellipsis-h"></i> 更多城市
            </a>
        </div>
        
        <?php if (isset($_SESSION['user_id'])): ?>
            <div class="popularity-badge">
                <i class="fas fa-fire"></i> 
                <?= $cityObj->getCityName($selectedCity) ?>人气值: <?= $userPopularity ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- 城市NFT展示区 -->
    <div class="city-section">
        <h2 class="city-title">
            <i class="fas fa-city"></i> <?= htmlspecialchars($cityObj->getCityName($selectedCity)) ?>
            <small><?= $totalAvatars ?>个头像在售</small>
        </h2>
        
        <!-- 使用claim_list.php的网格布局 -->
        <div class="nft-grid-fixed">
            <?php if (empty($avatars)): ?>
                <div class="empty-state" style="grid-column: 1 / -1;">
                    <div class="empty-icon">
                        <i class="fas fa-image"></i>
                    </div>
                    <h3>该城市暂无NFT头像出售</h3>
                    <p>尝试选择其他城市或创建您自己的NFT头像</p>
                </div>
            <?php else: ?>
                <?php foreach ($avatars as $avatar): ?>
                    <div class="nft-card-fixed">
                        <div class="nft-item-fixed">
                            <img src="../avatar/<?= htmlspecialchars($avatar['base_image']) ?>" 
                                 alt="NFT <?= htmlspecialchars($avatar['code']) ?>"
                                 loading="lazy">
                        </div>
                        <div class="nft-code-fixed"><?= htmlspecialchars($avatar['code']) ?></div>
                        <div class="nft-price-fixed">
                            <?= number_format($avatar['price'], $avatar['currency'] == 'cny' ? 2 : 0) ?>
                            <?= $avatar['currency'] == 'cny' ? '¥' : '人气值' ?>
                        </div>
                        <div class="nft-actions-fixed">
                            <a href="nft/view.php?id=<?= $avatar['id'] ?>" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-eye"></i> 查看
                            </a>
                            <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] != $avatar['owner_id']): ?>
                                <a href="nft/buy.php?id=<?= $avatar['id'] ?>" class="btn btn-primary btn-sm">
                                    <i class="fas fa-shopping-cart"></i> 购买
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <!-- 修正后的分页 -->
        <?php if ($totalAvatars > $perPage): ?>
            <nav class="mt-4">
                <ul class="pagination justify-content-center">
                    <!-- 上一页 -->
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="?<?= 
                            http_build_query(array_merge($_GET, ['page' => $page - 1])) 
                        ?>" aria-label="Previous">
                            <span aria-hidden="true">&laquo;</span>
                        </a>
                    </li>
                    
                    <!-- 第一页 -->
                    <?php if ($page > 3): ?>
                        <li class="page-item">
                            <a class="page-link" href="?<?= 
                                http_build_query(array_merge($_GET, ['page' => 1])) 
                            ?>">1</a>
                        </li>
                        <?php if ($page > 4): ?>
                            <li class="page-item disabled">
                                <span class="page-link">...</span>
                            </li>
                        <?php endif; ?>
                    <?php endif; ?>
                    
                    <!-- 中间页码 -->
                    <?php 
                    $startPage = max(1, $page - 2);
                    $endPage = min(ceil($totalAvatars / $perPage), $page + 2);
                    
                    for ($i = $startPage; $i <= $endPage; $i++): ?>
                        <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                            <a class="page-link" href="?<?= 
                                http_build_query(array_merge($_GET, ['page' => $i])) 
                            ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    
                    <!-- 最后一页 -->
                    <?php if ($page < ceil($totalAvatars / $perPage) - 2): ?>
                        <?php if ($page < ceil($totalAvatars / $perPage) - 3): ?>
                            <li class="page-item disabled">
                                <span class="page-link">...</span>
                            </li>
                        <?php endif; ?>
                        <li class="page-item">
                            <a class="page-link" href="?<?= 
                                http_build_query(array_merge($_GET, ['page' => ceil($totalAvatars / $perPage)])) 
                            ?>"><?= ceil($totalAvatars / $perPage) ?></a>
                        </li>
                    <?php endif; ?>
                    
                    <!-- 下一页 -->
                    <li class="page-item <?= $page >= ceil($totalAvatars / $perPage) ? 'disabled' : '' ?>">
                        <a class="page-link" href="?<?= 
                            http_build_query(array_merge($_GET, ['page' => $page + 1])) 
                        ?>" aria-label="Next">
                            <span aria-hidden="true">&raquo;</span>
                        </a>
                    </li>
                </ul>
            </nav>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>