<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../includes/header.php';
require_once '../../classes/User.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$user = new User($pdo);
$userId = $_SESSION['user_id'];

// 获取用户信息
$userInfo = $user->getUserById($userId);
if (!$userInfo) {
    header('Location: ../auth/login.php');
    exit;
}

$error = '';
$success = '';

// 处理密码修改
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    if (empty($currentPassword)) {
        $error = '请输入当前密码';
    } elseif (empty($newPassword)) {
        $error = '请输入新密码';
    } elseif (strlen($newPassword) < 6) {
        $error = '新密码长度不能少于6位';
    } elseif ($newPassword !== $confirmPassword) {
        $error = '两次输入的新密码不一致';
    } else {
        // 验证当前密码
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $dbUser = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$dbUser || !password_verify($currentPassword, $dbUser['password'])) {
            $error = '当前密码不正确';
        } else {
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            if ($user->updateUserPassword($userId, $hashedPassword)) {
                $success = '密码修改成功，请使用新密码重新登录';
            } else {
                $error = '密码修改失败，请稍后重试';
            }
        }
    }
}
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-3">
            <aside class="shop-sidebar">
                <nav class="sidebar-nav">
                    <a href="dashboard.php" class="nav-item"><i class="fas fa-tachometer-alt"></i> 仪表板</a>
                    <a href="orders.php" class="nav-item"><i class="fas fa-shopping-cart"></i> 我的订单</a>
                    <a href="profile.php" class="nav-item"><i class="fas fa-user-edit"></i> 个人信息</a>
                    <a href="shops.php" class="nav-item"><i class="fas fa-store"></i> 我的店铺</a>
                    <a href="address.php" class="nav-item"><i class="fas fa-address-book"></i> 收货地址</a>
                    <a href="security.php" class="nav-item active"><i class="fas fa-shield-alt"></i> 安全设置</a>
                </nav>

                <div class="sidebar-card user-sidebar-card text-center">
                    <div class="user-avatar mb-3">
                        <?php if (!empty($userInfo['avatar']) && $userInfo['avatar'] !== 'default.jpg'): ?>
                            <img src="<?= htmlspecialchars($userInfo['avatar']) ?>" alt="" class="rounded-circle" style="width:64px;height:64px;object-fit:cover;">
                        <?php else: ?>
                            <div class="avatar-placeholder-lg"><?= mb_substr($userInfo['username'], 0, 1) ?></div>
                        <?php endif; ?>
                    </div>
                    <h6 class="mb-1"><?= htmlspecialchars($userInfo['username']) ?></h6>
                    <p class="text-muted small mb-2"><?= htmlspecialchars($userInfo['email']) ?></p>
                    <p class="text-muted small">注册于 <?= date('Y-m-d', strtotime($userInfo['created_at'])) ?></p>
                </div>
            </aside>
        </div>

        <div class="col-md-9">
            <h4 class="mb-4">安全设置</h4>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header"><h5 class="card-title mb-0">修改密码</h5></div>
                        <div class="card-body">
                            <form method="POST">
                                <div class="form-group">
                                    <label>当前密码</label>
                                    <input type="password" name="current_password" class="form-control" placeholder="请输入当前密码" required>
                                </div>
                                
                                <div class="form-group">
                                    <label>新密码</label>
                                    <input type="password" name="new_password" class="form-control" placeholder="请输入新密码（至少6位）" required minlength="6">
                                </div>
                                
                                <div class="form-group">
                                    <label>确认新密码</label>
                                    <input type="password" name="confirm_password" class="form-control" placeholder="请再次输入新密码" required minlength="6">
                                </div>
                                
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-key"></i> 修改密码
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header"><h5 class="card-title mb-0">账户安全提示</h5></div>
                        <div class="card-body">
                            <div class="security-tips">
                                <div class="tip-item">
                                    <i class="fas fa-check-circle text-success"></i>
                                    <div>
                                        <h6>登录密码</h6>
                                        <p class="text-muted small mb-0">定期更换密码可以提高账户安全性</p>
                                    </div>
                                </div>
                                <div class="tip-item">
                                    <i class="fas fa-info-circle text-info"></i>
                                    <div>
                                        <h6>密码建议</h6>
                                        <p class="text-muted small mb-0">建议使用包含字母、数字和符号的组合</p>
                                    </div>
                                </div>
                                <div class="tip-item">
                                    <i class="fas fa-shield-alt text-primary"></i>
                                    <div>
                                        <h6>账户状态</h6>
                                        <p class="text-muted small mb-0">当前状态：<span class="badge badge-success">正常</span></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* ===== 侧边栏（统一风格） ===== */
.shop-sidebar { width: 100%; }
.sidebar-nav { background: #fff; border-radius: 12px; padding: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); margin-bottom: 16px; }
.nav-item { display: flex; align-items: center; gap: 10px; padding: 12px 14px; border-radius: 8px; color: #475569; text-decoration: none; font-size: 14px; font-weight: 500; transition: all .2s; position: relative; }
.nav-item:hover { background: #f1f5f9; color: #1e293b; text-decoration: none; }
.nav-item.active { background: #fff7ed; color: #ea580c; }
.nav-badge { margin-left: auto; background: #f1f5f9; color: #64748b; font-size: 11px; padding: 2px 8px; border-radius: 10px; font-weight: 600; }
.nav-item.active .nav-badge { background: #fed7aa; color: #c2410c; }
.sidebar-card { background: #fff; border-radius: 12px; padding: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); }
.sidebar-card h4 { font-size: 14px; font-weight: 600; color: #1e293b; margin: 0 0 12px; }
.sidebar-stat-row { display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid #f1f5f9; font-size: 13px; }
.sidebar-stat-row:last-child { border-bottom: none; }
.sidebar-stat-row .label { color: #64748b; }
.sidebar-stat-row .value { font-weight: 600; }
.user-sidebar-card .avatar-placeholder-lg { margin: 0 auto; }
.avatar-placeholder-lg {
    width: 64px; height: 64px; border-radius: 50%;
    background: linear-gradient(135deg, #ff6b00, #ff8533);
    color: #fff; display: flex; align-items: center; justify-content: center;
    font-size: 24px; font-weight: 700; margin: 0 auto;
}

/* 安全提示 */
.security-tips { display: flex; flex-direction: column; gap: 16px; }
.tip-item { display: flex; align-items: flex-start; gap: 12px; }
.tip-item i { font-size: 20px; margin-top: 2px; }
.tip-item h6 { margin: 0 0 4px; font-size: 14px; font-weight: 600; }
.tip-item p { margin: 0; }
</style>

<?php require_once '../includes/footer.php'; ?>
