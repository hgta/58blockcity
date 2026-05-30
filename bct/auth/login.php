<?php
require_once '../../config/database.php';
require_once '../../classes/User.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

$error = '';
$user = new User($pdo);

// 如果用户已登录，重定向到首页
if (isLoggedIn()) {
    header('Location: ' . ($_SESSION['redirect_url'] ?? '../index.php'));
    unset($_SESSION['redirect_url']);
    exit;
}

// 处理登录表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $remember = isset($_POST['remember']);
    
    if (empty($username) || empty($password)) {
        $error = '用户名和密码不能为空';
    } else {
        // 检查登录尝试次数
        if (isset($_SESSION['login_attempts']) && $_SESSION['login_attempts'] >= 5) {
            if (time() - $_SESSION['last_attempt_time'] < 300) { // 5分钟内
                $error = '登录尝试次数过多，请5分钟后再试';
            } else {
                // 重置尝试次数
                unset($_SESSION['login_attempts']);
            }
        }
        
        if (empty($error)) {
            // 使用新的方法获取用户信息
            $userData = $user->getUserByUsername($username);
			//var_dump($userData);
            
            if ($userData && password_verify($password, $userData['password'])) {
                // 检查用户状态
                if ($userData['status'] !== 'active') {
                    $error = '账户已被禁用，请联系管理员';
                } else {
                    // 登录成功
                    handleLogin($userData['id'], $userData['username'], 
                               $userData['email'], $userData['role'], $remember);
                    
                    // 重定向到之前访问的页面或首页
                    $redirectUrl = $_SESSION['redirect_url'] ?? '../user/dashboard.php';
                    unset($_SESSION['redirect_url']);
                    
                    header('Location: ' . $redirectUrl);
                    exit;
                }
            } else {
                // 登录失败
                $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0) + 1;
                $_SESSION['last_attempt_time'] = time();
                
                $error = '用户名或密码错误，请重试';
                if (($_SESSION['login_attempts'] ?? 0) >= 3) {
                    $error .= '（剩余尝试次数：' . (5 - $_SESSION['login_attempts']) . '）';
                }
            }
        }
    }
}
?>

<?php include '../includes/header.php'; ?>

<style>


.alert {
    padding: 12px 20px;
    margin-bottom: 20px;
    border-radius: 4px;
    font-size: 16px;
}

.alert-error {
    background-color: #ffebee;
    color: #c62828;
    border-left: 4px solid #c62828;
    animation: fadeIn 0.3s;
}

.remember-me {
    display: flex;
    align-items: center;
    margin: 15px 0;
}

.remember-me input {
    margin-right: 8px;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>

<div class="login-container">
    <div class="login-header">
        <h2>登录人气值市场</h2>
        <p>管理您的区块城市人气值</p>
    </div>
    
    <?php if (!empty($error)): ?>
        <div class="alert alert-error">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>
    
    <form class="login-form" action="login.php" method="POST">
        <div class="form-group">
            <label for="username">用户名</label>
            <input type="text" id="username" name="username" required 
                   value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
        </div>
        
        <div class="form-group">
            <label for="password">密码</label>
            <input type="password" id="password" name="password" required>
        </div>
        
        <div class="remember-me">
            <input type="checkbox" id="remember" name="remember" value="1" style="width: 10%;">
            <label for="remember">30天内自动登录</label>
        </div>
        
        <button type="submit" class="btn-login">登录</button>
    </form>
    
    <div class="login-footer">
        <p>没有账号？ <a href="register.php">立即注册</a> • <a href="forgot_password.php">忘记密码？</a></p>
    </div>
</div>

<?php include '../includes/footer.php'; ?>