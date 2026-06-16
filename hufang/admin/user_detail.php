<?php
require_once '../../config/database.php';
require_once '../includes/auth.php';
require_once '../../classes/User.php';
require_once '../../classes/Circle.php';
require_once '../../classes/Visit.php';

// 检查管理员权限
checkAdmin();

// 获取要查看的用户ID
$targetUserId = intval($_GET['id'] ?? 0);
if ($targetUserId <= 0) {
    header('Location: users.php');
    exit();
}

// 初始化类
$user = new User($pdo);
$circle = new Circle($pdo);
$visit = new Visit($pdo);

// 获取用户信息
$userInfo = $user->getUserById($targetUserId);
if (!$userInfo) {
    $_SESSION['error'] = '用户不存在';
    header('Location: users.php');
    exit();
}

// 获取用户创建的互访圈
$userCircles = $circle->getUserCircles($targetUserId);

// 获取用户参与的互访记录
$userVisits = $visit->getUserVisits($targetUserId);

// 获取用户统计数据
$stats = [
    'circles_created' => count($userCircles),
    'visits_requested' => 0,
    'visits_confirmed' => 0,
    'visits_completed' => 0
];

foreach ($userVisits as $visit) {
    if ($visit['status'] === 'pending') $stats['visits_requested']++;
    if ($visit['status'] === 'confirmed') $stats['visits_confirmed']++;
    if ($visit['status'] === 'completed') $stats['visits_completed']++;
}

// 处理状态变更
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_status'])) {
        $newStatus = $_POST['status'];
        
        // 不能修改超级管理员(用户ID=1)的状态
        if ($targetUserId != 1) {
            if ($user->updateUserStatus($targetUserId, $newStatus)) {
                $_SESSION['message'] = '用户状态已更新';
                header('Location: user_detail.php?id=' . $targetUserId);
                exit();
            } else {
                $_SESSION['error'] = '更新用户状态失败';
            }
        } else {
            $_SESSION['error'] = '不能修改超级管理员状态';
        }
    }
    
    // 重置密码
    if (isset($_POST['reset_password'])) {
        $newPassword = bin2hex(random_bytes(4)); // 生成8位随机密码
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        
        if ($user->updateUserPassword($targetUserId, $hashedPassword)) {
            $_SESSION['message'] = '密码已重置，新密码为: <strong>' . $newPassword . '</strong> (请通知用户及时修改)';
            header('Location: user_detail.php?id=' . $targetUserId);
            exit();
        } else {
            $_SESSION['error'] = '重置密码失败';
        }
    }
}
?>

$admin_site_config = ['site' => 'hufang', 'page_title' => '用户详情'];
require_once '../../shared/admin/admin-header.php';

<div class="container admin-container">
    <!-- 页面标题和面包屑导航 -->
    <div class="admin-header">
        <h1><i class="fas fa-user"></i> 用户详情</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="dashboard.php"><i class="fas fa-home"></i> 仪表盘</a></li>
                <li class="breadcrumb-item"><a href="users.php">用户管理</a></li>
                <li class="breadcrumb-item active" aria-current="page">用户详情</li>
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

    <div class="row">
        <!-- 左侧用户信息 -->
        <div class="col-md-4 mb-4">
            <div class="card">
                <div class="card-body text-center">
                    <img src="../assets/images/<?= htmlspecialchars($userInfo['avatar']) ?>" alt="<?= htmlspecialchars($userInfo['username']) ?>" class="rounded-circle mb-3" width="120" height="120">
                    <h3><?= htmlspecialchars($userInfo['username']) ?></h3>
                    <p class="text-muted">注册于 <?= date('Y-m-d H:i', strtotime($userInfo['created_at'])) ?></p>
                    
                    <div class="badge badge-<?= $userInfo['status'] === 'active' ? 'success' : 'secondary' ?> mb-3">
                        <?= $userInfo['status'] === 'active' ? '活跃用户' : '停用用户' ?>
                    </div>
                    
                    <ul class="list-group list-group-flush mb-3">
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span>邮箱</span>
                            <span><?= htmlspecialchars($userInfo['email']) ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span>电话</span>
                            <span><?= $userInfo['phone'] ? htmlspecialchars($userInfo['phone']) : '未设置' ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span>城市</span>
                            <span><?= $userInfo['city'] ? htmlspecialchars($userInfo['city']) : '未设置' ?></span>
                        </li>
                    </ul>
                    
                    <!-- 状态变更表单 -->
                    <form method="post" class="mb-3">
                        <div class="form-group">
                            <label for="status">用户状态</label>
                            <select name="status" id="status" class="form-control" <?= $targetUserId == 1 ? 'disabled' : '' ?>>
                                <option value="active" <?= $userInfo['status'] === 'active' ? 'selected' : '' ?>>活跃</option>
                                <option value="inactive" <?= $userInfo['status'] === 'inactive' ? 'selected' : '' ?>>停用</option>
                            </select>
                        </div>
                        <?php if ($targetUserId != 1): ?>
                            <button type="submit" name="update_status" class="btn btn-primary btn-block">
                                <i class="fas fa-save"></i> 更新状态
                            </button>
                        <?php endif; ?>
                    </form>
                    
                    <!-- 重置密码 -->
                    <form method="post">
                        <button type="submit" name="reset_password" class="btn btn-warning btn-block" onclick="return confirm('确定要重置此用户的密码吗？新密码将随机生成并显示。')">
                            <i class="fas fa-key"></i> 重置密码
                        </button>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- 右侧用户活动 -->
        <div class="col-md-8">
            <!-- 用户统计 -->
            <div class="row mb-4">
                <div class="col-md-4 mb-3 mb-md-0">
                    <div class="card bg-primary text-white">
                        <div class="card-body text-center">
                            <h5 class="card-title">创建的互访圈</h5>
                            <h2><?= $stats['circles_created'] ?></h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3 mb-md-0">
                    <div class="card bg-info text-white">
                        <div class="card-body text-center">
                            <h5 class="card-title">发起的互访</h5>
                            <h2><?= $stats['visits_requested'] ?></h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card bg-success text-white">
                        <div class="card-body text-center">
                            <h5 class="card-title">完成的互访</h5>
                            <h2><?= $stats['visits_completed'] ?></h2>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- 用户创建的互访圈 -->
            <div class="card mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="fas fa-users"></i> 创建的互访圈</h5>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($userCircles)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-users-slash fa-2x text-muted mb-3"></i>
                            <p class="text-muted">该用户尚未创建任何互访圈</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="thead-light">
                                    <tr>
                                        <th>名称</th>
                                        <th>城市</th>
                                        <th>区块数</th>
                                        <th>状态</th>
                                        <th>操作</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($userCircles as $circle): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($circle['name']) ?></td>
                                            <td><?= htmlspecialchars($circle['city']) ?></td>
                                            <td><?= $circle['block_count'] ?></td>
                                            <td>
                                                <span class="badge badge-<?= $circle['status'] === 'active' ? 'success' : 'secondary' ?>">
                                                    <?= $circle['status'] === 'active' ? '活跃' : '停用' ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="../circles/view.php?id=<?= $circle['id'] ?>" class="btn btn-sm btn-outline-primary" title="查看">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- 用户参与的互访记录 -->
            <div class="card">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="fas fa-exchange-alt"></i> 互访记录</h5>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($userVisits)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-exchange-alt fa-2x text-muted mb-3"></i>
                            <p class="text-muted">该用户尚未参与任何互访</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="thead-light">
                                    <tr>
                                        <th>互访圈</th>
                                        <th>圈主</th>
                                        <th>状态</th>
                                        <th>访问日期</th>
                                        <th>操作</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($userVisits as $visit): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($visit['circle_name']) ?></td>
                                            <td><?= htmlspecialchars($visit['circle_owner']) ?></td>
                                            <td>
                                                <span class="badge badge-<?= 
                                                    $visit['status'] === 'completed' ? 'success' : 
                                                    ($visit['status'] === 'confirmed' ? 'primary' : 
                                                    ($visit['status'] === 'pending' ? 'warning' : 'secondary')) ?>">
                                                    <?= $visit['status'] === 'completed' ? '已完成' : 
                                                      ($visit['status'] === 'confirmed' ? '已确认' : 
                                                      ($visit['status'] === 'pending' ? '待确认' : '已取消')) ?>
                                                </span>
                                            </td>
                                            <td><?= $visit['visit_date'] ? date('Y-m-d', strtotime($visit['visit_date'])) : '-' ?></td>
                                            <td>
                                                <a href="visit_detail.php?id=<?= $visit['id'] ?>" class="btn btn-sm btn-outline-primary" title="详情">
                                                    <i class="fas fa-info-circle"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../shared/admin/admin-footer.php'; ?>