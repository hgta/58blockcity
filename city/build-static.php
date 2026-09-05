<?php
/**
 * 城市门户静态缓存重生成（CLI / cron）
 *
 * 用法:
 *   php city/build-static.php all          # 全量重生成（建议每日 cron）
 *   php city/build-static.php hot          # 仅 is_hot=1 热门城市
 *   php city/build-static.php beijing      # 单城
 *
 * 复用 city.php 同一渲染管线（city_portal_build_ctx + city_portal_render），
 * 结果原子写入 city/cache/{pinyin}.html；单城异常不中断。
 */

require_once __DIR__ . '/../config/database.php';

if (!isset($pdo)) {
    $h = getenv('DB_HOST') ?: 'localhost';
    $n = getenv('DB_NAME');
    $u = getenv('DB_USER');
    $p = getenv('DB_PASS') ?: '';
    if (!$n || !$u) {
        fwrite(STDERR, "缺少数据库配置：请配置 config/database.php 或设置 DB_* 环境变量。\n");
        exit(1);
    }
    $pdo = new PDO("mysql:host={$h};dbname={$n};charset=utf8mb4", $u, $p, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
    ]);
}

require_once __DIR__ . '/../classes/City.php';
require_once __DIR__ . '/../includes/city-portal-render.php';

$mode = $argv[1] ?? 'all';
$mode = strtolower(trim($mode));

$cacheDir = __DIR__ . '/cache';
if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0775, true);
}
if (!is_dir($cacheDir) || !is_writable($cacheDir)) {
    fwrite(STDERR, "缓存目录不可写: {$cacheDir}\n");
    exit(1);
}

// 选择目标城市
$cityObj = new City($pdo);
$rows = [];
if ($mode === 'all') {
    $rows = $cityObj->getAllCities();
} elseif ($mode === 'hot') {
    $rows = $cityObj->getHotCities();
} elseif (preg_match('/^[a-z]+$/', $mode)) {
    $city = $cityObj->getCityByPinyin($mode);
    if (!$city) {
        fwrite(STDERR, "未找到城市: {$mode}\n");
        exit(1);
    }
    $rows = [$city];
} else {
    fwrite(STDERR, "用法: php city/build-static.php [all|hot|pinyin]\n");
    exit(2);
}

if (!$rows) {
    echo "无目标城市。\n";
    exit(0);
}
echo sprintf("[重生成] 模式=%s | 城市 %d 个 → %s\n", $mode, count($rows), $cacheDir);

$ok = 0; $err = 0;
foreach ($rows as $city) {
    $pinyin = trim((string)($city['pinyin'] ?? ''));
    if (!preg_match('/^[a-z]+$/', $pinyin)) {
        $err++;
        echo "  [err] 非法 pinyin 跳过: " . ($city['name'] ?? '?') . "\n";
        continue;
    }
    try {
        $ctx  = city_portal_build_ctx($pdo, $city, $pinyin);
        $html = city_portal_render($ctx);
        if ($html === '' || stripos($html, '<html') === false) {
            throw new RuntimeException('渲染结果为空或非完整 HTML');
        }
        $tmp = $cacheDir . '/' . $pinyin . '.html.' . getmypid() . '.tmp';
        if (file_put_contents($tmp, $html, LOCK_EX) === false) {
            throw new RuntimeException('临时文件写入失败');
        }
        if (!@rename($tmp, $cacheDir . '/' . $pinyin . '.html')) {
            @unlink($tmp);
            throw new RuntimeException('rename 失败');
        }
        $ok++;
        echo "  [ok] {$pinyin} " . number_format(strlen($html) / 1024, 1) . " KB\n";
    } catch (\Throwable $e) {
        $err++;
        echo "  [err] {$pinyin}: " . $e->getMessage() . "\n";
        // 单城异常不中断
    }
}

echo "\n=== 完成 ===\n";
echo "  成功: {$ok}  失败: {$err}\n";
echo "  建议 cron：每日一次 php " . __FILE__ . " all\n";
exit($err ? 1 : 0);
