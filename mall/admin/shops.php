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

// 店铺状态统计
$statsStmt = $pdo->prepare("SELECT status, COUNT(*) as count FROM shops GROUP BY status");
$statsStmt->execute();
$statusStats = $statsStmt->fetchAll(PDO::FETCH_ASSOC);
$totalActive = $totalPending = $totalSuspended = $totalClosed = 0;
foreach ($statusStats as $stat) {
    switch ($stat['status']) {
        case 'active': $totalActive = $stat['count']; break;
        case 'pending': $totalPending = $stat['count']; break;
        case 'suspended': $totalSuspended = $stat['count']; break;
        case 'closed': $totalClosed = $stat['count']; break;
    }
}

// 统一后台框架
$admin_site_config = ['site' => 'mall', 'page_title' => '店铺管理'];
require_once '../../shared/admin/admin-header.php';
?>

<?php if ($successMessage): ?>
<div class="admin-card" style="border-left:4px solid #22c55e; margin-bottom:16px;">
    <div class="admin-card-body" style="color:#86efac;"><?= htmlspecialchars($successMessage) ?></div>
</div>
<?php endif; ?>

<?php if ($errorMessage): ?>
<div class="admin-card" style="border-left:4px solid #ef4444; margin-bottom:16px;">
    <div class="admin-card-body" style="color:#fca5a5;"><?= htmlspecialchars($errorMessage) ?></div>
</div>
<?php endif; ?>

<!-- 统计卡片 -->
<div class="admin-stats-grid" style="margin-bottom:20px;">
    <div class="admin-stat-card">
        <div class="stat-icon info"><i class="fas fa-store"></i></div>
        <div class="stat-value"><?= $totalShops ?></div>
        <div class="stat-label">店铺总数</div>
    </div>
    <div class="admin-stat-card">
        <div class="stat-icon success"><i class="fas fa-check-circle"></i></div>
        <div class="stat-value"><?= $totalActive ?></div>
        <div class="stat-label">营业中</div>
    </div>
    <div class="admin-stat-card">
        <div class="stat-icon warning"><i class="fas fa-clock"></i></div>
        <div class="stat-value"><?= $totalPending ?></div>
        <div class="stat-label">待审核</div>
    </div>
    <div class="admin-stat-card">
        <div class="stat-icon danger"><i class="fas fa-pause-circle"></i></div>
        <div class="stat-value"><?= $totalSuspended + $totalClosed ?></div>
        <div class="stat-label">已暂停/关闭</div>
    </div>
</div>

<!-- 搜索和筛选 -->
<div class="admin-card" style="margin-bottom:20px;">
    <div class="admin-card-body">
        <form method="GET" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
            <input type="text" name="search" placeholder="搜索店铺名称、店主用户名或邮箱" value="<?= htmlspecialchars($search) ?>"
                   style="flex:1;min-width:200px;padding:8px 12px;background:#0f172a;border:1px solid #334155;border-radius:8px;color:#f1f5f9;font-size:13px;">
            <select name="status" style="padding:8px 12px;background:#0f172a;border:1px solid #334155;border-radius:8px;color:#f1f5f9;font-size:13px;">
                <option value="all">所有状态</option>
                <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>待审核</option>
                <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>营业中</option>
                <option value="suspended" <?= $status === 'suspended' ? 'selected' : '' ?>>已暂停</option>
                <option value="closed" <?= $status === 'closed' ? 'selected' : '' ?>>已关闭</option>
            </select>
            <select name="sort" style="padding:8px 12px;background:#0f172a;border:1px solid #334155;border-radius:8px;color:#f1f5f9;font-size:13px;">
                <option value="created_at_desc" <?= $sort === 'created_at_desc' ? 'selected' : '' ?>>最新创建</option>
                <option value="created_at_asc" <?= $sort === 'created_at_asc' ? 'selected' : '' ?>>最早创建</option>
                <option value="name_asc" <?= $sort === 'name_asc' ? 'selected' : '' ?>>名称 A-Z</option>
                <option value="name_desc" <?= $sort === 'name_desc' ? 'selected' : '' ?>>名称 Z-A</option>
                <option value="sales_desc" <?= $sort === 'sales_desc' ? 'selected' : '' ?>>销量最高</option>
                <option value="sales_asc" <?= $sort === 'sales_asc' ? 'selected' : '' ?>>销量最低</option>
            </select>
            <button type="submit" class="admin-btn admin-btn-primary admin-btn-sm"><i class="fas fa-search"></i> 搜索</button>
            <a href="shops.php" class="admin-btn admin-btn-secondary admin-btn-sm"><i class="fas fa-redo"></i> 重置</a>
        </form>
    </div>
</div>

<!-- 店铺列表 -->
<div class="admin-card">
    <div class="admin-card-header">
        <span class="admin-card-title">店铺列表 (<?= $totalShops ?>)</span>
    </div>
    <div class="admin-card-body" style="padding:0;">
        <?php if ($shops): ?>
            <table class="admin-data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>店铺信息</th>
                        <th>店主</th>
                        <th>销量/评分</th>
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
                            <div style="display:flex;align-items:center;gap:10px;">
                                <?php if (!empty($shopItem['shop_logo'])): ?>
                                    <img src="<?= htmlspecialchars($shopItem['shop_logo']) ?>" style="width:36px;height:36px;border-radius:6px;object-fit:cover;">
                                <?php else: ?>
                                    <div style="width:36px;height:36px;border-radius:6px;background:#1e293b;display:flex;align-items:center;justify-content:center;color:#64748b;">
                                        <i class="fas fa-store"></i>
                                    </div>
                                <?php endif; ?>
                                <div>
                                    <strong style="color:#f1f5f9;"><?= htmlspecialchars($shopItem['shop_name']) ?></strong>
                                    <?php if ($shopItem['is_recommended']): ?>
                                        <span class="admin-badge success">推荐</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div style="color:#e2e8f0;"><?= htmlspecialchars($shopItem['username']) ?></div>
                            <div style="font-size:12px;color:#64748b;"><?= htmlspecialchars($shopItem['email']) ?></div>
                        </td>
                        <td>
                            <div>销量: <?= number_format($shopItem['total_sales']) ?></div>
                            <div style="font-size:12px;color:#64748b;">评分: <?= number_format($shopItem['rating'], 1) ?></div>
                        </td>
                        <td>
                            <span class="admin-badge <?= 
                                $shopItem['status'] == 'active' ? 'success' : 
                                ($shopItem['status'] == 'pending' ? 'warning' : 
                                ($shopItem['status'] == 'suspended' ? 'danger' : 'default'))
                            ?>">
                                <?= $shopItem['status'] == 'active' ? '营业中' : 
                                    ($shopItem['status'] == 'pending' ? '待审核' : 
                                    ($shopItem['status'] == 'suspended' ? '已暂停' : '已关闭'))
                                ?>
                            </span>
                        </td>
                        <td style="color:#94a3b8;"><?= date('Y-m-d H:i', strtotime($shopItem['created_at'])) ?></td>
                        <td>
                            <div style="display:flex;gap:4px;">
                                <a href="../shop/view.php?id=<?= $shopItem['id'] ?>" target="_blank" class="admin-btn admin-btn-sm admin-btn-secondary">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <?php if ($shopItem['status'] == 'pending'): ?>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="shop_id" value="<?= $shopItem['id'] ?>">
                                        <input type="hidden" name="action" value="approve_shop">
                                        <button type="submit" class="admin-btn admin-btn-sm" style="background:#14532d;color:#86efac;border:none;">
                                            <i class="fas fa-check"></i>
                                        </button>
                                    </form>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="shop_id" value="<?= $shopItem['id'] ?>">
                                        <input type="hidden" name="action" value="reject_shop">
                                        <button type="submit" class="admin-btn admin-btn-sm" style="background:#7f1d1d;color:#fca5a5;border:none;">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </form>
                                <?php elseif ($shopItem['status'] == 'active'): ?>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="shop_id" value="<?= $shopItem['id'] ?>">
                                        <input type="hidden" name="action" value="suspend_shop">
                                        <button type="submit" class="admin-btn admin-btn-sm admin-btn-secondary">
                                            <i class="fas fa-pause"></i>
                                        </button>
                                    </form>
                                <?php elseif ($shopItem['status'] == 'suspended'): ?>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="shop_id" value="<?= $shopItem['id'] ?>">
                                        <input type="hidden" name="action" value="approve_shop">
                                        <button type="submit" class="admin-btn admin-btn-sm" style="background:#14532d;color:#86efac;border:none;">
                                            <i class="fas fa-play"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('确定要删除吗？此操作不可恢复！');">
                                    <input type="hidden" name="shop_id" value="<?= $shopItem['id'] ?>">
                                    <input type="hidden" name="action" value="delete_shop">
                                    <button type="submit" class="admin-btn admin-btn-sm" style="background:#7f1d1d;color:#fca5a5;border:none;">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="admin-empty-state">
                <i class="fas fa-store"></i>
                <h4>暂无店铺数据</h4>
                <p>没有找到符合条件的店铺</p>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($totalPages > 1): ?>
    <div style="padding:16px 20px;display:flex;justify-content:center;gap:6px;border-top:1px solid #1e293b;">
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" 
               class="admin-btn admin-btn-sm <?= $i == $page ? 'admin-btn-primary' : 'admin-btn-secondary' ?>" 
               style="min-width:36px;justify-content:center;"><?= $i ?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>

<?php require_once '../../shared/admin/admin-footer.php'; ?>