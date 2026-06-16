<?php
/**
 * 人气商城 — 用户管理
 */
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../classes/User.php';

$user = new User($pdo);

// 搜索 & 分页
$search = $_GET['search'] ?? '';
$page   = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 20;
$offset  = ($page - 1) * $perPage;

$where   = "1=1";
$params  = [];
if (!empty($search)) {
    $where .= " AND (username LIKE ? OR email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE $where");
$countStmt->execute($params);
$total = $countStmt->fetchColumn();

$listStmt = $pdo->prepare("SELECT id, username, email, role, city, phone, status, created_at, last_login FROM users WHERE $where ORDER BY id DESC LIMIT $perPage OFFSET $offset");
$listStmt->execute($params);
$users = $listStmt->fetchAll(PDO::FETCH_ASSOC);

$totalPages = ceil($total / $perPage);

// 统一后台框架配置
$admin_site_config = [
    'site'       => 'mall',
    'page_title' => '用户管理',
];
require_once '../../shared/admin/admin-header.php';
?>

<div class="admin-card">
    <div class="admin-card-header">
        <span class="admin-card-title"><i class="fas fa-users" style="margin-right:8px;color:var(--admin-accent);"></i>用户列表</span>
        <form method="GET" style="display:flex;gap:8px;">
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="搜索用户名/邮箱…" style="padding:6px 12px;border:1px solid #e2e8f0;border-radius:6px;font-size:13px;width:200px;">
            <button type="submit" class="admin-btn admin-btn-primary admin-btn-sm">搜索</button>
            <?php if ($search): ?>
                <a href="users.php" class="admin-btn admin-btn-secondary admin-btn-sm">清除</a>
            <?php endif; ?>
        </form>
    </div>
    <div class="admin-card-body" style="padding:0;">
        <table class="admin-data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>用户名</th>
                    <th>邮箱</th>
                    <th>角色</th>
                    <th>城市</th>
                    <th>手机</th>
                    <th>状态</th>
                    <th>注册时间</th>
                    <th>最后登录</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($users)): ?>
                <tr><td colspan="9" style="text-align:center;color:#999;padding:40px;">暂无用户数据</td></tr>
                <?php else: ?>
                <?php foreach ($users as $u): ?>
                <tr>
                    <td><?= $u['id'] ?></td>
                    <td><?= htmlspecialchars($u['username']) ?></td>
                    <td><?= htmlspecialchars($u['email']) ?></td>
                    <td>
                        <span class="admin-badge <?= $u['role'] === 'admin' ? 'danger' : 'info' ?>">
                            <?= $u['role'] === 'admin' ? '管理员' : '普通用户' ?>
                        </span>
                    </td>
                    <td><?= htmlspecialchars($u['city'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($u['phone'] ?? '-') ?></td>
                    <td>
                        <span class="admin-badge <?= $u['status'] === 'active' ? 'success' : 'warning' ?>">
                            <?= $u['status'] === 'active' ? '正常' : '停用' ?>
                        </span>
                    </td>
                    <td><?= date('Y-m-d', strtotime($u['created_at'])) ?></td>
                    <td><?= $u['last_login'] ? date('Y-m-d H:i', strtotime($u['last_login'])) : '-' ?></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($totalPages > 1): ?>
    <div style="padding:16px 20px;display:flex;justify-content:center;gap:6px;border-top:1px solid #f0f0f0;">
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <?php $query = http_build_query(['search' => $search, 'page' => $i]); ?>
            <a href="?<?= $query ?>" class="admin-btn admin-btn-sm <?= $i === $page ? 'admin-btn-primary' : 'admin-btn-secondary' ?>" style="min-width:36px;justify-content:center;"><?= $i ?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>

<div class="admin-card" style="margin-top:20px;">
    <div class="admin-card-header">
        <span class="admin-card-title"><i class="fas fa-chart-pie" style="margin-right:8px;color:var(--admin-accent);"></i>用户统计</span>
    </div>
    <div class="admin-card-body">
        <?php
        $statAdmins  = $pdo->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn();
        $statActive  = $pdo->query("SELECT COUNT(*) FROM users WHERE status='active'")->fetchColumn();
        $statInactive = $pdo->query("SELECT COUNT(*) FROM users WHERE status='inactive'")->fetchColumn();
        ?>
        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:16px;">
            <div style="text-align:center;padding:16px;background:#f8fafc;border-radius:8px;">
                <div style="font-size:24px;font-weight:700;color:#1976d2;"><?= number_format($total) ?></div>
                <div style="font-size:13px;color:#64748b;">总用户</div>
            </div>
            <div style="text-align:center;padding:16px;background:#f8fafc;border-radius:8px;">
                <div style="font-size:24px;font-weight:700;color:#7b1fa2;"><?= number_format($statAdmins) ?></div>
                <div style="font-size:13px;color:#64748b;">管理员</div>
            </div>
            <div style="text-align:center;padding:16px;background:#f8fafc;border-radius:8px;">
                <div style="font-size:24px;font-weight:700;color:#388e3c;"><?= number_format($statActive) ?></div>
                <div style="font-size:13px;color:#64748b;">活跃用户</div>
            </div>
            <div style="text-align:center;padding:16px;background:#f8fafc;border-radius:8px;">
                <div style="font-size:24px;font-weight:700;color:#f57c00;"><?= number_format($statInactive) ?></div>
                <div style="font-size:13px;color:#64748b;">停用用户</div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../shared/admin/admin-footer.php'; ?>
