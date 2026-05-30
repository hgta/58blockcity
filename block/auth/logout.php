<?php
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// 启动会话
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 记录登出用户信息（可选）
$userId = $_SESSION['user_id'] ?? '未知用户';
error_log("用户退出登录: ID=" . $userId . ", 时间=" . date('Y-m-d H:i:s'));

// 清除记住我令牌
if (isset($_COOKIE['remember_me'])) {
    try {
        // 直接引入数据库连接
        require_once '../../config/database.php';
        $stmt = $pdo->prepare("DELETE FROM remember_tokens WHERE token = :token");
        $stmt->execute([':token' => $_COOKIE['remember_me']]);
    } catch (PDOException $e) {
        error_log("清除记住我令牌错误: " . $e->getMessage());
    }
    
    // 清除cookie
    setcookie('remember_me', '', time() - 3600, '/', '', isset($_SERVER['HTTPS']), true);
}

// 清除所有会话数据
$_SESSION = [];

// 清除会话cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(), 
        '', 
        time() - 42000,
        $params["path"], 
        $params["domain"],
        $params["secure"], 
        $params["httponly"]
    );
}

// 销毁会话
session_destroy();

// 重新启动会话用于闪存消息
session_start();
$_SESSION['flash_messages']['success'][] = '您已成功退出登录';

// 重定向到登录页面
header('Location: login.php');
exit;
?>