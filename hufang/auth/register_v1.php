<?php
require_once '../../config/database.php';
require_once '../../classes/User.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

$errors = [];
$user = new User($pdo);

// 如果用户已登录，重定向到首页
if (isLoggedIn()) {
    header('Location: ../index.php');
    exit;
}

// 处理注册表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $passwordConfirm = $_POST['password_confirm'];
    $city = trim($_POST['city']);
    
    // 验证输入
    if (empty($username)) {
        $errors['username'] = '用户名不能为空';
    } elseif (strlen($username) < 4) {
        $errors['username'] = '用户名至少需要4个字符';
    }
    
    if (empty($email)) {
        $errors['email'] = '邮箱不能为空';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = '邮箱格式不正确';
    }
    
    if (empty($password)) {
        $errors['password'] = '密码不能为空';
    } elseif (strlen($password) < 6) {
        $errors['password'] = '密码至少需要6个字符';
    } elseif ($password !== $passwordConfirm) {
        $errors['password_confirm'] = '两次输入的密码不一致';
    }
    
    if (empty($city)) {
        $errors['city'] = '请选择所在城市';
    }
    
    // 检查用户名和邮箱是否已存在
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        if ($stmt->fetch()) {
            $errors['general'] = '用户名或邮箱已被注册';
        }
    }
	    
    // 如果没有错误，创建用户
    if (empty($errors)) {
        if ($user->register($username, $email, $password, $city)) {
			
            // 注册成功后自动登录
            if ($user->login($username, $password)) {
                header('Location: ../user/dashboard.php');
                exit;
            }
        } else {
            $errors['general'] = '注册失败，请稍后再试';
        }
    } else {
		//var_dump($errors);
	}
}
?>
<?php include '../includes/header.php'; ?>

<div class="register-container">
    <div class="register-header">
        <h2>加入58互访圈</h2>
        <p>创建您的账户，开始城市互访之旅</p>
    </div>
    
    <form class="register-form" action="register.php" method="POST">
        <div class="form-group">
            <label for="username">用户名</label>
            <input type="text" id="username" name="username" 
				value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" required>
			<?php if (isset($errors['username'])): ?>
                    <small class="text-danger"><?php echo htmlspecialchars($errors['username']); ?></small>
                <?php endif; ?>
        </div>
        
        <div class="form-group">
            <label for="email">电子邮箱</label>
            <input type="email" id="email" name="email" required>
			<?php if (isset($errors['email'])): ?>
                    <small class="text-danger"><?php echo htmlspecialchars($errors['email']); ?></small>
                <?php endif; ?>
        </div>
        
        <div class="form-group">
            <label for="password">密码</label>
            <input type="password" id="password" name="password" required>
            <p class="password-hint">至少8个字符，包含字母和数字</p>
			<?php if (isset($errors['password'])): ?>
                    <small class="text-danger"><?php echo htmlspecialchars($errors['password']); ?></small>
                <?php endif; ?>
        </div>
        
        <div class="form-group">
            <label for="password_confirm">确认密码</label>
            <input type="password" id="password_confirm" name="password_confirm" required>
			<?php if (isset($errors['password_confirm'])): ?>
                    <small class="text-danger"><?php echo htmlspecialchars($errors['password_confirm']); ?></small>
            <?php endif; ?>
        </div>
        
        <div class="form-group">
            <label for="city">所在城市</label>
            <input type="text" id="city" name="city" required>
        </div>
        
        <button type="submit" class="btn-register">注册</button>
    </form>
    
    <div class="register-footer">
        <p>已有账号？ <a href="login.php">立即登录</a></p>
    </div>
</div>

<?php include '../includes/footer.php'; ?>