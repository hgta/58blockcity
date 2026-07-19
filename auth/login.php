<?php
/**
 * 共享登录页面 — 所有子站共用
 * 
 * 用法: 子站在 auth/login.php 中设置 $site_config 后 include 本文件
 * 
 * $site_config = [
 *     'name' => 'BCT交易',        // 站点名称
 *     'desc' => '管理您的人气值',   // 副标题
 *     'redirect_after_login' => '../user/dashboard.php',
 *     'home_url' => '../index.php',
 *     'db_path' => '../../config/database.php',
 *     'class_path' => '../../classes/',
 * ];
 *
 * // 然后 include 共享 login:
 * require_once __DIR__ . '/../../auth/login.php';
 */

// 对于子站直接包含的场景，site_config 已设置
// 主站直接访问时，使用默认配置
if (!isset($site_config)) {
    $site_config = [
        'name'                   => '58区块城市',
        'desc'                   => '登录您的账户',
        'redirect_after_login'   => '../index.php',
        'home_url'               => '../index.php',
        'db_path'                => '../config/database.php',
        'class_path'             => '../classes/',
        'includes_path'          => __DIR__ . '/../mall/includes/',
    ];
}

// 共享库路径（绝对路径，不依赖子站上下文）
$sharedIncludes = dirname(__DIR__) . '/includes';

require_once $site_config['db_path'];
require_once $site_config['class_path'] . 'User.php';
require_once $sharedIncludes . '/functions.php';
require_once $sharedIncludes . '/auth.php';

$error = '';
$user = new User($pdo);

// 如果用户已登录，重定向
if (isLoggedIn()) {
    header('Location: ' . ($_SESSION['redirect_url'] ?? $site_config['redirect_after_login']));
    unset($_SESSION['redirect_url']);
    exit;
}

// 支持通过 ?redirect= 指定登录后回跳地址（仅允许本站相对路径或本域名，防止开放重定向）
if (isset($_GET['redirect']) && is_string($_GET['redirect'])) {
    $rd = $_GET['redirect'];
    $host = parse_url($rd, PHP_URL_HOST);
    $isSafe = ($host === null) || ($host === 'block.58.tl') || ($host === 'www.58.tl') || ($host === '58.tl');
    if ($isSafe && !preg_match('#^javascript:#i', $rd)) {
        $_SESSION['redirect_url'] = $rd;
    }
}

// 处理登录
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $remember = isset($_POST['remember']);
    
    if (empty($username) || empty($password)) {
        $error = '用户名和密码不能为空';
    } else {
        if (isset($_SESSION['login_attempts']) && $_SESSION['login_attempts'] >= 5) {
            if (time() - $_SESSION['last_attempt_time'] < 300) {
                $error = '登录尝试次数过多，请5分钟后再试';
            } else {
                unset($_SESSION['login_attempts']);
            }
        }
        
        if (empty($error)) {
            $userData = $user->getUserByUsername($username);
            
            if ($userData && password_verify($password, $userData['password'])) {
                if ($userData['status'] !== 'active') {
                    $error = '账户已被禁用，请联系管理员';
                } else {
                    handleLogin($userData['id'], $userData['username'], 
                               $userData['email'], $userData['role'], $remember);
                    
                    $redirectUrl = $_SESSION['redirect_url'] ?? $site_config['redirect_after_login'];
                    unset($_SESSION['redirect_url']);
                    header('Location: ' . $redirectUrl);
                    exit;
                }
            } else {
                $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0) + 1;
                $_SESSION['last_attempt_time'] = time();
                $error = '用户名或密码错误';
                if (($_SESSION['login_attempts'] ?? 0) >= 3) {
                    $error .= '（剩余尝试次数：' . (5 - $_SESSION['login_attempts']) . '）';
                }
            }
        }
    }
}
?>
<?php include $site_config['includes_path'] . 'header.php'; ?>

<style>
.alert { padding:12px 20px; margin-bottom:20px; border-radius:4px; font-size:16px; }
.alert-error { background:#ffebee; color:#c62828; border-left:4px solid #c62828; }
.remember-me { display:flex; align-items:center; margin:15px 0; }
.remember-me input { margin-right:8px; width:10%; }
.login-container { max-width:450px; margin:60px auto; padding:20px; }
.login-header { text-align:center; margin-bottom:25px; }
.login-header h2 { color:#333; font-size:24px; margin-bottom:8px; }
.login-header p { color:#999; font-size:14px; }
.form-group { margin-bottom:18px; }
.form-group label { display:block; margin-bottom:6px; font-weight:bold; color:#555; font-size:14px; }
.form-group input[type="text"],
.form-group input[type="password"] { width:100%; padding:12px; border:1px solid #ddd; border-radius:6px; font-size:15px; transition:border .3s; }
.form-group input:focus { border-color:#ff6b00; outline:none; }
.btn-login { width:100%; padding:14px; background:#ff6b00; color:white; border:none; border-radius:6px; font-size:17px; font-weight:bold; cursor:pointer; }
.btn-login:hover { background:#e05d00; }
.login-footer { text-align:center; margin-top:20px; font-size:14px; color:#666; }
.login-footer a { color:#ff6b00; font-weight:bold; }
</style>

<div class="login-container">
    <div class="login-header">
        <h2>登录<?= htmlspecialchars($site_config['name']) ?></h2>
        <p><?= htmlspecialchars($site_config['desc']) ?></p>
    </div>
    
    <?php if (!empty($error)): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    
    <form class="login-form" action="login.php" method="POST">
        <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
        <div class="form-group">
            <label for="username">用户名</label>
            <input type="text" id="username" name="username" required 
                   value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label for="password">密码</label>
            <input type="password" id="password" name="password" required>
        </div>
        <div class="remember-me">
            <input type="checkbox" id="remember" name="remember" value="1">
            <label for="remember">30天内自动登录</label>
        </div>
        <button type="submit" class="btn-login">登录</button>
    </form>
    
    <div class="login-footer">
        <p>没有账号？ <a href="register.php">立即注册</a> • <a href="forgot_password.php">忘记密码？</a></p>
    </div>
</div>

<?php include $site_config['includes_path'] . 'footer.php'; ?>
