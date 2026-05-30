<?php
require_once '../config/database.php';
require_once '../classes/User.php';

$error = '';
$user = new User($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    
    if (empty($username) || empty($password)) {
        $error = '用户名和密码是必填项';
    } elseif ($user->login($username, $password)) {
        header('Location: ../user/dashboard.php');
        exit;
    } else {
        $error = '用户名或密码不正确';
    }
}
?>

<?php require_once '../includes/header.php'; ?>

<div class="container auth-container">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="auth-card">
                <div class="auth-header">
                    <h2><i class="fas fa-sign-in-alt"></i> 用户登录</h2>
                    <p>请输入您的账号信息登录58互访圈</p>
                </div>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                
                <form method="post" class="auth-form">
                    <div class="form-group">
                        <label for="username">用户名</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fas fa-user"></i></span>
                            </div>
                            <input type="text" class="form-control" id="username" name="username" 
                                   value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="password">密码</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            </div>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                    </div>
                    
                    <div class="form-group form-check">
                        <input type="checkbox" class="form-check-input" id="remember" name="remember">
                        <label class="form-check-label" for="remember">记住我</label>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-block btn-auth">
                        <i class="fas fa-sign-in-alt"></i> 登录
                    </button>
                    
                    <div class="auth-links mt-3">
                        <a href="forgot-password.php"><i class="fas fa-question-circle"></i> 忘记密码?</a>
                        <a href="register.php" class="float-right"><i class="fas fa-user-plus"></i> 注册新账号</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>