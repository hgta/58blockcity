<?php
// 模特关注 / 取消关注（AJAX）
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../classes/Model.php';

if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');

$userId = $_SESSION['user_id'] ?? 0;
if (!$userId) {
    echo json_encode(['error' => 'unauthorized']);
    exit;
}

$modelId = intval($_POST['model_id'] ?? 0);
if (!$modelId) {
    echo json_encode(['error' => 'invalid']);
    exit;
}

$model = new Model($pdo);
try {
    $action = $model->follow($modelId, $userId);
    $info = $model->getById($modelId);
    echo json_encode([
        'action'        => $action,
        'follower_count' => intval($info['follower_count'] ?? 0),
        'ok'            => true,
    ]);
} catch (Exception $e) {
    echo json_encode(['error' => 'exception', 'msg' => $e->getMessage()]);
}
