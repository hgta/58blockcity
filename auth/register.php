<?php
/**
 * 共享注册页面
 * 子站调用: <?php $site_config=[...]; require_once '../../auth/register.php';
 */

if (!isset($site_config)) { die('缺少站点配置'); }

// 共享库路径（绝对路径，不依赖子站上下文）
$sharedIncludes = dirname(__DIR__) . '/includes';

require_once $site_config['db_path'];
require_once $site_config['class_path'] . 'User.php';
require_once $sharedIncludes . '/functions.php';
require_once $sharedIncludes . '/auth.php';

$errors = [];
$success = '';
$user = new User($pdo);

if (isLoggedIn()) {
    header('Location: ' . $site_config['redirect_after_login']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $city = trim($_POST['city'] ?? '');
    
    if (strlen($username) < 4 || strlen($username) > 20) $errors[] = '用户名需要4-20个字符';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = '请输入有效的邮箱地址';
    if (strlen($password) < 6) $errors[] = '密码至少6位';
    if (!preg_match('/[a-zA-Z]/', $password) || !preg_match('/[0-9]/', $password)) $errors[] = '密码需包含字母和数字';
    if ($password !== $confirmPassword) $errors[] = '两次密码不一致';
    
    if (empty($errors)) {
        try {
            $userId = $user->register($username, $email, $password, $city);
            if ($userId) {
                handleLogin($userId, $username, $email, 'user', false);
                header('Location: ' . $site_config['redirect_after_login']);
                exit;
            }
        } catch (Exception $e) {
            $errors[] = $e->getMessage();
        }
    }
}

// 获取城市列表
$cities = [];
try {
    $stmt = $pdo->query("SELECT name FROM cities ORDER BY rank LIMIT 100");
    $cities = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {}
?>
<?php include $site_config['includes_path'] . 'header.php'; ?>

<style>
.register-container { max-width:480px; margin:40px auto; padding:20px; }
.register-header { text-align:center; margin-bottom:25px; }
.register-header h2 { color:#333; font-size:24px; margin-bottom:8px; }
.register-header p { color:#999; font-size:14px; }
.form-group { margin-bottom:16px; }
.form-group label { display:block; margin-bottom:6px; font-weight:bold; color:#555; font-size:14px; }
.form-group input, .form-group select { width:100%; padding:12px; border:1px solid #ddd; border-radius:6px; font-size:15px; }
.form-group input:focus { border-color:#ff6b00; outline:none; }
.btn-register { width:100%; padding:14px; background:#ff6b00; color:white; border:none; border-radius:6px; font-size:17px; font-weight:bold; cursor:pointer; }
.btn-register:hover { background:#e05d00; }
.alert { padding:10px 15px; border-radius:4px; margin-bottom:15px; }
.alert-error { background:#ffebee; color:#c62828; }
.alert-success { background:#d4edda; color:#155724; }
.register-footer { text-align:center; margin-top:20px; font-size:14px; color:#666; }
.register-footer a { color:#ff6b00; font-weight:bold; }
</style>

<div class="register-container">
    <div class="register-header">
        <h2>注册<?= htmlspecialchars($site_config['name']) ?></h2>
        <p><?= htmlspecialchars($site_config['desc']) ?></p>
    </div>
    
    <?php if (!empty($errors)): ?>
        <?php foreach ($errors as $e): ?>
            <div class="alert alert-error"><?= htmlspecialchars($e) ?></div>
        <?php endforeach; ?>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    
    <form method="POST" action="register.php">
        <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
        <div class="form-group">
            <label>用户名</label>
            <input type="text" name="username" required value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" placeholder="4-20个字符">
        </div>
        <div class="form-group">
            <label>邮箱</label>
            <input type="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" placeholder="your@email.com">
        </div>
        <div class="form-group">
            <label>密码</label>
            <input type="password" name="password" required placeholder="至少6位，含字母和数字">
        </div>
        <div class="form-group">
            <label>确认密码</label>
            <input type="password" name="confirm_password" required placeholder="再次输入密码">
        </div>
        <div class="form-group">
            <label>所在城市</label>
            <input type="text" name="city" list="cityList" value="<?= htmlspecialchars($_POST['city'] ?? '') ?>" placeholder="选择或输入城市">
            <datalist id="cityList">
                <?php foreach ($cities as $c): ?>
                    <option value="<?= htmlspecialchars($c) ?>">
                <?php endforeach; ?>
            </datalist>
        </div>
        <button type="submit" class="btn-register">注册</button>
    </form>
    
    <div class="register-footer">
        <p>已有账号？ <a href="login.php">立即登录</a></p>
    </div>
</div>

<?php include $site_config['includes_path'] . 'footer.php'; ?>
