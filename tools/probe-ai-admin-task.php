<?php
/**
 * 后台 AI 小任务链路探针（CLI，免后台鉴权）
 * change: help-content-admin (task 9.x 诊断工具)
 *
 * 用法：
 *   php tools/probe-ai-admin-task.php                # 用默认短提示词跑一次
 *   php tools/probe-ai-admin-task.php "你的问题"      # 自定义提示词
 *   php tools/probe-ai-admin-task.php --article=7    # 用真实文章 #7 复现「AI 摘要」的实际负载（推荐）
 *   php tools/probe-ai-admin-task.php --len=3000     # 用 N 字填充文本模拟长输入
 *
 * 输出：读到的配置、各渠道尝试明细、耗时、最终结果（原始 JSON）
 * 用途：后台按钮报"网络异常"时，用它能立刻分清是 PHP 端点问题还是 AI 链路问题
 */

if (is_file(__DIR__ . '/../config/database.php')) require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/SecureCrypto.php';
require_once __DIR__ . '/../classes/AiProvider.php';
require_once __DIR__ . '/../classes/AiAdminTask.php';

if (!isset($pdo)) {
    $h = getenv('DB_HOST') ?: 'localhost';
    $n = getenv('DB_NAME'); $u = getenv('DB_USER'); $p = getenv('DB_PASS') ?: '';
    if (!$n || !$u) { fwrite(STDERR, "缺少数据库配置。\n"); exit(1); }
    $pdo = new PDO("mysql:host={$h};dbname={$n};charset=utf8mb4", $u, $p, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}

echo "=== 1. 后台 AI 小任务配置 ===\n";
$cfg = AiAdminTask::config($pdo);
print_r($cfg);

echo "\n=== 2. 参与路由的聊天渠道（purpose=chat 且启用）===\n";
foreach (AiProvider::routeList($pdo) as $p) {
    echo sprintf("  #%d %s | preset=%s | model=%s | key=%s\n",
        $p->id(), $p->name(), $p->preset(), $p->model(),
        (SecureCrypto::decrypt($pdo->query("SELECT api_key_cipher FROM ai_providers WHERE id=" . $p->id())->fetchColumn()) !== null ? '已配置' : '空')
    );
}

// ---- 构造与「AI 摘要」一致或相近的负载 ----
$opts = getopt('', ['article::', 'len::', 'maxinput::']);
$messages = null;
$desc = '';

// 与线上一致：读取 ai_admin_task_max_input（默认 1500），--maxinput 可临时覆盖用于对比实验
$maxInput = 1500;
try {
    $v = (int)$pdo->query("SELECT setting_value FROM system_settings WHERE setting_key='ai_admin_task_max_input'")->fetchColumn();
    if ($v > 0) $maxInput = max(300, min(3000, $v));
} catch (Exception $ex) {}
if (!empty($opts['maxinput'])) $maxInput = max(100, min(6000, (int)$opts['maxinput']));
echo "送入模型的正文字数上限: {$maxInput}（system_settings.ai_admin_task_max_input，可用 --maxinput 覆盖）\n";

if (!empty($opts['article'])) {
    $aid = (int)$opts['article'];
    $a = $pdo->query("SELECT * FROM help_articles WHERE id = {$aid}")->fetch();
    if (!$a) { fwrite(STDERR, "文章 #{$aid} 不存在\n"); exit(1); }
    $raw = $a['content_type'] === 'steps' ? (string)$a['content_steps'] : (string)$a['content_richtext'];
    $plain = preg_replace('#<(script|style)[^>]*>.*?</\1>#is', '', $raw);
    $plain = preg_replace('#<br\s*/?>#i', ' ', $plain);
    $plain = preg_replace('#</(p|div|li|h[1-6]|tr)>#i', ' ', $plain);
    $plain = html_entity_decode(strip_tags($plain), ENT_QUOTES, 'UTF-8');
    $plain = trim(preg_replace('/\s+/u', ' ', $plain));
    $total = mb_strlen($plain);
    if ($total > $maxInput) $plain = mb_substr($plain, 0, $maxInput);
    $desc = "真实文章 #{$aid}《{$a['title']}》（正文 {$total} 字，截取 " . mb_strlen($plain) . ' 字）';
    $messages = [
        ['role' => 'system', 'content' => '你是帮助中心编辑。为下面的文章写一段中文摘要，用于搜索结果与列表展示。'
            . '要求：80 到 120 字；说清这篇文章能帮用户解决什么问题、包含哪些要点；'
            . '不要出现"本文介绍了""这篇文章"之类的套话；不要分点；只输出摘要正文，不要标题和引号。'],
        ['role' => 'user', 'content' => '标题：' . $a['title'] . "\n正文：" . $plain],
    ];
} elseif (!empty($opts['len'])) {
    $len = max(50, (int)$opts['len']);
    $pad = str_repeat('区块链城市的区块可以认领、交易与合并，居民数与人气值影响城市排名。', (int)ceil($len / 33));
    $pad = mb_substr($pad, 0, $len);
    $desc = "长输入模拟（{$len} 字）";
    $messages = [
        ['role' => 'system', 'content' => '你是帮助中心编辑，把用户给的材料压缩成 100 字以内的中文摘要，只输出摘要正文。'],
        ['role' => 'user', 'content' => $pad],
    ];
} else {
    $q = $argv[1] ?? '用30字说明什么是区块';
    $desc = "短提示词（{$q}）";
    $messages = [
        ['role' => 'system', 'content' => '你是帮助中心编辑，只输出正文，不要解释。'],
        ['role' => 'user', 'content' => $q],
    ];
}

echo "\n=== 3. 执行（{$desc}）===\n";
$t0 = microtime(true);
try {
    $res = AiAdminTask::run($pdo, $messages);
    printf("总耗时 %.1fs  ok=%s\n", microtime(true) - $t0, var_export($res['ok'], true));
    echo "最终渠道: " . ($res['meta']['provider'] !== '' ? $res['meta']['provider'] : '(无)') . "\n";
    echo "尝试明细:\n";
    foreach ($res['meta']['attempts'] as $a) {
        printf("  · %-22s ok=%-5s %6dms  %s\n", $a['name'], var_export($a['ok'], true), $a['ms'], mb_substr($a['error'], 0, 100));
    }
    echo "\n回答:\n" . mb_substr($res['answer'], 0, 300) . "\n";
    exit($res['ok'] ? 0 : 2);
} catch (Throwable $t) {
    echo "异常/错误: " . get_class($t) . ': ' . $t->getMessage() . "\n";
    echo $t->getFile() . ':' . $t->getLine() . "\n";
    exit(3);
}
