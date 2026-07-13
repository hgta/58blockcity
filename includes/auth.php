<?php
/**
 * 用户认证相关辅助函数 (统一认证源)
 * 
 * 所有子站 (bct, block, hufang, nft, mall) 共用此文件。
 * 各子站的 includes/auth.php 为透明代理。
 */

// 统一常量
if (!defined('AUTH_COOKIE_DOMAIN')) {
    define('AUTH_COOKIE_DOMAIN', '.58.tl');
}
if (!defined('AUTH_REMEMBER_DAYS')) {
    define('AUTH_REMEMBER_DAYS', 30);
}
if (!defined('AUTH_REGENERATE_SECONDS')) {
    define('AUTH_REGENERATE_SECONDS', 1800); // 30分钟
}
if (!defined('AUTH_IDLE_TIMEOUT')) {
    define('AUTH_IDLE_TIMEOUT', 3600); // 1小时无操作 session 过期
}

// 启动会话并进行合理配置
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 86400 * AUTH_REMEMBER_DAYS,
        'path'     => '/',
        'domain'   => AUTH_COOKIE_DOMAIN,
        'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    
    session_start();
    
    // 防止会话固定攻击：定期重新生成 session ID
    if (empty($_SESSION['_regenerated_at'])) {
        $_SESSION['_regenerated_at'] = time();
    } elseif (time() - $_SESSION['_regenerated_at'] > AUTH_REGENERATE_SECONDS) {
        session_regenerate_id(true);
        $_SESSION['_regenerated_at'] = time();
    }
}

/**
 * 检查用户是否已登录，包含自动登录功能
 * @return bool 是否已登录
 */
function isLoggedIn() {
    // 检查会话中的用户ID
    if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
        // 更新最后活动时间
        $_SESSION['last_activity'] = time();
        return true;
    }
    
    // 检查自动登录cookie
    if (isset($_COOKIE['remember_me'])) {
        return attemptAutoLogin($_COOKIE['remember_me']);
    }
    
    return false;
}

/**
 * 尝试自动登录
 * @param string $token 记住我令牌
 * @return bool 是否登录成功
 */
function attemptAutoLogin($token) {
    global $pdo;
    
    try {
        // 查找有效的记住我令牌
        $stmt = $pdo->prepare("
            SELECT u.id, u.username, u.email, u.role 
            FROM users u 
            INNER JOIN remember_tokens rt ON u.id = rt.user_id 
            WHERE rt.token = :token AND rt.expires_at > NOW()
        ");
        $stmt->execute([':token' => $token]);
        $user = $stmt->fetch();
        
        if ($user) {
            // 设置会话
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['last_activity'] = time();
            
            // 更新令牌过期时间（滑动过期）
            $stmt = $pdo->prepare("
                UPDATE remember_tokens 
                SET expires_at = DATE_ADD(NOW(), INTERVAL " . AUTH_REMEMBER_DAYS . " DAY) 
                WHERE token = :token
            ");
            $stmt->execute([':token' => $token]);
            
            // 更新cookie（domain 统一，跨子站共享）
            setcookie('remember_me', $token, time() + 86400 * AUTH_REMEMBER_DAYS, '/', AUTH_COOKIE_DOMAIN, isset($_SERVER['HTTPS']), true);
            
            return true;
        }
    } catch (PDOException $e) {
        error_log("自动登录错误: " . $e->getMessage());
    }
    
    // 清除无效的cookie
    setcookie('remember_me', '', time() - 3600, '/', AUTH_COOKIE_DOMAIN, isset($_SERVER['HTTPS']), true);
    return false;
}

/**
 * 检查会话是否过期
 * 注意：即使 session 过期，如果用户有 remember_me cookie，
 * isLoggedIn() 会自动登录，不会真正丢失登录状态。
 * 这里只做 session 的空闲超时（7天），不再调用 logout() 清除 remember_me
 * @return bool 是否过期
 */
function isSessionExpired() {
    if (isset($_SESSION['last_activity']) && 
        (time() - $_SESSION['last_activity']) > AUTH_IDLE_TIMEOUT) {
        return true;
    }
    return false;
}

/**
 * 检查用户是否已登录，如果未登录则重定向
 */
function checkLogin() {
    // 如果 session 过期，清除 session 数据但不清除 remember_me cookie
    if (isSessionExpired()) {
        $_SESSION = [];
        session_destroy();
        session_start();
        // 不调用 logout()，保留 remember_me cookie 让 isLoggedIn() 自动登录
    }
    
    if (!isLoggedIn()) {
        $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
        header('Location: ../auth/login.php');
        exit;
    }
    
    // 更新最后活动时间
    $_SESSION['last_activity'] = time();
}


/**
 * 创建记住我令牌
 * @param int $userId 用户ID
 */
function createRememberMeToken($userId) {
    global $pdo;
    
    try {
        // 生成随机令牌
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', time() + 86400 * AUTH_REMEMBER_DAYS);
        
        // 使用 INSERT ... ON DUPLICATE KEY UPDATE（原子操作，无竞态）
        $stmt = $pdo->prepare("
            INSERT INTO remember_tokens (user_id, token, expires_at) 
            VALUES (:user_id, :token, :expires_at)
            ON DUPLICATE KEY UPDATE token = :token2, expires_at = :expires_at2
        ");
        $stmt->execute([
            ':user_id'     => $userId,
            ':token'       => $token,
            ':expires_at'  => $expires,
            ':token2'      => $token,
            ':expires_at2' => $expires,
        ]);
        
        // 设置cookie（domain 统一，跨子站共享）
        setcookie('remember_me', $token, time() + 86400 * AUTH_REMEMBER_DAYS, '/', AUTH_COOKIE_DOMAIN, isset($_SERVER['HTTPS']), true);
        
    } catch (PDOException $e) {
        error_log("创建记住我令牌错误: " . $e->getMessage());
    }
}

/**
 * 用户登出
 */
function logout() {
    // 清除记住我令牌
    if (isset($_COOKIE['remember_me'])) {
        global $pdo;
        try {
            $stmt = $pdo->prepare("DELETE FROM remember_tokens WHERE token = :token");
            $stmt->execute([':token' => $_COOKIE['remember_me']]);
        } catch (PDOException $e) {
            error_log("清除记住我令牌错误: " . $e->getMessage());
        }
        setcookie('remember_me', '', time() - 3600, '/', AUTH_COOKIE_DOMAIN, isset($_SERVER['HTTPS']), true);
    }
    
    // 清除会话
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
}

// 其他函数保持不变...
/**
 * 用户登录处理
 * @param int $userId 用户ID
 * @param string $username 用户名
 * @param string $email 邮箱
 * @param string $role 角色
 * @param bool $remember 是否记住登录
 */
function handleLogin($userId, $username, $email, $role, $remember = false) {
    global $pdo;
    
    // 防止会话固定攻击：登录时强制重新生成 session ID
    session_regenerate_id(true);
    $_SESSION['_regenerated_at'] = time();
    
    // 设置会话变量
    $_SESSION['user_id'] = $userId;
    $_SESSION['username'] = $username;
    $_SESSION['email'] = $email;
    $_SESSION['role'] = $role;
    $_SESSION['last_activity'] = time();
    $_SESSION['login_time'] = time();
    $_SESSION['login_ip'] = $_SERVER['REMOTE_ADDR'] ?? '';
    
    // 更新最后登录时间
    if (class_exists('User')) {
        $user = new User($pdo);
        if (method_exists($user, 'updateLastLogin')) {
            $user->updateLastLogin($userId);
        }
    }
    
    // 如果选择记住我，创建记住我令牌
    if ($remember) {
        createRememberMeToken($userId);
    }
    
    // 清除可能的登录尝试限制
    if (isset($_SESSION['login_attempts'])) {
        unset($_SESSION['login_attempts']);
    }
}

/**
 * 检查用户是否有权限访问管理后台
 * 根据用户角色判断是否为管理员
 */
function checkAdmin() {
    checkLogin();
    
    if (empty($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
        header('Location: ../index.php');
        exit;
    }
}

/**
 * 强制要求登录（API/页面通用入口）
 * 未登录则重定向到登录页。不再依赖各处重复的 isset($_SESSION['user_id'])。
 * 
 * @param string $loginUrl 登录页URL，默认为项目根 auth/login.php
 */
function requireLogin($loginUrl = '../auth/login.php') {
    if (!isLoggedIn()) {
        $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
        header('Location: ' . $loginUrl);
        exit;
    }
}

/**
 * 断言登录状态（API 端点使用）
 * 返回当前用户ID，未登录则输出 JSON 错误并退出。
 * 
 * @return int 当前用户ID
 */
function assertLogin() {
    if (!isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => '请先登录']);
        exit;
    }
    return (int)$_SESSION['user_id'];
}

/**
 * 生成CSRF令牌
 * @return string CSRF令牌
 */
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * 验证CSRF令牌
 * @param string $token 待验证的令牌
 * @return bool 是否有效
 */
function verifyCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * 验证表单提交的CSRF令牌
 */
function validateCsrfToken() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? '';
        if (!verifyCsrfToken($token)) {
            die('CSRF令牌验证失败');
        }
    }
}

/**
 * 设置闪存消息（一次性消息，显示后自动清除）
 * @param string $type 消息类型（success, error, warning, info）
 * @param string $message 消息内容
 */
function setFlashMessage($type, $message) {
    $_SESSION['flash_messages'][$type][] = $message;
}

/**
 * 获取并清除闪存消息
 * @param string $type 消息类型
 * @return array 消息数组
 */
function getFlashMessages($type) {
    $messages = $_SESSION['flash_messages'][$type] ?? [];
    unset($_SESSION['flash_messages'][$type]);
    return $messages;
}

/**
 * 显示闪存消息
 */
function displayFlashMessages() {
    $types = ['success', 'error', 'warning', 'info'];
    foreach ($types as $type) {
        $messages = getFlashMessages($type);
        foreach ($messages as $message) {
            echo '<div class="alert alert-'.$type.'">'.htmlspecialchars($message).'</div>';
        }
    }
}
?>