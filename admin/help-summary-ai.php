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

header('Content-Type: application/json; charset=utf-8');

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
if (mb_strlen($plain) > 3000) $plain = mb_substr($plain, 0, 3000); // 控制 token

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
    if (!$res['ok']) {
        sum_out(false, '', 'AI 调用失败：' . $res['error'], $meta);
    }

    $s = trim((string)$res['answer']);
    $s = preg_replace('/[`"\']/u', '', $s);
    $s = preg_replace('/^\s*(摘要|简介|summary)\s*[:：]\s*/iu', '', $s); // 去掉模型自带的"摘要："标签
    $s = trim(preg_replace('/\s+/u', ' ', $s));
    if ($s === '') sum_out(false, '', 'AI 返回为空');
    if (mb_strlen($s) > 500) $s = mb_substr($s, 0, 497) . '…';

    sum_out(true, $s, '', $meta);
} catch (Exception $ex) {
    sum_out(false, '', '异常：' . $ex->getMessage());
}
