<?php
/**
 * 区块热力图生成接口
 * 输出 PNG 格式的区块状态热力图
 * GET 参数: city_id (int), zone (A-H|Z)
 */

require_once '../../config/database.php';

header('Content-Type: image/png');
header('Cache-Control: public, max-age=3600');

$cityId = isset($_GET['city_id']) ? (int)$_GET['city_id'] : 0;
$zone = isset($_GET['zone']) ? strtoupper($_GET['zone']) : '';

if ($cityId <= 0 || !preg_match('/^[A-HZ]$/', $zone)) {
    // 返回空白图
    $img = imagecreatetruecolor(101, 99);
    $bg = imagecolorallocate($img, 240, 240, 240);
    imagefill($img, 0, 0, $bg);
    imagepng($img);
    imagedestroy($img);
    exit;
}

// 获取该城市该区域 blocks 的最后更新时间，用于缓存键
$tsStmt = $pdo->prepare("SELECT MAX(updated_at) as ts FROM blocks WHERE city_id = ? AND zone = ?");
$tsStmt->execute([$cityId, $zone]);
$tsRow = $tsStmt->fetch(PDO::FETCH_ASSOC);
$cacheTs = $tsRow && $tsRow['ts'] ? strtotime($tsRow['ts']) : time();

// 缓存目录
$cacheDir = __DIR__ . '/../cache/heatmap';
if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0755, true);
}
$cacheFile = $cacheDir . '/heatmap_' . $cityId . '_' . $zone . '_' . $cacheTs . '.png';

// 若缓存存在直接输出
if (file_exists($cacheFile)) {
    readfile($cacheFile);
    exit;
}

// 查询区块状态
$blockStmt = $pdo->prepare("SELECT block_number, status FROM blocks WHERE city_id = ? AND zone = ?");
$blockStmt->execute([$cityId, $zone]);
$blocks = [];
while ($row = $blockStmt->fetch(PDO::FETCH_ASSOC)) {
    $blocks[$row['block_number']] = $row['status'];
}

// 查询合并区块
$mergedStmt = $pdo->prepare("SELECT merged_blocks FROM merged_blocks WHERE city_id = ? AND zone = ?");
$mergedStmt->execute([$cityId, $zone]);
$merged = [];
while ($row = $mergedStmt->fetch(PDO::FETCH_ASSOC)) {
    $nums = explode(',', $row['merged_blocks']);
    foreach ($nums as $n) {
        $merged[trim($n)] = true;
    }
}

// 颜色定义
$colors = [
    'avail'       => [232, 245, 232], // #e8f5e8 可认领
    'sold'        => [255, 107, 0],   // #ff6b00 已认领
    'merged'      => [25, 118, 210],  // #1976d2 合并区块
    'cross_avail' => [165, 214, 167], // #a5d6a7 跨区边界可认领
    'cross_sold'  => [255, 152, 0],   // #ff9800 跨区边界已认领
];

// 创建图像 101 列 × 99 行
$w = 101;
$h = 99;
$img = imagecreatetruecolor($w, $h);

// 分配颜色
$gdColors = [];
foreach ($colors as $key => $rgb) {
    $gdColors[$key] = imagecolorallocate($img, $rgb[0], $rgb[1], $rgb[2]);
}

// 填充像素
for ($row = 1; $row <= $h; $row++) {
    for ($col = 1; $col <= $w; $col++) {
        $bn = str_pad($col, 2, '0', STR_PAD_LEFT) . str_pad($row, 2, '0', STR_PAD_LEFT);
        $isBoundary = ($col == $w || $row == $h);

        if (isset($merged[$bn])) {
            $key = 'merged';
        } elseif (isset($blocks[$bn]) && ($blocks[$bn] === 'sold' || $blocks[$bn] === 'reserved')) {
            $key = $isBoundary ? 'cross_sold' : 'sold';
        } else {
            $key = $isBoundary ? 'cross_avail' : 'avail';
        }

        imagesetpixel($img, $col - 1, $row - 1, $gdColors[$key]);
    }
}

// 保存缓存并输出
imagepng($img, $cacheFile);
readfile($cacheFile);
imagedestroy($img);
exit;
