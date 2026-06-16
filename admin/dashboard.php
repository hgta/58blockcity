<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../classes/Shop.php';
require_once '../classes/Category.php';
require_once '../classes/User.php';
require_once '../classes/Order.php';
require_once '../classes/Product.php';

checkAdmin();

$shop = new Shop($pdo);
$user = new User($pdo);
$order = new Order($pdo);
$product = new Product($pdo);

// 统计总数
$totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalShops = $pdo->query("SELECT COUNT(*) FROM shops")->fetchColumn();
$totalOrders = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$totalProducts = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
$totalBctOrders = $pdo->query("SELECT COUNT(*) FROM bct_orders")->fetchColumn();

// 最近注册用户
$recentUsers = $pdo->query("SELECT id, username, email, created_at FROM users ORDER BY created_at DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);

// 待审核店铺（虽然新店铺现在直接 active，但保留显示非 active 的店铺）
$pendingShops = $pdo->query("SELECT s.id, s.shop_name, s.status, s.created_at, u.username as owner_name FROM shops s LEFT JOIN users u ON s.user_id = u.id WHERE s.status != 'active' ORDER BY s.created_at DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);

// 最近订单
$recentOrders = $pdo->query("SELECT o.id, o.order_no, o.total_amount, o.status, o.created_at, u.username as buyer_name FROM orders o LEFT JOIN users u ON o.user_id = u.id ORDER BY o.created_at DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);

// 本月订单统计
$monthStats = $pdo->query("SELECT COUNT(*) as cnt, COALESCE(SUM(total_amount),0) as amt FROM orders WHERE status='completed' AND MONTH(created_at)=MONTH(CURDATE()) AND YEAR(created_at)=YEAR(CURDATE())")->fetch(PDO::FETCH_ASSOC);

// 统一后台框架
$admin_site_config = ['site' => 'main', 'page_title' => '总控看板'];
require_once '../shared/admin/admin-header.php';
?>

<div class="admin-stats-grid" style="margin-bottom:20px;">
    <div class="admin-stat-card">
        <div class="stat-icon info"><i class="fas fa-users"></i></div>
        <div class="stat-value"><?= number_format($totalUsers) ?></div>
        <div class="stat-label">注册用户</div>
    </div>
    <div class="admin-stat-card">
        <div class="stat-icon success"><i class="fas fa-store"></i></div>
        <div class="stat-value"><?= number_format($totalShops) ?></div>
        <div class="stat-label">店铺总数</div>
    </div>
    <div class="admin-stat-card">
        <div class="stat-icon warning"><i class="fas fa-shopping-cart"></i></div>
        <div class="stat-value"><?= number_format($totalOrders) ?></div>
        <div class="stat-label">订单总数</div>
    </div>
    <div class="admin-stat-card">
        <div class="stat-icon accent"><i class="fas fa-box"></i></div>
        <div class="stat-value"><?= number_format($totalProducts) ?></div>
        <div class="stat-label">商品总数</div>
    </div>
</div>

<div class="admin-stats-grid" style="margin-bottom:20px;">
    <div class="admin-stat-card">
        <div class="stat-icon success"><i class="fas fa-check-circle"></i></div>
        <div class="stat-value"><?= number_format($monthStats['cnt'] ?? 0) ?></div>
        <div class="stat-label">本月成交订单</div>
    </div>
    <div class="admin-stat-card">
        <div class="stat-icon warning"><i class="fas fa-coins"></i></div>
        <div class="stat-value"><?= number_format($monthStats['amt'] ?? 0, 2) ?></div>
        <div class="stat-label">本月成交总额 (BCT)</div>
    </div>
    <div class="admin-stat-card">
        <div class="stat-icon info"><i class="fas fa-exchange-alt"></i></div>
        <div class="stat-value"><?= number_format($totalBctOrders) ?></div>
        <div class="stat-label">BCT 交易订单</div>
    </div>
</div>

<div style="display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:20px;">
    <div class="admin-card">
        <div class="admin-card-header">
            <span class="admin-card-title"><i class="fas fa-user-plus"></i> 最近注册用户</span>
            <a href="users.php" class="admin-btn admin-btn-sm admin-btn-secondary">查看全部</a>
        </div>
        <div class="admin-card-body" style="padding:0;">
            <table class="admin-data-table">
                <thead><tr><th>用户</th><th>邮箱</th><th>注册时间</th></tr></thead>
                <tbody>
                    <?php foreach ($recentUsers as $u): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($u['username']) ?></strong></td>
                        <td><?= htmlspecialchars($u['email']) ?></td>
                        <td><?= date('m-d H:i', strtotime($u['created_at'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($recentUsers)): ?>
                    <tr><td colspan="3" style="text-align:center;color:#64748b;padding:24px;">暂无注册用户</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="admin-card">
        <div class="admin-card-header">
            <span class="admin-card-title"><i class="fas fa-file-invoice"></i> 最近订单</span>
            <a href="orders.php" class="admin-btn admin-btn-sm admin-btn-secondary">查看全部</a>
        </div>
        <div class="admin-card-body" style="padding:0;">
            <table class="admin-data-table">
                <thead><tr><th>订单号</th><th>买家</th><th>金额</th><th>状态</th></tr></thead>
                <tbody>
                    <?php foreach ($recentOrders as $o): ?>
                    <?php $statusMap = ['pending'=>'待支付','paid'=>'已支付','shipped'=>'已发货','completed'=>'已完成','cancelled'=>'已取消','refunded'=>'已退款']; ?>
                    <tr>
                        <td><?= htmlspecialchars($o['order_no']) ?></td>
                        <td><?= htmlspecialchars($o['buyer_name'] ?? '未知') ?></td>
                        <td><?= number_format($o['total_amount'], 2) ?> BCT</td>
                        <td><span class="admin-badge <?= $o['status']=='completed'?'success':($o['status']=='pending'?'warning':($o['status']=='cancelled'?'danger':'info')) ?>"><?= $statusMap[$o['status']] ?? $o['status'] ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($recentOrders)): ?>
                    <tr><td colspan="4" style="text-align:center;color:#64748b;padding:24px;">暂无订单</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if (!empty($pendingShops)): ?>
<div class="admin-card" style="margin-bottom:20px;">
    <div class="admin-card-header">
        <span class="admin-card-title"><i class="fas fa-exclamation-triangle"></i> 非正常状态店铺</span>
        <a href="shops.php" class="admin-btn admin-btn-sm admin-btn-secondary">查看全部</a>
    </div>
    <div class="admin-card-body" style="padding:0;">
        <table class="admin-data-table">
            <thead><tr><th>店铺</th><th>店主</th><th>状态</th><th>创建时间</th></tr></thead>
            <tbody>
                <?php foreach ($pendingShops as $s): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($s['shop_name']) ?></strong></td>
                    <td><?= htmlspecialchars($s['owner_name'] ?? '未知') ?></td>
                    <td><span class="admin-badge <?= $s['status']=='pending'?'warning':'danger' ?>"><?= $s['status'] ?></span></td>
                    <td><?= date('m-d H:i', strtotime($s['created_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php require_once '../shared/admin/admin-footer.php'; ?>
