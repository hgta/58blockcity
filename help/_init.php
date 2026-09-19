<?php
/**
 * 帮助中心引导文件
 * change: help-center-ai-assistant (task 2.1)
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('Asia/Shanghai');
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
ini_set('display_errors', '0');

// ---------- 数据库 ----------
require_once __DIR__ . '/../config/database.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    $h = getenv('DB_HOST') ?: 'localhost';
    $n = getenv('DB_NAME'); $u = getenv('DB_USER'); $p = getenv('DB_PASS') ?: '';
    if ($n && $u) {
        $pdo = new PDO("mysql:host={$h};dbname={$n};charset=utf8mb4", $u, $p, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
        ]);
    } else {
        http_response_code(503);
        exit('帮助中心暂时无法访问，请稍后再试。');
    }
}

// ---------- 基础函数 ----------

/** HTML转义 */
function e($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

/** 站点基路径：help.58.tl 独立域 → / ，否则 /help/ */
function help_base() {
    static $base = null;
    if ($base !== null) return $base;
    $host = strtolower($_SERVER['HTTP_HOST'] ?? '');
    if (strpos($host, 'help.') === 0) $base = '/';
    elseif (!empty($_SERVER['SCRIPT_NAME']) && strpos($_SERVER['SCRIPT_NAME'], '/help/') === 0) $base = '/help/';
    else $base = '/help/';
    return $base;
}

function help_url($path = '') {
    return help_base() . ltrim($path, '/');
}

function article_url($slug)  { return help_url('article/' . $slug); }
function category_url($slug) { return help_url('category/' . $slug); }

/** system_settings 缓存读取 */
function help_setting($key, $default = null) {
    global $pdo;
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try {
            foreach ($pdo->query("SELECT setting_key, setting_value FROM system_settings") as $row) {
                $cache[$row['setting_key']] = $row['setting_value'];
            }
        } catch (Exception $ex) { /* 表未建时静默降级 */ }
    }
    return array_key_exists($key, $cache) ? $cache[$key] : $default;
}

/** 访问者指纹：登录用户用ID，否则IP+UA 哈希（不存明文IP） */
function help_visitor_hash() {
    if (!empty($_SESSION['user_id'])) return 'u' . $_SESSION['user_id'];
    $ip  = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $ua  = $_SERVER['HTTP_USER_AGENT'] ?? '';
    return 'v' . hash('sha256', $ip . '|' . $ua . '|' . session_id());
}

/** 正文纯文本摘要 */
function help_plain_summary($html, $len = 120) {
    $plain = trim(preg_replace('/\s+/u', ' ', strip_tags((string)$html)));
    return mb_substr($plain, 0, $len);
}

/** 判断表是否存在（后台首次部署友好） */
function help_tables_ready() {
    global $pdo;
    try {
        $pdo->query("SELECT 1 FROM help_articles LIMIT 1");
        return true;
    } catch (Exception $ex) {
        return false;
    }
}
