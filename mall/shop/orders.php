<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../classes/Shop.php';
require_once '../../classes/Order.php';

// 检查用户是否已登录
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$shop = new Shop($pdo);
$order = new Order($pdo);

// 获取用户店铺信息
$userShop = $shop->getShopByUserId($_SESSION['user_id']);
if (!$userShop) {
    header('Location: create.php');
    exit;
}

$shopId = isset($_GET['id']) ? intval($_GET['id']) : $userShop['id'];
if ($userShop['id'] != $shopId) {
    header('Location: orders.php?id=' . $userShop['id']);
    exit;
}

$error = '';
$success = '';

// 处理订单状态更新
if (isset($_GET['action']) && isset($_GET['order_id'])) {
    $orderId = intval($_GET['order_id']);
    $actionType = $_GET['action'];
    $orderInfo = $order->getOrderById($orderId);

    if ($orderInfo && $orderInfo['shop_id'] == $shopId) {
        if ($actionType === 'ship' && $orderInfo['status'] === 'paid') {
            $order->updateOrderStatus($orderId, 'shipped');
            $success = '订单已标记为发货';
        } elseif ($actionType === 'complete' && $orderInfo['status'] === 'shipped') {
            $order->updateOrderStatus($orderId, 'completed');
            $success = '订单已标记为完成';
        } elseif ($actionType === 'cancel' && in_array($orderInfo['status'], ['pending', 'paid'])) {
            $order->updateOrderStatus($orderId, 'cancelled');
            $success = '订单已取消';
        } else {
            $error = '当前状态不允许该操作';
        }
    } else {
        $error = '订单不存在或无权操作';
    }

    header('Location: orders.php?id=' . $shopId . '&' . ($error ? 'error=' . urlencode($error) : 'success=' . urlencode($success)));
    exit;
}

if (isset($_GET['success'])) $success = $_GET['success'];
if (isset($_GET['error'])) $error = $_GET['error'];

// 筛选与分页参数
$statusFilter = isset($_GET['status']) ? $_GET['status'] : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 10;

// 获取订单统计
$statusStats = $order->getShopOrderStatusStats($shopId);

// 获取订单列表
$filters = [
    'status' => $statusFilter,
    'search' => $search,
    'page' => $page,
    'per_page' => $perPage
];
$orders = $order->getShopOrdersWithFilter($shopId, $filters);
$totalOrders = $order->getShopOrderCountWithFilter($shopId, $filters);
$totalPages = ceil($totalOrders / $perPage);

// 状态映射
$statusMap = [
    'pending' => '待付款',
    'paid' => '待发货',
    'shipped' => '已发货',
    'completed' => '已完成',
    'cancelled' => '已取消',
    'refunded' => '已退款'
];
$statusClassMap = [
    'pending' => 'status-pending',
    'paid' => 'status-paid',
    'shipped' => 'status-shipped',
    'completed' => 'status-completed',
    'cancelled' => 'status-cancelled',
    'refunded' => 'status-refunded'
];
$statusIcons = [
    'pending' => 'fa-clock',
    'paid' => 'fa-check-circle',
    'shipped' => 'fa-truck',
    'completed' => 'fa-check-double',
    'cancelled' => 'fa-times-circle',
    'refunded' => 'fa-undo'
];
require_once '../includes/header.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-3">
            <!-- 店铺管理侧边栏 -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">店铺管理</h5>
                </div>
                <div class="list-group list-group-flush">
                    <a href="manage.php?id=<?= $shopId ?>" class="list-group-item list-group-item-action">
                        <i class="fas fa-tachometer-alt"></i> 店铺概览
                    </a>
                    <a href="products.php?id=<?= $shopId ?>" class="list-group-item list-group-item-action">
                        <i class="fas fa-box"></i> 商品管理
                    </a>
                    <a href="products.php?action=add&id=<?= $shopId ?>" class="list-group-item list-group-item-action">
                        <i class="fas fa-plus"></i> 添加商品
                    </a>
                    <a href="orders.php?id=<?= $shopId ?>" class="list-group-item list-group-item-action active">
                        <i class="fas fa-shopping-cart"></i> 订单管理
                    </a>
                    <a href="coupons.php?id=<?= $shopId ?>" class="list-group-item list-group-item-action">
                        <i class="fas fa-ticket-alt"></i> 优惠券
                    </a>
                    <a href="payment-settings.php?id=<?= $shopId ?>" class="list-group-item list-group-item-action">
                        <i class="fas fa-cog"></i> 支付设置
                    </a>
                </div>
            </div>
        </div>

        <div class="col-md-9">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="card-title mb-0">订单管理</h4>
                    <span class="text-muted small">共 <?= $totalOrders ?> 笔订单</span>
                </div>
                <div class="card-body">
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>
                    <?php if ($success): ?>
                        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                    <?php endif; ?>

                    <!-- 订单统计卡片 -->
                    <div class="order-stats-row">
                        <div class="order-stat-card" onclick="window.location='?id=<?= $shopId ?>'">
                            <div class="stat-icon"><i class="fas fa-file-invoice"></i></div>
                            <div class="stat-value"><?= $statusStats['total'] ?? 0 ?></div>
                            <div class="stat-label">全部订单</div>
                        </div>
                        <div class="order-stat-card <?= $statusFilter === 'pending' ? 'active' : '' ?>" onclick="window.location='?id=<?= $shopId ?>&status=pending'">
                            <div class="stat-icon"><i class="fas fa-clock"></i></div>
                            <div class="stat-value"><?= $statusStats['pending'] ?? 0 ?></div>
                            <div class="stat-label">待付款</div>
                        </div>
                        <div class="order-stat-card <?= $statusFilter === 'paid' ? 'active' : '' ?>" onclick="window.location='?id=<?= $shopId ?>&status=paid'">
                            <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                            <div class="stat-value"><?= $statusStats['paid'] ?? 0 ?></div>
                            <div class="stat-label">待发货</div>
                        </div>
                        <div class="order-stat-card <?= $statusFilter === 'shipped' ? 'active' : '' ?>" onclick="window.location='?id=<?= $shopId ?>&status=shipped'">
                            <div class="stat-icon"><i class="fas fa-truck"></i></div>
                            <div class="stat-value"><?= $statusStats['shipped'] ?? 0 ?></div>
                            <div class="stat-label">已发货</div>
                        </div>
                        <div class="order-stat-card <?= $statusFilter === 'completed' ? 'active' : '' ?>" onclick="window.location='?id=<?= $shopId ?>&status=completed'">
                            <div class="stat-icon"><i class="fas fa-check-double"></i></div>
                            <div class="stat-value"><?= $statusStats['completed'] ?? 0 ?></div>
                            <div class="stat-label">已完成</div>
                        </div>
                        <div class="order-stat-card <?= $statusFilter === 'cancelled' ? 'active' : '' ?>" onclick="window.location='?id=<?= $shopId ?>&status=cancelled'">
                            <div class="stat-icon"><i class="fas fa-times-circle"></i></div>
                            <div class="stat-value"><?= $statusStats['cancelled'] ?? 0 ?></div>
                            <div class="stat-label">已取消</div>
                        </div>
                    </div>

                    <!-- 搜索栏 -->
                    <div class="order-search-bar">
                        <div class="search-box-sm">
                            <input type="text" id="orderSearch" placeholder="搜索订单号、买家名或商品名..." value="<?= htmlspecialchars($search) ?>">
                            <button onclick="doOrderSearch()"><i class="fas fa-search"></i></button>
                        </div>
                        <?php if ($search): ?>
                            <a href="?id=<?= $shopId ?>" class="clear-search">清除搜索</a>
                        <?php endif; ?>
                    </div>

                    <!-- 订单列表 -->
                    <?php if (empty($orders)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-file-invoice fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">暂无订单</h5>
                            <p class="text-muted">当前条件下没有订单数据</p>
                        </div>
                    <?php else: ?>
                        <div class="order-list">
                            <?php foreach ($orders as $o): ?>
                                <div class="order-row">
                                    <div class="order-row-header">
                                        <div class="order-meta">
                                            <span class="order-no"><?= htmlspecialchars($o['order_no']) ?></span>
                                            <span class="order-time"><?= date('Y-m-d H:i', strtotime($o['created_at'])) ?></span>
                                        </div>
                                        <span class="order-status-badge <?= $statusClassMap[$o['status']] ?? '' ?>">
                                            <i class="fas <?= $statusIcons[$o['status']] ?? 'fa-circle' ?> mr-1"></i>
                                            <?= $statusMap[$o['status']] ?? '未知' ?>
                                        </span>
                                    </div>
                                    <div class="order-row-body">
                                        <div class="buyer-info">
                                            <div class="buyer-avatar">
                                                <?php if (!empty($o['buyer_avatar'])): ?>
                                                    <img src="<?= htmlspecialchars($o['buyer_avatar']) ?>" alt="">
                                                <?php else: ?>
                                                    <div class="avatar-placeholder"><?= mb_substr($o['buyer_name'] ?? '用', 0, 1) ?></div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="buyer-detail">
                                                <div class="buyer-name"><?= htmlspecialchars($o['buyer_name'] ?? '未知用户') ?></div>
                                                <?php if (!empty($o['buyer_phone'])): ?>
                                                    <div class="buyer-phone"><?= htmlspecialchars($o['buyer_phone']) ?></div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="order-goods">
                                            <?php
                                            $items = $order->getOrderItems($o['id']);
                                            foreach ($items as $idx => $item):
                                                if ($idx >= 2) {
                                                    echo '<div class="more-goods">+' . (count($items) - 2) . ' 件商品</div>';
                                                    break;
                                                }
                                            ?>
                                                <div class="goods-item">
                                                    <img src="../<?= htmlspecialchars($item['product_image'] ?: 'assets/images/default-product.png') ?>" alt="" class="goods-thumb">
                                                    <div class="goods-info">
                                                        <div class="goods-name"><?= htmlspecialchars($item['product_name']) ?></div>
                                                        <div class="goods-qty">x<?= $item['quantity'] ?></div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                        <div class="order-amount">
                                            <div class="amount-label">实付金额</div>
                                            <div class="amount-value"><span style="font-weight:bold;color:#e74c3c;">Ⓟ</span><?= number_format($o['total_amount'], 0) ?> 人气值</div>
                                        </div>
                                        <div class="order-actions-col">
                                            <?php if ($o['status'] === 'paid'): ?>
                                                <a href="ship_order.php?id=<?= $shopId ?>&order_id=<?= $o['id'] ?>" class="btn btn-sm btn-primary mb-1">
                                                    <i class="fas fa-truck"></i> 发货
                                                </a>
                                            <?php elseif ($o['status'] === 'shipped'): ?>
                                                <a href="orders.php?action=complete&id=<?= $shopId ?>&order_id=<?= $o['id'] ?>" class="btn btn-sm btn-success mb-1" onclick="return confirm('确认完成订单？')">
                                                    <i class="fas fa-check"></i> 完成
                                                </a>
                                            <?php endif; ?>
                                            <?php if (in_array($o['status'], ['pending', 'paid'])): ?>
                                                <a href="orders.php?action=cancel&id=<?= $shopId ?>&order_id=<?= $o['id'] ?>" class="btn btn-sm btn-outline-secondary" onclick="return confirm('确认取消订单？')">
                                                    取消
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- 分页 -->
                        <?php if ($totalPages > 1): ?>
                            <nav class="mt-4">
                                <ul class="pagination justify-content-center">
                                    <?php if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">上一页</a>
                                        </li>
                                    <?php endif; ?>
                                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                        <?php if ($i == 1 || $i == $totalPages || ($i >= $page - 2 && $i <= $page + 2)): ?>
                                            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
                                            </li>
                                        <?php elseif ($i == $page - 3 || $i == $page + 3): ?>
                                            <li class="page-item disabled"><span class="page-link">...</span></li>
                                        <?php endif; ?>
                                    <?php endfor; ?>
                                    <?php if ($page < $totalPages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">下一页</a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* 订单统计卡片 */
.order-stats-row {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    gap: 10px;
    margin-bottom: 20px;
}
.order-stat-card {
    background: #fff;
    border: 1px solid #e9ecef;
    border-radius: 10px;
    padding: 14px 8px;
    text-align: center;
    cursor: pointer;
    transition: all 0.2s;
}
.order-stat-card:hover {
    border-color: #ff6b00;
    box-shadow: 0 2px 8px rgba(255,107,0,0.12);
}
.order-stat-card.active {
    border-color: #ff6b00;
    background: #fff7f0;
}
.order-stat-card .stat-icon {
    font-size: 18px;
    color: #6c757d;
    margin-bottom: 6px;
}
.order-stat-card.active .stat-icon {
    color: #ff6b00;
}
.order-stat-card .stat-value {
    font-size: 20px;
    font-weight: 700;
    color: #1a1a2e;
    margin-bottom: 2px;
}
.order-stat-card.active .stat-value {
    color: #ff6b00;
}
.order-stat-card .stat-label {
    font-size: 12px;
    color: #6c757d;
}

/* 搜索栏 */
.order-search-bar {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 16px;
}
.clear-search {
    font-size: 13px;
    color: #6c757d;
    text-decoration: none;
}
.clear-search:hover {
    color: #ff6b00;
}

/* 订单列表 */
.order-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.order-row {
    background: #fff;
    border: 1px solid #e9ecef;
    border-radius: 10px;
    overflow: hidden;
    transition: all 0.2s;
}
.order-row:hover {
    box-shadow: 0 2px 12px rgba(0,0,0,0.06);
}
.order-row-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 14px;
    background: #f8f9fa;
    border-bottom: 1px solid #e9ecef;
}
.order-meta {
    display: flex;
    align-items: center;
    gap: 12px;
}
.order-no {
    font-size: 13px;
    font-weight: 600;
    color: #1a1a2e;
}
.order-time {
    font-size: 12px;
    color: #6c757d;
}
.order-status-badge {
    font-size: 12px;
    padding: 3px 10px;
    border-radius: 12px;
    font-weight: 500;
}
.status-pending { background: #fff3cd; color: #856404; }
.status-paid { background: #d1ecf1; color: #0c5460; }
.status-shipped { background: #d4edda; color: #155724; }
.status-completed { background: #e2e3e5; color: #383d41; }
.status-cancelled { background: #f8d7da; color: #721c24; }
.status-refunded { background: #e2e3e5; color: #383d41; }

.order-row-body {
    display: grid;
    grid-template-columns: 1.4fr 1.8fr 0.9fr 0.9fr;
    gap: 12px;
    padding: 14px;
    align-items: center;
}
.buyer-info {
    display: flex;
    align-items: center;
    gap: 10px;
}
.buyer-avatar img,
.avatar-placeholder {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    object-fit: cover;
}
.avatar-placeholder {
    background: #ff6b00;
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    font-weight: 600;
}
.buyer-name {
    font-size: 14px;
    font-weight: 600;
    color: #1a1a2e;
}
.buyer-phone {
    font-size: 12px;
    color: #6c757d;
}
.order-goods {
    display: flex;
    flex-direction: column;
    gap: 6px;
}
.goods-item {
    display: flex;
    align-items: center;
    gap: 8px;
}
.goods-thumb {
    width: 44px;
    height: 44px;
    border-radius: 6px;
    object-fit: cover;
    background: #f8f9fa;
}
.goods-name {
    font-size: 13px;
    color: #1a1a2e;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    max-width: 160px;
}
.goods-qty {
    font-size: 12px;
    color: #6c757d;
}
.more-goods {
    font-size: 12px;
    color: #ff6b00;
}
.order-amount {
    text-align: right;
}
.amount-label {
    font-size: 11px;
    color: #6c757d;
    margin-bottom: 2px;
}
.amount-value {
    font-size: 16px;
    font-weight: 700;
    color: #e74c3c;
}
.order-actions-col {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

/* 分页 */
.pagination .page-item.active .page-link {
    background-color: #ff6b00;
    border-color: #ff6b00;
}
.pagination .page-link {
    color: #495057;
}
.pagination .page-link:hover {
    color: #ff6b00;
}

@media (max-width: 768px) {
    .order-stats-row {
        grid-template-columns: repeat(3, 1fr);
    }
    .order-row-body {
        grid-template-columns: 1fr;
        gap: 10px;
    }
}
</style>

<script>
function doOrderSearch() {
    const keyword = document.getElementById('orderSearch').value.trim();
    const url = new URL(window.location.href);
    if (keyword) {
        url.searchParams.set('search', keyword);
    } else {
        url.searchParams.delete('search');
    }
    url.searchParams.delete('page');
    window.location.href = url.toString();
}
document.addEventListener('DOMContentLoaded', function() {
    const input = document.getElementById('orderSearch');
    if (input) {
        input.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') doOrderSearch();
        });
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>
