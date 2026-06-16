<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../classes/User.php';
require_once '../classes/Shop.php';

checkAdmin();

$user = new User($pdo);
$shop = new Shop($pdo);

$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 20;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$where = "WHERE 1=1";
$params = [];
if ($search) {
    $where .= " AND (username LIKE ? OR email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM users $where");
$countStmt->execute($params);
$total = $countStmt->fetchColumn();
$totalPages = ceil($total / $perPage);
$offset = ($page - 1) * $perPage;

$stmt = $pdo->prepare("SELECT id, username, email, role, status, created_at, last_login FROM users $where ORDER BY created_at DESC LIMIT ? OFFSET ?");
foreach ($params as $i => $v) { $stmt->bindValue($i+1, $v); }
$stmt->bindValue(count($params)+1, $perPage, PDO::PARAM_INT);
$stmt->bindValue(count($params)+2, $offset, PDO::PARAM_INT);
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

$admin_site_config = ['site' => 'main', 'page_title' => '用户管理'];
require_once '../shared/admin/admin-header.php';
?>

<div class="admin-card">
    <div class="admin-card-header">
        <span class="admin-card-title"><i class="fas fa-users"></i> 用户列表 (共 <?= $total ?> 人)</span>
        <form method="GET" style="display:flex;gap:8px;">
            <input type="text" name="search" placeholder="搜索用户名或邮箱..." value="<?= htmlspecialchars($search) ?>"
                   style="padding:6px 12px;background:#0f172a;border:1px solid #334155;border-radius:6px;color:#f1f5f9;font-size:13px;width:200px;">
            <button type="submit" class="admin-btn admin-btn-primary admin-btn-sm">搜索</button>
            <?php if ($search): ?><a href="users.php" class="admin-btn admin-btn-secondary admin-btn-sm">重置</a><?php endif; ?>
        </form>
    </div>
    <div class="admin-card-body" style="padding:0;">
        <table class="admin-data-table">
            <thead>
                <tr><th>ID</th><th>用户名</th><th>邮箱</th><th>角色</th><th>状态</th><th>注册时间</th><th>最后登录</th></tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                <tr>
                    <td><?= $u['id'] ?></td>
                    <td><strong><?= htmlspecialchars($u['username']) ?></strong></td>
                    <td><?= htmlspecialchars($u['email']) ?></td>
                    <td><span class="admin-badge <?= $u['role']=='admin'?'danger':'info' ?>"><?= $u['role'] ?></span></td>
                    <td><span class="admin-badge <?= $u['status']=='active'?'success':'default' ?>"><?= $u['status'] ?></span></td>
                    <td><?= $u['created_at'] ? date('Y-m-d', strtotime($u['created_at'])) : '-' ?></td>
                    <td><?= $u['last_login'] ? date('m-d H:i', strtotime($u['last_login'])) : '-' ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($users)): ?>
                <tr><td colspan="7" style="text-align:center;color:#64748b;padding:30px;">未找到用户</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        <?php if ($totalPages > 1): ?>
        <div style="display:flex;gap:6px;justify-content:center;padding:16px 20px;border-top:1px solid #1e293b;">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['page'=>$i])) ?>" class="admin-btn admin-btn-sm <?= $i==$page?'admin-btn-primary':'admin-btn-secondary' ?>" style="min-width:36px;justify-content:center;"><?= $i ?></a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../shared/admin/admin-footer.php'; ?>
