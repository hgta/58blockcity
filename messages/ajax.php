<?php
session_start();
require_once '../config/database.php';
require_once '../classes/Message.php';

header('Content-Type: application/json');

$userId = $_SESSION['user_id'] ?? 0;
if (!$userId) { echo json_encode(['error' => '请先登录']); exit; }

$msg = new Message($pdo);
$action = $_GET['action'] ?? '';

if ($action === 'send' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $toId = intval($_POST['to_user_id'] ?? 0);
    $message = trim($_POST['message'] ?? '');
    if ($toId <= 0 || $toId == $userId || empty($message)) {
        echo json_encode(['success' => false, 'error' => '参数无效']); exit;
    }
    $result = $msg->send($userId, $toId, $message);
    echo json_encode(['success' => (bool)$result]);
    exit;
}

if ($action === 'recent') {
    $withId = intval($_GET['with'] ?? 0);
    if ($withId <= 0) { echo json_encode([]); exit; }
    $messages = $msg->getRecentMessages($userId, $withId, 6);
    echo json_encode($messages);
    exit;
}

if ($action === 'conversation') {
    $withId = intval($_GET['with'] ?? 0);
    if ($withId <= 0) { echo json_encode([]); exit; }
    $msg->markRead($userId, $withId);
    $messages = $msg->getMessages($userId, $withId, 1, 50);
    echo json_encode($messages);
    exit;
}

if ($action === 'mark_read') {
    $fromId = intval($_GET['from'] ?? 0);
    $msg->markRead($userId, $fromId);
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'unread') {
    echo json_encode(['count' => $msg->getUnreadCount($userId)]);
    exit;
}

echo json_encode(['error' => '未知操作']);
