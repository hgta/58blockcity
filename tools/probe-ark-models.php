<?php
/**
 * 方舟渠道可用模型探测（CLI）
 * change: help-content-admin (task 9.x 诊断/选型工具)
 *
 * 用法：
 *   php tools/probe-ark-models.php                  # 用后台「聊天」渠道的端点与 Key 逐个试候选模型
 *   php tools/probe-ark-models.php --model=a,b,c    # 指定候选模型名
 *   php tools/probe-ark-models.php --timeout=20     # 单模型超时秒数（默认 15）
 *
 * 目的：找出"能用且快"的模型，替换掉带深度思考、动辄 90s+ 的模型
 * 说明：非流式调用（stream:false），与后台小任务一致；耗时含模型思考时间，直接可比
 */

if (is_file(__DIR__ . '/../config/database.php')) require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/SecureCrypto.php';

if (!isset($pdo)) {
    $h = getenv('DB_HOST') ?: 'localhost';
    $n = getenv('DB_NAME'); $u = getenv('DB_USER'); $p = getenv('DB_PASS') ?: '';
    if (!$n || !$u) { fwrite(STDERR, "缺少数据库配置。\n"); exit(1); }
    $pdo = new PDO("mysql:host={$h};dbname={$n};charset=utf8mb4", $u, $p, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}

// ---- 取渠道凭据：优先聊天渠道里 endpoint 指向方舟的那条；否则用嵌入渠道 ----
$prov = null;
foreach ($pdo->query("SELECT * FROM ai_providers WHERE is_enabled = 1 ORDER BY is_default DESC, sort_order, id") as $r) {
    if (stripos($r['endpoint'], 'ark.cn-beijing.volces.com') !== false) { $prov = $r; break; }
}
if (!$prov) {
    fwrite(STDERR, "没找到指向方舟（ark.cn-beijing.volces.com）的启用渠道，请先在后台配置。\n");
    exit(1);
}
$endpoint = rtrim($prov['endpoint'], '/');
$key = SecureCrypto::decrypt($prov['api_key_cipher']);
echo "渠道: {$prov['name']}  (#{$prov['id']}, purpose={$prov['purpose']})\n";
echo "端点: {$endpoint}\n\n";

// ---- 参数 ----
$opts = getopt('', ['model::', 'timeout::']);
$timeout = max(5, (int)($opts['timeout'] ?? 12)); // 单个模型最多等 12s，超时即视为"慢，不选"

// 候选模型：按控制台「模型列表」里的模型名（API Model Name，通常与控制台展示名一致或为其连字符形式）
// 注意：方舟控制台每个模型卡片上的「Model Name」才是准的，可点复制后用 --model= 指定
$candidates = [
    'ark-code-latest',        // 当前在用的路由别名（实测 ~92s）
    'auto',                   // 智能调度（效果+速度）
    'doubao-seed-2-1-lite',   // 描述：1M 超长上下文，通常较快
    'doubao-seed-2-0-mini',   // 描述：面向低时延、高并发 ← 最可能是快的
    'doubao-seed-2-1-turbo',  // 描述：效果与成本均衡
    'doubao-seed-2-1-pro',
    'deepseek-v4-flash',
    'deepseek-v4-pro',
    'doubao-seed-evolving',
    'glm-5-3',
    'kimi-k2-7-code',
    'minimax-m3',
];
if (!empty($opts['model'])) {
    $candidates = array_filter(array_map('trim', explode(',', $opts['model'])));
}

echo "=== 逐个测试（非流式，单模型超时 {$timeout}s）===\n";
printf("%-34s %-6s %8s  %s\n", 'model', 'ok', 'ms', '说明');
$works = [];
foreach ($candidates as $m) {
    $payload = json_encode([
        'model' => $m,
        'messages' => [['role' => 'user', 'content' => '用30字说明什么是区块']],
        'stream' => false,
    ], JSON_UNESCAPED_UNICODE);
    $ch = curl_init($endpoint . '/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $key],
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_RETURNTRANSFER => true,
    ]);
    $t0 = microtime(true);
    $body = curl_exec($ch);
    $errno = curl_errno($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $ms = (int)round((microtime(true) - $t0) * 1000);

    $ok = ($errno === 0 && $http === 200);
    $note = '';
    if (!$ok) {
        $note = $errno !== 0 ? ('网络错误(' . $errno . ')') : ('HTTP ' . $http . ' ' . mb_substr(preg_replace('/\s+/', ' ', (string)$body), 0, 80));
    } else {
        $obj = json_decode((string)$body, true);
        $content = $obj['choices'][0]['message']['content'] ?? '';
        $note = '推理字段=' . (isset($obj['choices'][0]['message']['reasoning_content']) ? '有(深度思考)' : '无')
              . ' | ' . mb_substr($content, 0, 30);
        $works[$m] = $ms;
    }
    printf("%-34s %-6s %8d  %s\n", $m, $ok ? 'YES' : 'no', $ms, $note);
}

echo "\n=== 结论 ===\n";
if (!$works) {
    echo "没有可用模型：请到方舟控制台查看本套餐的模型名，然后用 --model=名1,名2 再试。\n";
    exit(2);
}
asort($works);
foreach ($works as $m => $ms) {
    printf("  %-34s %5.1fs\n", $m, $ms / 1000);
}
$best = array_key_first($works);
echo "\n推荐：{$best}（最快可用）\n";
echo "把它填到「自定义（OpenAI 兼容）」渠道的模型里；后台小任务也可在 system_settings.ai_admin_task_model 单独指定。\n";
