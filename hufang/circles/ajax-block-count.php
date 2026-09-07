<?php
require_once '../../config/database.php';
require_once '../includes/auth.php';
require_once '../../classes/Block.php';

header('Content-Type: application/json; charset=utf-8');

$userId = assertLogin();

$cityName = trim($_POST['city'] ?? ($_GET['city'] ?? ''));
if ($cityName === '') {
    echo json_encode(['success' => false, 'msg' => '请先选择所在城市']);
    exit;
}

// 查找城市 ID：支持精确匹配与去除“市”后缀匹配
$stmt = $pdo->prepare("SELECT id FROM cities WHERE name = ? LIMIT 1");
$stmt->execute([$cityName]);
$row = $stmt->fetch();
if ($row) {
    $cityId = (int)$row['id'];
} else {
    $short = preg_replace('/市$/u', '', $cityName);
    if ($short !== $cityName) {
        $stmt = $pdo->prepare("SELECT id FROM cities WHERE name = ? LIMIT 1");
        $stmt->execute([$short]);
        $row = $stmt->fetch();
        $cityId = $row ? (int)$row['id'] : 0;
    } else {
        $cityId = 0;
    }
}

if (!$cityId) {
    echo json_encode(['success' => false, 'msg' => '城市不存在：' . $cityName]);
    exit;
}

$block = new Block($pdo);
$count = $block->countUserBlocksByCity($userId, $cityId);
echo json_encode(['success' => true, 'count' => $count]);
