<?php
/**
 * 共享登出 — 所有子站共用
 * 子站 auth/logout.php: <?php require_once '../../auth/logout.php';
 */
$projectRoot = dirname(__DIR__);
// 会话统一初始化（设置跨子站 cookie domain .58.tl），勿直接 session_start()
require_once $projectRoot . '/includes/session.php';
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
    // 必须用 .58.tl 域清除，否则子站（domain=.58.tl）写入的 cookie 清不掉
    setcookie('remember_me', '', time() - 3600, '/', AUTH_COOKIE_DOMAIN, isset($_SERVER['HTTPS']), true);
}

// 清除会话
$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params["path"], 
              $params["domain"], $params["secure"], $params["httponly"]);
}
session_destroy();

// 重新启动用于闪存消息（保持同一 cookie domain）
// session_destroy() 之后需重新设置 cookie 参数再启动，
// 否则新会话的 cookie domain 会退回默认值，导致跨子站闪存消息丢失。
session_set_cookie_params([
    'lifetime' => 86400 * AUTH_REMEMBER_DAYS,
    'path'     => '/',
    'domain'   => AUTH_COOKIE_DOMAIN,
    'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();
$_SESSION['flash_messages']['success'][] = '您已成功退出登录';

header('Location: login.php');
exit;
