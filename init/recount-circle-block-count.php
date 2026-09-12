<?php
/**
 * 重算所有互访圈的 block_count（实际拥有区块数口径：多块合并按 1 块计）
 *
 * 背景：互访圈的“城市区块数”原先按 block 子站的单块认领数统计，
 *       会把多块合并的组拆开逐块计数（投票数口径）；现改为按实际区块数统计。
 *       本脚本用于把历史数据一次性修正到新口径。
 *
 * 执行方式:
 *   php init/recount-circle-block-count.php            # 实际写入
 *   php init/recount-circle-block-count.php --dry-run  # 仅预览，不写入
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/Block.php';

$dryRun = in_array('--dry-run', $argv ?? [], true);

echo "=== 重算互访圈区块数（实际区块数口径）===\n";
echo $dryRun ? "模式: 预览（不写入）\n\n" : "模式: 写入\n\n";

// 城市名 -> 城市 ID 映射（兼容“市”后缀差异）
$cityMap = [];
foreach ($pdo->query("SELECT id, name FROM cities")->fetchAll(PDO::FETCH_ASSOC) as $c) {
    $name = trim($c['name']);
    $cityMap[$name] = (int)$c['id'];
    $short = preg_replace('/市$/u', '', $name);
    if ($short !== $name && !isset($cityMap[$short])) {
        $cityMap[$short] = (int)$c['id'];
    }
}

$block = new Block($pdo);

$circles = $pdo->query("SELECT id, user_id, city, block_count FROM circles ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
echo "共 " . count($circles) . " 个互访圈\n\n";

$updated = 0;
$unchanged = 0;
$unknownCity = 0;

$updateStmt = $pdo->prepare("UPDATE circles SET block_count = ? WHERE id = ?");

foreach ($circles as $circle) {
    $cityName = trim((string)$circle['city']);
    $cityId = $cityMap[$cityName] ?? ($cityMap[preg_replace('/市$/u', '', $cityName)] ?? 0);

    if (!$cityId) {
        echo "  - #{$circle['id']} 城市无法匹配（{$cityName}），跳过\n";
        $unknownCity++;
        continue;
    }

    $newCount = $block->countUserActualBlocksByCity((int)$circle['user_id'], $cityId);
    $oldCount = (int)$circle['block_count'];

    if ($newCount === $oldCount) {
        $unchanged++;
        continue;
    }

    if (!$dryRun) {
        $updateStmt->execute([$newCount, (int)$circle['id']]);
    }
    echo "  - #{$circle['id']} {$cityName}: {$oldCount} -> {$newCount}\n";
    $updated++;
}

echo "\n结果: 需更新 {$updated} 个，未变化 {$unchanged} 个，城市未匹配 {$unknownCity} 个\n";
echo "重算完成!\n";
