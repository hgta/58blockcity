<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../classes/Shop.php';
require_once '../../classes/Product.php';
require_once '../../classes/Order.php';
require_once '../../classes/Coupon.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$shop = new Shop($pdo);
$product = new Product($pdo);
$order = new Order($pdo);
$coupon = new Coupon($pdo);

$userId = $_SESSION['user_id'];

// 获取用户信息
$userStmt = $pdo->prepare("SELECT username, email, created_at, avatar FROM users WHERE id = ?");
$userStmt->execute([$userId]);
$userInfo = $userStmt->fetch(PDO::FETCH_ASSOC);

// 店铺信息（支持多店铺）
$userShops = $shop->getUserShops($userId);
$hasShop = !empty($userShops);

require_once '../includes/header.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-3">
            <aside class="shop-sidebar">
                <nav class="sidebar-nav">
                    <a href="dashboard.php" class="nav-item"><i class="fas fa-tachometer-alt"></i> 仪表板</a>
                    <a href="orders.php" class="nav-item"><i class="fas fa-shopping-cart"></i> 我的订单</a>
                    <a href="profile.php" class="nav-item"><i class="fas fa-user-edit"></i> 个人信息</a>
                    <a href="shops.php" class="nav-item active"><i class="fas fa-store"></i> 我的店铺</a>
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
            <?php if ($hasShop): ?>
                <?php foreach ($userShops as $userShop): ?>
                    <?php
                    $shopId = $userShop['id'];
                    $shopProductStats = $product->getShopProductStats($shopId);
                    $shopCouponStats = $coupon->getShopCouponStats($shopId);
                    $shopDailyStats = $shop->getShopDailyStats($shopId);
                    ?>
                <!-- 店铺概览卡片 -->
                <div class="shop-overview-card mb-4">
                    <div class="shop-overview-header">
                        <div class="shop-overview-logo">
                            <?php if (!empty($userShop['shop_logo'])): ?>
                                <img src="../<?= htmlspecialchars($userShop['shop_logo']) ?>" alt="">
                            <?php else: ?>
                                <div class="shop-logo-placeholder"><i class="fas fa-store"></i></div>
                            <?php endif; ?>
                        </div>
                        <div class="shop-overview-info">
                            <h4><?= htmlspecialchars($userShop['shop_name']) ?></h4>
                            <p class="text-muted mb-2"><?= htmlspecialchars($userShop['shop_description'] ?? '暂无描述') ?></p>
                            <div class="shop-overview-badges">
                                <span class="badge badge-<?= $userShop['status'] === 'active' ? 'success' : 'warning' ?>">
                                    <?= $userShop['status'] === 'active' ? '营业中' : '审核中' ?>
                                </span>
                                <span class="badge badge-secondary">
                                    <i class="fas fa-star"></i> <?= number_format($userShop['rating'] ?? 5, 1) ?>
                                </span>
                            </div>
                        </div>
                        <div class="shop-overview-actions">
                            <a href="../shop/manage.php?id=<?= $shopId ?>" class="btn btn-primary">
                                <i class="fas fa-cog"></i> 管理店铺
                            </a>
                            <a href="../shop/view.php?id=<?= $shopId ?>" class="btn btn-outline-secondary" target="_blank">
                                <i class="fas fa-external-link-alt"></i> 查看店铺
                            </a>
                        </div>
                    </div>
                </div>

                <!-- 统计数据 -->
                <div class="dashboard-stats-row mt-4">
                    <div class="dash-stat-card stat-blue">
                        <div class="dash-stat-icon"><i class="fas fa-box"></i></div>
                        <div class="dash-stat-info">
                            <div class="dash-stat-value"><?= $shopProductStats['active_products'] ?? 0 ?></div>
                            <div class="dash-stat-label">在售商品</div>
                        </div>
                    </div>
                    <div class="dash-stat-card stat-green">
                        <div class="dash-stat-icon"><i class="fas fa-file-invoice"></i></div>
                        <div class="dash-stat-info">
                            <div class="dash-stat-value"><?= $shopDailyStats['today']['orders'] ?? 0 ?></div>
                            <div class="dash-stat-label">今日订单</div>
                        </div>
                    </div>
                    <div class="dash-stat-card stat-orange">
                        <div class="dash-stat-icon"><i class="fas fa-yen-sign"></i></div>
                        <div class="dash-stat-info">
                            <div class="dash-stat-value"><?= number_format($shopDailyStats['today']['revenue'] ?? 0, 0) ?></div>
                            <div class="dash-stat-label">今日营收 (BCT)</div>
                        </div>
                    </div>
                    <div class="dash-stat-card stat-purple">
                        <div class="dash-stat-icon"><i class="fas fa-ticket-alt"></i></div>
                        <div class="dash-stat-info">
                            <div class="dash-stat-value"><?= $shopCouponStats['active'] ?? 0 ?></div>
                            <div class="dash-stat-label">进行中优惠券</div>
                        </div>
                    </div>
                </div>

                <!-- 快捷入口 -->
                <div class="card mt-4 mb-4">
                    <div class="card-header"><h5 class="card-title mb-0">店铺管理</h5></div>
                    <div class="card-body">
                        <div class="quick-actions-grid">
                            <a href="../shop/products.php?id=<?= $shopId ?>" class="qa-item">
                                <i class="fas fa-box text-primary"></i>
                                <span>商品管理</span>
                            </a>
                            <a href="../shop/orders.php?id=<?= $shopId ?>" class="qa-item">
                                <i class="fas fa-shopping-cart text-success"></i>
                                <span>订单管理</span>
                            </a>
                            <a href="../shop/coupons.php?id=<?= $shopId ?>" class="qa-item">
                                <i class="fas fa-ticket-alt text-warning"></i>
                                <span>优惠券</span>
                            </a>
                            <a href="../shop/payment-settings.php?id=<?= $shopId ?>" class="qa-item">
                                <i class="fas fa-credit-card text-info"></i>
                                <span>支付设置</span>
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>

                <div class="text-center mt-3 mb-5">
                    <a href="../shop/create.php" class="btn btn-outline-primary">
                        <i class="fas fa-plus"></i> 再开一个店
                    </a>
                </div>
            <?php else: ?>
                <!-- 没有店铺的空状态 -->
                <div class="card">
                    <div class="card-body text-center py-5">
                        <div class="empty-shop-state">
                            <i class="fas fa-store fa-3x text-muted mb-4"></i>
                            <h5>您还没有店铺</h5>
                            <p class="text-muted mb-4">创建店铺后，您可以发布商品、管理订单、设置优惠券等</p>
                            <a href="../shop/create.php" class="btn btn-primary btn-lg">
                                <i class="fas fa-plus"></i> 创建店铺
                            </a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
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
.user-sidebar-card .avatar-placeholder-lg { margin: 0 auto; }
.avatar-placeholder-lg {
    width: 64px; height: 64px; border-radius: 50%;
    background: linear-gradient(135deg, #ff6b00, #ff8533);
    color: #fff; display: flex; align-items: center; justify-content: center;
    font-size: 24px; font-weight: 700; margin: 0 auto;
}

/* 店铺概览 */
.shop-overview-card { background: #fff; border-radius: 12px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); }
.shop-overview-header { display: flex; align-items: center; gap: 20px; flex-wrap: wrap; }
.shop-overview-logo { width: 80px; height: 80px; border-radius: 12px; overflow: hidden; background: #f8f9fa; flex-shrink: 0; }
.shop-overview-logo img { width: 100%; height: 100%; object-fit: cover; }
.shop-logo-placeholder { width: 100%; height: 100%; background: linear-gradient(135deg, #ff6b00, #ff8533); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 28px; }
.shop-overview-info { flex: 1; min-width: 0; }
.shop-overview-info h4 { margin: 0 0 6px; font-weight: 700; color: #1a1a2e; }
.shop-overview-badges { display: flex; gap: 8px; }
.shop-overview-actions { display: flex; gap: 10px; flex-wrap: wrap; }
.shop-overview-actions .btn { display: inline-flex; align-items: center; gap: 6px; padding: 10px 20px; border-radius: 8px; font-size: 14px; font-weight: 600; }
.shop-overview-actions .btn-primary { background: #f97316; color: #fff; border: none; }
.shop-overview-actions .btn-primary:hover { background: #ea580c; }
.shop-overview-actions .btn-outline-secondary { background: transparent; color: #475569; border: 1px solid #e2e8f0; }
.shop-overview-actions .btn-outline-secondary:hover { background: #f8f9fa; }

/* 统计卡片 */
.dashboard-stats-row {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 14px;
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
.dash-stat-icon { font-size: 22px; width: 44px; height: 44px; border-radius: 10px; background: rgba(255,255,255,0.2); display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.dash-stat-info { flex: 1; }
.dash-stat-value { font-size: 22px; font-weight: 800; line-height: 1; }
.dash-stat-label { font-size: 12px; opacity: 0.9; margin-top: 4px; }

/* 快捷操作 */
.quick-actions-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px; }
.qa-item { display: flex; align-items: center; gap: 10px; padding: 12px; border-radius: 10px; background: #f8f9fa; color: #495057; text-decoration: none; transition: all 0.2s; }
.qa-item:hover { background: #fff; box-shadow: 0 2px 8px rgba(0,0,0,0.08); color: #ff6b00; text-decoration: none; }
.qa-item i { font-size: 18px; width: 32px; height: 32px; border-radius: 8px; background: #fff; display: flex; align-items: center; justify-content: center; }
.qa-item span { font-size: 13px; font-weight: 500; }

/* 空状态 */
.empty-shop-state { padding: 40px 20px; }
.empty-shop-state i { opacity: 0.4; }

@media (max-width: 768px) {
    .dashboard-stats-row { grid-template-columns: repeat(2, 1fr); }
    .shop-overview-header { flex-direction: column; text-align: center; }
    .shop-overview-actions { width: 100%; justify-content: center; }
}
</style>

<?php require_once '../includes/footer.php'; ?>
