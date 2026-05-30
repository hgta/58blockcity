<?php
require_once '../../config/database.php';
require_once '../../classes/NFT.php';
require_once '../../classes/City.php';
require_once '../../classes/PurchaseRequest.php';
require_once '../includes/auth.php';

// 验证登录状态
checkLogin();

$userId = $_SESSION['user_id'];
$nft = new NFT($pdo);
$city = new City($pdo);
$purchaseRequest = new PurchaseRequest($pdo);

// 搜索参数
$searchCode = $_GET['code'] ?? '';
$searchCity = $_GET['city'] ?? '';
$page = $_GET['page'] ?? 1;
$perPage = 200; // 每页200个，更紧凑

// 获取所有城市
$allCities = $city->getAllCities();

// 获取可求购的NFT列表（带搜索条件）

// // 确保参数是整数
// $perPage = 200;
// $page = max(1, (int)($_GET['page'] ?? 1));
// $offset = ($page - 1) * $perPage;

// // 调用方法
// $nfts = $nft->getAvailableNftsForPurchase($perPage, $offset, $searchCode, $searchCity);

// //$nfts = $nft->getAvailableNftsForPurchase((int)$perPage, (int)(($page - 1) * $perPage), $searchCode, $searchCity);
// $totalNfts = $nft->getTotalAvailableNftCount($searchCode, $searchCity);

// 获取NFT列表（带搜索条件）
$nfts = $nft->getAllNfts($perPage, ($page - 1) * $perPage, $searchCode, $searchTag);
$totalNfts = $nft->getTotalNftCount($searchCode, $searchTag);

// 获取用户已有的求购记录
$userPurchaseRequests = $purchaseRequest->getUserPurchaseRequests($userId);
$userRequestedNfts = array_column($userPurchaseRequests, 'nft_id');
?>

<?php require_once '../includes/header.php'; ?>

<div class="container">
    <div class="page-header">
        <h1><i class="fas fa-hand-holding-usd"></i> NFT头像求购市场</h1>
        <p class="lead">您可以在这里寻找并求购心仪的NFT头像</p>
    </div>
    
    <!-- 搜索和筛选栏 -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="get" class="row g-3">
                <div class="col-md-4">
                    <label for="codeSearch" class="form-label">编号查询</label>
                    <input type="text" class="form-control" id="codeSearch" name="code" 
                           placeholder="输入编号(如AB01)" value="<?= htmlspecialchars($searchCode) ?>">
                </div>
                <div class="col-md-4">
                    <label for="cityFilter" class="form-label">城市筛选</label>
                    <select class="form-select" id="cityFilter" name="city">
                        <option value="">所有城市</option>
                        <?php foreach ($allCities as $cityItem): ?>
                            <option value="<?= htmlspecialchars($cityItem['name']) ?>" 
                                <?= $searchCity === $cityItem['name'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cityItem['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="fas fa-search"></i> 搜索
                    </button>
                    <a href="purchase_list.php" class="btn btn-outline-secondary">
                        <i class="fas fa-sync-alt"></i> 重置
                    </a>
                </div>
            </form>
        </div>
    </div>
    
    <!-- 统计信息 -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="text-muted">
            共 <?= number_format($totalNfts) ?> 个可求购NFT头像
            <?php if ($searchCode || $searchCity): ?>
                (筛选结果: <?= count($nfts) ?>)
            <?php endif; ?>
        </div>
        <div>
            <a href="/user/purchase_requests.php" class="btn btn-outline-primary me-2">
                <i class="fas fa-list"></i> 我的求购记录
            </a>
            <a href="/nft/sale_list.php" class="btn btn-outline-success">
                <i class="fas fa-store"></i> 前往出售市场
            </a>
        </div>
    </div>
    
    <!-- NFT列表 -->
    <div class="nft-grid-fixed">
        <?php if (empty($nfts)): ?>
            <div class="empty-state" style="grid-column: 1 / -1;">
                <div class="empty-icon">
                    <i class="fas fa-image"></i>
                </div>
                <h3>没有找到符合条件的NFT头像</h3>
                <p>尝试修改搜索条件或查看其他页面</p>
            </div>
        <?php else: ?>
            <?php foreach ($nfts as $item): ?>
                <div class="nft-card-fixed">
                    <div class="nft-item-fixed">
                        <img src="../avatar/<?= htmlspecialchars($item['base_image']) ?>" 
                             alt="NFT <?= htmlspecialchars($item['code']) ?>"
                             loading="lazy">
                        <div class="nft-city-badge"><?= htmlspecialchars($item['city']) ?></div>
                    </div>
                    <div class="nft-code-fixed"><?= htmlspecialchars($item['code']) ?></div>
                    <div class="nft-actions-fixed">
                        <?php if (in_array($item['id'], $userRequestedNfts)): ?>
                            <?php 
                            $request = array_filter($userPurchaseRequests, function($req) use ($item) {
                                return $req['nft_id'] == $item['id'];
                            });
                            $request = reset($request);
                            ?>
                            <a href="/nft/purchase_detail.php?id=<?= $item['id'] ?>" 
                               class="btn btn-info btn-sm"
                               data-bs-toggle="tooltip" 
                               title="您已求购 - <?= $request['currency'] == 'cny' ? '¥' : '人气值' ?><?= number_format($request['price'], $request['currency'] == 'cny' ? 2 : 0) ?>">
                                <i class="fas fa-edit"></i> 修改求购
                            </a>
                        <?php else: ?>
                            <a href="/nft/purchase_detail.php?id=<?= $item['id'] ?>" 
                               class="btn btn-primary btn-sm">
                                <i class="fas fa-hand-holding-usd"></i> 我要求购
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <!-- 分页 -->
    <?php if ($totalNfts > $perPage): ?>
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
                $endPage = min(ceil($totalNfts / $perPage), $page + 2);
                
                for ($i = $startPage; $i <= $endPage; $i++): ?>
                    <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                        <a class="page-link" href="?<?= 
                            http_build_query(array_merge($_GET, ['page' => $i])) 
                        ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
                
                <!-- 最后一页 -->
                <?php if ($page < ceil($totalNfts / $perPage) - 2): ?>
                    <?php if ($page < ceil($totalNfts / $perPage) - 3): ?>
                        <li class="page-item disabled">
                            <span class="page-link">...</span>
                        </li>
                    <?php endif; ?>
                    <li class="page-item">
                        <a class="page-link" href="?<?= 
                            http_build_query(array_merge($_GET, ['page' => ceil($totalNfts / $perPage)])) 
                        ?>"><?= ceil($totalNfts / $perPage) ?></a>
                    </li>
                <?php endif; ?>
                
                <!-- 下一页 -->
                <li class="page-item <?= $page >= ceil($totalNfts / $perPage) ? 'disabled' : '' ?>">
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

<?php require_once '../includes/footer.php'; ?>

<script>
// 启用工具提示
document.addEventListener('DOMContentLoaded', function() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
</script>