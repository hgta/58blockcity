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
$visitObj = new Visit($pdo);

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
$userVisits = $visitObj->getUserVisits($targetUserId);

// 获取用户统计数据
$stats = [
    'circles_created' => count($userCircles),
    'visits_requested' => 0,
    'visits_confirmed' => 0,
    'visits_completed' => 0
];

foreach ($userVisits as $v) {
    if ($v['status'] === 'pending') $stats['visits_requested']++;
    if ($v['status'] === 'confirmed') $stats['visits_confirmed']++;
    if ($v['status'] === 'completed') $stats['visits_completed']++;
}

// 处理状态变更
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_status'])) {
        $newStatus = $_POST['status'];
        
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
        $newPassword = bin2hex(random_bytes(4));
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

$admin_site_config = ['site' => 'hufang', 'page_title' => '用户详情'];
require_once '../../shared/admin/admin-header.php';
?>

<?php if (isset($_SESSION['message'])): ?>
    <div class="admin-alert success"><i class="fas fa-check-circle"></i> <?= $_SESSION['message'] ?></div>
    <?php unset($_SESSION['message']); ?>
<?php endif; ?>
<?php if (isset($_SESSION['error'])): ?>
    <div class="admin-alert danger"><i class="fas fa-exclamation-circle"></i> <?= $_SESSION['error'] ?></div>
    <?php unset($_SESSION['error']); ?>
<?php endif; ?>

<div style="display:grid;grid-template-columns:340px 1fr;gap:20px;align-items:start;">
    <!-- 左侧用户信息 -->
    <div class="admin-card">
        <div class="admin-card-body" style="text-center;">
            <img src="../assets/images/<?= htmlspecialchars($userInfo['avatar'] ?? 'default.jpg') ?>" alt="" style="width:120px;height:120px;border-radius:50%;object-fit:cover;margin-bottom:16px;">
            <h3 style="margin:0 0 8px;"><?= htmlspecialchars($userInfo['username']) ?></h3>
            <p class="admin-text-muted" style="font-size:13px;margin-bottom:12px;">注册于 <?= date('Y-m-d H:i', strtotime($userInfo['created_at'])) ?></p>
            <?php if ($userInfo['status'] === 'active'): ?>
                <span class="admin-badge success" style="margin-bottom:16px;">活跃用户</span>
            <?php else: ?>
                <span class="admin-badge default" style="margin-bottom:16px;">停用用户</span>
            <?php endif; ?>
            
            <div style="text-align:left;border-top:1px solid var(--admin-border);padding-top:16px;margin-top:8px;">
                <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--admin-border);">
                    <span class="admin-text-muted">邮箱</span>
                    <span><?= htmlspecialchars($userInfo['email']) ?></span>
                </div>
                <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--admin-border);">
                    <span class="admin-text-muted">电话</span>
                    <span><?= $userInfo['phone'] ? htmlspecialchars($userInfo['phone']) : '未设置' ?></span>
                </div>
                <div style="display:flex;justify-content:space-between;padding:8px 0;">
                    <span class="admin-text-muted">城市</span>
                    <span><?= $userInfo['city'] ? htmlspecialchars($userInfo['city']) : '未设置' ?></span>
                </div>
            </div>
            
            <!-- 状态变更表单 -->
            <form method="post" style="margin-top:16px;text-align:left;">
                <div class="admin-form-group">
                    <label class="admin-form-label">用户状态</label>
                    <select name="status" class="admin-form-select" <?= $targetUserId == 1 ? 'disabled' : '' ?>>
                        <option value="active" <?= $userInfo['status'] === 'active' ? 'selected' : '' ?>>活跃</option>
                        <option value="inactive" <?= $userInfo['status'] === 'inactive' ? 'selected' : '' ?>>停用</option>
                    </select>
                </div>
                <?php if ($targetUserId != 1): ?>
                    <button type="submit" name="update_status" class="admin-btn admin-btn-primary" style="width:100%;"><i class="fas fa-save"></i> 更新状态</button>
                <?php endif; ?>
            </form>
            
            <!-- 重置密码 -->
            <form method="post" style="margin-top:12px;">
                <button type="submit" name="reset_password" class="admin-btn admin-btn-default" style="width:100%;" onclick="return confirm('确定要重置此用户的密码吗？新密码将随机生成并显示。')">
                    <i class="fas fa-key"></i> 重置密码
                </button>
            </form>
        </div>
    </div>
    
    <!-- 右侧用户活动 -->
    <div>
        <!-- 用户统计 -->
        <div class="admin-stats-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:20px;">
            <div class="admin-stat-card">
                <div class="stat-icon accent"><i class="fas fa-users"></i></div>
                <div class="stat-value"><?= $stats['circles_created'] ?></div>
                <div class="stat-label">创建的互访圈</div>
            </div>
            <div class="admin-stat-card">
                <div class="stat-icon info"><i class="fas fa-paper-plane"></i></div>
                <div class="stat-value"><?= $stats['visits_requested'] ?></div>
                <div class="stat-label">发起的互访</div>
            </div>
            <div class="admin-stat-card">
                <div class="stat-icon success"><i class="fas fa-check-circle"></i></div>
                <div class="stat-value"><?= $stats['visits_completed'] ?></div>
                <div class="stat-label">完成的互访</div>
            </div>
        </div>
        
        <!-- 用户创建的互访圈 -->
        <div class="admin-card" style="margin-bottom:20px;">
            <div class="admin-card-header">
                <span class="admin-card-title"><i class="fas fa-users"></i> 创建的互访圈</span>
            </div>
            <?php if (empty($userCircles)): ?>
                <div class="admin-empty-state"><i class="fas fa-users-slash"></i><p>该用户尚未创建任何互访圈</p></div>
            <?php else: ?>
                <div class="admin-table-responsive">
                    <table class="admin-data-table">
                        <thead><tr><th>名称</th><th>城市</th><th>区块数</th><th>状态</th><th>操作</th></tr></thead>
                        <tbody>
                            <?php foreach ($userCircles as $c): ?>
                                <tr>
                                    <td><?= htmlspecialchars($c['name']) ?></td>
                                    <td><?= htmlspecialchars($c['city']) ?></td>
                                    <td><?= $c['block_count'] ?></td>
                                    <td>
                                        <?php if ($c['status'] === 'active'): ?>
                                            <span class="admin-badge success">活跃</span>
                                        <?php else: ?>
                                            <span class="admin-badge default">停用</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="../circles/view.php?id=<?= $c['id'] ?>" class="admin-btn admin-btn-sm admin-btn-default" title="查看"><i class="fas fa-eye"></i></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- 用户参与的互访记录 -->
        <div class="admin-card">
            <div class="admin-card-header">
                <span class="admin-card-title"><i class="fas fa-exchange-alt"></i> 互访记录</span>
            </div>
            <?php if (empty($userVisits)): ?>
                <div class="admin-empty-state"><i class="fas fa-exchange-alt"></i><p>该用户尚未参与任何互访</p></div>
            <?php else: ?>
                <div class="admin-table-responsive">
                    <table class="admin-data-table">
                        <thead><tr><th>互访圈</th><th>圈主</th><th>状态</th><th>访问日期</th><th>操作</th></tr></thead>
                        <tbody>
                            <?php foreach ($userVisits as $v): ?>
                                <tr>
                                    <td><?= htmlspecialchars($v['circle_name']) ?></td>
                                    <td><?= htmlspecialchars($v['circle_owner']) ?></td>
                                    <td>
                                        <?php
                                        $statusMap = [
                                            'completed' => ['已完成', 'success'],
                                            'confirmed' => ['已确认', 'info'],
                                            'pending'   => ['待确认', 'warning'],
                                            'cancelled' => ['已取消', 'default'],
                                        ];
                                        $s = $statusMap[$v['status']] ?? [$v['status'], 'default'];
                                        ?>
                                        <span class="admin-badge <?= $s[1] ?>"><?= $s[0] ?></span>
                                    </td>
                                    <td><?= $v['visit_date'] ? date('Y-m-d', strtotime($v['visit_date'])) : '-' ?></td>
                                    <td>
                                        <a href="visit_detail.php?id=<?= $v['id'] ?>" class="admin-btn admin-btn-sm admin-btn-default" title="详情"><i class="fas fa-info-circle"></i></a>
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

<?php require_once '../../shared/admin/admin-footer.php'; ?>
