<?php
require_once '../../config/database.php';
require_once '../includes/auth.php';
require_once '../../classes/User.php';

// 检查管理员权限
checkAdmin();
$user = new User($pdo);

// 处理搜索和分页
$search = $_GET['search'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 15;

// 获取用户数据
$totalUsers = $user->getTotalUsersCount($search);
$users = $user->getUsersWithPagination($page, $perPage, $search);
$totalPages = ceil($totalUsers / $perPage);

// 处理用户状态变更
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $targetUserId = intval($_POST['user_id']);
    
    if ($targetUserId !== 1) { // 不能修改超级管理员
        switch ($_POST['action']) {
            case 'activate':
                $user->updateUserStatus($targetUserId, 'active');
                $_SESSION['message'] = '用户已激活';
                break;
            case 'deactivate':
                $user->updateUserStatus($targetUserId, 'inactive');
                $_SESSION['message'] = '用户已停用';
                break;
            case 'delete':
                $user->deleteUser($targetUserId);
                $_SESSION['message'] = '用户已删除';
                break;
        }
        header('Location: users.php');
        exit();
    } else {
        $_SESSION['error'] = '不能修改超级管理员状态';
    }
}

$admin_site_config = ['site' => 'hufang', 'page_title' => '用户管理'];
require_once '../../shared/admin/admin-header.php';
?>

<?php if (isset($_SESSION['message'])): ?>
    <div class="admin-alert success">
        <i class="fas fa-check-circle"></i> <?= $_SESSION['message'] ?>
    </div>
    <?php unset($_SESSION['message']); ?>
<?php endif; ?>

<?php if (isset($_SESSION['error'])): ?>
    <div class="admin-alert danger">
        <i class="fas fa-exclamation-circle"></i> <?= $_SESSION['error'] ?>
    </div>
    <?php unset($_SESSION['error']); ?>
<?php endif; ?>

<!-- 筛选栏 -->
<div class="admin-card">
    <div class="admin-card-header">
        <span class="admin-card-title"><i class="fas fa-filter"></i> 搜索用户</span>
    </div>
    <form method="get" class="admin-form-row">
        <div class="admin-form-group" style="flex:2;">
            <input type="text" name="search" class="admin-form-input" placeholder="搜索用户名、邮箱或城市..." value="<?= htmlspecialchars($search) ?>">
        </div>
        <div class="admin-form-group">
            <button type="submit" class="admin-btn admin-btn-primary"><i class="fas fa-search"></i> 搜索</button>
            <a href="users.php" class="admin-btn admin-btn-default"><i class="fas fa-undo"></i> 重置</a>
        </div>
    </form>
</div>

<!-- 用户列表 -->
<div class="admin-card">
    <div class="admin-card-header" style="justify-content:space-between;">
        <span class="admin-card-title"><i class="fas fa-users"></i> 用户列表</span>
        <span class="admin-text-muted">共 <?= number_format($totalUsers) ?> 条记录</span>
    </div>
    <div class="admin-table-responsive">
        <table class="admin-data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>用户名</th>
                    <th>邮箱</th>
                    <th>城市</th>
                    <th>注册时间</th>
                    <th>状态</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($users)): ?>
                    <tr>
                        <td colspan="7" class="admin-empty-state">
                            <i class="fas fa-user-slash"></i>
                            <p>没有找到用户</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td><?= $u['id'] ?></td>
                            <td>
                                <div style="display:flex;align-items:center;gap:8px;">
                                    <img src="../assets/images/<?= htmlspecialchars($u['avatar'] ?? 'default.jpg') ?>" alt="" style="width:32px;height:32px;border-radius:50%;object-fit:cover;flex-shrink:0;">
                                    <span><?= htmlspecialchars($u['username']) ?></span>
                                </div>
                            </td>
                            <td><?= htmlspecialchars($u['email']) ?></td>
                            <td><?= htmlspecialchars($u['city']) ?></td>
                            <td><?= date('Y-m-d H:i', strtotime($u['created_at'])) ?></td>
                            <td>
                                <?php if ($u['status'] === 'active'): ?>
                                    <span class="admin-badge success">活跃</span>
                                <?php else: ?>
                                    <span class="admin-badge default">停用</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="admin-btn-group">
                                    <a href="user_edit.php?id=<?= $u['id'] ?>" class="admin-btn admin-btn-sm admin-btn-default" title="编辑">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <?php if ($u['id'] != 1): ?>
                                        <?php if ($u['status'] === 'active'): ?>
                                            <form method="post" style="display:inline;">
                                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                                <input type="hidden" name="action" value="deactivate">
                                                <button type="submit" class="admin-btn admin-btn-sm admin-btn-default" title="停用" onclick="return confirm('确定要停用此用户吗？')">
                                                    <i class="fas fa-ban"></i>
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <form method="post" style="display:inline;">
                                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                                <input type="hidden" name="action" value="activate">
                                                <button type="submit" class="admin-btn admin-btn-sm admin-btn-default" title="激活" onclick="return confirm('确定要激活此用户吗？')">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        <form method="post" style="display:inline;">
                                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <button type="submit" class="admin-btn admin-btn-sm admin-btn-danger" title="删除" onclick="return confirm('确定要删除此用户吗？此操作不可恢复！')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
        <?php $prefix = $search !== '' ? '?search=' . urlencode($search) . '&' : '?'; ?>
        <div class="admin-pagination">
            <div class="admin-page-info">第 <?= $page ?> 页 / 共 <?= $totalPages ?> 页</div>
            <div class="admin-page-buttons">
                <a href="users.php<?= $prefix ?>page=1" class="admin-btn admin-btn-sm admin-btn-default <?= $page <= 1 ? 'disabled' : '' ?>">首页</a>
                <a href="users.php<?= $prefix ?>page=<?= $page - 1 ?>" class="admin-btn admin-btn-sm admin-btn-default <?= $page <= 1 ? 'disabled' : '' ?>">上一页</a>
                <?php
                $start = max(1, $page - 2);
                $end = min($totalPages, $page + 2);
                for ($i = $start; $i <= $end; $i++):
                ?>
                    <a href="users.php<?= $prefix ?>page=<?= $i ?>" class="admin-btn admin-btn-sm <?= $i === $page ? 'admin-btn-primary' : 'admin-btn-default' ?>"><?= $i ?></a>
                <?php endfor; ?>
                <a href="users.php<?= $prefix ?>page=<?= $page + 1 ?>" class="admin-btn admin-btn-sm admin-btn-default <?= $page >= $totalPages ? 'disabled' : '' ?>">下一页</a>
                <a href="users.php<?= $prefix ?>page=<?= $totalPages ?>" class="admin-btn admin-btn-sm admin-btn-default <?= $page >= $totalPages ? 'disabled' : '' ?>">末页</a>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once '../../shared/admin/admin-footer.php'; ?>
