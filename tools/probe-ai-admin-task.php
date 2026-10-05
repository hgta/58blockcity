<?php
/**
 * 后台 AI 小任务链路探针（CLI，免后台鉴权）
 * change: help-content-admin (task 9.x 诊断工具)
 *
 * 用法：
 *   php tools/probe-ai-admin-task.php            # 用默认提示词跑一次
 *   php tools/probe-ai-admin-task.php "你的问题"  # 自定义提示词
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

$q = $argv[1] ?? '用30字说明什么是区块';
echo "\n=== 3. 执行（提示词：{$q}）===\n";
$t0 = microtime(true);
try {
    $res = AiAdminTask::run($pdo, [
        ['role' => 'system', 'content' => '你是帮助中心编辑，只输出正文，不要解释。'],
        ['role' => 'user', 'content' => $q],
    ]);
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
