<?php
/**
 * 统一的会话初始化（全站唯一入口）
 *
 * 必须在任何 session_start() 之前引入。作用是先设置跨子站的 cookie domain
 * （.58.tl），再启动会话，使登录状态能在 www / mall / block / bct / v 等
 * 子域之间共享。
 *
 * 背景（重要）：
 *   PHP 的 session_set_cookie_params() 必须在 session_start() 之前调用，
 *   一旦会话已启动就无法再修改 cookie 的 domain。若某个页面先用默认参数
 *   session_start()，它写入的 session cookie 只对本域有效，登录页（domain=.58.tl）
 *   设置的登录状态在该页面就读不到 —— 表现为「登录后导航栏仍显示登录按钮」。
 *
 * 用法：
 *   require_once __DIR__ . '/../includes/session.php';   // 相对本文件位置调整
 *   // 之后无需再写 session_start()
 */

if (!defined('AUTH_COOKIE_DOMAIN')) {
    define('AUTH_COOKIE_DOMAIN', '.58.tl');
}
if (!defined('AUTH_REMEMBER_DAYS')) {
    define('AUTH_REMEMBER_DAYS', 30);
}
if (!defined('AUTH_REGENERATE_SECONDS')) {
    define('AUTH_REGENERATE_SECONDS', 1800);
}
if (!defined('AUTH_IDLE_TIMEOUT')) {
    define('AUTH_IDLE_TIMEOUT', 3600);
}

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
