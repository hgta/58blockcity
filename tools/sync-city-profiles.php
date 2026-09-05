<?php
/**
 * 城市资料 JSON → city_profiles 入库同步器
 *
 * 用法: php tools/sync-city-profiles.php [--dry-run] [--limit=N]
 *
 * - 读取 data/city-profiles/*.json（crawl-city-profiles.php 产出）
 * - 按文件名 pinyin 匹配 cities 行（JSON 内 city_id 优先且交叉校验）
 * - upsert by city_id（INSERT ... ON DUPLICATE KEY UPDATE），幂等可重跑
 * - status 自动判定：admin_area / population / gdp 任一非空 → 1，否则 0
 * - 找不到城市的 pinyin 记录跳过并在结尾给出清单
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

$dryRun = false;
$limit = 0;
foreach (array_slice($argv, 1) as $a) {
    if ($a === '--dry-run') {
        $dryRun = true;
    } elseif (preg_match('/^--limit=(\d+)$/', $a, $m)) {
        $limit = (int)$m[1];
    } else {
        fwrite(STDERR, "未知参数: {$a}（支持 --dry-run / --limit=N）\n");
        exit(2);
    }
}

$dir = __DIR__ . '/../data/city-profiles';
if (!is_dir($dir)) {
    fwrite(STDERR, "目录不存在（先运行 crawl-city-profiles.php）: {$dir}\n");
    exit(1);
}
$files = glob($dir . '/*.json');
if (!$files) {
    echo "无 JSON 可同步: {$dir}\n";
    exit(0);
}
if ($limit > 0) {
    $files = array_slice($files, 0, $limit);
}
echo sprintf("[同步] %d 个 JSON | dry-run=%s\n", count($files), $dryRun ? 'Y' : 'N');

// pinyin → cities 行 映射
$map = [];
foreach ($pdo->query("SELECT id, name, pinyin FROM cities WHERE status = 'active'")->fetchAll() as $r) {
    $map[$r['pinyin']] = $r;
}

$sql = "INSERT INTO city_profiles
        (city_id, admin_area, population, gdp, gdp_per_capita, urbanization_rate,
         universities, feature_tags, slogan, position, landmarks, food, potential,
         districts, intro, data_year, status)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        ON DUPLICATE KEY UPDATE
         admin_area = VALUES(admin_area),
         population = VALUES(population),
         gdp = VALUES(gdp),
         gdp_per_capita = VALUES(gdp_per_capita),
         urbanization_rate = VALUES(urbanization_rate),
         universities = VALUES(universities),
         feature_tags = VALUES(feature_tags),
         slogan = VALUES(slogan),
         position = VALUES(position),
         landmarks = VALUES(landmarks),
         food = VALUES(food),
         potential = VALUES(potential),
         districts = VALUES(districts),
         intro = VALUES(intro),
         data_year = VALUES(data_year),
         status = VALUES(status),
         updated_at = CURRENT_TIMESTAMP";
$stmt = $dryRun ? null : $pdo->prepare($sql);

$ok = 0; $skipped = 0; $missing = [];
foreach ($files as $f) {
    $pinyin = basename($f, '.json');
    $j = json_decode(file_get_contents($f), true);
    if (!is_array($j)) {
        $skipped++;
        echo "  [skip] {$pinyin}: JSON 解析失败\n";
        continue;
    }

    // 取城市：JSON 内 city_id 交叉校验（防文件名与库不同步）
    $row = null;
    if (!empty($j['city_id']) && isset($map[$pinyin])) {
        $row = $map[$pinyin];
        if ((int)$row['id'] !== (int)$j['city_id']) {
            echo "  [warn] {$pinyin}: JSON city_id({$j['city_id']}) 与库({$row['id']})不一致，以库为准\n";
        }
    } elseif (!empty($j['city_id'])) {
        $tmp = $pdo->prepare("SELECT id, name, pinyin FROM cities WHERE id = ? LIMIT 1");
        $tmp->execute([(int)$j['city_id']]);
        $row = $tmp->fetch() ?: null;
    } elseif (isset($map[$pinyin])) {
        $row = $map[$pinyin];
    }

    if (!$row) {
        $missing[] = $pinyin;
        $skipped++;
        echo "  [skip] {$pinyin}: cities 无对应城市\n";
        continue;
    }
    $cityId = (int)$row['id'];

    $hasData = (trim((string)($j['admin_area'] ?? '')) !== ''
        || trim((string)($j['population'] ?? '')) !== ''
        || trim((string)($j['gdp'] ?? '')) !== '');
    $status = $hasData ? 1 : 0;

    $vals = [
        $cityId,
        trim((string)($j['admin_area'] ?? '')),
        trim((string)($j['population'] ?? '')),
        trim((string)($j['gdp'] ?? '')),
        trim((string)($j['gdp_per_capita'] ?? '')),
        trim((string)($j['urbanization_rate'] ?? '')),
        trim((string)($j['universities'] ?? '')),
        trim((string)($j['feature_tags'] ?? '')),
        trim((string)($j['slogan'] ?? '')),
        trim((string)($j['position'] ?? '')),
        trim((string)($j['landmarks'] ?? '')),
        trim((string)($j['food'] ?? '')),
        trim((string)($j['potential'] ?? '')),
        trim((string)($j['districts'] ?? '')),
        trim((string)($j['intro'] ?? '')),
        trim((string)($j['data_year'] ?? '')),
        $status,
    ];

    if ($dryRun) {
        echo "  [dry] {$pinyin}(#{$cityId}) status={$status}\n";
        $ok++;
        continue;
    }
    $stmt->execute($vals);
    $ok++;
    echo "  [ok] {$pinyin}(#{$cityId}) status={$status}\n";
}

echo "\n=== 完成 ===\n";
echo "  已同步(或 dry-run 预览): {$ok}\n";
echo "  跳过: {$skipped}\n";
echo ($dryRun ? "(dry-run 未写库)\n" : '');
if ($missing) {
    echo "  未匹配城市清单(" . count($missing) . "): " . implode(', ', $missing) . "\n";
}
