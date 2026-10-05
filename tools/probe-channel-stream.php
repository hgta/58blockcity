<?php
/**
 * 渠道流式探针：测「首字延迟」（前台小帮的体验关键指标）
 * change: help-content-admin (task 10.x 诊断工具)
 *
 * 用法：
 *   php tools/probe-channel-stream.php                     # 测所有启用的聊天渠道，默认 1500 字输入
 *   php tools/probe-channel-stream.php --len=3000          # 指定输入字数（模拟带知识库的长提示词）
 *   php tools/probe-channel-stream.php --channel=1         # 只测某个渠道
 *   php tools/probe-channel-stream.php --chatlike          # 用近似前台小帮的 system+知识片段结构
 *
 * 判定：首字延迟 < ai_chat_first_byte_timeout（默认 8s）→ 该渠道可作前台默认；
 *       超过则会被前台判为失败并切走（代码见 AiProvider::$firstByteTimeout）
 */

if (is_file(__DIR__ . '/../config/database.php')) require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/SecureCrypto.php';
require_once __DIR__ . '/../classes/AiProvider.php';

if (!isset($pdo)) {
    $h = getenv('DB_HOST') ?: 'localhost';
    $n = getenv('DB_NAME'); $u = getenv('DB_USER'); $p = getenv('DB_PASS') ?: '';
    if (!$n || !$u) { fwrite(STDERR, "缺少数据库配置。\n"); exit(1); }
    $pdo = new PDO("mysql:host={$h};dbname={$n};charset=utf8mb4", $u, $p, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}

$opts = getopt('', ['len::', 'channel::', 'chatlike']);
$len = max(20, (int)($opts['len'] ?? 1500));
$chatlike = isset($opts['chatlike']);

// 首字节保护阈值（前台判定线）
$limit = AiProvider::$firstByteTimeout;
try {
    $v = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key='ai_chat_first_byte_timeout'")->fetchColumn();
    if ($v !== false && $v !== null && $v !== '') $limit = (float)$v;
} catch (Exception $ex) {}
echo "前台首字节保护阈值: {$limit}s（首字晚于此值会被判失败并切换）\n\n";

// 构造输入
$pad = str_repeat('区块链城市的区块可以认领、交易与合并，居民数与人气值影响城市排名。', (int)ceil($len / 33));
$pad = mb_substr($pad, 0, $len);
if ($chatlike) {
    $messages = [
        ['role' => 'system', 'content' => "你是\"58区块城市\"平台的官方帮助助手，名字叫\"小帮\"。优先依据官方资料回答。\n"
            . "=== 官方帮助资料 ===\n【示例文章】" . $pad],
        ['role' => 'user', 'content' => '认领区块要钱吗？'],
    ];
} else {
    $messages = [
        ['role' => 'system', 'content' => '你是帮助中心编辑，只输出正文，不要解释。'],
        ['role' => 'user', 'content' => $pad],
    ];
}
echo "输入规模: {$len} 字" . ($chatlike ? '（近似前台小帮结构）' : '') . "\n\n";

$list = AiProvider::routeList($pdo);
if (!empty($opts['channel'])) {
    $want = array_map('intval', explode(',', $opts['channel']));
    $list = array_values(array_filter($list, function ($p) use ($want) { return in_array($p->id(), $want, true); }));
}

printf("%-24s %-6s %10s %10s  %s\n", '渠道', 'ok', 'first', 'total', '说明');
$verdict = [];
foreach ($list as $p) {
    $firstMs = null;
    $chars = 0;
    $t0 = microtime(true);
    $old = AiProvider::$firstByteTimeout;
    AiProvider::$firstByteTimeout = 0; // 探针不启用保护，先测真实首字延迟
    $res = $p->chatStream($messages, function ($delta) use (&$firstMs, $t0) {
        if ($firstMs === null && $delta !== '') $firstMs = (int)round((microtime(true) - $t0) * 1000);
    });
    AiProvider::$firstByteTimeout = $old;
    $totalMs = (int)round((microtime(true) - $t0) * 1000);
    $chars = mb_strlen($res['answer']);

    $verdict[$p->name()] = $firstMs;
    $note = $res['ok'] ? ('字符数=' . $chars) : ('失败: ' . mb_substr($res['error'], 0, 60));
    printf("%-24s %-6s %9s %9dms  %s\n",
        $p->name(), $res['ok'] ? 'YES' : 'no',
        $firstMs !== null ? ($firstMs . 'ms') : '-', $totalMs, $note);
}

echo "\n=== 判定（阈值 {$limit}s）===\n";
foreach ($verdict as $name => $ms) {
    if ($ms === null) { echo "  {$name}: 拿不到首字，不能作前台默认渠道\n"; continue; }
    $ok = ($ms / 1000) < $limit;
    printf("  %-24s 首字 %.1fs → %s\n", $name, $ms / 1000, $ok ? '✅ 可作前台默认' : '❌ 会被前台判失败并切走');
}
