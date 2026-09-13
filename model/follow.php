<?php
/**
 * 模特关注 / 取消关注接口（AJAX，JSON）
 */
require_once __DIR__ . '/includes/bootstrap.php';

// 允许商城站（mall.58.tl）等兄弟子站跨域调用关注接口（凭据型请求需精确 Origin）
$allowOrigins = [
    'https://mall.58.tl',
    'https://model.58.tl',
    'https://www.58.tl',
];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowOrigins, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Credentials: true');
    header('Vary: Origin');
}
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: X-Requested-With, Content-Type');
    http_response_code(204);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

$userId = (int)($_SESSION['user_id'] ?? 0);
if (!$userId) {
    echo json_encode(['error' => 'unauthorized']);
    exit;
}

$modelId = intval($_POST['model_id'] ?? 0);
if (!$modelId) {
    echo json_encode(['error' => 'invalid']);
    exit;
}

try {
    $action = $modelObj->follow($modelId, $userId);
    $info   = $modelObj->getById($modelId);
    echo json_encode([
        'action'         => $action,
        'follower_count' => intval($info['follower_count'] ?? 0),
        'ok'             => true,
    ]);
} catch (Exception $e) {
    echo json_encode(['error' => 'exception', 'msg' => $e->getMessage()]);
}
