<?php
/**
 * 人气商城 — 用户管理
 */
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../classes/User.php';

$user = new User($pdo);
$currentAdminId = intval($_SESSION['user_id'] ?? 0);

// 搜索 & 分页
$search = $_GET['search'] ?? '';
$page   = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 20;
$offset  = ($page - 1) * $perPage;

// 操作处理：停用 / 激活 / 删除（与互访圈后台一致）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $targetId = intval($_POST['user_id']);
    if ($targetId === 1 || $targetId === $currentAdminId) {
        $_SESSION['admin_err'] = '不能对自己或超级管理员(id=1)执行该操作';
    } else {
        switch ($_POST['action']) {
            case 'activate':
                $user->updateUserStatus($targetId, 'active');
                $_SESSION['admin_msg'] = '用户已激活';
                break;
            case 'deactivate':
                $user->updateUserStatus($targetId, 'inactive');
                $_SESSION['admin_msg'] = '用户已停用';
                break;
            case 'delete':
                $user->deleteUser($targetId);
                $_SESSION['admin_msg'] = '用户已删除';
                break;
        }
    }
    // 重定向避免刷新重复提交，并保留筛选/分页
    $rq = [];
    if ($search !== '') $rq['search'] = $search;
    if ($page > 1) $rq['page'] = $page;
    header('Location: users.php' . ($rq ? '?' . http_build_query($rq) : ''));
    exit;
}

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

<?php if (!empty($_SESSION['admin_msg'])): ?>
    <div class="admin-alert success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($_SESSION['admin_msg']) ?></div>
    <?php unset($_SESSION['admin_msg']); ?>
<?php endif; ?>
<?php if (!empty($_SESSION['admin_err'])): ?>
    <div class="admin-alert danger"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($_SESSION['admin_err']) ?></div>
    <?php unset($_SESSION['admin_err']); ?>
<?php endif; ?>

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
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($users)): ?>
                <tr><td colspan="10" style="text-align:center;color:#999;padding:40px;">暂无用户数据</td></tr>
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
                    <td>
                        <?php if ($u['id'] != 1 && $u['id'] != $currentAdminId): ?>
                        <div style="display:flex;gap:6px;flex-wrap:wrap;">
                            <?php if ($u['status'] === 'active'): ?>
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                <input type="hidden" name="action" value="deactivate">
                                <button type="submit" class="admin-btn admin-btn-sm admin-btn-secondary" title="停用" onclick="return confirm('确定要停用该用户吗？')"><i class="fas fa-ban"></i> 停用</button>
                            </form>
                            <?php else: ?>
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                <input type="hidden" name="action" value="activate">
                                <button type="submit" class="admin-btn admin-btn-sm admin-btn-secondary" title="激活" onclick="return confirm('确定要激活该用户吗？')"><i class="fas fa-check"></i> 激活</button>
                            </form>
                            <?php endif; ?>
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                <input type="hidden" name="action" value="delete">
                                <button type="submit" class="admin-btn admin-btn-sm admin-btn-danger" title="删除" onclick="return confirm('确定要删除该用户吗？此操作不可恢复！')"><i class="fas fa-trash"></i> 删除</button>
                            </form>
                        </div>
                        <?php else: ?>
                        <span class="admin-text-muted">保护中</span>
                        <?php endif; ?>
                    </td>
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
