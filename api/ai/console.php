<?php
/**
 * AI 训练台服务端代理（管理员专用）
 * change: admin-ai-training-console (task 3.1/3.2/3.3)
 *
 * 浏览器 → 本代理（checkAdmin）→ HermesClient → 本机 Hermes 原生 API
 * 浏览器永不接触 Hermes 地址与 API Key。
 *
 * GET  ?action=sessions                     会话列表
 * POST {action:"create_session", title}     新建会话
 * POST {action:"delete_session", id}        删除会话
 * POST {action:"fork_session", id}          分叉会话
 * GET  ?action=messages&id=...              会话历史消息
 * POST {action:"chat", session_id, message} 会话内流式对话（SSE 透传）
 * POST {action:"distill_faq", question, answer, category_id}      存为 FAQ 草稿
 * POST {action:"distill_article", title, summary, content, category_id} 存为文章草稿
 * POST {action:"memory_write", session_id, content}    写入记忆（admin 区，带验证）
 * POST {action:"memory_publish", session_id, content}  发布到小帮（assistant 区，带验证）
 * GET  ?action=unmatched                    未命中问题聚合
 * GET  ?action=prompt                       当前人设配置
 * POST {action:"save_prompt", prompt}       保存人设
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../classes/SecureCrypto.php';
require_once __DIR__ . '/../../classes/HermesClient.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/auth.php';
date_default_timezone_set('Asia/Shanghai');
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
ini_set('display_errors', '0');

// API 版管理员校验：失败返回 JSON 401/403，而非 302 重定向到不存在的登录页
checkAdminApi();

// ---------- 输出工具 ----------
function tc_out(array $data, int $code = 200) {
    // 注意：业务错误不要用 5xx——Cloudflare 会把源站 5xx 响应替换成自带的 HTML 错误页，
    // 前端 r.json() 随即报 "Unexpected token '<'"。统一 200 + ok:false 承载错误信息
    if ($code >= 500) $code = 200;
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
function tc_ok($data = null)   { tc_out(['ok' => true, 'data' => $data]); }
function tc_err(string $msg, int $code = 400) { tc_out(['ok' => false, 'msg' => $msg], $code); }
function tc_sse(array $data) {
    echo 'data: ' . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n\n";
    if (function_exists('flush')) { @ob_flush(); @flush(); }
}

function tc_client(): HermesClient {
    global $pdo;
    $c = HermesClient::load($pdo, $err);
    if (!$c) tc_err('训练台依赖本机 Hermes 智能体：' . $err, 503);
    return $c;
}

function tc_body(): array {
    $raw = file_get_contents('php://input');
    $b = json_decode($raw, true);
    return is_array($b) ? $b : [];
}

// ---------- 路由 ----------
$action = $_GET['action'] ?? ($_SERVER['REQUEST_METHOD'] === 'POST' ? '' : '');
$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        switch ($action) {
            case 'sessions':
                $list = tc_client()->listSessions();
                if ($list === null) tc_err(tc_client()->lastError ?: '获取会话列表失败', 502);
                tc_ok($list);
                break;

            case 'messages':
                $id = trim((string)($_GET['id'] ?? ''));
                if ($id === '') tc_err('缺少会话 id');
                $msgs = tc_client()->sessionMessages($id);
                if ($msgs === null) tc_err(tc_client()->lastError ?: '获取历史消息失败', 502);
                tc_ok($msgs);
                break;

            case 'unmatched':
                $rows = $pdo->query(
                    "SELECT question, COUNT(*) AS cnt, MAX(created_at) AS last_at
                     FROM ai_chat_logs
                     WHERE status = 'unmatched' AND created_at > NOW() - INTERVAL 30 DAY
                     GROUP BY question
                     ORDER BY cnt DESC, last_at DESC
                     LIMIT 50"
                )->fetchAll();
                tc_ok($rows);
                break;

            case 'prompt':
                $cur = '';
                $st = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'ai_assistant_system_prompt'");
                $st->execute();
                $cur = (string)$st->fetchColumn();
                tc_ok(['prompt' => $cur, 'source' => $cur !== '' ? 'custom' : 'default']);
                break;

            default:
                tc_err('未知 action', 404);
        }
    }

    if ($method === 'POST') {
        $b = tc_body();
        $action = $b['action'] ?? '';

        switch ($action) {
            // -------- 会话管理（转发 Hermes 原生） --------
            case 'create_session':
                $title = mb_substr(trim((string)($b['title'] ?? '')), 0, 100);
                $res = tc_client()->createSession($title !== '' ? ['title' => $title] : []);
                if ($res === null) tc_err(tc_client()->lastError ?: '创建会话失败', 502);
                tc_ok($res);
                break;

            case 'delete_session':
            case 'fork_session':
                $id = trim((string)($b['id'] ?? ''));
                if ($id === '') tc_err('缺少会话 id');
                $c = tc_client();
                $res = $action === 'delete_session' ? $c->deleteSession($id) : $c->forkSession($id);
                if ($res === null && $c->lastHttpCode !== 200 && $c->lastHttpCode !== 204) {
                    tc_err($c->lastError ?: '操作失败', 502);
                }
                tc_ok($res);
                break;

            // -------- 会话内流式对话（SSE 透传，admin 记忆区） --------
            case 'chat':
                $sid = trim((string)($b['session_id'] ?? ''));
                $msg = trim((string)($b['message'] ?? ''));
                if ($sid === '' || $msg === '') tc_err('缺少会话或消息内容');
                if (mb_strlen($msg) > 8000) tc_err('消息过长（上限 8000 字）');

                header('Content-Type: text/event-stream; charset=utf-8');
                header('Cache-Control: no-cache');
                header('X-Accel-Buffering: no');

                $client = tc_client();
                $res = $client->chatStream($sid, $msg, function ($chunk) {
                    // Hermes SSE 原样分块透传给前端（前端按行解析）
                    echo $chunk;
                    if (function_exists('flush')) { @ob_flush(); @flush(); }
                }, HermesClient::SESSION_KEY_ADMIN, 180.0);
                if (!$res['ok']) {
                    tc_sse(['type' => 'error', 'msg' => $res['error']]);
                }
                exit;

            // -------- 沉淀：存为 FAQ 草稿 --------
            case 'distill_faq':
                $q = mb_substr(trim((string)($b['question'] ?? '')), 0, 500);
                $a = trim((string)($b['answer'] ?? ''));
                $cat = (int)($b['category_id'] ?? 11); // 默认"规则与FAQ"
                if ($q === '' || $a === '') tc_err('问题与回答不能为空');
                $pdo->prepare("INSERT INTO help_faq (category_id, question, answer, source, status) VALUES (?,?,?,'ai_draft','draft')")
                    ->execute([$cat, $q, $a]);
                tc_ok(['faq_id' => (int)$pdo->lastInsertId(), 'edit_url' => 'help-faq.php']);
                break;

            // -------- 沉淀：存为文章草稿 --------
            case 'distill_article':
                $title = mb_substr(trim((string)($b['title'] ?? '')), 0, 200);
                $summary = mb_substr(trim((string)($b['summary'] ?? '')), 0, 500);
                $content = trim((string)($b['content'] ?? ''));
                $cat = (int)($b['category_id'] ?? 1); // 默认"新手上路"
                if ($title === '' || $content === '') tc_err('标题与内容不能为空');
                $slug = 'ai-draft-' . date('Ymd-His') . '-' . substr((string)mt_rand(), 0, 4);
                $pdo->prepare("INSERT INTO help_articles (category_id, title, slug, summary, content_type, content_richtext, is_ai_generated, status) VALUES (?,?,?,?,'richtext',?,1,'draft')")
                    ->execute([$cat, $title, $slug, $summary, $content]);
                tc_ok(['article_id' => (int)$pdo->lastInsertId(), 'edit_url' => 'help-articles.php']);
                break;

            // -------- 沉淀：写入记忆 / 发布到小帮（祈使句包装 + 自动验证） --------
            case 'memory_write':
            case 'memory_publish':
                $content = trim((string)($b['content'] ?? ''));
                if ($content === '') tc_err('记忆内容不能为空');
                if (mb_strlen($content) > 500) tc_err('记忆内容过长（上限 500 字，Hermes 记忆区容量有限）');

                $isPublish = $action === 'memory_publish';
                $key = $isPublish ? HermesClient::SESSION_KEY_ASSISTANT : HermesClient::SESSION_KEY_ADMIN;
                $client = tc_client();

                // 写入会话：优先复用指定会话（继续上下文），否则临时创建
                $sid = trim((string)($b['session_id'] ?? ''));
                if ($sid === '') {
                    $created = $client->createSession(['title' => 'memory-' . ($isPublish ? 'publish' : 'write')], $key);
                    $sid = (string)($created['id'] ?? $created['session_id'] ?? $created['session']['id'] ?? '');
                    if ($sid === '') tc_err('创建记忆写入会话失败: ' . $client->lastError, 502);
                }

                // 1) 祈使句写入指令（探测结论：Hermes 有记忆存储标准——必须说明用途、
                //    声明为跨会话稳定信息，裸内容会被其安全策略拒绝）
                $purpose = $isPublish
                    ? '该内容是「58区块城市」平台官方助手「小帮」的人设与行为规范，属于需要跨会话稳定生效的运营配置，由平台管理员正式发布'
                    : '该内容是「58区块城市」平台管理员对助手「小帮」的调教试验内容，属于需要跨会话稳定生效的人设偏好';
                $cmd = "系统指令：以下内容用于58区块城市平台AI助手调教（用途：{$purpose}）。"
                    . "请将其中需要长期保持的信息写入你的长期记忆（如更合适也可写入 blockcity-58tl-assistant 技能）：\n"
                    . $content . "\n写入后只回复 DONE";
                $w = $client->chatOnce($sid, $cmd, $key);
                if (!$w['ok']) tc_err('记忆写入失败: ' . $w['error'], 502);

                // 2) 自动验证：确认写入结果（Hermes 若因内容标准拒绝，会在这里暴露）
                $v = $client->chatOnce($sid, '请复述你刚刚对上述调教内容的处理结果（已写入长期记忆/技能，还是拒绝及原因），并给出记忆中的要点', $key);
                $combined = $w['answer'] . "\n" . $v['answer'];
                $verified = mb_stripos($combined, 'DONE') !== false
                         || mb_stripos($combined, '已写入') !== false
                         || mb_stripos($combined, '已保存') !== false;

                tc_ok([
                    'target'   => $isPublish ? 'assistant' : 'admin',
                    'write_reply' => mb_substr($w['answer'], 0, 200),
                    'verify_reply' => mb_substr($v['answer'], 0, 300),
                    'verified' => $verified,
                ]);
                break;

            // -------- 人设配置 --------
            case 'save_prompt':
                $p = trim((string)($b['prompt'] ?? ''));
                if ($p === '') tc_err('人设文案不能为空（如需恢复默认请传 restore=true）');
                if (isset($b['restore']) && $b['restore']) $p = '';
                if (mb_strlen($p) > 4000) tc_err('人设文案过长（上限 4000 字）');
                $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('ai_assistant_system_prompt', ?)
                               ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)")
                    ->execute([$p]);
                tc_ok(['source' => $p !== '' ? 'custom' : 'default']);
                break;

            default:
                tc_err('未知 action', 404);
        }
    }

    tc_err('方法不允许', 405);
} catch (Exception $ex) {
    tc_err('服务异常: ' . $ex->getMessage(), 500);
}
