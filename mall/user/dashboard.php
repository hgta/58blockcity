<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../includes/header.php';
require_once '../../classes/Shop.php';
require_once '../../classes/Order.php';
require_once '../../classes/Product.php';
require_once '../../classes/Coupon.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$shop = new Shop($pdo);
$order = new Order($pdo);
$product = new Product($pdo);
$coupon = new Coupon($pdo);

$userId = $_SESSION['user_id'];

// 获取用户信息
$userStmt = $pdo->prepare("SELECT username, email, created_at, avatar FROM users WHERE id = ?");
$userStmt->execute([$userId]);
$userInfo = $userStmt->fetch(PDO::FETCH_ASSOC);

// 店铺信息
$userShop = $shop->getShopByUserId($userId);
$hasShop = !empty($userShop);
$shopId = $hasShop ? $userShop['id'] : 0;

// 订单统计
$orderStats = $order->getUserOrderStats($userId);
$recentOrders = $order->getUserOrders($userId, ['page' => 1, 'per_page' => 5]);

// 店铺数据（如果有）
$shopDailyStats = [];
$shopProductStats = [];
$shopCouponStats = [];
if ($hasShop) {
    $shopDailyStats = $shop->getShopDailyStats($shopId);
    $shopProductStats = $product->getShopProductStats($shopId);
    $shopCouponStats = $coupon->getShopCouponStats($shopId);
}

$recentProducts = $product->getRecentProducts(6);
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-3">
            <aside class="shop-sidebar">
                <nav class="sidebar-nav">
                    <a href="dashboard.php" class="nav-item active"><i class="fas fa-tachometer-alt"></i> 仪表板</a>
                    <a href="orders.php" class="nav-item"><i class="fas fa-shopping-cart"></i> 我的订单</a>
                    <a href="profile.php" class="nav-item"><i class="fas fa-user-edit"></i> 个人信息</a>
                    <a href="shops.php" class="nav-item"><i class="fas fa-store"></i> 我的店铺</a>
                    <a href="address.php" class="nav-item"><i class="fas fa-address-book"></i> 收货地址</a>
                    <a href="security.php" class="nav-item"><i class="fas fa-shield-alt"></i> 安全设置</a>
                </nav>

                <div class="sidebar-card user-sidebar-card text-center">
                    <div class="user-avatar mb-3">
                        <?php if (!empty($userInfo['avatar'])): ?>
                            <img src="<?= htmlspecialchars($userInfo['avatar']) ?>" alt="" class="rounded-circle" style="width:64px;height:64px;object-fit:cover;">
                        <?php else: ?>
                            <div class="avatar-placeholder-lg"><?= mb_substr($userInfo['username'], 0, 1) ?></div>
                        <?php endif; ?>
                    </div>
                    <h6 class="mb-1"><?= htmlspecialchars($userInfo['username']) ?></h6>
                    <p class="text-muted small mb-2"><?= htmlspecialchars($userInfo['email']) ?></p>
                    <p class="text-muted small">注册于 <?= date('Y-m-d', strtotime($userInfo['created_at'])) ?></p>
                </div>
            </aside>
        </div>

        <div class="col-md-9">
            <!-- 欢迎横幅 -->
            <div class="welcome-banner">
                <div class="welcome-text">
                    <h4>欢迎回来，<?= htmlspecialchars($userInfo['username']) ?>！</h4>
                    <p>今天是 <?= date('Y年m月d日 l') ?>，祝您购物愉快！</p>
                </div>
                <div class="welcome-icon"><i class="fas fa-shopping-bag"></i></div>
            </div>

            <!-- 用户数据卡片 -->
            <div class="dashboard-stats-row">
                <div class="dash-stat-card stat-blue">
                    <div class="dash-stat-icon"><i class="fas fa-shopping-cart"></i></div>
                    <div class="dash-stat-info">
                        <div class="dash-stat-value"><?= $orderStats['total_orders'] ?? 0 ?></div>
                        <div class="dash-stat-label">总订单</div>
                    </div>
                </div>
                <div class="dash-stat-card stat-green">
                    <div class="dash-stat-icon"><i class="fas fa-check-circle"></i></div>
                    <div class="dash-stat-info">
                        <div class="dash-stat-value"><?= $orderStats['completed_orders'] ?? 0 ?></div>
                        <div class="dash-stat-label">已完成</div>
                    </div>
                </div>
                <div class="dash-stat-card stat-orange">
                    <div class="dash-stat-icon"><i class="fas fa-clock"></i></div>
                    <div class="dash-stat-info">
                        <div class="dash-stat-value"><?= ($orderStats['pending_orders'] ?? 0) + ($orderStats['paid_orders'] ?? 0) ?></div>
                        <div class="dash-stat-label">待处理</div>
                    </div>
                </div>
                <div class="dash-stat-card stat-purple">
                    <div class="dash-stat-icon"><i class="fas fa-truck"></i></div>
                    <div class="dash-stat-info">
                        <div class="dash-stat-value"><?= $orderStats['shipped_orders'] ?? 0 ?></div>
                        <div class="dash-stat-label">运输中</div>
                    </div>
                </div>
            </div>

            <?php if ($hasShop): ?>
            <!-- 店铺数据看板 -->
            <div class="shop-dashboard-section">
                <div class="section-header">
                    <h5><i class="fas fa-store text-primary mr-2"></i>店铺数据看板</h5>
                    <a href="../shop/manage.php?id=<?= $shopId ?>" class="btn btn-sm btn-outline-primary">进入店铺管理</a>
                </div>
                <div class="dashboard-stats-row">
                    <div class="dash-stat-card stat-red">
                        <div class="dash-stat-icon"><i class="fas fa-yen-sign"></i></div>
                        <div class="dash-stat-info">
                            <div class="dash-stat-value">¥<?= number_format($shopDailyStats['today']['revenue'] ?? 0, 0) ?></div>
                            <div class="dash-stat-label">今日营收</div>
                            <?php if (($shopDailyStats['yesterday']['revenue'] ?? 0) > 0): ?>
                                <div class="dash-stat-trend <?= ($shopDailyStats['today']['revenue'] ?? 0) >= ($shopDailyStats['yesterday']['revenue'] ?? 0) ? 'up' : 'down' ?>">
                                    <?= ($shopDailyStats['today']['revenue'] ?? 0) >= ($shopDailyStats['yesterday']['revenue'] ?? 0) ? '↑' : '↓' ?>
                                    <?= number_format(abs(($shopDailyStats['today']['revenue'] ?? 0) - ($shopDailyStats['yesterday']['revenue'] ?? 0)), 0) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="dash-stat-card stat-teal">
                        <div class="dash-stat-icon"><i class="fas fa-file-invoice"></i></div>
                        <div class="dash-stat-info">
                            <div class="dash-stat-value"><?= $shopDailyStats['today']['orders'] ?? 0 ?></div>
                            <div class="dash-stat-label">今日订单</div>
                        </div>
                    </div>
                    <div class="dash-stat-card stat-indigo">
                        <div class="dash-stat-icon"><i class="fas fa-box"></i></div>
                        <div class="dash-stat-info">
                            <div class="dash-stat-value"><?= $shopProductStats['active_products'] ?? 0 ?></div>
                            <div class="dash-stat-label">在售商品</div>
                        </div>
                    </div>
                    <div class="dash-stat-card stat-pink">
                        <div class="dash-stat-icon"><i class="fas fa-ticket-alt"></i></div>
                        <div class="dash-stat-info">
                            <div class="dash-stat-value"><?= $shopCouponStats['active'] ?? 0 ?></div>
                            <div class="dash-stat-label">进行中优惠券</div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="row">
                <!-- 最近订单 -->
                <div class="col-md-7">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">最近订单</h5>
                            <a href="orders.php" class="btn btn-sm btn-outline-primary">查看全部</a>
                        </div>
                        <div class="card-body p-0">
                            <?php if (!empty($recentOrders)): ?>
                                <div class="order-mini-list">
                                    <?php foreach ($recentOrders as $o): ?>
                                        <div class="order-mini-item">
                                            <div class="order-mini-left">
                                                <div class="order-mini-no"><?= htmlspecialchars($o['order_no']) ?></div>
                                                <div class="order-mini-time"><?= date('m-d H:i', strtotime($o['created_at'])) ?></div>
                                            </div>
                                            <div class="order-mini-center">
                                                <span class="order-mini-status status-<?= $o['status'] ?>">
                                                    <?= ['pending' => '待付款', 'paid' => '待发货', 'shipped' => '运输中', 'completed' => '已完成', 'cancelled' => '已取消'][$o['status']] ?? $o['status'] ?>
                                                </span>
                                            </div>
                                            <div class="order-mini-right">
                                                <div class="order-mini-amount">¥<?= number_format($o['total_amount'], 2) ?></div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-center text-muted py-5">
                                    <i class="fas fa-shopping-cart fa-2x mb-3"></i>
                                    <p>暂无订单记录</p>
                                    <a href="../product/list.php" class="btn btn-primary">去购物</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- 快速操作 + 店铺摘要 -->
                <div class="col-md-5">
                    <?php if ($hasShop): ?>
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">我的店铺</h5>
                                <a href="../shop/manage.php?id=<?= $shopId ?>" class="btn btn-sm btn-outline-primary">管理</a>
                            </div>
                            <div class="card-body">
                                <div class="d-flex align-items-center mb-3">
                                    <div class="shop-avatar mr-3">
                                        <?php if (!empty($userShop['shop_logo'])): ?>
                                            <img src="../<?= htmlspecialchars($userShop['shop_logo']) ?>" alt="" class="rounded" style="width:48px;height:48px;object-fit:cover;">
                                        <?php else: ?>
                                            <div class="shop-avatar-placeholder"><i class="fas fa-store"></i></div>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <h6 class="mb-1"><?= htmlspecialchars($userShop['shop_name']) ?></h6>
                                        <span class="badge badge-<?= $userShop['status'] === 'active' ? 'success' : 'warning' ?>">
                                            <?= $userShop['status'] === 'active' ? '营业中' : '审核中' ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="shop-mini-stats">
                                    <div class="shop-mini-stat">
                                        <div class="sms-value"><?= $shopProductStats['total_products'] ?? 0 ?></div>
                                        <div class="sms-label">商品</div>
                                    </div>
                                    <div class="shop-mini-stat">
                                        <div class="sms-value"><?= $userShop['total_sales'] ?? 0 ?></div>
                                        <div class="sms-label">销量</div>
                                    </div>
                                    <div class="shop-mini-stat">
                                        <div class="sms-value"><?= number_format($userShop['rating'] ?? 5, 1) ?></div>
                                        <div class="sms-label">评分</div>
                                    </div>
                                    <div class="shop-mini-stat">
                                        <div class="sms-value"><?= $shopCouponStats['total'] ?? 0 ?></div>
                                        <div class="sms-label">优惠券</div>
                                    </div>
                                </div>
                                <div class="mt-3 d-flex gap-2">
                                    <a href="../shop/products.php?id=<?= $shopId ?>" class="btn btn-sm btn-outline-secondary flex-fill">商品</a>
                                    <a href="../shop/orders.php?id=<?= $shopId ?>" class="btn btn-sm btn-outline-secondary flex-fill">订单</a>
                                    <a href="../shop/coupons.php?id=<?= $shopId ?>" class="btn btn-sm btn-outline-secondary flex-fill">优惠券</a>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="card">
                            <div class="card-body text-center py-4">
                                <i class="fas fa-store fa-2x text-muted mb-3"></i>
                                <h6>您还没有店铺</h6>
                                <p class="text-muted small mb-3">开启您的电商之旅</p>
                                <a href="../shop/create.php" class="btn btn-primary">创建店铺</a>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="card mt-3">
                        <div class="card-header"><h5 class="card-title mb-0">快速操作</h5></div>
                        <div class="card-body">
                            <div class="quick-actions-grid">
                                <a href="../product/list.php" class="qa-item">
                                    <i class="fas fa-shopping-bag text-primary"></i>
                                    <span>浏览商品</span>
                                </a>
                                <a href="orders.php" class="qa-item">
                                    <i class="fas fa-list-alt text-success"></i>
                                    <span>我的订单</span>
                                </a>
                                <a href="profile.php" class="qa-item">
                                    <i class="fas fa-user-cog text-info"></i>
                                    <span>个人设置</span>
                                </a>
                                <a href="../shop/coupons.php?id=<?= $shopId ?>" class="qa-item">
                                    <i class="fas fa-ticket-alt text-warning"></i>
                                    <span>优惠券</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 推荐商品 -->
            <div class="card mt-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">猜你喜欢</h5>
                    <a href="../product/list.php" class="btn btn-sm btn-outline-primary">更多推荐</a>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php if (!empty($recentProducts)): ?>
                            <?php foreach ($recentProducts as $p): ?>
                                <div class="col-md-4 col-6 mb-3">
                                    <div class="product-card-small">
                                        <a href="../product/detail.php?id=<?= $p['id'] ?>">
                                            <div class="product-image">
                                                <img src="../<?= htmlspecialchars($p['main_image']) ?>" alt="<?= htmlspecialchars($p['name']) ?>">
                                            </div>
                                            <div class="product-info">
                                                <h6 class="product-name"><?= htmlspecialchars(mb_substr($p['name'], 0, 16)) ?><?= mb_strlen($p['name']) > 16 ? '...' : '' ?></h6>
                                                <div class="product-price">
                                                    <span class="bct-price"><?= number_format($p['price_bct'], 0) ?> BCT</span>
                                                </div>
                                            </div>
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="col-12 text-center text-muted py-4">
                                <i class="fas fa-box-open fa-2x mb-3"></i>
                                <p>暂无推荐商品</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.welcome-banner {
    background: linear-gradient(135deg, #ff6b00 0%, #ff8533 100%);
    color: #fff;
    border-radius: 12px;
    padding: 24px 28px;
    margin-bottom: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.welcome-banner h4 { margin-bottom: 6px; font-weight: 700; }
.welcome-banner p { margin: 0; opacity: 0.9; }
.welcome-icon { font-size: 48px; opacity: 0.25; }

.avatar-placeholder-lg {
    width: 64px; height: 64px; border-radius: 50%;
    background: linear-gradient(135deg, #ff6b00, #ff8533);
    color: #fff; display: flex; align-items: center; justify-content: center;
    font-size: 24px; font-weight: 700; margin: 0 auto;
}

.dashboard-stats-row {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 14px;
    margin-bottom: 20px;
}
.dash-stat-card {
    border-radius: 12px;
    padding: 16px;
    color: #fff;
    display: flex;
    align-items: center;
    gap: 12px;
    position: relative;
    overflow: hidden;
}
.dash-stat-card::after {
    content: ''; position: absolute; right: -10px; bottom: -10px;
    width: 60px; height: 60px; border-radius: 50%;
    background: rgba(255,255,255,0.15);
}
.stat-blue { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
.stat-green { background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); }
.stat-orange { background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); }
.stat-purple { background: linear-gradient(135deg, #a18cd1 0%, #fbc2eb 100%); }
.stat-red { background: linear-gradient(135deg, #ff5858 0%, #f09819 100%); }
.stat-teal { background: linear-gradient(135deg, #30cfd0 0%, #330867 100%); }
.stat-indigo { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
.stat-pink { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }

.dash-stat-icon { font-size: 22px; width: 44px; height: 44px; border-radius: 10px; background: rgba(255,255,255,0.2); display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.dash-stat-info { flex: 1; }
.dash-stat-value { font-size: 22px; font-weight: 800; line-height: 1; }
.dash-stat-label { font-size: 12px; opacity: 0.9; margin-top: 4px; }
.dash-stat-trend { position: absolute; top: 10px; right: 12px; font-size: 11px; background: rgba(255,255,255,0.25); padding: 2px 8px; border-radius: 10px; }
.dash-stat-trend.up::before { content: '↑ '; }
.dash-stat-trend.down::before { content: '↓ '; }

.shop-dashboard-section { margin-bottom: 20px; }
.section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
.section-header h5 { margin: 0; font-weight: 700; color: #1a1a2e; }

.order-mini-list { }
.order-mini-item { display: flex; align-items: center; justify-content: space-between; padding: 12px 16px; border-bottom: 1px solid #f1f3f5; transition: background 0.15s; }
.order-mini-item:hover { background: #f8f9fa; }
.order-mini-item:last-child { border-bottom: none; }
.order-mini-left { flex: 1; min-width: 0; }
.order-mini-no { font-size: 13px; font-weight: 600; color: #1a1a2e; }
.order-mini-time { font-size: 12px; color: #6c757d; }
.order-mini-center { margin: 0 12px; }
.order-mini-status { font-size: 11px; padding: 3px 10px; border-radius: 10px; font-weight: 500; }
.order-mini-status.status-pending { background: #fff3cd; color: #856404; }
.order-mini-status.status-paid { background: #d1ecf1; color: #0c5460; }
.order-mini-status.status-shipped { background: #d4edda; color: #155724; }
.order-mini-status.status-completed { background: #e2e3e5; color: #383d41; }
.order-mini-status.status-cancelled { background: #f8d7da; color: #721c24; }
.order-mini-right { text-align: right; }
.order-mini-amount { font-size: 14px; font-weight: 700; color: #e74c3c; }

.shop-avatar-placeholder { width: 48px; height: 48px; border-radius: 8px; background: #ff6b00; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 20px; }
.shop-mini-stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; text-align: center; }
.shop-mini-stat { background: #f8f9fa; border-radius: 8px; padding: 10px 4px; }
.sms-value { font-size: 16px; font-weight: 700; color: #1a1a2e; }
.sms-label { font-size: 11px; color: #6c757d; margin-top: 2px; }

.quick-actions-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px; }
.qa-item { display: flex; align-items: center; gap: 10px; padding: 12px; border-radius: 10px; background: #f8f9fa; color: #495057; text-decoration: none; transition: all 0.2s; }
.qa-item:hover { background: #fff; box-shadow: 0 2px 8px rgba(0,0,0,0.08); color: #ff6b00; text-decoration: none; }
.qa-item i { font-size: 18px; width: 32px; height: 32px; border-radius: 8px; background: #fff; display: flex; align-items: center; justify-content: center; }
.qa-item span { font-size: 13px; font-weight: 500; }

.product-card-small { border: 1px solid #e9ecef; border-radius: 10px; overflow: hidden; transition: all 0.2s; }
.product-card-small:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.08); transform: translateY(-2px); }
.product-card-small a { color: inherit; text-decoration: none; }
.product-card-small .product-image { height: 120px; overflow: hidden; background: #f8f9fa; }
.product-card-small .product-image img { width: 100%; height: 100%; object-fit: cover; }
.product-card-small .product-info { padding: 10px; }
.product-card-small .product-name { font-size: 13px; margin-bottom: 6px; color: #1a1a2e; font-weight: 600; line-height: 1.4; }
.product-card-small .product-price { font-size: 13px; color: #ff6b00; font-weight: 700; }

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
.user-sidebar-card .avatar-placeholder-lg { margin: 0 auto; }

@media (max-width: 768px) {
    .dashboard-stats-row { grid-template-columns: repeat(2, 1fr); }
    .welcome-banner { flex-direction: column; text-align: center; gap: 10px; }
}
</style>

<?php require_once '../includes/footer.php'; ?>
