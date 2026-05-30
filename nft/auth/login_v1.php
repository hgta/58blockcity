<?php
require_once '../../config/database.php';
require_once '../../classes/User.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

$error = '';
$user = new User($pdo);

// 如果用户已登录，重定向到首页
if (isLoggedIn()) {
    header('Location: ../index.php');
    exit;
}

// 处理登录表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    if (empty($username) || empty($password)) {
        $error = '用户名和密码不能为空';
    } else {
        if ($user->login($username, $password)) {
            // 登录成功，重定向到用户后台
            header('Location: ../user/dashboard.php');
            exit;
        } else {
            $error = '用户名或密码错误';
        }
    }
}
?>
<?php include '../includes/header.php'; ?>

<div class="login-container">
    <div class="login-header">
        <h2>登录NFT头像平台</h2>
        <p>管理您的区块城市NFT头像</p>
    </div>
    
    <form class="login-form" action="login.php" method="POST">
        <div class="form-group">
            <label for="username">用户名</label>
            <input type="text" id="username" name="username" required>
        </div>
        
        <div class="form-group">
            <label for="password">密码</label>
            <input type="password" id="password" name="password" required>
        </div>
        
        <button type="submit" class="btn-login">登录</button>
    </form>
    
    <div class="login-footer">
        <p>没有账号？ <a href="register.php">立即注册</a> • <a href="forgot_password.php">忘记密码？</a></p>
    </div>
</div>

<?php include '../includes/footer.php'; ?>