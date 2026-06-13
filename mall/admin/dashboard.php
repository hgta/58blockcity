<?php
/**
 * 人气商城 — 管理后台看板
 * (已迁移至统一后台框架)
 */

require_once '../../config/database.php';
require_once '../../classes/Product.php';
require_once '../../classes/Shop.php';
require_once '../../classes/User.php';
require_once '../../classes/Order.php';

$product = new Product($pdo);
$shop = new Shop($pdo);
$user = new User($pdo);
$order = new Order($pdo);

$totalProducts = $product->getProductCount();
$totalShops = $shop->getShopCount();
$totalUsers = $user->getUserCount();
$totalOrders = $order->getOrderCount();

$today = date('Y-m-d');
$todayOrders = $order->getTodayOrderCount($today);
$todayRevenue = $order->getTodayRevenue($today);

$recentOrders = $order->getRecentOrders(10);

// 统一后台框架配置
$admin_site_config = [
    'site'       => 'mall',
    'page_title' => '商城管理看板',
];
require_once '../../shared/admin/admin-header.php';
?>

<!-- 统计卡片 -->
<div class="admin-stats-grid">
    <div class="admin-stat-card">
        <div class="stat-icon danger"><i class="fas fa-box"></i></div>
        <div class="stat-value"><?= number_format($totalProducts) ?></div>
        <div class="stat-label">商品总数</div>
    </div>
    <div class="admin-stat-card">
        <div class="stat-icon warning"><i class="fas fa-store"></i></div>
        <div class="stat-value"><?= number_format($totalShops) ?></div>
        <div class="stat-label">店铺总数</div>
    </div>
    <div class="admin-stat-card">
        <div class="stat-icon info"><i class="fas fa-users"></i></div>
        <div class="stat-value"><?= number_format($totalUsers) ?></div>
        <div class="stat-label">注册用户</div>
    </div>
    <div class="admin-stat-card">
        <div class="stat-icon success"><i class="fas fa-shopping-cart"></i></div>
        <div class="stat-value"><?= number_format($totalOrders) ?></div>
        <div class="stat-label">总订单数</div>
    </div>
    <div class="admin-stat-card">
        <div class="stat-icon accent"><i class="fas fa-money-bill-wave"></i></div>
        <div class="stat-value">¥<?= number_format($todayRevenue, 2) ?></div>
        <div class="stat-label">今日收入</div>
    </div>
    <div class="admin-stat-card">
        <div class="stat-icon accent"><i class="fas fa-chart-line"></i></div>
        <div class="stat-value"><?= number_format($todayOrders) ?></div>
        <div class="stat-label">今日订单</div>
    </div>
</div>

<!-- 快捷操作 -->
<div class="admin-card">
    <div class="admin-card-header">
        <span class="admin-card-title"><i class="fas fa-bolt" style="margin-right:8px;color:var(--admin-accent);"></i>快捷操作</span>
    </div>
    <div class="admin-card-body">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:16px;">
            <a href="categories.php?action=add" class="admin-btn admin-btn-primary admin-btn-sm" style="justify-content:center;">
                <i class="fas fa-plus"></i> 添加商品
            </a>
            <a href="shops.php?action=approve" class="admin-btn admin-btn-secondary admin-btn-sm" style="justify-content:center;">
                <i class="fas fa-check"></i> 审核店铺
            </a>
            <a href="categories.php" class="admin-btn admin-btn-secondary admin-btn-sm" style="justify-content:center;">
                <i class="fas fa-tags"></i> 分类管理
            </a>
            <a href="seed.php" class="admin-btn admin-btn-secondary admin-btn-sm" style="justify-content:center;">
                <i class="fas fa-seedling"></i> 数据种子
            </a>
        </div>
    </div>
</div>

<!-- 最近订单 -->
<div class="admin-card">
    <div class="admin-card-header">
        <span class="admin-card-title"><i class="fas fa-list" style="margin-right:8px;color:var(--admin-accent);"></i>最近订单</span>
    </div>
    <div class="admin-card-body" style="padding:0;">
        <?php if (empty($recentOrders)): ?>
            <div class="admin-empty-state">
                <i class="fas fa-inbox"></i>
                <h4>暂无订单</h4>
                <p>还没有收到任何订单</p>
            </div>
        <?php else: ?>
            <table class="admin-data-table">
                <thead>
                    <tr>
                        <th>订单号</th>
                        <th>用户</th>
                        <th>金额</th>
                        <th>状态</th>
                        <th>时间</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentOrders as $o): ?>
                    <tr>
                        <td><?= htmlspecialchars($o['order_no'] ?? '#'.$o['id']) ?></td>
                        <td>用户<?= $o['user_id'] ?></td>
                        <td>¥<?= number_format($o['total_amount'] ?? 0, 2) ?></td>
                        <td>
                            <?php
                            $statusMap = [
                                'pending'   => ['待付款', 'warning'],
                                'paid'      => ['已付款', 'info'],
                                'shipped'   => ['已发货', 'success'],
                                'completed' => ['已完成', 'success'],
                                'canceled'  => ['已取消', 'default'],
                            ];
                            $s = $statusMap[$o['status']] ?? [$o['status'], 'default'];
                            ?>
                            <span class="admin-badge <?= $s[1] ?>"><?= $s[0] ?></span>
                        </td>
                        <td><?= date('m-d H:i', strtotime($o['created_at'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../../shared/admin/admin-footer.php'; ?>
