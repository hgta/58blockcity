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
if (!defined('AUTH_SESSION_TTL')) {
    define('AUTH_SESSION_TTL', 86400 * AUTH_REMEMBER_DAYS);
}
// 会话存储目录（站点级隔离）
//
// 为什么不改 php.ini：宝塔的 php.ini 是按 **PHP 版本**全局共享的，
// 改 session.save_path / gc_maxlifetime 会波及同版本下所有站点
// （所有站会话混进同一目录，或反过来被别人的 1440 秒默认值清掉）。
// 这里在本请求内 ini_set 覆盖，作用域仅本站，随代码部署生效、与应用同版本管理。
//
// 默认取「系统临时目录 + 站点专名」的子目录，原因：
//   · 位于站点根之外，sess_* 文件不会被 Web 直接访问；
//   · PHP 的 GC 只扫描 save_path 下以 sess_ 开头的文件、且不递归子目录，
//     因此同机其他站点（save_path=/tmp）清理时不会误删本站会话。
// 如需改用固定磁盘目录，在 config 中 define('AUTH_SESSION_DIR', '/your/path') 即可。
if (!defined('AUTH_SESSION_DIR')) {
    define('AUTH_SESSION_DIR', rtrim(sys_get_temp_dir(), '/\\') . '/58blockcity-sessions');
}

if (session_status() === PHP_SESSION_NONE) {
    // 独立会话目录（不可写时回退系统默认，只记录日志、不阻断请求）
    $authSessionDir = AUTH_SESSION_DIR;
    if (!is_dir($authSessionDir)) {
        @mkdir($authSessionDir, 0700, true);
        @chmod($authSessionDir, 0700);
    }
    if (is_dir($authSessionDir) && is_writable($authSessionDir)) {
        @ini_set('session.save_path', $authSessionDir);
    } else {
        error_log('[session] 独立会话目录不可用，回退系统默认 save_path: ' . $authSessionDir);
    }
    // 会话数据存活期跟随「记住我」（默认 30 天）。
    // 不设时 php.ini 默认 1440 秒（24 分钟）就会把会话文件 GC 掉，
    // 与 Cookie 声明的 30 天严重不一致 —— 这是"勾了 30 天仍频繁重登"的主因。
    @ini_set('session.gc_maxlifetime', (string)AUTH_SESSION_TTL);

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
