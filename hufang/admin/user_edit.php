<?php
require_once '../../config/database.php';
require_once '../../classes/User.php';
require_once '../includes/auth.php';

// 检查管理员权限
checkAdmin();

$user = new User($pdo);

// Get user ID from URL
$userId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Fetch user data
$userData = $user->getUserById($userId);

if (!$userData) {
    header('Location: users.php?error=user_not_found');
    exit;
}

$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $role = trim($_POST['role']);
    $status = trim($_POST['status']);
    $cityVal = trim($_POST['city']);
    
    if (empty($username) || empty($email)) {
        $error = '用户名和邮箱不能为空';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = '邮箱格式不正确';
    } else {
        $updateData = [
            'username' => $username,
            'email' => $email,
            'role' => $role,
            'status' => $status,
            'city' => $cityVal
        ];
        
        if (!empty($_POST['password'])) {
            $updateData['password'] = password_hash($_POST['password'], PASSWORD_DEFAULT);
        }
        
        if ($user->updateUserById($userId, $updateData)) {
            $success = '用户信息更新成功';
            $userData = $user->getUserById($userId);
        } else {
            $error = '更新用户信息时出错';
        }
    }
}

$roles = ['user' => '普通用户', 'moderator' => '版主', 'admin' => '管理员'];
$statuses = ['active' => '活跃', 'suspended' => '已停用', 'banned' => '已封禁'];
$cities = ['北京', '上海', '广州', '深圳', '杭州', '成都', '重庆', '武汉', '南京', '其他'];

$admin_site_config = ['site' => 'hufang', 'page_title' => '编辑用户'];
require_once '../../shared/admin/admin-header.php';
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
    <a href="users.php" class="admin-btn admin-btn-default"><i class="fas fa-arrow-left"></i> 返回列表</a>
</div>

<?php if ($error): ?>
    <div class="admin-alert danger"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<?php if ($success): ?>
    <div class="admin-alert success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<div class="admin-card">
    <div class="admin-card-header"><span class="admin-card-title"><i class="fas fa-user-edit"></i> 编辑用户</span></div>
    <div class="admin-card-body" style="max-width:700px;margin:0 auto;">
        <form method="POST">
            <div class="admin-form-group">
                <label class="admin-form-label">用户名</label>
                <input type="text" name="username" class="admin-form-input" value="<?= htmlspecialchars($userData['username']) ?>" required>
            </div>
            <div class="admin-form-group">
                <label class="admin-form-label">邮箱</label>
                <input type="email" name="email" class="admin-form-input" value="<?= htmlspecialchars($userData['email']) ?>" required>
            </div>
            <div class="admin-form-group">
                <label class="admin-form-label">新密码 (留空则不修改)</label>
                <input type="password" name="password" class="admin-form-input">
                <span class="admin-form-hint">密码至少8个字符</span>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:0 16px;">
                <div class="admin-form-group">
                    <label class="admin-form-label">用户角色</label>
                    <select name="role" class="admin-form-select">
                        <?php foreach ($roles as $value => $label): ?>
                            <option value="<?= $value ?>" <?= $userData['role'] == $value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="admin-form-group">
                    <label class="admin-form-label">账户状态</label>
                    <select name="status" class="admin-form-select">
                        <?php foreach ($statuses as $value => $label): ?>
                            <option value="<?= $value ?>" <?= $userData['status'] == $value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="admin-form-group">
                <label class="admin-form-label">所在城市</label>
                <select name="city" class="admin-form-select">
                    <?php foreach ($cities as $cityOpt): ?>
                        <option value="<?= $cityOpt ?>" <?= $userData['city'] == $cityOpt ? 'selected' : '' ?>><?= htmlspecialchars($cityOpt) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="admin-form-group">
                <label class="admin-form-label">注册时间</label>
                <p class="admin-text-muted" style="padding:8px 0;margin:0;"><?= htmlspecialchars($userData['created_at']) ?></p>
            </div>
            <div class="admin-form-group">
                <label class="admin-form-label">最后登录</label>
                <p class="admin-text-muted" style="padding:8px 0;margin:0;"><?= htmlspecialchars($userData['last_login'] ?? '从未登录') ?></p>
            </div>
            <div style="margin-top:20px;display:flex;gap:12px;">
                <button type="submit" class="admin-btn admin-btn-primary"><i class="fas fa-save"></i> 保存更改</button>
                <a href="users.php" class="admin-btn admin-btn-default">返回列表</a>
            </div>
        </form>
    </div>
</div>

<?php require_once '../../shared/admin/admin-footer.php'; ?>
