<?php
/**
 * 种子脚本：为所有城市补齐 Z 区特殊区块
 *
 * Z 区除了常规网格 9701-9999（3列×99行）外，还有两类“特殊区块”：
 *   - 顶层（row0, col1-99）：0100, 0200, ... 9900  → 99 个
 *   - 左侧（col0, row1-99）：0001, 0002, ... 0099  → 99 个
 * 合计 198 个，价格取自 config/block_prices.php。
 *
 * 补齐后每个城市区块总数 = A-H(9504) + Z常规(297) + Z特殊(198) = 9999。
 *
 * 用法（在已部署的环境中，使用真实数据库连接后执行）：
 *   php init/seed_z_special.php
 *
 * 脚本幂等：使用 INSERT IGNORE，重复执行不会报错也不会产生重复记录。
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/block_prices.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    fwrite(STDERR, "错误：未获取到有效的数据库连接 \$pdo（请检查 config/database.php）。\n");
    exit(1);
}

// 取出所有城市
$stmt = $pdo->query("SELECT id, name, pinyin FROM cities ORDER BY id");
$cities = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($cities)) {
    fwrite(STDERR, "未找到任何城市，无需处理。\n");
    exit(0);
}

// 构造 198 个特殊区块号
$specialNumbers = [];
for ($i = 1; $i <= 99; $i++) {
    $specialNumbers[] = str_pad($i * 100, 4, '0', STR_PAD_LEFT); // 0100 ... 9900
}
for ($i = 1; $i <= 99; $i++) {
    $specialNumbers[] = str_pad($i, 4, '0', STR_PAD_LEFT);        // 0001 ... 0099
}

$totalInserted = 0;
$totalSkipped  = 0;

$pdo->beginTransaction();
try {
    $insertSql = "INSERT IGNORE INTO blocks
        (city_id, zone, block_number, price, owner_id, status, created_at, updated_at)
        VALUES (?, 'Z', ?, ?, NULL, 'available', NOW(), NOW())";
    $insertStmt = $pdo->prepare($insertSql);

    foreach ($cities as $city) {
        $inserted = 0;
        foreach ($specialNumbers as $bn) {
            $price = calculateBlockPriceNew('Z', $bn);
            $insertStmt->execute([$city['id'], $bn, $price]);
            $inserted += $insertStmt->rowCount();
        }
        $skipped = count($specialNumbers) - $inserted;
        $totalInserted += $inserted;
        $totalSkipped  += $skipped;
        echo sprintf(
            "城市 %s(%s) [#%d]：新增 %d 个特殊区块，已存在跳过 %d 个\n",
            $city['name'], $city['pinyin'], $city['id'], $inserted, $skipped
        );
    }
    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    fwrite(STDERR, "执行失败，已回滚：" . $e->getMessage() . "\n");
    exit(1);
}

echo "\n完成。共新增 {$totalInserted} 个 Z 区特殊区块，跳过（已存在）{$totalSkipped} 个。\n";
