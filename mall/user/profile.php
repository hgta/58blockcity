<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
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

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_profile') {
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $city = trim($_POST['city'] ?? '');
        
        if (empty($username)) {
            $error = '用户名不能为空';
        } elseif (empty($email)) {
            $error = '邮箱不能为空';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = '邮箱格式不正确';
        } else {
            $updateData = [
                'username' => $username,
                'email' => $email,
                'phone' => $phone,
                'city' => $city,
                'avatar' => $userInfo['avatar'],
                'id' => $userId
            ];
            
            if ($user->updateUser($updateData)) {
                $success = '个人信息更新成功';
                $_SESSION['username'] = $username;
                $userInfo = $user->getUserById($userId);
            } else {
                $error = '更新失败，请稍后重试';
            }
        }
    } elseif ($action === 'update_avatar') {
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/../assets/uploads/avatars/';
            $dirReady = true;
            if (!is_dir($uploadDir)) {
                if (!@mkdir($uploadDir, 0777, true)) {
                    $error = '无法创建上传目录，请联系管理员检查目录权限';
                    $dirReady = false;
                }
            }
            
            if ($dirReady) {
                $fileExtension = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
                $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                
                if (!in_array($fileExtension, $allowedExtensions)) {
                    $error = '仅支持 JPG、PNG、GIF、WEBP 格式的图片';
                } else {
                $fileName = 'avatar_' . $userId . '_' . time() . '.' . $fileExtension;
                $filePath = $uploadDir . $fileName;
                
                if (move_uploaded_file($_FILES['avatar']['tmp_name'], $filePath)) {
                    $avatarPath = 'assets/uploads/avatars/' . $fileName;
                    if ($user->updateAvatar($userId, $avatarPath)) {
                        $success = '头像更新成功';
                        $userInfo = $user->getUserById($userId);
                    } else {
                        $error = '头像保存失败';
                    }
                } else {
                    $error = '文件上传失败';
                }
            }
            }
        } else {
            $error = '请选择要上传的头像';
        }
    }
}

require_once '../includes/header.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-3">
            <aside class="shop-sidebar">
                <nav class="sidebar-nav">
                    <a href="dashboard.php" class="nav-item"><i class="fas fa-tachometer-alt"></i> 仪表板</a>
                    <a href="orders.php" class="nav-item"><i class="fas fa-shopping-cart"></i> 我的订单</a>
                    <a href="profile.php" class="nav-item active"><i class="fas fa-user-edit"></i> 个人信息</a>
                    <a href="shops.php" class="nav-item"><i class="fas fa-store"></i> 我的店铺</a>
                    <a href="address.php" class="nav-item"><i class="fas fa-address-book"></i> 收货地址</a>
                    <a href="security.php" class="nav-item"><i class="fas fa-shield-alt"></i> 安全设置</a>
                </nav>

                <div class="sidebar-card user-sidebar-card text-center">
                    <div class="user-avatar mb-3">
                        <?php if (!empty($userInfo['avatar']) && $userInfo['avatar'] !== 'default.jpg'): ?>
                            <img src="../<?= htmlspecialchars($userInfo['avatar']) ?>" alt="" class="rounded-circle" style="width:64px;height:64px;object-fit:cover;">
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
            <h4 class="mb-4">个人信息</h4>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            
            <div class="row">
                <!-- 头像卡片 -->
                <div class="col-md-4 mb-4">
                    <div class="card">
                        <div class="card-header"><h5 class="card-title mb-0">我的头像</h5></div>
                        <div class="card-body text-center">
                            <div class="profile-avatar mb-3">
                                <?php if (!empty($userInfo['avatar']) && $userInfo['avatar'] !== 'default.jpg'): ?>
                                    <img src="../<?= htmlspecialchars($userInfo['avatar']) ?>" alt="" id="avatarPreview">
                                <?php else: ?>
                                    <div class="avatar-placeholder-xl" id="avatarPreviewPlaceholder"><?= mb_substr($userInfo['username'], 0, 1) ?></div>
                                <?php endif; ?>
                            </div>
                            <form method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="action" value="update_avatar">
                                <div class="form-group">
                                    <input type="file" name="avatar" id="avatarInput" class="d-none" accept="image/*" onchange="this.form.submit()">
                                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="document.getElementById('avatarInput').click()">
                                        <i class="fas fa-camera"></i> 更换头像
                                    </button>
                                </div>
                            </form>
                            <p class="text-muted small mb-0">支持 JPG、PNG、GIF 格式，建议尺寸 200x200</p>
                        </div>
                    </div>
                </div>
                
                <!-- 基本信息表单 -->
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header"><h5 class="card-title mb-0">基本信息</h5></div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="update_profile">
                                
                                <div class="form-group">
                                    <label>用户名</label>
                                    <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($userInfo['username']) ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label>邮箱</label>
                                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($userInfo['email']) ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label>手机号码</label>
                                    <input type="tel" name="phone" class="form-control" value="<?= htmlspecialchars($userInfo['phone'] ?? '') ?>" placeholder="请输入手机号码">
                                </div>
                                
                                <div class="form-group">
                                    <label>所在城市</label>
                                    <input type="text" name="city" class="form-control" value="<?= htmlspecialchars($userInfo['city'] ?? '') ?>" placeholder="请输入所在城市">
                                </div>
                                
                                <div class="form-group">
                                    <label>注册时间</label>
                                    <input type="text" class="form-control" value="<?= date('Y-m-d H:i:s', strtotime($userInfo['created_at'])) ?>" disabled>
                                </div>
                                
                                <div class="form-group">
                                    <label>最后登录</label>
                                    <input type="text" class="form-control" value="<?= $userInfo['last_login'] ? date('Y-m-d H:i:s', strtotime($userInfo['last_login'])) : '暂无记录' ?>" disabled>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> 保存修改
                                </button>
                            </form>
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

/* 头像区域 */
.profile-avatar { width: 120px; height: 120px; margin: 0 auto; border-radius: 50%; overflow: hidden; background: #f8f9fa; }
.profile-avatar img { width: 100%; height: 100%; object-fit: cover; }
.avatar-placeholder-xl {
    width: 120px; height: 120px; border-radius: 50%;
    background: linear-gradient(135deg, #ff6b00, #ff8533);
    color: #fff; display: flex; align-items: center; justify-content: center;
    font-size: 48px; font-weight: 700; margin: 0 auto;
}

@media(max-width:768px){
    .row { flex-direction: column; }
    .col-md-3, .col-md-4, .col-md-6, .col-md-9 { width: 100%; max-width: 100%; flex: none; }
    .form-control, .form-select { width: 100%; font-size: 16px; }
    .btn { min-height: 44px; width: 100%; }
}
</style>

<?php require_once '../includes/footer.php'; ?>
