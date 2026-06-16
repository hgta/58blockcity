<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

// 检查管理员权限
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

require_once '../../classes/Shop.php';
require_once '../../classes/User.php';

$shop = new Shop($pdo);
$user = new User($pdo);

// ===== POST 处理（必须在任何 HTML 输出之前） =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $shopId = intval($_POST['shop_id']);
    
    switch ($_POST['action']) {
        case 'approve_shop':
            $updateStmt = $pdo->prepare("UPDATE shops SET status = 'active', updated_at = NOW() WHERE id = ?");
            if ($updateStmt->execute([$shopId])) {
                $_SESSION['success_message'] = '店铺已审核通过';
            } else {
                $_SESSION['error_message'] = '操作失败，请重试';
            }
            break;
            
        case 'suspend_shop':
            $updateStmt = $pdo->prepare("UPDATE shops SET status = 'suspended', updated_at = NOW() WHERE id = ?");
            if ($updateStmt->execute([$shopId])) {
                $_SESSION['success_message'] = '店铺已暂停';
            } else {
                $_SESSION['error_message'] = '操作失败，请重试';
            }
            break;
            
        case 'reject_shop':
            $updateStmt = $pdo->prepare("UPDATE shops SET status = 'closed', updated_at = NOW() WHERE id = ?");
            if ($updateStmt->execute([$shopId])) {
                $_SESSION['success_message'] = '店铺已拒绝';
            } else {
                $_SESSION['error_message'] = '操作失败，请重试';
            }
            break;
            
        case 'delete_shop':
            $orderCheck = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE shop_id = ?");
            $orderCheck->execute([$shopId]);
            $orderCount = $orderCheck->fetchColumn();
            
            if ($orderCount > 0) {
                $_SESSION['error_message'] = '该店铺有订单记录，无法删除';
            } else {
                $deleteStmt = $pdo->prepare("DELETE FROM shops WHERE id = ?");
                if ($deleteStmt->execute([$shopId])) {
                    $_SESSION['success_message'] = '店铺已删除';
                } else {
                    $_SESSION['error_message'] = '删除失败，请重试';
                }
            }
            break;
    }
    
    header("Location: shops.php?" . $_SERVER['QUERY_STRING']);
    exit;
}

// 显示消息
$successMessage = '';
$errorMessage = '';
if (isset($_SESSION['success_message'])) {
    $successMessage = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
if (isset($_SESSION['error_message'])) {
    $errorMessage = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

// 分页参数
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

// 搜索和筛选参数
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'created_at_desc';

// 构建查询条件
$whereConditions = [];
$params = [];

if (!empty($search)) {
    $whereConditions[] = "(s.shop_name LIKE ? OR u.username LIKE ? OR u.email LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if (!empty($status) && $status !== 'all') {
    $whereConditions[] = "s.status = ?";
    $params[] = $status;
}

$whereClause = $whereConditions ? "WHERE " . implode(" AND ", $whereConditions) : "";

// 排序处理
$orderBy = "s.created_at DESC";
switch ($sort) {
    case 'name_asc':        $orderBy = "s.shop_name ASC"; break;
    case 'name_desc':       $orderBy = "s.shop_name DESC"; break;
    case 'sales_asc':       $orderBy = "s.total_sales ASC"; break;
    case 'sales_desc':      $orderBy = "s.total_sales DESC"; break;
    case 'rating_asc':      $orderBy = "s.rating ASC"; break;
    case 'rating_desc':     $orderBy = "s.rating DESC"; break;
    case 'created_at_asc':  $orderBy = "s.created_at ASC"; break;
    case 'created_at_desc':
    default:                $orderBy = "s.created_at DESC"; break;
}

$stmt = $pdo->prepare("
    SELECT s.*, u.username, u.email 
    FROM shops s 
    LEFT JOIN users u ON s.user_id = u.id 
    $whereClause 
    ORDER BY $orderBy 
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$shops = $stmt->fetchAll(PDO::FETCH_ASSOC);

$countStmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM shops s 
    LEFT JOIN users u ON s.user_id = u.id 
    $whereClause
");
$countStmt->execute($params);
$totalShops = $countStmt->fetchColumn();
$totalPages = ceil($totalShops / $perPage);

require_once '../includes/header.php';
?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-md-2">
            <!-- 管理侧边栏 -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">管理后台</h5>
                </div>
                <div class="list-group list-group-flush">
                    <a href="dashboard.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-tachometer-alt"></i> 仪表板
                    </a>
                    <a href="shops.php" class="list-group-item list-group-item-action active">
                        <i class="fas fa-store"></i> 店铺管理
                    </a>
                    <a href="products.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-box"></i> 商品管理
                    </a>
                    <a href="orders.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-shopping-cart"></i> 订单管理
                    </a>
                    <a href="categories.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-tags"></i> 分类管理
                    </a>
                    <a href="users.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-users"></i> 用户管理
                    </a>
                </div>
            </div>
            
            <!-- 统计信息 -->
            <div class="card mt-3">
                <div class="card-header">
                    <h6 class="card-title mb-0">店铺统计</h6>
                </div>
                <div class="card-body">
                    <?php
                    $statsStmt = $pdo->prepare("
                        SELECT 
                            status,
                            COUNT(*) as count 
                        FROM shops 
                        GROUP BY status
                    ");
                    $statsStmt->execute();
                    $statusStats = $statsStmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    $totalActive = 0;
                    $totalPending = 0;
                    $totalSuspended = 0;
                    $totalClosed = 0;
                    
                    foreach ($statusStats as $stat) {
                        switch ($stat['status']) {
                            case 'active':
                                $totalActive = $stat['count'];
                                break;
                            case 'pending':
                                $totalPending = $stat['count'];
                                break;
                            case 'suspended':
                                $totalSuspended = $stat['count'];
                                break;
                            case 'closed':
                                $totalClosed = $stat['count'];
                                break;
                        }
                    }
                    ?>
                    <div class="small">
                        <div class="d-flex justify-content-between">
                            <span>营业中:</span>
                            <span class="text-success"><?= $totalActive ?></span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span>待审核:</span>
                            <span class="text-warning"><?= $totalPending ?></span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span>已暂停:</span>
                            <span class="text-danger"><?= $totalSuspended ?></span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span>已关闭:</span>
                            <span class="text-muted"><?= $totalClosed ?></span>
                        </div>
                        <hr class="my-2">
                        <div class="d-flex justify-content-between font-weight-bold">
                            <span>总计:</span>
                            <span><?= $totalShops ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-10">
            <!-- 页面标题和操作 -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>店铺管理</h2>
                <div>
                    <a href="shops-export.php" class="btn btn-outline-secondary">
                        <i class="fas fa-download"></i> 导出数据
                    </a>
                </div>
            </div>
            
            <!-- 消息提示 -->
            <?php if ($successMessage): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($successMessage) ?>
                    <button type="button" class="close" data-dismiss="alert">
                        <span>&times;</span>
                    </button>
                </div>
            <?php endif; ?>
            
            <?php if ($errorMessage): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($errorMessage) ?>
                    <button type="button" class="close" data-dismiss="alert">
                        <span>&times;</span>
                    </button>
                </div>
            <?php endif; ?>
            
            <!-- 搜索和筛选 -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <input type="text" class="form-control" name="search" placeholder="搜索店铺名称、店主用户名或邮箱" 
                                   value="<?= htmlspecialchars($search) ?>">
                        </div>
                        <div class="col-md-2">
                            <select class="form-control" name="status">
                                <option value="all">所有状态</option>
                                <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>待审核</option>
                                <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>营业中</option>
                                <option value="suspended" <?= $status === 'suspended' ? 'selected' : '' ?>>已暂停</option>
                                <option value="closed" <?= $status === 'closed' ? 'selected' : '' ?>>已关闭</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select class="form-control" name="sort">
                                <option value="created_at_desc" <?= $sort === 'created_at_desc' ? 'selected' : '' ?>>最新创建</option>
                                <option value="created_at_asc" <?= $sort === 'created_at_asc' ? 'selected' : '' ?>>最早创建</option>
                                <option value="name_asc" <?= $sort === 'name_asc' ? 'selected' : '' ?>>名称 A-Z</option>
                                <option value="name_desc" <?= $sort === 'name_desc' ? 'selected' : '' ?>>名称 Z-A</option>
                                <option value="sales_desc" <?= $sort === 'sales_desc' ? 'selected' : '' ?>>销量最高</option>
                                <option value="sales_asc" <?= $sort === 'sales_asc' ? 'selected' : '' ?>>销量最低</option>
                                <option value="rating_desc" <?= $sort === 'rating_desc' ? 'selected' : '' ?>>评分最高</option>
                                <option value="rating_asc" <?= $sort === 'rating_asc' ? 'selected' : '' ?>>评分最低</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary btn-block">
                                <i class="fas fa-search"></i> 搜索
                            </button>
                        </div>
                        <div class="col-md-2">
                            <a href="shops.php" class="btn btn-outline-secondary btn-block">
                                <i class="fas fa-redo"></i> 重置
                            </a>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- 店铺列表 -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">店铺列表 (<?= $totalShops ?>)</h5>
                </div>
                <div class="card-body p-0">
                    <?php if ($shops): ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="thead-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>店铺信息</th>
                                        <th>店主信息</th>
                                        <th>统计信息</th>
                                        <th>状态</th>
                                        <th>创建时间</th>
                                        <th>操作</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($shops as $shopItem): ?>
                                        <tr>
                                            <td><?= $shopItem['id'] ?></td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="shop-logo mr-3">
                                                        <?php if (!empty($shopItem['shop_logo'])): ?>
                                                            <img src="<?= htmlspecialchars($shopItem['shop_logo']) ?>" 
                                                                 alt="<?= htmlspecialchars($shopItem['shop_name']) ?>" 
                                                                 class="rounded" style="width: 40px; height: 40px; object-fit: cover;">
                                                        <?php else: ?>
                                                            <div class="bg-light rounded d-flex align-items-center justify-content-center" 
                                                                 style="width: 40px; height: 40px;">
                                                                <i class="fas fa-store text-muted"></i>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div>
                                                        <strong><?= htmlspecialchars($shopItem['shop_name']) ?></strong>
                                                        <?php if ($shopItem['is_recommended']): ?>
                                                            <span class="badge badge-success badge-sm ml-1">推荐</span>
                                                        <?php endif; ?>
                                                        <div class="text-muted small">
                                                            <?= htmlspecialchars(mb_substr($shopItem['shop_description'] ?? '', 0, 30)) ?>...
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="small">
                                                    <div><strong><?= htmlspecialchars($shopItem['username']) ?></strong></div>
                                                    <div class="text-muted"><?= htmlspecialchars($shopItem['email']) ?></div>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="small">
                                                    <div>销量: <?= number_format($shopItem['total_sales']) ?></div>
                                                    <div>评分: <?= number_format($shopItem['rating'], 1) ?></div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge badge-<?= 
                                                    $shopItem['status'] == 'active' ? 'success' : 
                                                    ($shopItem['status'] == 'pending' ? 'warning' : 
                                                    ($shopItem['status'] == 'suspended' ? 'danger' : 'secondary'))
                                                ?>">
                                                    <?= $shopItem['status'] == 'active' ? '营业中' : 
                                                        ($shopItem['status'] == 'pending' ? '待审核' : 
                                                        ($shopItem['status'] == 'suspended' ? '已暂停' : '已关闭'))
                                                    ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="small">
                                                    <?= date('Y-m-d', strtotime($shopItem['created_at'])) ?>
                                                    <div class="text-muted">
                                                        <?= date('H:i', strtotime($shopItem['created_at'])) ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="../shop/view.php?id=<?= $shopItem['id'] ?>" 
                                                       class="btn btn-outline-primary" target="_blank" title="查看店铺">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    
                                                    <?php if ($shopItem['status'] == 'pending'): ?>
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="shop_id" value="<?= $shopItem['id'] ?>">
                                                            <input type="hidden" name="action" value="approve_shop">
                                                            <button type="submit" class="btn btn-outline-success" title="审核通过">
                                                                <i class="fas fa-check"></i>
                                                            </button>
                                                        </form>
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="shop_id" value="<?= $shopItem['id'] ?>">
                                                            <input type="hidden" name="action" value="reject_shop">
                                                            <button type="submit" class="btn btn-outline-danger" title="拒绝申请">
                                                                <i class="fas fa-times"></i>
                                                            </button>
                                                        </form>
                                                    <?php elseif ($shopItem['status'] == 'active'): ?>
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="shop_id" value="<?= $shopItem['id'] ?>">
                                                            <input type="hidden" name="action" value="suspend_shop">
                                                            <button type="submit" class="btn btn-outline-warning" title="暂停店铺">
                                                                <i class="fas fa-pause"></i>
                                                            </button>
                                                        </form>
                                                    <?php elseif ($shopItem['status'] == 'suspended'): ?>
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="shop_id" value="<?= $shopItem['id'] ?>">
                                                            <input type="hidden" name="action" value="approve_shop">
                                                            <button type="submit" class="btn btn-outline-success" title="恢复营业">
                                                                <i class="fas fa-play"></i>
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                    
                                                    <form method="POST" class="d-inline" onsubmit="return confirm('确定要删除这个店铺吗？此操作不可恢复！');">
                                                        <input type="hidden" name="shop_id" value="<?= $shopItem['id'] ?>">
                                                        <input type="hidden" name="action" value="delete_shop">
                                                        <button type="submit" class="btn btn-outline-danger" title="删除店铺">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-store fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">暂无店铺数据</h5>
                            <p class="text-muted">没有找到符合条件的店铺</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- 分页 -->
                <?php if ($totalPages > 1): ?>
                    <div class="card-footer">
                        <nav aria-label="Page navigation">
                            <ul class="pagination justify-content-center mb-0">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">
                                            <i class="fas fa-chevron-left"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>
                                
                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                    <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>">
                                            <?= $i ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>
                                
                                <?php if ($page < $totalPages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">
                                            <i class="fas fa-chevron-right"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
.badge-sm {
    font-size: 0.7em;
    padding: 0.25em 0.4em;
}
.shop-logo img {
    border: 1px solid #dee2e6;
}
.table td {
    vertical-align: middle;
}
.btn-group-sm > .btn {
    padding: 0.25rem 0.5rem;
}
</style>

<?php require_once '../includes/footer.php'; ?>