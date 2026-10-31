<?php
require_once '../../config/database.php';
require_once '../../classes/User.php';
require_once '../../includes/auth.php';
require_once '../../classes/Shop.php';
require_once '../../classes/Order.php';
require_once '../../classes/Notification.php';
require_once '../includes/functions.php';

// 兜底：如果公共函数库未部署，在此文件内也定义一次 normalizeImageUrl
if (!function_exists('normalizeImageUrl')) {
    function normalizeImageUrl($imageUrl) {
        if (empty($imageUrl)) {
            return '/assets/images/default-product.jpg';
        }
        $imageUrl = trim($imageUrl);
        if (preg_match('#^(https?:)?//#i', $imageUrl) || substr($imageUrl, 0, 1) === '/') {
            return $imageUrl;
        }
        return '/' . ltrim($imageUrl, '/');
    }
}

// 检查用户是否已登录
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$shop = new Shop($pdo);
$order = new Order($pdo);

// 获取店铺ID
$shopId = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$shopId) {
    $userShop = $shop->getShopByUserId($_SESSION['user_id']);
    if (!$userShop) { header('Location: create.php'); exit; }
    $shopId = $userShop['id'];
}

$userShop = $shop->getShopById($shopId);
if (!$userShop || ($userShop['user_id'] != $_SESSION['user_id'] && ($_SESSION['role'] ?? '') !== 'admin')) {
    $myShop = $shop->getShopByUserId($_SESSION['user_id']);
    if ($myShop) { header('Location: orders.php?id=' . $myShop['id']); exit; }
    header('Location: create.php'); exit;
}

$error = '';
$success = '';

// 处理订单状态更新
if (isset($_GET['action']) && isset($_GET['order_id'])) {
    $orderId = intval($_GET['order_id']);
    $actionType = $_GET['action'];
    $orderInfo = $order->getOrderById($orderId);

    if ($orderInfo && $orderInfo['shop_id'] == $shopId) {
        if ($actionType === 'confirm_paid' && $orderInfo['status'] === 'pending') {
            // 卖家线下已收款，主动确认：pending → paid（进入待发货）
            if ($order->updateOrderStatus($orderId, 'paid')) {
                $success = '订单已确认收款，请尽快发货';
                // 通知买家：卖家已确认收款
                try {
                    $notify = new Notification($pdo);
                    $notify->sendSystemNotify($orderInfo['user_id'], 'order_paid', $orderId,
                        '订单 ' . $orderInfo['order_no'] . ' 卖家已确认收款，等待卖家发货',
                        '../../user/order_detail.php?id=' . $orderId
                    );
                } catch (Exception $e) { /* 通知失败不影响主流程 */ }
            } else {
                $error = '操作失败，请重试';
            }
        } elseif ($actionType === 'ship' && $orderInfo['status'] === 'paid') {
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
            <aside class="shop-sidebar">
                <nav class="sidebar-nav">
                    <a href="manage.php?id=<?= $shopId ?>" class="nav-item">
                        <i class="fas fa-tachometer-alt"></i> 数据看板
                    </a>
                    <a href="products.php?id=<?= $shopId ?>" class="nav-item">
                        <i class="fas fa-box"></i> 商品管理
                    </a>
                    <a href="products.php?action=add&id=<?= $shopId ?>" class="nav-item">
                        <i class="fas fa-plus"></i> 添加商品
                    </a>
                    <a href="orders.php?id=<?= $shopId ?>" class="nav-item active">
                        <i class="fas fa-shopping-cart"></i> 订单管理
                    </a>
                    <a href="coupons.php?id=<?= $shopId ?>" class="nav-item">
                        <i class="fas fa-ticket-alt"></i> 优惠券
                    </a>
                    <a href="payment-settings.php?id=<?= $shopId ?>" class="nav-item">
                        <i class="fas fa-cog"></i> 支付设置
                    </a>
                </nav>

                <div class="sidebar-card">
                    <h4>订单统计</h4>
                    <div class="sidebar-stat-row">
                        <span class="label">全部订单</span>
                        <span class="value text-primary"><?= $statusStats['total'] ?? 0 ?></span>
                    </div>
                    <div class="sidebar-stat-row">
                        <span class="label">待付款</span>
                        <span class="value text-warning"><?= $statusStats['pending'] ?? 0 ?></span>
                    </div>
                    <div class="sidebar-stat-row">
                        <span class="label">待发货</span>
                        <span class="value text-info"><?= $statusStats['paid'] ?? 0 ?></span>
                    </div>
                    <div class="sidebar-stat-row">
                        <span class="label">已完成</span>
                        <span class="value text-success"><?= $statusStats['completed'] ?? 0 ?></span>
                    </div>
                </div>
            </aside>
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
                                                    <img src="<?= htmlspecialchars(User::avatarUrl($o['buyer_avatar'])) ?>" alt="" onerror="this.onerror=null;this.src='https://www.58.tl/assets/images/default.jpg'">
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
                                            <div class="amount-value"><?= htmlspecialchars(order_money($o['total_amount'], $o['currency'] ?? 'popularity')) ?></div>
                                        </div>
                                        <div class="order-actions-col">
                                            <button type="button" class="btn btn-sm btn-outline-primary mb-1" onclick="showOrderDetail(<?= $o['id'] ?>)">
                                                <i class="fas fa-eye"></i> 详情
                                            </button>
                                            <?php if ($o['status'] === 'pending'): ?>
                                                <a href="orders.php?action=confirm_paid&id=<?= $shopId ?>&order_id=<?= $o['id'] ?>" class="btn btn-sm btn-success mb-1" onclick="return confirm('确认已线下收到买家付款？\n确认后订单将进入「待发货」，请核实款项后再操作。')">
                                                    <i class="fas fa-hand-holding-usd"></i> 确认收款
                                                </a>
                                            <?php elseif ($o['status'] === 'paid'): ?>
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

                                    <!-- 订单详情数据（隐藏，点击「详情」时注入弹窗） -->
                                    <div id="order-detail-data-<?= $o['id'] ?>" style="display:none;">
                                        <div class="od-section">
                                            <div class="od-section-title"><i class="fas fa-user"></i> 买家与收货信息</div>
                                            <div class="od-grid">
                                                <div class="od-item"><span class="od-label">买家</span><span><?= htmlspecialchars($o['buyer_name'] ?? '未知用户') ?></span></div>
                                                <div class="od-item"><span class="od-label">联系电话</span><span><?= !empty($o['buyer_phone']) ? htmlspecialchars($o['buyer_phone']) : '未填写' ?></span></div>
                                                <div class="od-item od-full">
                                                    <span class="od-label">收货地址</span>
                                                    <span class="od-address"><?= !empty($o['shipping_address']) ? htmlspecialchars($o['shipping_address']) : '未填写' ?></span>
                                                    <?php if (!empty($o['shipping_address'])): ?>
                                                        <button type="button" class="od-copy-btn" onclick="copyOrderText(this, '<?= htmlspecialchars(addslashes($o['shipping_address'])) ?>')">复制</button>
                                                    <?php endif; ?>
                                                </div>
                                                <?php if (!empty($o['buyer_note'])): ?>
                                                    <div class="od-item od-full"><span class="od-label">买家备注</span><span><?= htmlspecialchars($o['buyer_note']) ?></span></div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="od-section">
                                            <div class="od-section-title"><i class="fas fa-box"></i> 商品明细</div>
                                            <?php foreach ($items as $item): ?>
                                                <div class="od-goods-item">
                                                    <img src="../<?= htmlspecialchars($item['product_image'] ?: 'assets/images/default-product.png') ?>" alt="">
                                                    <div class="od-goods-info">
                                                        <div class="od-goods-name"><?= htmlspecialchars($item['product_name']) ?></div>
                                                        <div class="od-goods-qty">x<?= $item['quantity'] ?></div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                        <div class="od-section">
                                            <div class="od-section-title"><i class="fas fa-file-invoice"></i> 订单信息</div>
                                            <div class="od-grid">
                                                <div class="od-item"><span class="od-label">订单号</span><span><?= htmlspecialchars($o['order_no']) ?></span></div>
                                                <div class="od-item"><span class="od-label">状态</span><span><?= $statusMap[$o['status']] ?? '未知' ?></span></div>
                                                <div class="od-item"><span class="od-label">下单时间</span><span><?= date('Y-m-d H:i:s', strtotime($o['created_at'])) ?></span></div>
                                                <div class="od-item"><span class="od-label">付款时间</span><span><?= !empty($o['paid_at']) ? date('Y-m-d H:i:s', strtotime($o['paid_at'])) : '未付款' ?></span></div>
                                                <div class="od-item"><span class="od-label">实付金额</span><span class="od-amount"><?= htmlspecialchars(order_money($o['total_amount'], $o['currency'] ?? 'popularity')) ?></span></div>
                                                <?php if (!empty($o['shipping_company'])): ?>
                                                    <div class="od-item od-full"><span class="od-label">物流信息</span><span><?= htmlspecialchars($o['shipping_company']) ?><?= !empty($o['tracking_no']) ? '：' . htmlspecialchars($o['tracking_no']) : '' ?></span></div>
                                                <?php endif; ?>
                                            </div>
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
/* ===== 侧边栏（统一风格） ===== */
.shop-sidebar { width: 100%; }
.sidebar-nav { background: #fff; border-radius: 12px; padding: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); margin-bottom: 16px; }
.nav-item { display: flex; align-items: center; gap: 10px; padding: 12px 14px; border-radius: 8px; color: #475569; text-decoration: none; font-size: 14px; font-weight: 500; transition: all .2s; position: relative; }
.nav-item:hover { background: #f1f5f9; color: #1e293b; text-decoration: none; }
.nav-item.active { background: #fff7ed; color: #ea580c; }
.nav-badge { margin-left: auto; background: #f1f5f9; color: #64748b; font-size: 11px; padding: 2px 8px; border-radius: 10px; font-weight: 600; }
.nav-item.active .nav-badge { background: #fed7aa; color: #c2410c; }
.sidebar-card { background: #fff; border-radius: 12px; padding: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); }
.sidebar-card h4 { font-size: 14px; font-weight: 600; color: #1e293b; margin: 0 0 12px; }
.sidebar-stat-row { display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid #f1f5f9; font-size: 13px; }
.sidebar-stat-row:last-child { border-bottom: none; }
.sidebar-stat-row .label { color: #64748b; }
.sidebar-stat-row .value { font-weight: 600; }

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

/* ===== 订单详情弹窗 ===== */
.od-modal-backdrop {
    position: fixed; inset: 0;
    background: rgba(0,0,0,0.5);
    z-index: 9998;
    display: none;
    align-items: center; justify-content: center;
    padding: 16px;
}
.od-modal-backdrop.show { display: flex; }
.od-modal {
    background: #fff;
    border-radius: 12px;
    width: 100%;
    max-width: 560px;
    max-height: 85vh;
    overflow-y: auto;
    box-shadow: 0 20px 60px rgba(0,0,0,0.25);
    animation: odFadeIn .2s ease;
}
@keyframes odFadeIn { from { transform: translateY(16px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
.od-modal-header {
    display: flex; align-items: center; justify-content: space-between;
    padding: 16px 20px;
    border-bottom: 1px solid #e9ecef;
    position: sticky; top: 0; background: #fff;
}
.od-modal-title { font-size: 16px; font-weight: 700; color: #1a1a2e; }
.od-modal-close {
    width: 30px; height: 30px; border: none; background: #f1f5f9;
    border-radius: 50%; cursor: pointer; color: #64748b;
    display: flex; align-items: center; justify-content: center;
}
.od-modal-close:hover { background: #e2e8f0; }
.od-modal-body { padding: 16px 20px 20px; }
.od-section { margin-bottom: 18px; }
.od-section:last-child { margin-bottom: 0; }
.od-section-title {
    font-size: 13px; font-weight: 700; color: #ff6b00;
    margin-bottom: 10px; display: flex; align-items: center; gap: 6px;
}
.od-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px 16px; }
.od-item { font-size: 13px; color: #334155; display: flex; align-items: flex-start; gap: 8px; flex-wrap: wrap; }
.od-item.od-full { grid-column: 1 / -1; }
.od-label { color: #94a3b8; flex: none; min-width: 60px; }
.od-address { word-break: break-all; line-height: 1.5; }
.od-copy-btn {
    border: 1px solid #e2e8f0; background: #f8fafc; color: #475569;
    font-size: 11px; padding: 2px 8px; border-radius: 4px; cursor: pointer; flex: none;
}
.od-copy-btn:hover { background: #eff6ff; border-color: #bfdbfe; }
.od-goods-item { display: flex; align-items: center; gap: 10px; padding: 6px 0; border-bottom: 1px dashed #f1f5f9; }
.od-goods-item:last-child { border-bottom: none; }
.od-goods-item img { width: 44px; height: 44px; border-radius: 6px; object-fit: cover; background: #f8f9fa; flex: none; }
.od-goods-info { flex: 1; min-width: 0; }
.od-goods-name { font-size: 13px; color: #1a1a2e; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.od-goods-qty { font-size: 12px; color: #6c757d; }
.od-amount { color: #e74c3c; font-weight: 700; }
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

// ===== 订单详情弹窗 =====
function showOrderDetail(orderId) {
    var dataEl = document.getElementById('order-detail-data-' + orderId);
    var modal = document.getElementById('orderDetailModal');
    if (!dataEl || !modal) return;
    document.getElementById('orderDetailBody').innerHTML = dataEl.innerHTML;
    modal.classList.add('show');
    document.body.style.overflow = 'hidden';
}
function closeOrderDetail() {
    var modal = document.getElementById('orderDetailModal');
    if (!modal) return;
    modal.classList.remove('show');
    document.body.style.overflow = '';
}
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeOrderDetail();
});

// 复制文本（详情弹窗内）
function copyOrderText(btn, text) {
    var onOk = function() {
        var orig = btn.textContent;
        btn.textContent = '已复制';
        setTimeout(function() { btn.textContent = orig; }, 1500);
    };
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(onOk, function() { fallbackCopy(text, onOk); });
    } else {
        fallbackCopy(text, onOk);
    }
}
function fallbackCopy(text, onOk) {
    var tmp = document.createElement('textarea');
    tmp.value = text;
    tmp.style.cssText = 'position:fixed;top:-9999px;left:-9999px;';
    document.body.appendChild(tmp);
    tmp.select();
    try { document.execCommand('copy'); onOk(); } catch (e) {}
    document.body.removeChild(tmp);
}
</script>

<!-- 订单详情弹窗 -->
<div class="od-modal-backdrop" id="orderDetailModal" onclick="if(event.target === this) closeOrderDetail()">
    <div class="od-modal" role="dialog" aria-modal="true">
        <div class="od-modal-header">
            <div class="od-modal-title"><i class="fas fa-file-invoice"></i> 订单详情</div>
            <button type="button" class="od-modal-close" onclick="closeOrderDetail()" aria-label="关闭">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="od-modal-body" id="orderDetailBody"></div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
