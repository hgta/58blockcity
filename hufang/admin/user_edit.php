<?php
require_once '../../config/database.php';
require_once '../../classes/User.php';
//require_once '../../classes/Admin.php';
require_once '../includes/auth.php';
//require_once '../includes/admin_auth.php';

// Check if user is admin
/* if (!isAdmin()) {
    header('Location: ../auth/login.php');
    exit;
} */
// 检查管理员权限
checkAdmin();

$user = new User($pdo);
//$admin = new Admin($pdo);

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
    $city = trim($_POST['city']);
    
    // Basic validation
    if (empty($username) || empty($email)) {
        $error = '用户名和邮箱不能为空';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = '邮箱格式不正确';
    } else {
        // Prepare update data
        $updateData = [
            'username' => $username,
            'email' => $email,
            'role' => $role,
            'status' => $status,
            'city' => $city
        ];
        
        // Update password if provided
        if (!empty($_POST['password'])) {
            $updateData['password'] = password_hash($_POST['password'], PASSWORD_DEFAULT);
        }
        
        // Perform update
        if ($user->updateUserById($userId, $updateData)) {
            $success = '用户信息更新成功';
            // Refresh user data
            $userData = $user->getUserById($userId);
        } else {
            $error = '更新用户信息时出错';
        }
    }
}

// Available roles and statuses
$roles = ['user' => '普通用户', 'moderator' => '版主', 'admin' => '管理员'];
$statuses = ['active' => '活跃', 'suspended' => '已停用', 'banned' => '已封禁'];
$cities = ['北京', '上海', '广州', '深圳', '杭州', '成都', '重庆', '武汉', '南京', '其他'];

$admin_site_config = ['site' => 'hufang', 'page_title' => '编辑用户'];
require_once '../../shared/admin/admin-header.php';
?>

<style>
.admin-container {
    display: flex;
    min-height: calc(100vh - 120px);
}

.admin-sidebar {
    width: 250px;
    background: #f8f9fa;
    padding: 20px;
    border-right: 1px solid #dee2e6;
}

.admin-content {
    flex: 1;
    padding: 20px;
}

.user-edit-form {
    max-width: 800px;
    margin: 0 auto;
    background: #fff;
    padding: 25px;
    border-radius: 5px;
    box-shadow: 0 0 10px rgba(0,0,0,0.05);
}

.form-row {
    display: flex;
    gap: 20px;
}

.form-row .form-group {
    flex: 1;
}

.form-actions {
    display: flex;
    gap: 10px;
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid #eee;
}

.form-control-static {
    padding: 8px 0;
    margin: 0;
    color: #555;
}

.admin-nav {
    height: 100%;
    display: flex;
    flex-direction: column;
    background: #2c3e50;
    color: #ecf0f1;
}

.admin-brand {
    padding: 20px;
    text-align: center;
    border-bottom: 1px solid rgba(255,255,255,0.1);
}

.admin-brand h3 {
    margin: 0 0 5px 0;
    font-size: 18px;
    color: #fff;
}

.admin-brand p {
    margin: 0;
    font-size: 14px;
    color: #bdc3c7;
}

.admin-menu {
    flex: 1;
    list-style: none;
    padding: 0;
    margin: 0;
    overflow-y: auto;
}

.menu-header {
    padding: 15px 20px 5px;
    font-size: 12px;
    text-transform: uppercase;
    color: #7f8c8d;
    letter-spacing: 1px;
}

.menu-item {
    border-left: 3px solid transparent;
    transition: all 0.3s;
}

.menu-item:hover {
    background: rgba(255,255,255,0.05);
}

.menu-item.active {
    border-left-color: #3498db;
    background: rgba(255,255,255,0.1);
}

.menu-item a {
    display: flex;
    align-items: center;
    padding: 12px 20px;
    color: #ecf0f1;
    text-decoration: none;
}

.menu-item i {
    width: 24px;
    text-align: center;
    margin-right: 10px;
    font-size: 16px;
}

.menu-badge {
    margin-left: auto;
    background: #e74c3c;
    color: white;
    border-radius: 10px;
    padding: 2px 8px;
    font-size: 12px;
}

.admin-footer {
    padding: 15px;
    border-top: 1px solid rgba(255,255,255,0.1);
}

.logout-btn {
    display: flex;
    align-items: center;
    color: #bdc3c7;
    text-decoration: none;
    transition: color 0.3s;
}

.logout-btn:hover {
    color: #ecf0f1;
}

.logout-btn i {
    margin-right: 10px;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .admin-nav {
        width: 60px;
        overflow: hidden;
    }
    
    .admin-brand h3, 
    .menu-header,
    .menu-item span,
    .logout-btn span {
        display: none;
    }
    
    .menu-item i {
        margin-right: 0;
        font-size: 20px;
    }
    
    .menu-item a {
        justify-content: center;
        padding: 15px 0;
    }
    
    .menu-badge {
        position: absolute;
        top: 5px;
        right: 5px;
    }
}
</style>

<div class="admin-container">
    <div class="admin-sidebar">
        <?php //include '../includes/admin_nav.php'; ?>
		
		<?php
		// Check if user is admin
		//if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
		//	header('Location: ../auth/login.php');
		//	exit;
		//}
		?>

		<nav class="admin-nav">
			<div class="admin-brand">
				<h3>管理后台</h3>
				<p><?php echo htmlspecialchars($_SESSION['username']); ?></p>
			</div>
			
			<ul class="admin-menu">
				<li class="menu-header">主导航</li>
				
				<li class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
					<a href="dashboard.php">
						<i class="fas fa-tachometer-alt"></i>
						<span>控制面板</span>
					</a>
				</li>
				
				<li class="menu-item <?php echo strpos($_SERVER['PHP_SELF'], 'users') !== false ? 'active' : ''; ?>">
					<a href="users.php">
						<i class="fas fa-users"></i>
						<span>用户管理</span>
						<?php //if ($unreadReports = getUnreadReportCount()): ?>
							<span class="menu-badge"><?php echo $unreadReports > 9 ? '9+' : $unreadReports; ?></span>
						<?php //endif; ?>
					</a>
				</li>
				
				<li class="menu-item <?php echo strpos($_SERVER['PHP_SELF'], 'circles') !== false ? 'active' : ''; ?>">
					<a href="circles.php">
						<i class="fas fa-project-diagram"></i>
						<span>互访圈管理</span>
					</a>
				</li>
				
				<li class="menu-item <?php echo strpos($_SERVER['PHP_SELF'], 'visits') !== false ? 'active' : ''; ?>">
					<a href="visits.php">
						<i class="fas fa-handshake"></i>
						<span>互访记录</span>
					</a>
				</li>
				
				<li class="menu-item <?php echo strpos($_SERVER['PHP_SELF'], 'reports') !== false ? 'active' : ''; ?>">
					<a href="reports.php">
						<i class="fas fa-flag"></i>
						<span>举报管理</span>
						<?php //if ($pendingReports = getPendingReportCount()): ?>
							<span class="menu-badge"><?php echo $pendingReports > 9 ? '9+' : $pendingReports; ?></span>
						<?php //endif; ?>
					</a>
				</li>
				
				<li class="menu-item <?php echo strpos($_SERVER['PHP_SELF'], 'content') !== false ? 'active' : ''; ?>">
					<a href="content.php">
						<i class="fas fa-file-alt"></i>
						<span>内容管理</span>
					</a>
				</li>
				
				<li class="menu-header">系统设置</li>
				
				<li class="menu-item <?php echo strpos($_SERVER['PHP_SELF'], 'settings') !== false ? 'active' : ''; ?>">
					<a href="settings.php">
						<i class="fas fa-cog"></i>
						<span>系统设置</span>
					</a>
				</li>
				
				<li class="menu-item <?php echo strpos($_SERVER['PHP_SELF'], 'logs') !== false ? 'active' : ''; ?>">
					<a href="logs.php">
						<i class="fas fa-clipboard-list"></i>
						<span>操作日志</span>
					</a>
				</li>
				
				<li class="menu-item">
					<a href="../index.php" target="_blank">
						<i class="fas fa-external-link-alt"></i>
						<span>查看前台</span>
					</a>
				</li>
			</ul>
			
			<div class="admin-footer">
				<a href="../auth/logout.php" class="logout-btn">
					<i class="fas fa-sign-out-alt"></i>
					<span>退出登录</span>
				</a>
			</div>
		</nav>
    </div>
    
    <div class="admin-content">
        <h2>编辑用户</h2>
        
        <div class="breadcrumb">
            <a href="../admin/dashboard.php">管理后台</a> &raquo; 
            <a href="../admin/users.php">用户管理</a> &raquo; 
            <span>编辑用户</span>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <form method="POST" class="user-edit-form">
            <div class="form-group">
                <label for="username">用户名</label>
                <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($userData['username']); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="email">邮箱</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($userData['email']); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="password">新密码 (留空则不修改)</label>
                <input type="password" id="password" name="password">
                <small class="form-text">密码至少8个字符</small>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="role">用户角色</label>
                    <select id="role" name="role" class="form-control">
                        <?php foreach ($roles as $value => $label): ?>
                            <option value="<?php echo $value; ?>" <?php echo $userData['role'] == $value ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="status">账户状态</label>
                    <select id="status" name="status" class="form-control">
                        <?php foreach ($statuses as $value => $label): ?>
                            <option value="<?php echo $value; ?>" <?php echo $userData['status'] == $value ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label for="city">所在城市</label>
                <select id="city" name="city" class="form-control">
                    <?php foreach ($cities as $city): ?>
                        <option value="<?php echo $city; ?>" <?php echo $userData['city'] == $city ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($city); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>注册时间</label>
                <p class="form-control-static"><?php echo htmlspecialchars($userData['created_at']); ?></p>
            </div>
            
            <div class="form-group">
                <label>最后登录</label>
                <p class="form-control-static"><?php echo htmlspecialchars($userData['last_login'] ?? '从未登录'); ?></p>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">保存更改</button>
                <a href="users.php" class="btn btn-secondary">返回列表</a>
                <?php if ($userData['status'] != 'banned'): ?>
                    <a href="user_actions.php?action=ban&id=<?php echo $userId; ?>" class="btn btn-danger" 
                       onclick="return confirm('确定要封禁此用户吗？')">封禁用户</a>
                <?php else: ?>
                    <a href="user_actions.php?action=unban&id=<?php echo $userId; ?>" class="btn btn-success" 
                       onclick="return confirm('确定要解封此用户吗？')">解除封禁</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<?php require_once '../../shared/admin/admin-footer.php'; ?>