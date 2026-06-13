<?php
/**
 * 共享登出 — 所有子站共用
 * 子站 auth/logout.php: <?php require_once '../../auth/logout.php';
 */
session_start();
$projectRoot = dirname(__DIR__);
require_once $projectRoot . '/config/database.php';

$userId = $_SESSION['user_id'] ?? '未知用户';
error_log("用户退出: ID={$userId}, 时间=" . date('Y-m-d H:i:s'));

// 清除记住我令牌
if (isset($_COOKIE['remember_me'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM remember_tokens WHERE token = :token");
        $stmt->execute([':token' => $_COOKIE['remember_me']]);
    } catch (PDOException $e) {
        error_log("清除令牌错误: " . $e->getMessage());
    }
    setcookie('remember_me', '', time() - 3600, '/', '', isset($_SERVER['HTTPS']), true);
}

// 清除会话
$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params["path"], 
              $params["domain"], $params["secure"], $params["httponly"]);
}
session_destroy();

// 重新启动用于闪存消息
session_start();
$_SESSION['flash_messages']['success'][] = '您已成功退出登录';

header('Location: login.php');
exit;
