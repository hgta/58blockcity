<?php
/**
 * 用 AI 为帮助文章生成摘要（后台按钮用）
 * change: help-content-admin (task 8.2)
 *
 * POST JSON: {title: string, content: string(富文本HTML或纯文本)}
 * 返回 JSON: {ok: bool, summary: string, msg: string}
 *
 * - 复用现有聊天渠道（AiProvider 路由 + 故障切换）
 * - 服务端去标签、压空白、限 500 字（help_articles.summary 为 VARCHAR(500)）
 */

require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../classes/SecureCrypto.php';
require_once '../classes/AiProvider.php';
require_once '../classes/AiAdminTask.php';

checkAdmin();

// 关键：任何 PHP 警告/notice 若输出到正文，都会污染 JSON 让前端只能看到"网络异常"。
// 这里一律不显示，只写日志；未捕获的致命错误由 shutdown 兜底成 JSON。
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');

register_shutdown_function(function () {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'summary' => '', 'msg' => 'PHP 致命错误：' . $e['message']
            . ' @ ' . basename($e['file']) . ':' . $e['line']], JSON_UNESCAPED_UNICODE);
    }
});

function sum_out($ok, $summary = '', $msg = '', $meta = null)
{
    $out = ['ok' => $ok, 'summary' => $summary, 'msg' => $msg];
    if ($meta) $out['meta'] = $meta; // 渠道名/耗时/各渠道尝试明细，供后台提示
    echo json_encode($out, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') sum_out(false, '', '方法不允许');

$in = json_decode(file_get_contents('php://input'), true);
$title = trim((string)($in['title'] ?? ''));
$raw   = trim((string)($in['content'] ?? ''));

// HTML → 纯文本
$plain = preg_replace('#<(script|style)[^>]*>.*?</\1>#is', '', $raw);
$plain = preg_replace('#<br\s*/?>#i', ' ', $plain);
$plain = preg_replace('#</(p|div|li|h[1-6]|tr)>#i', ' ', $plain);
$plain = strip_tags($plain);
$plain = html_entity_decode($plain, ENT_QUOTES, 'UTF-8');
$plain = trim(preg_replace('/\s+/u', ' ', $plain));

if ($plain === '') sum_out(false, '', '正文为空，先写点内容再生成摘要');
// 控制送入模型的正文字数：思考型模型的耗时随输入增长，整篇长文常导致超 30s。
// 摘要只需开头主要内容，默认取前 1500 字（system_settings.ai_admin_task_max_input 可调，上限 3000）
$maxInput = 1500;
try {
    $v = (int)$pdo->query("SELECT setting_value FROM system_settings WHERE setting_key='ai_admin_task_max_input'")->fetchColumn();
    if ($v > 0) $maxInput = max(300, min(3000, $v));
} catch (Exception $ex) {}
$inputLen = mb_strlen($plain);
if ($inputLen > $maxInput) $plain = mb_substr($plain, 0, $maxInput);

try {
    $messages = [
        [
            'role' => 'system',
            'content' => '你是帮助中心编辑。为下面的文章写一段中文摘要，用于搜索结果与列表展示。'
                . '要求：80 到 120 字；说清这篇文章能帮用户解决什么问题、包含哪些要点；'
                . '不要出现"本文介绍了""这篇文章"之类的套话；不要分点；只输出摘要正文，不要标题和引号。',
        ],
        ['role' => 'user', 'content' => '标题：' . $title . "\n正文：" . $plain],
    ];

    $res = AiAdminTask::run($pdo, $messages);
    $meta = $res['meta'];
    $meta['input_len'] = $inputLen . '→' . mb_strlen($plain); // 便于判断是否因输入过长而超时
    if (!$res['ok']) {
        // 全是超时且输入偏长 → 直接给出可操作的调参建议
        $allTimeout = true;
        foreach ($res['meta']['attempts'] as $a) {
            if (strpos($a['error'], 'timed out') === false && strpos($a['error'], '首字节超时') === false) $allTimeout = false;
        }
        $hint = '';
        if ($allTimeout && $inputLen > 800) {
            $hint = '（两条渠道都在 ' . $res['meta']['timeout'] . 's 内没响应，通常是输入过长导致模型思考超时。'
                . '可到 后台 →「语义检索控制台」→「开关与阈值」区，把「后台任务：正文字数上限」调小（如 800）、'
                . '或把「后台任务：单渠道超时」调大后重试）';
        }
        sum_out(false, '', 'AI 调用失败：' . $res['error'] . $hint, $meta);
    }

    $s = trim((string)$res['answer']);
    $s = preg_replace('/[`"\']/u', '', $s);
    $s = preg_replace('/^\s*(摘要|简介|summary)\s*[:：]\s*/iu', '', $s); // 去掉模型自带的"摘要："标签
    $s = trim(preg_replace('/\s+/u', ' ', $s));
    if ($s === '') sum_out(false, '', 'AI 返回为空');
    if (mb_strlen($s) > 500) $s = mb_substr($s, 0, 497) . '…';

    sum_out(true, $s, '', $meta);
} catch (Throwable $ex) {
    // 捕获 Exception 与 Error（PHP7+），保证任何异常都以 JSON 返回
    sum_out(false, '', '异常：' . $ex->getMessage());
}
