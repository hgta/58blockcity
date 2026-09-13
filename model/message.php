<?php
/**
 * 模特留言板接口（AJAX，JSON）
 *   action=like   点赞（登录可选，公开留言允许游客点赞）
 *   action=delete 软删除（需登录，且为留言本人 / 模特本人 / 管理员）
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_once APP_ROOT . '/classes/ModelMessage.php';

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

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    echo json_encode(['error' => 'method']);
    exit;
}

$msgObj = new ModelMessage($pdo);
$userId = (int)($_SESSION['user_id'] ?? 0);
$action = $_POST['action'] ?? '';
$msgId  = intval($_POST['id'] ?? 0);

if ($msgId <= 0) {
    echo json_encode(['error' => 'invalid']);
    exit;
}

$message = $msgObj->getById($msgId);
if (!$message || $message['status'] !== 'active') {
    echo json_encode(['error' => 'not_found']);
    exit;
}

if ($action === 'like') {
    $msgObj->like($msgId);
    $fresh = $msgObj->getById($msgId);
    echo json_encode(['ok' => true, 'like_count' => intval($fresh['like_count'] ?? 0)]);
    exit;
}

if ($action === 'delete') {
    if (!$userId) {
        echo json_encode(['error' => 'unauthorized']);
        exit;
    }

    // 模特归属用户（模特本人可删自己留言板下的内容）
    $stmt = $pdo->prepare("SELECT user_id FROM models WHERE id = ?");
    $stmt->execute([intval($message['model_id'])]);
    $modelOwnerId = (int)$stmt->fetchColumn();

    // 管理员可删任何留言
    $isAdmin = false;
    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $isAdmin = ($stmt->fetchColumn() === 'admin');

    // 权限判定：留言作者 / 该模特归属用户 / 管理员
    $isOwner = (intval($message['user_id']) === $userId);
    $isModelOwner = ($modelOwnerId > 0 && $modelOwnerId === $userId);
    if (!$isOwner && !$isModelOwner && !$isAdmin) {
        echo json_encode(['error' => 'forbidden']);
        exit;
    }

    $ok = $msgObj->softDelete($msgId, $userId, $modelOwnerId, $isAdmin);
    echo json_encode($ok ? ['ok' => true] : ['error' => 'fail']);
    exit;
}

echo json_encode(['error' => 'unknown_action']);
