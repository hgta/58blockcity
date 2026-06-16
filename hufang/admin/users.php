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
?>

$admin_site_config = ['site' => 'hufang', 'page_title' => '用户管理'];
require_once '../../shared/admin/admin-header.php';

<div class="container admin-container">
    <!-- 页面标题和面包屑导航 -->
    <div class="admin-header">
        <h1><i class="fas fa-users"></i> 用户管理</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="dashboard.php"><i class="fas fa-home"></i> 仪表盘</a></li>
                <li class="breadcrumb-item active" aria-current="page">用户管理</li>
            </ol>
        </nav>
    </div>

    <!-- 消息提示 -->
    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?= $_SESSION['message'] ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
        <?php unset($_SESSION['message']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?= $_SESSION['error'] ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <!-- 搜索和添加用户 -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row align-items-center">
                <div class="col-md-8 mb-3 mb-md-0">
                    <form method="get" class="search-form">
                        <div class="input-group">
                            <input type="text" name="search" class="form-control" placeholder="搜索用户名、邮箱或城市..." value="<?= htmlspecialchars($search) ?>">
                            <div class="input-group-append">
                                <button class="btn btn-primary" type="submit">
                                    <i class="fas fa-search"></i> 搜索
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="col-md-4 text-md-right">
                    <a href="user_add.php" class="btn btn-success">
                        <i class="fas fa-plus"></i> 添加用户
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- 用户列表 -->
    <div class="card">
        <div class="card-header bg-white">
            <h5 class="mb-0">用户列表</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="thead-light">
                        <tr>
                            <th width="5%">ID</th>
                            <th width="20%">用户名</th>
                            <th width="20%">邮箱</th>
                            <th width="15%">城市</th>
                            <th width="15%">注册时间</th>
                            <th width="10%">状态</th>
                            <th width="15%">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($users)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-5">
                                    <div class="empty-state">
                                        <i class="fas fa-user-slash fa-3x text-muted mb-3"></i>
                                        <h4 class="text-muted">没有找到用户</h4>
                                        <p class="text-muted">尝试修改搜索条件或添加新用户</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?= $user['id'] ?></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <img src="../assets/images/<?= htmlspecialchars($user['avatar']) ?>" alt="<?= htmlspecialchars($user['username']) ?>" class="rounded-circle avatar-sm mr-2" style="width:32px;height:32px;object-fit:cover;flex-shrink:0;">
                                            <span><?= htmlspecialchars($user['username']) ?></span>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($user['email']) ?></td>
                                    <td><?= htmlspecialchars($user['city']) ?></td>
                                    <td><?= date('Y-m-d H:i', strtotime($user['created_at'])) ?></td>
                                    <td>
                                        <span class="badge badge-<?= $user['status'] === 'active' ? 'success' : 'secondary' ?>">
                                            <?= $user['status'] === 'active' ? '活跃' : '停用' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="user_edit.php?id=<?= $user['id'] ?>" class="btn btn-outline-primary" title="编辑">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            
                                            <?php if ($user['id'] != 1): ?>
                                                <?php if ($user['status'] === 'active'): ?>
                                                    <form method="post" class="d-inline">
                                                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                        <input type="hidden" name="action" value="deactivate">
                                                        <button type="submit" class="btn btn-outline-warning" title="停用" onclick="return confirm('确定要停用此用户吗？')">
                                                            <i class="fas fa-ban"></i>
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <form method="post" class="d-inline">
                                                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                        <input type="hidden" name="action" value="activate">
                                                        <button type="submit" class="btn btn-outline-success" title="激活" onclick="return confirm('确定要激活此用户吗？')">
                                                            <i class="fas fa-check"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                                
                                                <form method="post" class="d-inline">
                                                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                    <input type="hidden" name="action" value="delete">
                                                    <button type="submit" class="btn btn-outline-danger" title="删除" onclick="return confirm('确定要删除此用户吗？此操作不可恢复！')">
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
        </div>
        
        <!-- 分页信息和导航 -->
                <?php if ($totalPages > 1): ?>
                    <!-- 分页信息 -->
                    <div class="pagination-info-clean">
                        <div class="total-count">共 <?= $totalCities ?> 条记录</div>
                        <div class="page-indicator">第 <?= $page ?> 页 / 共 <?= $totalPages ?> 页</div>
                    </div>

                    <!-- 分页导航 -->
                    <nav aria-label="Page navigation">
                        <ul class="pagination pagination-clean justify-content-center">
                            <!-- 上一页 -->
                            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                <a class="page-link" href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>" aria-label="Previous">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            </li>

                            <!-- 首页 -->
                            <?php if ($page > 3): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=1&search=<?= urlencode($search) ?>">1</a>
                                </li>
                                <?php if ($page > 4): ?>
                                    <li class="page-item">
                                        <span class="page-ellipsis">...</span>
                                    </li>
                                <?php endif; ?>
                            <?php endif; ?>

                            <!-- 中间页码 -->
                            <?php for ($i = max(1, $page - 1); $i <= min($totalPages, $page + 1); $i++): ?>
                                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                    <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>">
                                        <?= $i ?>
                                    </a>
                                </li>
                            <?php endfor; ?>

                            <!-- 末页 -->
                            <?php if ($page < $totalPages - 2): ?>
                                <?php if ($page < $totalPages - 3): ?>
                                    <li class="page-item">
                                        <span class="page-ellipsis">...</span>
                                    </li>
                                <?php endif; ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?= $totalPages ?>&search=<?= urlencode($search) ?>">
                                        <?= $totalPages ?>
                                    </a>
                                </li>
                            <?php endif; ?>

                            <!-- 下一页 -->
                            <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                                <a class="page-link" href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>" aria-label="Next">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                        </ul>
                    </nav>
                <?php endif; ?>
    </div>
</div>

<?php require_once '../../shared/admin/admin-footer.php'; ?>