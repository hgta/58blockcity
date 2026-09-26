<?php
/**
 * AI 助手对话端点（SSE 流式）
 * change: help-center-ai-assistant (task 3.2/3.4)
 *
 * POST JSON: {question: string, history: [{role, content}], page: string}
 * 输出 SSE:
 *   {"type":"meta","provider":"..."}
 *   {"type":"delta","text":"..."}
 *   {"type":"done","matched":bool,"sources":[{title,url}],"log_id":n}
 *   {"type":"error"|"limit","msg":"..."}
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../classes/SecureCrypto.php';
require_once __DIR__ . '/../../classes/AiProvider.php';

// 会话统一初始化（设置跨子站 cookie domain），勿直接 session_start()
require_once __DIR__ . '/../../includes/session.php';
date_default_timezone_set('Asia/Shanghai');
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
ini_set('display_errors', '0');

// ---------- 跨子域 CORS（help.* 等子域携带 Cookie 调用本接口） ----------
$origin = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
if (preg_match('#^https://([a-z0-9-]+\.)*58\.tl$#i', $origin)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    header('Vary: Origin');
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
}

// ---------- 引导 PDO（与 tools 脚本一致的兜底） ----------
if (!isset($pdo) || !($pdo instanceof PDO)) {
    $h = getenv('DB_HOST') ?: 'localhost';
    $n = getenv('DB_NAME'); $u = getenv('DB_USER'); $p = getenv('DB_PASS') ?: '';
    if (!$n || !$u) { ai_sse_error('服务暂不可用', 503); }
    $pdo = new PDO("mysql:host={$h};dbname={$n};charset=utf8mb4", $u, $p, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}

// ---------- 工具 ----------
function ai_settings() {
    global $pdo;
    static $c = null;
    if ($c === null) {
        $c = [];
        try { foreach ($pdo->query("SELECT setting_key, setting_value FROM system_settings") as $r) $c[$r['setting_key']] = $r['setting_value']; }
        catch (Exception $e) {}
    }
    return $c;
}
function ai_s($k, $d) { $s = ai_settings(); return array_key_exists($k, $s) ? $s[$k] : $d; }

function ai_sse($data) {
    echo 'data: ' . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n\n";
    if (function_exists('flush')) { @ob_flush(); @flush(); }
}
/** 早期错误（SSE 头未发出前）：输出 JSON */
function ai_sse_error($msg, $code = 500) {
    // 不要用 5xx：Cloudflare 会把源站 5xx 替换成自带 HTML 错误页，前端无法解析
    if ($code >= 500) $code = 200;
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'msg' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

/** 访问者指纹（脱敏，不存明文IP） */
function ai_visitor_hash() {
    if (!empty($_SESSION['user_id'])) return 'u' . $_SESSION['user_id'];
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    return 'v' . hash('sha256', $ip . '|' . $ua . '|' . session_id());
}

/** 帮助中心地址（生成引用链接用） */
function ai_help_base() {
    $refHost = parse_url((string)($_SERVER['HTTP_REFERER'] ?? ''), PHP_URL_HOST);
    if ($refHost && strpos(strtolower($refHost), 'help.') === 0) return 'https://' . $refHost . '/';
    $cfg = ai_s('help_site_url', '');
    if ($cfg !== '') return rtrim($cfg, '/') . '/';
    return 'https://' . ($_SERVER['HTTP_HOST'] ?? 'www.58.tl') . '/help/';
}

// ---------- 输入 ----------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') ai_sse_error('方法不允许', 405);
if (ai_s('ai_assistant_enabled', '1') !== '1') ai_sse_error('AI 助手暂未开放', 503);

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) ai_sse_error('请求格式错误', 400);

$question = mb_substr(trim((string)($input['question'] ?? '')), 0, 500);
$page     = mb_substr(trim((string)($input['page'] ?? '')), 0, 500);
$history  = is_array($input['history'] ?? null) ? $input['history'] : [];
if ($question === '') ai_sse_error('问题不能为空', 400);

header('Content-Type: text/event-stream; charset=utf-8');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no');

// ---------- 频控 ----------
$vh = ai_visitor_hash();
$userId = !empty($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

try {
    // 时间窗限流（所有访客）
    $window = max(10, (int)ai_s('ai_rate_window', '60'));
    $winMax = max(1, (int)ai_s('ai_rate_max_per_window', '3'));
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM ai_chat_logs WHERE visitor_ip_hash = :vh AND created_at > NOW() - INTERVAL " . $window . " SECOND AND status IN ('ok','unmatched')");
    $stmt->execute([':vh' => $vh]);
    if ((int)$stmt->fetchColumn() >= $winMax) {
        http_response_code(429);
        ai_sse(['type' => 'limit', 'msg' => '提问太快啦，请稍等一分钟再试～']);
        exit;
    }

    // 登录用户每日限额
    if ($userId) {
        $dayMax = (int)ai_s('ai_daily_user_limit', '30');
        if ($dayMax > 0) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM ai_chat_logs WHERE user_id = :uid AND created_at > CURDATE() AND status IN ('ok','unmatched')");
            $stmt->execute([':uid' => $userId]);
            if ((int)$stmt->fetchColumn() >= $dayMax) {
                http_response_code(429);
                ai_sse(['type' => 'limit', 'msg' => '今天的问题额度已用完，明天再来吧～也可以先浏览帮助教程']);
                exit;
            }
        }
    }

    // 全站每日总量
    $dayTotal = (int)ai_s('ai_daily_total_limit', '0');
    if ($dayTotal > 0) {
        $cnt = (int)$pdo->query("SELECT COUNT(*) FROM ai_chat_logs WHERE created_at > CURDATE() AND status IN ('ok','unmatched')")->fetchColumn();
        if ($cnt >= $dayTotal) {
            http_response_code(429);
            ai_sse(['type' => 'limit', 'msg' => 'AI 助手今日繁忙，请明天再试，或先浏览帮助教程']);
            exit;
        }
    }
} catch (Exception $ex) {
    ai_sse_error('服务异常', 500);
}

// ---------- RAG 检索 ----------
$sources = [];
$knowledge = '';
$matched = false;
$topN = max(1, min(5, (int)ai_s('ai_rag_topn', '3')));
$base = ai_help_base();

try {
    $stmt = $pdo->prepare(
        "SELECT id, title, slug, summary, content_richtext,
                MATCH(title, summary, content_richtext) AGAINST(:kw1 IN NATURAL LANGUAGE MODE) AS score
         FROM help_articles
         WHERE status = 'published' AND MATCH(title, summary, content_richtext) AGAINST(:kw2 IN NATURAL LANGUAGE MODE)
         ORDER BY score DESC LIMIT " . $topN
    );
    $stmt->execute([':kw1' => $question, ':kw2' => $question]);
    $arts = $stmt->fetchAll();

    if (!$arts) { // LIKE 兜底
        $like = '%' . $question . '%';
        $stmt = $pdo->prepare(
            "SELECT id, title, slug, summary, content_richtext, 0 AS score
             FROM help_articles WHERE status = 'published' AND (title LIKE :l1 OR summary LIKE :l2 OR content_richtext LIKE :l3)
             ORDER BY view_count DESC LIMIT " . $topN
        );
        $stmt->execute([':l1' => $like, ':l2' => $like, ':l3' => $like]);
        $arts = $stmt->fetchAll();
    }

    foreach ($arts as $a) {
        $plain = trim(preg_replace('/\s+/u', ' ', strip_tags((string)$a['content_richtext'])));
        $chunk = mb_substr($a['summary'] !== '' ? $a['summary'] . ' ' . $plain : $plain, 0, 800);
        $knowledge .= "【{$a['title']}】{$chunk}\n\n";
        $sources[] = ['title' => $a['title'], 'url' => $base . 'article/' . $a['slug']];
        $matched = true;
    }

    // FAQ 补充（最多2条，避免上下文过长）
    $like = '%' . $question . '%';
    $stmt = $pdo->prepare(
        "SELECT question, answer FROM help_faq WHERE status = 'published' AND (question LIKE :l1 OR answer LIKE :l2) LIMIT 2"
    );
    $stmt->execute([':l1' => $like, ':l2' => $like]);
    foreach ($stmt->fetchAll() as $f) {
        $knowledge .= "【常见问题：{$f['question']}】" . mb_substr($f['answer'], 0, 300) . "\n\n";
        $matched = true;
    }
} catch (Exception $ex) { /* 检索失败则纯模型回答 */ }

// ---------- 组装消息 ----------
// 人设主文案可配置（后台训练台编辑），空值回退内置默认；结构性拼接（RAG/页面/用户）保留在代码层
$defaultSystem = "你是\"58区块城市\"平台的官方帮助助手，名字叫\"小帮\"。规则：\n"
    . "1. 优先依据下面的官方帮助资料回答；资料未覆盖时，明确告知暂无官方资料，建议用户到帮助中心留言，不要编造价格、规则、日期等事实。\n"
    . "2. 用简体中文回答，简洁清晰；涉及操作步骤时用编号列表。\n"
    . "3. 语气友好，面向不熟悉互联网产品的新手用户。\n";
$system = trim((string)ai_s('ai_assistant_system_prompt', ''));
if ($system === '') $system = $defaultSystem;
// Hermes 渠道时追加记忆禁写令：前台用户对话不得写入/修改共享记忆区（防污染，训练台是唯一持笔人）
try {
    $hermesActive = (int)$pdo->query("SELECT COUNT(*) FROM ai_providers WHERE preset='hermes' AND is_enabled=1")->fetchColumn() > 0;
} catch (Exception $ex) { $hermesActive = false; }
if ($hermesActive) {
    $system .= "\n4. 不要写入或修改你的长期记忆（记忆由管理员统一维护），只需依据当前对话与上述资料回答。\n";
}
if ($knowledge !== '') {
    $system .= "\n=== 官方帮助资料 ===\n" . $knowledge;
}
if ($page !== '') {
    $system .= "\n=== 用户当前所在页面 ===\n" . $page . "\n（回答可结合该页面所属功能模块给出针对性指引）";
}
if ($userId) {
    try {
        $u = $pdo->prepare("SELECT username FROM users WHERE id = :id");
        $u->execute([':id' => $userId]);
        $name = $u->fetchColumn();
        if ($name) $system .= "\n=== 当前用户 ===\n已登录用户：{$name}";
    } catch (Exception $ex) {}
}

$messages = [['role' => 'system', 'content' => $system]];
$maxRounds = max(1, (int)ai_s('ai_max_context_rounds', '6'));
$clean = [];
foreach (array_slice($history, -$maxRounds) as $h) {
    $role = ($h['role'] ?? '') === 'assistant' ? 'assistant' : 'user';
    $text = mb_substr(trim((string)($h['content'] ?? '')), 0, 2000);
    if ($text !== '') $clean[] = ['role' => $role, 'content' => $text];
}
foreach ($clean as $m) $messages[] = $m;
$messages[] = ['role' => 'user', 'content' => $question];

// ---------- 调用（故障切换） ----------
$logId = null;
$res = AiProvider::chatWithFailover($pdo, $messages, function ($delta) {
    ai_sse(['type' => 'delta', 'text' => $delta]);
});

// ---------- 日志 ----------
try {
    $ins = $pdo->prepare(
        "INSERT INTO ai_chat_logs (user_id, visitor_ip_hash, source_page, question, answer_digest, matched, provider_id, status)
         VALUES (:uid, :vh, :page, :q, :a, :m, :pid, :st)"
    );
    $ins->execute([
        ':uid' => $userId, ':vh' => $vh, ':page' => $page,
        ':q' => mb_substr($question, 0, 200),
        ':a' => $res['ok'] ? mb_substr($res['answer'], 0, 300) : null,
        ':m' => $matched ? 1 : 0,
        ':pid' => $res['provider'] ? $res['provider']->id() : null,
        ':st' => $res['ok'] ? ($matched ? 'ok' : 'unmatched') : 'error',
    ]);
    $logId = (int)$pdo->lastInsertId();
} catch (Exception $ex) {}

if (!$res['ok']) {
    error_log('[ai-chat] ' . $res['error']);
    ai_sse(['type' => 'error', 'msg' => 'AI 助手暂时开小差了，请稍后再试，或到帮助中心留言', 'log_id' => $logId]);
    exit;
}

ai_sse(['type' => 'done', 'matched' => $matched, 'sources' => $sources, 'log_id' => $logId]);
