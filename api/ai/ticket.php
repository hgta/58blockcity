<?php
/**
 * AI 助手留言工单提交接口
 * change: help-center-ai-assistant (task 3.4)
 * POST JSON: {question, contact?, chat_log_id?}
 */

require_once __DIR__ . '/../../config/database.php';

// 会话统一初始化（设置跨子站 cookie domain），勿直接 session_start()
require_once __DIR__ . '/../../includes/session.php';
date_default_timezone_set('Asia/Shanghai');
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
ini_set('display_errors', '0');

// ---------- 跨子域 CORS ----------
$origin = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
if (preg_match('#^https://([a-z0-9-]+\.)*58\.tl$#i', $origin)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    header('Vary: Origin');
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
}

header('Content-Type: application/json; charset=utf-8');

function tj_out($ok, $msg = '', $code = 200) {
    http_response_code($code);
    echo json_encode(['ok' => $ok, 'msg' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') tj_out(false, '方法不允许', 405);

if (!isset($pdo) || !($pdo instanceof PDO)) {
    $h = getenv('DB_HOST') ?: 'localhost';
    $n = getenv('DB_NAME'); $u = getenv('DB_USER'); $p = getenv('DB_PASS') ?: '';
    if (!$n || !$u) tj_out(false, '服务暂不可用', 503);
    $pdo = new PDO("mysql:host={$h};dbname={$n};charset=utf8mb4", $u, $p, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) tj_out(false, '请求格式错误', 400);

$question  = mb_substr(trim((string)($input['question'] ?? '')), 0, 1000);
$contact   = mb_substr(trim((string)($input['contact'] ?? '')), 0, 200);
$chatLogId = (int)($input['chat_log_id'] ?? 0) ?: null;

if ($question === '') tj_out(false, '问题描述不能为空', 400);

$userId = !empty($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
$ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
$vh = 'v' . hash('sha256', $ip . '|' . $ua . '|' . session_id());
if ($userId) $vh = 'u' . $userId;

// 简单频控：同指纹 10 分钟内最多 3 条工单
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM ai_feedback_tickets WHERE visitor_ip_hash = :vh AND created_at > NOW() - INTERVAL 600 SECOND");
    $stmt->execute([':vh' => $vh]);

    if ((int)$stmt->fetchColumn() >= 3) tj_out(false, '提交太频繁，请稍后再试', 429);

    $ins = $pdo->prepare(
        "INSERT INTO ai_feedback_tickets (user_id, visitor_ip_hash, contact, question, chat_log_id)
         VALUES (:uid, :vh, :contact, :q, :clid)"
    );
    $ins->execute([':uid' => $userId, ':vh' => $vh, ':contact' => $contact, ':q' => $question, ':clid' => $chatLogId]);
    tj_out(true, '已收到你的留言，我们会尽快处理并回复');
} catch (Exception $ex) {
    tj_out(false, '服务异常，请稍后再试', 500);
}
