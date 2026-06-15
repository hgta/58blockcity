<?php
require_once '../../config/database.php';
require_once '../includes/auth.php';
require_once '../../classes/UserBCTAccount.php';
require_once '../../classes/User.php';

checkAdmin();

$account = new UserBCTAccount($pdo);
$user = new User($pdo);

// 统计
$totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalCities = $pdo->query("SELECT COUNT(DISTINCT city) FROM user_bct_accounts")->fetchColumn();
$totalAccounts = $pdo->query("SELECT COUNT(*) FROM user_bct_accounts")->fetchColumn();
$totalBalance = $pdo->query("SELECT COALESCE(SUM(balance),0) FROM user_bct_accounts")->fetchColumn();

// 最近交易记录
$recentTx = $pdo->query("
    SELECT t.*, u.username, u.city as user_city
    FROM bct_transactions t
    LEFT JOIN users u ON t.user_id = u.id
    ORDER BY t.created_at DESC LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

// BCT 订单统计
$orderStats = $pdo->query("
    SELECT 
        COUNT(*) as total_orders,
        SUM(CASE WHEN status='completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END) as pending
    FROM bct_orders
")->fetch(PDO::FETCH_ASSOC);

$currentPage = 'dashboard';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>BCT 管理后台 - 58区块城市</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; background:#f5f7fa; color:#333; }
.admin-layout { display:flex; min-height:100vh; }
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
.main-content { flex:1; margin-left:260px; min-height:100vh; }
.topbar { background:#fff; padding:15px 30px; display:flex; justify-content:space-between; align-items:center; box-shadow:0 1px 3px rgba(0,0,0,0.05); position:sticky; top:0; z-index:50; }
.topbar h1 { font-size:20px; font-weight:600; }
.topbar-right { display:flex; align-items:center; gap:15px; font-size:14px; }
.topbar-right a { color:#666; text-decoration:none; }
.topbar-right a:hover { color:#ff6b00; }
.menu-toggle { display:none; background:none; border:none; font-size:20px; cursor:pointer; color:#333; }
.content { padding:25px 30px; }
.stats-row { display:grid; grid-template-columns:repeat(4,1fr); gap:20px; margin-bottom:25px; }
.stat-card { background:#fff; border-radius:12px; padding:20px; box-shadow:0 1px 3px rgba(0,0,0,0.05); display:flex; align-items:center; gap:15px; }
.stat-icon { width:50px; height:50px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:20px; flex-shrink:0; }
.stat-icon.blue { background:#e3f2fd; color:#1976d2; }
.stat-icon.green { background:#e8f5e9; color:#388e3c; }
.stat-icon.orange { background:#fff3e0; color:#f57c00; }
.stat-icon.purple { background:#f3e5f5; color:#7b1fa2; }
.stat-info h3 { font-size:24px; font-weight:700; margin-bottom:2px; }
.stat-info p { font-size:13px; color:#888; }
.section-row { display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:25px; }
.section-card { background:#fff; border-radius:12px; box-shadow:0 1px 3px rgba(0,0,0,0.05); overflow:hidden; }
.section-header { padding:15px 20px; border-bottom:1px solid #f0f0f0; display:flex; justify-content:space-between; align-items:center; }
.section-header h3 { font-size:16px; font-weight:600; }
.data-table { width:100%; border-collapse:collapse; }
.data-table th { text-align:left; padding:12px 20px; font-size:12px; color:#888; font-weight:500; text-transform:uppercase; border-bottom:1px solid #f0f0f0; }
.data-table td { padding:12px 20px; font-size:14px; border-bottom:1px solid #f8f9fa; }
.data-table tr:last-child td { border-bottom:none; }
.data-table tr:hover { background:#fafbfc; }
.badge { display:inline-block; padding:3px 10px; border-radius:20px; font-size:12px; font-weight:500; }
.badge-success { background:#e8f5e9; color:#388e3c; }
.badge-warning { background:#fff3e0; color:#f57c00; }
.badge-default { background:#f5f5f5; color:#666; }
.quick-links { display:grid; grid-template-columns:repeat(2,1fr); gap:15px; margin-bottom:25px; }
.qa-item { display:flex; align-items:center; gap:12px; padding:20px; background:#fff; border-radius:12px; box-shadow:0 1px 3px rgba(0,0,0,0.05); text-decoration:none; color:#333; transition:all .2s; }
.qa-item:hover { transform:translateY(-2px); box-shadow:0 4px 12px rgba(0,0,0,0.1); }
.qa-icon { width:40px; height:40px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:18px; }
.qa-icon.blue { background:#e3f2fd; color:#1976d2; }
.qa-icon.green { background:#e8f5e9; color:#388e3c; }
.qa-icon.orange { background:#fff3e0; color:#f57c00; }
.qa-icon.red { background:#ffebee; color:#c62828; }
@media (max-width:1024px) { .stats-row { grid-template-columns:repeat(2,1fr); } .section-row { grid-template-columns:1fr; } }
@media (max-width:768px) { .sidebar { transform:translateX(-100%); } .sidebar.open { transform:translateX(0); } .main-content { margin-left:0; } .menu-toggle { display:block; } .stats-row { grid-template-columns:1fr; } .quick-links { grid-template-columns:1fr; } }
.overlay { display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.5); z-index:99; }
@media (max-width:768px) { .overlay.show { display:block; } }
</style>
</head>
<body>
<div class="admin-layout">
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h2><i class="fas fa-coins"></i> BCT 管理</h2>
            <p>58区块城市</p>
        </div>
        <nav class="nav-menu">
            <a href="dashboard.php" class="nav-item <?= $currentPage=='dashboard'?'active':'' ?>"><i class="fas fa-tachometer-alt"></i> 仪表板</a>
            <a href="bct_management.php" class="nav-item"><i class="fas fa-wallet"></i> BCT 余额管理</a>
            <a href="trigger_match.php" class="nav-item"><i class="fas fa-exchange-alt"></i> 触发匹配</a>
            <a href="../../index.php" class="nav-item"><i class="fas fa-home"></i> 返回前台</a>
        </nav>
        <div class="sidebar-footer">
            管理员: <?= htmlspecialchars($_SESSION['username'] ?? 'Admin') ?>
        </div>
    </aside>

    <div class="overlay" id="overlay" onclick="toggleSidebar()"></div>

    <div class="main-content">
        <div class="topbar">
            <button class="menu-toggle" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
            <h1>BCT 管理后台</h1>
            <div class="topbar-right">
                <span>今日: <?= date('Y-m-d') ?></span>
                <a href="../../auth/logout.php"><i class="fas fa-sign-out-alt"></i> 退出</a>
            </div>
        </div>

        <div class="content">
            <!-- 快捷入口 -->
            <div class="quick-links">
                <a href="bct_management.php" class="qa-item">
                    <div class="qa-icon blue"><i class="fas fa-wallet"></i></div>
                    <div>
                        <div style="font-weight:600;margin-bottom:4px;">BCT 余额管理</div>
                        <div style="font-size:13px;color:#888;">调整用户余额、冻结/解冻</div>
                    </div>
                </a>
                <a href="trigger_match.php" class="qa-item">
                    <div class="qa-icon green"><i class="fas fa-exchange-alt"></i></div>
                    <div>
                        <div style="font-weight:600;margin-bottom:4px;">触发匹配</div>
                        <div style="font-size:13px;color:#888;">BCT 买卖订单匹配</div>
                    </div>
                </a>
            </div>

            <!-- 统计卡片 -->
            <div class="stats-row">
                <div class="stat-card">
                    <div class="stat-icon blue"><i class="fas fa-users"></i></div>
                    <div class="stat-info">
                        <h3><?= number_format($totalUsers) ?></h3>
                        <p>总注册用户</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon green"><i class="fas fa-city"></i></div>
                    <div class="stat-info">
                        <h3><?= number_format($totalCities) ?></h3>
                        <p>开通城市数</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon orange"><i class="fas fa-wallet"></i></div>
                    <div class="stat-info">
                        <h3><?= number_format($totalAccounts) ?></h3>
                        <p>BCT 账户数</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon purple"><i class="fas fa-coins"></i></div>
                    <div class="stat-info">
                        <h3><?= number_format($totalBalance) ?></h3>
                        <p>BCT 总余额</p>
                    </div>
                </div>
            </div>

            <div class="section-row">
                <!-- BCT 订单统计 -->
                <div class="section-card">
                    <div class="section-header">
                        <h3><i class="fas fa-file-invoice"></i> BCT 订单概况</h3>
                    </div>
                    <div style="padding:20px;">
                        <div style="display:flex;justify-content:space-around;text-align:center;margin-bottom:20px;">
                            <div>
                                <div style="font-size:28px;font-weight:700;color:#1976d2;"><?= number_format($orderStats['total_orders'] ?? 0) ?></div>
                                <div style="font-size:13px;color:#888;">总订单</div>
                            </div>
                            <div>
                                <div style="font-size:28px;font-weight:700;color:#388e3c;"><?= number_format($orderStats['completed'] ?? 0) ?></div>
                                <div style="font-size:13px;color:#888;">已完成</div>
                            </div>
                            <div>
                                <div style="font-size:28px;font-weight:700;color:#f57c00;"><?= number_format($orderStats['pending'] ?? 0) ?></div>
                                <div style="font-size:13px;color:#888;">进行中</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 最近交易 -->
                <div class="section-card">
                    <div class="section-header">
                        <h3><i class="fas fa-history"></i> 最近交易记录</h3>
                    </div>
                    <table class="data-table">
                        <tr><th>用户</th><th>类型</th><th>金额</th><th>时间</th></tr>
                        <?php foreach ($recentTx as $tx): ?>
                        <tr>
                            <td><?= htmlspecialchars($tx['username'] ?? '未知') ?></td>
                            <td><span class="badge <?= $tx['type']=='in'?'badge-success':'badge-default' ?>"><?= $tx['type']=='in'?'收入':'支出' ?></span></td>
                            <td><?= number_format($tx['amount'] ?? 0) ?></td>
                            <td><?= $tx['created_at'] ? date('m-d H:i', strtotime($tx['created_at'])) : '-' ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($recentTx)): ?>
                        <tr><td colspan="4" style="text-align:center;color:#999;padding:20px;">暂无交易记录</td></tr>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
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
