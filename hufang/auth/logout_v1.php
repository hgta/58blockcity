<?php
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// 销毁所有会话数据
$_SESSION = array();

// 如果要清除会话cookie，需要同时清除cookie的内容
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 最后销毁会话
session_destroy();

// 重定向到登录页面
header('Location: login.php');
exit;
?>