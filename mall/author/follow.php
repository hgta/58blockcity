<?php
// 作者关注 / 取消关注（AJAX）
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../classes/Author.php';

// 会话已由上面的 includes/auth.php 初始化（含跨子站 cookie domain）
header('Content-Type: application/json; charset=utf-8');

$userId = $_SESSION['user_id'] ?? 0;
if (!$userId) {
    echo json_encode(['error' => 'unauthorized']);
    exit;
}

$authorId = intval($_POST['author_id'] ?? 0);
if (!$authorId) {
    echo json_encode(['error' => 'invalid']);
    exit;
}

$author = new Author($pdo);
try {
    $action = $author->follow($authorId, $userId);
    $info = $author->getById($authorId);
    echo json_encode([
        'action'        => $action,
        'follower_count' => intval($info['follower_count'] ?? 0),
        'ok'            => true,
    ]);
} catch (Exception $e) {
    echo json_encode(['error' => 'exception', 'msg' => $e->getMessage()]);
}
