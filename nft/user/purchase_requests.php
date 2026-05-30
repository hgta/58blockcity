<?php
require_once '../../config/database.php';
require_once '../../classes/PurchaseRequest.php';
require_once '../../classes/NFT.php';
require_once '../../classes/User.php';
require_once '../includes/auth.php';
require_once '../../classes/City.php';

// 验证登录状态
checkLogin();

$userId = $_SESSION['user_id'];
$purchaseRequest = new PurchaseRequest($pdo);
$nft = new NFT($pdo);
$user = new User($pdo);
$cityObj = new City($pdo);

// 获取搜索和筛选参数
$status = $_GET['status'] ?? 'pending';
$searchCode = $_GET['code'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1)); 
$perPage = 15;

// 获取用户求购记录
$requests = $purchaseRequest->getUserPurchaseRequests($userId, $status, $searchCode);
$totalRequests = count($requests);
$paginatedRequests = array_slice($requests, ($page - 1) * $perPage, $perPage);
$totalPages = ceil($totalRequests / $perPage);

// 状态选项
$statusOptions = [
    'pending' => '待响应',
    'completed' => '已完成',
    'canceled' => '已取消',
    'all' => '全部状态'
];
?>

<?php include '../includes/header.php'; ?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0"><i class="fas fa-hand-holding-usd me-2"></i>我的求购记录</h2>
        <div>
            <span class="badge bg-primary"><?= $totalRequests ?> 条记录</span>
            <a href="/nft/purchase_list.php" class="btn btn-sm btn-outline-success ms-2">
                <i class="fas fa-plus"></i> 新增求购
            </a>
        </div>
    </div>

    <!-- 搜索和筛选栏 -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="get" class="row g-3">
                <div class="col-md-4">
                    <label for="codeSearch" class="form-label">NFT编号</label>
                    <input type="text" class="form-control" id="codeSearch" name="code" 
                           placeholder="输入NFT编号" value="<?= htmlspecialchars($searchCode) ?>">
                </div>
                <div class="col-md-4">
                    <label for="statusFilter" class="form-label">状态</label>
                    <select class="form-select" id="statusFilter" name="status">
                        <?php foreach ($statusOptions as $value => $label): ?>
                            <option value="<?= $value ?>" <?= $status === $value ? 'selected' : '' ?>>
                                <?= $label ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="fas fa-search"></i> 搜索
                    </button>
                    <a href="purchase_requests.php" class="btn btn-outline-secondary">
                        <i class="fas fa-sync-alt"></i> 重置
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- 求购记录表格 -->
    <div class="card">
        <div class="card-body p-0">
            <?php if (empty($paginatedRequests)): ?>
                <div class="empty-state p-5 text-center">
                    <div class="empty-icon text-muted mb-3">
                        <i class="fas fa-hand-holding-usd fa-3x"></i>
                    </div>
                    <h3 class="h5">没有找到求购记录</h3>
                    <p class="text-muted">您尚未创建任何求购请求或不符合筛选条件</p>
                    <a href="/nft/purchase_list.php" class="btn btn-primary mt-3">
                        <i class="fas fa-hand-holding-usd me-2"></i>去求购市场
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th width="80px">NFT</th>
                                <th>编号</th>
                                <th>城市</th>
                                <th>求购价格</th>
                                <th>状态</th>
                                <th>创建时间</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($paginatedRequests as $request): ?>
                                <?php 
                                    $nftInfo = $nft->getNftById($request['nft_id']);
                                    $cityName = $cityObj->getCityName($request['city_id']) ?? '未知城市';
                                ?>
                                <tr>
                                    <!-- NFT头像 -->
                                    <td>
                                        <?php if ($nftInfo): ?>
                                            <div class="avatar-circle-sm">
                                                <img src="../avatar/<?= htmlspecialchars($nftInfo['base_image']) ?>" 
                                                     class="avatar-img" 
                                                     alt="NFT <?= htmlspecialchars($nftInfo['code']) ?>">
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <!-- 编号 -->
                                    <td class="align-middle">
                                        <?= $nftInfo ? htmlspecialchars($nftInfo['code']) : '已删除' ?>
                                    </td>
                                    
                                    <!-- 城市 -->
                                    <td class="align-middle">
                                        <i class="fas fa-map-marker-alt text-muted me-1"></i>
                                        <?= htmlspecialchars($cityName) ?>
                                    </td>
                                    
                                    <!-- 求购价格 -->
                                    <td class="align-middle">
                                        <span class="fw-bold text-primary">
                                            <?= number_format($request['price'], $request['currency'] == 'cny' ? 2 : 0) ?>
                                        </span>
                                        <small class="text-muted">
                                            <?= $request['currency'] == 'cny' ? '¥' : '人气值' ?>
                                        </small>
                                    </td>
                                    
                                    <!-- 状态 -->
                                    <td class="align-middle">
                                        <?php 
                                            $statusClass = [
                                                'pending' => 'badge bg-warning',
                                                'completed' => 'badge bg-success',
                                                'canceled' => 'badge bg-secondary'
                                            ][$request['status']] ?? 'badge bg-light';
                                        ?>
                                        <span class="<?= $statusClass ?>">
                                            <?= $statusOptions[$request['status']] ?? $request['status'] ?>
                                        </span>
                                    </td>
                                    
                                    <!-- 创建时间 -->
                                    <td class="align-middle">
                                        <?= date('Y-m-d H:i', strtotime($request['created_at'])) ?>
                                    </td>
                                    
                                    <!-- 操作 -->
                                    <td class="align-middle">
                                        <div class="d-flex gap-2">
                                            <a href="/nft/view.php?id=<?= $request['nft_id'] ?>" 
                                               class="btn btn-sm btn-outline-primary"
                                               title="查看NFT">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <?php if ($request['status'] === 'pending'): ?>
                                                <a href="edit_purchase.php?id=<?= $request['id'] ?>" 
                                                   class="btn btn-sm btn-outline-warning"
                                                   title="编辑">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="cancel_purchase.php?id=<?= $request['id'] ?>" 
                                                   class="btn btn-sm btn-outline-danger"
                                                   title="取消"
                                                   onclick="return confirm('确定要取消这条求购请求吗？')">
                                                    <i class="fas fa-times"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- 分页 -->
    <?php if ($totalPages > 1): ?>
        <nav class="mt-4">
            <ul class="pagination justify-content-center">
                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="?<?= 
                        http_build_query(array_merge($_GET, ['page' => $page - 1])) 
                    ?>">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                </li>
                
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                        <a class="page-link" href="?<?= 
                            http_build_query(array_merge($_GET, ['page' => $i])) 
                        ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
                
                <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                    <a class="page-link" href="?<?= 
                        http_build_query(array_merge($_GET, ['page' => $page + 1])) 
                    ?>">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                </li>
            </ul>
        </nav>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>

<script>
// 启用工具提示
document.addEventListener('DOMContentLoaded', function() {
    $('[data-bs-toggle="tooltip"]').tooltip();
});
</script>