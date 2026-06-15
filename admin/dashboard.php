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

$currentPage = 'dashboard';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>后台管理 - 58区块城市</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; background:#f5f7fa; color:#333; }
.admin-layout { display:flex; min-height:100vh; }

/* 侧边栏 */
.sidebar { width:260px; background:#1a1a2e; color:#fff; position:fixed; height:100vh; overflow-y:auto; z-index:100; transition:transform .3s; }
.sidebar-header { padding:20px; border-bottom:1px solid rgba(255,255,255,0.1); }
.sidebar-header h2 { font-size:18px; font-weight:700; }
.sidebar-header p { font-size:12px; opacity:0.6; margin-top:4px; }
.nav-menu { padding:15px 0; }
.nav-item { display:flex; align-items:center; gap:12px; padding:12px 20px; color:rgba(255,255,255,0.8); text-decoration:none; font-size:14px; transition:all .2s; border-left:3px solid transparent; }
.nav-item:hover { background:rgba(255,255,255,0.05); color:#fff; }
.nav-item.active { background:rgba(255,107,0,0.15); color:#ff6b00; border-left-color:#ff6b00; }
.nav-item i { width:20px; text-align:center; }
.sidebar-footer { padding:15px 20px; border-top:1px solid rgba(255,255,255,0.1); font-size:12px; opacity:0.5; }

/* 主内容区 */
.main-content { flex:1; margin-left:260px; min-height:100vh; }
.topbar { background:#fff; padding:15px 30px; display:flex; justify-content:space-between; align-items:center; box-shadow:0 1px 3px rgba(0,0,0,0.05); position:sticky; top:0; z-index:50; }
.topbar h1 { font-size:20px; font-weight:600; }
.topbar-right { display:flex; align-items:center; gap:15px; font-size:14px; }
.topbar-right a { color:#666; text-decoration:none; }
.topbar-right a:hover { color:#ff6b00; }
.menu-toggle { display:none; background:none; border:none; font-size:20px; cursor:pointer; color:#333; }

.content { padding:25px 30px; }

/* 统计卡片 */
.stats-row { display:grid; grid-template-columns:repeat(4,1fr); gap:20px; margin-bottom:25px; }
.stat-card { background:#fff; border-radius:12px; padding:20px; box-shadow:0 1px 3px rgba(0,0,0,0.05); display:flex; align-items:center; gap:15px; }
.stat-icon { width:50px; height:50px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:20px; flex-shrink:0; }
.stat-icon.blue { background:#e3f2fd; color:#1976d2; }
.stat-icon.green { background:#e8f5e9; color:#388e3c; }
.stat-icon.orange { background:#fff3e0; color:#f57c00; }
.stat-icon.purple { background:#f3e5f5; color:#7b1fa2; }
.stat-info h3 { font-size:24px; font-weight:700; margin-bottom:2px; }
.stat-info p { font-size:13px; color:#888; }

/* 数据区块 */
.section-row { display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:25px; }
.section-card { background:#fff; border-radius:12px; box-shadow:0 1px 3px rgba(0,0,0,0.05); overflow:hidden; }
.section-header { padding:15px 20px; border-bottom:1px solid #f0f0f0; display:flex; justify-content:space-between; align-items:center; }
.section-header h3 { font-size:16px; font-weight:600; }
.section-header a { font-size:13px; color:#ff6b00; text-decoration:none; }
.section-body { padding:0; }
.data-table { width:100%; border-collapse:collapse; }
.data-table th { text-align:left; padding:12px 20px; font-size:12px; color:#888; font-weight:500; text-transform:uppercase; border-bottom:1px solid #f0f0f0; }
.data-table td { padding:12px 20px; font-size:14px; border-bottom:1px solid #f8f9fa; }
.data-table tr:last-child td { border-bottom:none; }
.data-table tr:hover { background:#fafbfc; }
.badge { display:inline-block; padding:3px 10px; border-radius:20px; font-size:12px; font-weight:500; }
.badge-success { background:#e8f5e9; color:#388e3c; }
.badge-warning { background:#fff3e0; color:#f57c00; }
.badge-danger { background:#ffebee; color:#c62828; }
.badge-info { background:#e3f2fd; color:#1976d2; }
.badge-default { background:#f5f5f5; color:#666; }

/* 响应式 */
@media (max-width:1024px) {
    .stats-row { grid-template-columns:repeat(2,1fr); }
    .section-row { grid-template-columns:1fr; }
}
@media (max-width:768px) {
    .sidebar { transform:translateX(-100%); }
    .sidebar.open { transform:translateX(0); }
    .main-content { margin-left:0; }
    .menu-toggle { display:block; }
    .stats-row { grid-template-columns:1fr; }
}
.overlay { display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.5); z-index:99; }
@media (max-width:768px) { .overlay.show { display:block; } }
</style>
</head>
<body>
<div class="admin-layout">
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h2><i class="fas fa-cube"></i> 58区块城市</h2>
            <p>管理后台</p>
        </div>
        <nav class="nav-menu">
            <a href="dashboard.php" class="nav-item <?= $currentPage=='dashboard'?'active':'' ?>"><i class="fas fa-tachometer-alt"></i> 仪表板</a>
            <a href="users.php" class="nav-item <?= $currentPage=='users'?'active':'' ?>"><i class="fas fa-users"></i> 用户管理</a>
            <a href="shops.php" class="nav-item <?= $currentPage=='shops'?'active':'' ?>"><i class="fas fa-store"></i> 店铺管理</a>
            <a href="orders.php" class="nav-item <?= $currentPage=='orders'?'active':'' ?>"><i class="fas fa-shopping-cart"></i> 订单管理</a>
            <a href="products.php" class="nav-item <?= $currentPage=='products'?'active':'' ?>"><i class="fas fa-box"></i> 商品管理</a>
            <a href="bct_orders.php" class="nav-item <?= $currentPage=='bct_orders'?'active':'' ?>"><i class="fas fa-coins"></i> BCT交易</a>
            <a href="../index.php" class="nav-item"><i class="fas fa-home"></i> 返回前台</a>
        </nav>
        <div class="sidebar-footer">
            管理员: <?= htmlspecialchars($_SESSION['username'] ?? 'Admin') ?>
        </div>
    </aside>

    <div class="overlay" id="overlay" onclick="toggleSidebar()"></div>

    <div class="main-content">
        <div class="topbar">
            <button class="menu-toggle" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
            <h1>仪表板</h1>
            <div class="topbar-right">
                <span>今日: <?= date('Y-m-d') ?></span>
                <a href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> 退出</a>
            </div>
        </div>

        <div class="content">
            <!-- 统计卡片 -->
            <div class="stats-row">
                <div class="stat-card">
                    <div class="stat-icon blue"><i class="fas fa-users"></i></div>
                    <div class="stat-info">
                        <h3><?= number_format($totalUsers) ?></h3>
                        <p>注册用户</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon green"><i class="fas fa-store"></i></div>
                    <div class="stat-info">
                        <h3><?= number_format($totalShops) ?></h3>
                        <p>店铺总数</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon orange"><i class="fas fa-shopping-cart"></i></div>
                    <div class="stat-info">
                        <h3><?= number_format($totalOrders) ?></h3>
                        <p>订单总数</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon purple"><i class="fas fa-box"></i></div>
                    <div class="stat-info">
                        <h3><?= number_format($totalProducts) ?></h3>
                        <p>商品总数</p>
                    </div>
                </div>
            </div>

            <!-- 本月概览 -->
            <div class="stats-row" style="margin-bottom:25px;">
                <div class="stat-card" style="flex:1;">
                    <div class="stat-icon green" style="background:#e8f5e9; color:#388e3c;"><i class="fas fa-check-circle"></i></div>
                    <div class="stat-info">
                        <h3><?= number_format($monthStats['cnt'] ?? 0) ?></h3>
                        <p>本月成交订单</p>
                    </div>
                </div>
                <div class="stat-card" style="flex:1;">
                    <div class="stat-icon orange" style="background:#fff3e0; color:#f57c00;"><i class="fas fa-coins"></i></div>
                    <div class="stat-info">
                        <h3><?= number_format($monthStats['amt'] ?? 0, 2) ?></h3>
                        <p>本月成交总额 (BCT)</p>
                    </div>
                </div>
                <div class="stat-card" style="flex:1;">
                    <div class="stat-icon blue" style="background:#e3f2fd; color:#1976d2;"><i class="fas fa-exchange-alt"></i></div>
                    <div class="stat-info">
                        <h3><?= number_format($totalBctOrders) ?></h3>
                        <p>BCT 交易订单</p>
                    </div>
                </div>
            </div>

            <div class="section-row">
                <!-- 最近注册用户 -->
                <div class="section-card">
                    <div class="section-header">
                        <h3><i class="fas fa-user-plus"></i> 最近注册用户</h3>
                        <a href="users.php">查看全部</a>
                    </div>
                    <div class="section-body">
                        <table class="data-table">
                            <tr><th>用户</th><th>邮箱</th><th>注册时间</th></tr>
                            <?php foreach ($recentUsers as $u): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($u['username']) ?></strong></td>
                                <td><?= htmlspecialchars($u['email']) ?></td>
                                <td><?= date('m-d H:i', strtotime($u['created_at'])) ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($recentUsers)): ?>
                            <tr><td colspan="3" style="text-align:center;color:#999;padding:20px;">暂无注册用户</td></tr>
                            <?php endif; ?>
                        </table>
                    </div>
                </div>

                <!-- 最近订单 -->
                <div class="section-card">
                    <div class="section-header">
                        <h3><i class="fas fa-file-invoice"></i> 最近订单</h3>
                        <a href="orders.php">查看全部</a>
                    </div>
                    <div class="section-body">
                        <table class="data-table">
                            <tr><th>订单号</th><th>买家</th><th>金额</th><th>状态</th></tr>
                            <?php foreach ($recentOrders as $o): ?>
                            <tr>
                                <td><?= htmlspecialchars($o['order_no']) ?></td>
                                <td><?= htmlspecialchars($o['buyer_name'] ?? '未知') ?></td>
                                <td><?= number_format($o['total_amount'], 2) ?> BCT</td>
                                <td>
                                    <?php $statusMap = ['pending'=>'待支付','paid'=>'已支付','shipped'=>'已发货','completed'=>'已完成','cancelled'=>'已取消','refunded'=>'已退款']; ?>
                                    <span class="badge badge-<?= $o['status']=='completed'?'success':($o['status']=='pending'?'warning':($o['status']=='cancelled'?'danger':'info')) ?>"><?= $statusMap[$o['status']] ?? $o['status'] ?></span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($recentOrders)): ?>
                            <tr><td colspan="4" style="text-align:center;color:#999;padding:20px;">暂无订单</td></tr>
                            <?php endif; ?>
                        </table>
                    </div>
                </div>
            </div>

            <?php if (!empty($pendingShops)): ?>
            <div class="section-card" style="margin-bottom:25px;">
                <div class="section-header">
                    <h3><i class="fas fa-exclamation-triangle"></i> 非正常状态店铺</h3>
                    <a href="shops.php">查看全部</a>
                </div>
                <div class="section-body">
                    <table class="data-table">
                        <tr><th>店铺</th><th>店主</th><th>状态</th><th>创建时间</th></tr>
                        <?php foreach ($pendingShops as $s): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($s['shop_name']) ?></strong></td>
                            <td><?= htmlspecialchars($s['owner_name'] ?? '未知') ?></td>
                            <td><span class="badge badge-<?= $s['status']=='pending'?'warning':'danger' ?>"><?= $s['status'] ?></span></td>
                            <td><?= date('m-d H:i', strtotime($s['created_at'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('open');
    document.getElementById('overlay').classList.toggle('show');
}
</script>
</body>
</html>
