<?php
/**
 * 帮助中心引导文件
 * change: help-center-ai-assistant (task 2.1)
 */

// 会话统一初始化（设置跨子站 cookie domain），勿直接 session_start()
require_once __DIR__ . '/../includes/session.php';

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
if (!function_exists('e')) {
    function e($s) {
        return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    }
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

// ---------- 权威域与请求路径（change: help-seo-foundation D1/D2） ----------

/** 权威域基址：help 子站唯一可索引域，www.58.tl/help/* 全部 301 到此 */
function help_canonical_base() {
    return defined('HELP_CANONICAL_BASE') ? HELP_CANONICAL_BASE : 'https://help.58.tl/';
}

/** 权威域绝对 URL（用于 canonical / og:url / sitemap，不随请求 host 变化） */
function help_canonical_url($path = '') {
    return help_canonical_base() . ltrim((string)$path, '/');
}

/** 当前请求路径：去掉 /help/ 前缀与结尾斜杠（www.58.tl/help/faq → /faq） */
function help_request_path() {
    $uri = parse_url(isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/', PHP_URL_PATH);
    if (!is_string($uri) || $uri === '') $uri = '/';
    if ($uri === '/help') $uri = '/';
    elseif (strpos($uri, '/help/') === 0) $uri = substr($uri, 5);
    $uri = '/' . ltrim($uri, '/');
    return $uri === '/' ? '/' : rtrim($uri, '/');
}

/** 已知路由白名单：不在其中的路径判为软 404，不得回落首页 */
function help_is_known_path($path) {
    if ($path === '/') return true;
    return (bool)preg_match(
        '#^/(?:index\.php|search|faq|glossary|ask|sitemap\.xml|category/[a-z0-9\-]+|article/[a-z0-9\-]+)$#',
        $path
    );
}

/** 当前页面权威 URL（页面未显式传 canonical 时自动推导） */
function help_current_canonical() {
    $route = isset($_GET['route']) ? preg_replace('/[^a-z0-9\-]/', '', (string)$_GET['route']) : '';
    if ($route !== '' && $route !== 'home' && $route !== 'sitemap') {
        if ($route === 'article' || $route === 'category') {
            $slug = isset($_GET['slug']) ? preg_replace('/[^a-z0-9\-]/', '', (string)$_GET['slug']) : '';
            return help_canonical_url($route . ($slug !== '' ? '/' . $slug : ''));
        }
        return help_canonical_url($route);
    }
    $path = help_request_path();
    return help_canonical_url($path === '/' || $path === '/index.php' ? '' : ltrim($path, '/'));
}

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

/** 正文纯文本摘要：在字数上限内优先于句末标点收尾，避免出现半句（change: help-content-seo D3） */
function help_plain_summary($html, $len = 120) {
    $plain = trim(preg_replace('/\s+/u', ' ', strip_tags((string)$html)));
    if ($plain === '') return '';
    if (mb_strlen($plain) <= $len) return $plain;
    $cut = mb_substr($plain, 0, $len);
    $last = -1;
    foreach (['。', '！', '？', '!', '?', '；', ';'] as $p) {
        $i = mb_strrpos($cut, $p);
        if ($i !== false && $i > $last) $last = $i;
    }
    // 句末位置过靠前（不足上限一半）时仍硬截，避免摘要过短；多留 1 字给省略号，保证不超上限
    if ($last >= (int)($len * 0.5)) return mb_substr($plain, 0, $last + 1);
    return mb_substr($plain, 0, max(1, $len - 1)) . '…';
}

/**
 * 分类 slug → 业务子站入口（change: help-content-seo D5）
 * 集中一处维护：新增/调整子站入口只改这里，模板不动；无映射的分类返回空数组。
 *
 * @param string $catSlug help_categories.slug
 * @return array [['name'=>'', 'url'=>''], ...]
 */
function help_subsite_links($catSlug) {
    static $map = [
        'getting-started' => [
            ['name' => '58区块城市主站', 'url' => 'https://www.58.tl/'],
            ['name' => '全国城市一览',   'url' => 'https://www.58.tl/all-cities.php'],
        ],
        'block' => [
            ['name' => '区块认领',     'url' => 'https://block.58.tl/claim_list.php'],
            ['name' => '区块交易市场', 'url' => 'https://block.58.tl/sale_list.php'],
        ],
        'bct' => [
            ['name' => 'BCT 行情市场', 'url' => 'https://bct.58.tl/market.php'],
        ],
        'nft' => [
            ['name' => 'NFT 头像市场', 'url' => 'https://nft.58.tl/'],
        ],
        'mall' => [
            ['name' => '人气商城', 'url' => 'https://mall.58.tl/'],
            ['name' => '商品浏览', 'url' => 'https://mall.58.tl/product/list.php'],
        ],
        'club' => [
            ['name' => '互访圈', 'url' => 'https://v.58.tl/'],
        ],
        'bid' => [
            ['name' => '拍卖大厅', 'url' => 'https://bid.58.tl/'],
        ],
        'task' => [
            ['name' => '任务广场', 'url' => 'https://task.58.tl/'],
        ],
    ];
    return isset($map[$catSlug]) ? $map[$catSlug] : [];
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

/**
 * 百度主动推送（help 子站）：节流 + 异常隔离
 * change: help-baidu-indexing D3/D4
 *
 * 约定：
 *   - 只推权威域 URL（https://help.58.tl/…），绝不推 www.58.tl/help/…
 *   - 同一 URL 冷却期内不重复推（默认 24h，system_settings.help_push_cooldown_hours 可覆盖）
 *   - help_push_log 表不存在时降级为「不节流、直接推」，不报错
 *   - 任何异常只记日志，绝不向上抛（后台保存 MUST NOT 被推送阻塞）
 *
 * @param string $url 权威域绝对 URL
 * @param string $action 'push' 推送 | 'dead' 登记为死链
 * @return bool 是否真正发起了推送
 */
function help_push_url($url, $action = 'push') {
    global $pdo;
    $url = trim((string)$url);
    if ($url === '') return false;

    $log = function ($msg) { error_log('[SEO] [help-push] ' . $msg); };

    // 1) 死链：只登记，不走推送接口
    if ($action === 'dead') {
        try {
            if ($pdo instanceof PDO) {
                $pdo->prepare("INSERT INTO help_push_log (url, action, pushed_at) VALUES (?, 'dead', NOW())
                               ON DUPLICATE KEY UPDATE action = 'dead', pushed_at = NOW()")
                    ->execute([$url]);
            }
            $log('dead url=' . $url);
        } catch (Exception $ex) {
            $log('写入死链失败（表可能未建）：' . $ex->getMessage() . ' url=' . $url);
        }
        return false;
    }

    // 2) 冷却期判断
    $cooldown = max(1, (int)help_setting('help_push_cooldown_hours', '24'));
    $lastPush = null;
    try {
        if ($pdo instanceof PDO) {
            $st = $pdo->prepare("SELECT pushed_at FROM help_push_log WHERE url = ? AND action = 'push' LIMIT 1");
            $st->execute([$url]);
            $lastPush = $st->fetchColumn();
        }
    } catch (Exception $ex) {
        $log('推送日志不可用，降级为不节流：' . $ex->getMessage());
        $lastPush = null;
    }
    if ($lastPush && (time() - strtotime((string)$lastPush)) < $cooldown * 3600) {
        $log('冷却期跳过 url=' . $url . ' 上次=' . $lastPush . ' 冷却=' . $cooldown . 'h');
        return false;
    }

    // 3) 推送（异常隔离：网络/接口问题不得中断后台保存）
    try {
        require_once __DIR__ . '/../classes/SeoHelper.php';
        SeoHelper::pushContentUrl($url);
    } catch (Throwable $ex) {
        $log('推送异常：' . $ex->getMessage() . ' url=' . $url);
        return false;
    }

    // 4) 记录推送时间
    try {
        if ($pdo instanceof PDO) {
            $pdo->prepare("INSERT INTO help_push_log (url, action, pushed_at) VALUES (?, 'push', NOW())
                           ON DUPLICATE KEY UPDATE action = 'push', pushed_at = NOW()")
                ->execute([$url]);
        }
    } catch (Exception $ex) {
        $log('写入推送日志失败：' . $ex->getMessage() . ' url=' . $url);
    }
    return true;
}
