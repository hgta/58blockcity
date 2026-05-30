<?php
require_once '../../config/database.php';
require_once '../../classes/User.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../../classes/City.php';

$errors = [];
$success = '';
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
    } elseif (!preg_match('/^[a-zA-Z0-9_\x{4e00}-\x{9fa5}]+$/u', $username)) {
        $errors['username'] = '用户名只能包含中文、英文、数字和下划线';
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
    } elseif (!preg_match('/[a-zA-Z]/', $password) || !preg_match('/[0-9]/', $password)) {
        $errors['password'] = '密码必须包含字母和数字';
    } elseif ($password !== $passwordConfirm) {
        $errors['password_confirm'] = '两次输入的密码不一致';
    }
    
    if (empty($city)) {
        $errors['city'] = '请选择所在城市';
    }
    
    // 检查用户名和邮箱是否已存在
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            $existingUser = $stmt->fetch();
            
            if ($existingUser) {
                // 检查具体是用户名还是邮箱已存在
                $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
                $stmt->execute([$username]);
                if ($stmt->fetch()) {
                    $errors['username'] = '用户名已被注册';
                }
                
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    $errors['email'] = '邮箱已被注册';
                }
            }
        } catch (PDOException $e) {
            $errors['general'] = '系统错误，请稍后再试';
        }
    }
    
    // 如果没有错误，创建用户
    if (empty($errors)) {
        if ($user->register($username, $email, $password, $city)) {
            // 注册成功后自动登录
            if ($user->login($username, $password)) {
                $success = '注册成功！正在跳转...';
                // 使用JavaScript延迟跳转，让用户看到成功消息
                echo "<script>
                    setTimeout(function() {
                        window.location.href = '../user/dashboard.php';
                    }, 1500);
                </script>";
            }
        } else {
            $errors['general'] = '注册失败，请稍后再试';
        }
    }
}

// 获取所有城市
$cityObj = new City($pdo);
$cities = $cityObj->getAllCities();
?>
<?php include '../includes/header.php'; ?>

<style>
.register-container {
    max-width: 500px;
    margin: 40px auto;
    padding: 30px;
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.register-header {
    text-align: center;
    margin-bottom: 30px;
}

.register-header h2 {
    color: #333;
    margin-bottom: 10px;
}

.register-header p {
    color: #666;
    font-size: 14px;
}

.register-form {
    margin-bottom: 20px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
    color: #333;
}

.form-group input,
.form-group select {
    width: 100%;
    padding: 12px;
    border: 2px solid #e1e1e1;
    border-radius: 6px;
    font-size: 16px;
    transition: border-color 0.3s;
    box-sizing: border-box;
}

.form-group input:focus,
.form-group select:focus {
    outline: none;
    border-color: #007bff;
}

.form-group input.error,
.form-group select.error {
    border-color: #dc3545;
}

.text-danger {
    color: #dc3545;
    font-size: 14px;
    margin-top: 5px;
    display: block;
}

.text-success {
    color: #28a745;
    font-size: 14px;
    margin-top: 5px;
    display: block;
}

.password-hint {
    font-size: 12px;
    color: #666;
    margin: 5px 0 0 0;
}

.btn-register {
    width: 100%;
    padding: 12px;
    background: #007bff;
    color: white;
    border: none;
    border-radius: 6px;
    font-size: 16px;
    cursor: pointer;
    transition: background 0.3s;
}

.btn-register:hover {
    background: #0056b3;
}

.btn-register:disabled {
    background: #6c757d;
    cursor: not-allowed;
}

.register-footer {
    text-align: center;
    padding-top: 20px;
    border-top: 1px solid #e1e1e1;
}

.register-footer a {
    color: #007bff;
    text-decoration: none;
}

.register-footer a:hover {
    text-decoration: underline;
}

.alert {
    padding: 12px 20px;
    margin-bottom: 20px;
    border-radius: 6px;
    font-size: 14px;
    animation: fadeIn 0.3s;
}

.alert-error {
    background-color: #f8d7da;
    color: #721c24;
    border-left: 4px solid #dc3545;
}

.alert-success {
    background-color: #d4edda;
    color: #155724;
    border-left: 4px solid #28a745;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

.form-row {
    display: flex;
    gap: 15px;
}

.form-row .form-group {
    flex: 1;
}
</style>

<div class="register-container">
    <div class="register-header">
        <h2>加入58互访圈</h2>
        <p>创建您的账户，开始区块互访之旅</p>
    </div>
    
    <?php if (!empty($errors['general'])): ?>
        <div class="alert alert-error">
            <?php echo htmlspecialchars($errors['general']); ?>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($success)): ?>
        <div class="alert alert-success">
            <?php echo htmlspecialchars($success); ?>
        </div>
    <?php endif; ?>
    
    <form class="register-form" action="register.php" method="POST" id="registerForm">
        <div class="form-group">
            <label for="username">用户名</label>
            <input type="text" id="username" name="username" 
                   value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" 
                   class="<?php echo isset($errors['username']) ? 'error' : ''; ?>" 
                   required>
            <?php if (isset($errors['username'])): ?>
                <small class="text-danger"><?php echo htmlspecialchars($errors['username']); ?></small>
            <?php else: ?>
                <small class="password-hint">4-20个字符，支持中文、英文、数字和下划线</small>
            <?php endif; ?>
        </div>
        
        <div class="form-group">
            <label for="email">电子邮箱</label>
            <input type="email" id="email" name="email" 
                   value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" 
                   class="<?php echo isset($errors['email']) ? 'error' : ''; ?>" 
                   required>
            <?php if (isset($errors['email'])): ?>
                <small class="text-danger"><?php echo htmlspecialchars($errors['email']); ?></small>
            <?php endif; ?>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label for="password">密码</label>
                <input type="password" id="password" name="password" 
                       class="<?php echo isset($errors['password']) ? 'error' : ''; ?>" 
                       required>
            </div>
            
            <div class="form-group">
                <label for="password_confirm">确认密码</label>
                <input type="password" id="password_confirm" name="password_confirm" 
                       class="<?php echo isset($errors['password_confirm']) ? 'error' : ''; ?>" 
                       required>
            </div>
        </div>
        
        <?php if (isset($errors['password'])): ?>
            <small class="text-danger"><?php echo htmlspecialchars($errors['password']); ?></small>
        <?php elseif (isset($errors['password_confirm'])): ?>
            <small class="text-danger"><?php echo htmlspecialchars($errors['password_confirm']); ?></small>
        <?php else: ?>
            <small class="password-hint">至少6个字符，必须包含字母和数字</small>
        <?php endif; ?>
        
        <div class="form-group">
            <label for="city">所在城市</label>
            <!--<select id="city" name="city" class="<?php echo isset($errors['city']) ? 'error' : ''; ?>" required>
                <option value="">请选择城市</option>
                <?php foreach ($cities as $cityOption): ?>
                    <option value="<?php echo htmlspecialchars($cityOption); ?>" 
                        <?php echo (isset($_POST['city']) && $_POST['city'] === $cityOption) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($cityOption); ?>
                    </option>
                <?php endforeach; ?>
            </select>-->
			
			<input type="text" class="<?php echo isset($errors['city']) ? 'error' : 'form-control'; ?>" id="city" name="city" 
                           value="<?= htmlspecialchars($_POST['city'] ?? '') ?>" 
                           list="cityOptions" autocomplete="off" required
                           placeholder="输入城市名称或从下拉列表选择">
                    <datalist id="cityOptions">
                        <?php foreach ($cities as $city): ?>
                            <option value="<?= htmlspecialchars($city['name']) ?>">
                        <?php endforeach; ?>
                    </datalist>
					
            <?php if (isset($errors['city'])): ?>
                <small class="text-danger"><?php echo htmlspecialchars($errors['city']); ?></small>
            <?php endif; ?>
        </div>
        
        <button type="submit" class="btn-register" id="submitBtn">注册</button>
    </form>
    
    <div class="register-footer">
        <p>已有账号？ <a href="login.php">立即登录</a></p>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('registerForm');
    const submitBtn = document.getElementById('submitBtn');
    
    // 实时验证密码匹配
    const password = document.getElementById('password');
    const passwordConfirm = document.getElementById('password_confirm');
    
    function validatePasswordMatch() {
        if (password.value && passwordConfirm.value) {
            if (password.value !== passwordConfirm.value) {
                passwordConfirm.setCustomValidity('两次输入的密码不一致');
            } else {
                passwordConfirm.setCustomValidity('');
            }
        }
    }
    
    password.addEventListener('input', validatePasswordMatch);
    passwordConfirm.addEventListener('input', validatePasswordMatch);
    
    // 表单提交时的客户端验证
    form.addEventListener('submit', function() {
        submitBtn.disabled = true;
        submitBtn.textContent = '注册中...';
    });
});
</script>

<?php include '../includes/footer.php'; ?>