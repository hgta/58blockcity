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
$totalCities = $pdo->query("SELECT COUNT(DISTINCT city) FROM user_bct_account")->fetchColumn();
$totalAccounts = $pdo->query("SELECT COUNT(*) FROM user_bct_account")->fetchColumn();
$totalBalance = $pdo->query("SELECT COALESCE(SUM(balance),0) FROM user_bct_account")->fetchColumn();

// 最近交易记录
$recentTx = $pdo->query("
    SELECT t.*, u.username, u.city as user_city
    FROM bct_transactions t
    LEFT JOIN users u ON t.from_user = u.id
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

// 统一后台框架
$admin_site_config = ['site' => 'bct', 'page_title' => 'BCT 管理看板'];
require_once '../../shared/admin/admin-header.php';
?>

<!-- 统计卡片 -->
<div class="admin-stats-grid" style="margin-bottom:20px;">
    <div class="admin-stat-card">
        <div class="stat-icon info"><i class="fas fa-users"></i></div>
        <div class="stat-value"><?= number_format($totalUsers) ?></div>
        <div class="stat-label">总注册用户</div>
    </div>
    <div class="admin-stat-card">
        <div class="stat-icon success"><i class="fas fa-city"></i></div>
        <div class="stat-value"><?= number_format($totalCities) ?></div>
        <div class="stat-label">开通城市数</div>
    </div>
    <div class="admin-stat-card">
        <div class="stat-icon warning"><i class="fas fa-wallet"></i></div>
        <div class="stat-value"><?= number_format($totalAccounts) ?></div>
        <div class="stat-label">BCT 账户数</div>
    </div>
    <div class="admin-stat-card">
        <div class="stat-icon accent"><i class="fas fa-coins"></i></div>
        <div class="stat-value"><?= number_format($totalBalance) ?></div>
        <div class="stat-label">BCT 总余额</div>
    </div>
</div>

<div style="display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:20px;">
    <!-- BCT 订单统计 -->
    <div class="admin-card">
        <div class="admin-card-header">
            <span class="admin-card-title"><i class="fas fa-file-invoice"></i> BCT 订单概况</span>
        </div>
        <div class="admin-card-body">
            <div style="display:flex;justify-content:space-around;text-align:center;">
                <div>
                    <div style="font-size:28px;font-weight:700;color:#60a5fa;"><?= number_format($orderStats['total_orders'] ?? 0) ?></div>
                    <div style="font-size:13px;color:#94a3b8;">总订单</div>
                </div>
                <div>
                    <div style="font-size:28px;font-weight:700;color:#4ade80;"><?= number_format($orderStats['completed'] ?? 0) ?></div>
                    <div style="font-size:13px;color:#94a3b8;">已完成</div>
                </div>
                <div>
                    <div style="font-size:28px;font-weight:700;color:#fbbf24;"><?= number_format($orderStats['pending'] ?? 0) ?></div>
                    <div style="font-size:13px;color:#94a3b8;">进行中</div>
                </div>
            </div>
        </div>
    </div>

    <!-- 最近交易记录 -->
    <div class="admin-card">
        <div class="admin-card-header">
            <span class="admin-card-title"><i class="fas fa-history"></i> 最近交易记录</span>
        </div>
        <div class="admin-card-body" style="padding:0;">
            <table class="admin-data-table">
                <thead><tr><th>用户</th><th>类型</th><th>金额</th><th>时间</th></tr></thead>
                <tbody>
                    <?php foreach ($recentTx as $tx): ?>
                    <tr>
                        <td><?= htmlspecialchars($tx['username'] ?? '未知') ?></td>
                        <td><span class="admin-badge <?= ($tx['type'] ?? '') == 'in' ? 'success' : 'default' ?>"><?= ($tx['type'] ?? '') == 'in' ? '收入' : '支出' ?></span></td>
                        <td><?= number_format($tx['amount'] ?? 0) ?></td>
                        <td><?= $tx['created_at'] ? date('m-d H:i', strtotime($tx['created_at'])) : '-' ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($recentTx)): ?>
                    <tr><td colspan="4" style="text-align:center;color:#64748b;padding:24px;">暂无交易记录</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once '../../shared/admin/admin-footer.php'; ?>
