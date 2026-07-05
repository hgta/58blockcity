<?php
/**
 * 站内信 AJAX 核心（各子站共享）
 */
if (!defined('MSG_ROOT')) die('请先定义 MSG_ROOT');
session_start();
require_once MSG_ROOT . '/config/database.php';
require_once MSG_ROOT . '/classes/Message.php';

header('Content-Type: application/json');
$userId = $_SESSION['user_id'] ?? 0;
if (!$userId) { echo json_encode(['error' => '请先登录']); exit; }

$msg = new Message($pdo);
$action = $_GET['action'] ?? '';

if ($action === 'send' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $toId = intval($_POST['to_user_id'] ?? 0);
    $message = trim($_POST['message'] ?? '');
    if ($toId <= 0 || $toId == $userId || empty($message)) {
        echo json_encode(['success' => false]); exit;
    }
    echo json_encode(['success' => (bool)$msg->send($userId, $toId, $message)]);
    exit;
}
if ($action === 'recent') {
    $withId = intval($_GET['with'] ?? 0);
    echo json_encode($withId > 0 ? $msg->getRecentMessages($userId, $withId, 6) : []);
    exit;
}
if ($action === 'conversation') {
    $withId = intval($_GET['with'] ?? 0);
    if ($withId > 0) { $msg->markRead($userId, $withId); echo json_encode($msg->getMessages($userId, $withId, 1, 50)); }
    else echo json_encode([]);
    exit;
}
if ($action === 'mark_read') {
    $msg->markRead($userId, intval($_GET['from'] ?? 0));
    echo json_encode(['success' => true]);
    exit;
}
if ($action === 'unread') {
    echo json_encode(['count' => $msg->getUnreadCount($userId)]);
    exit;
}
echo json_encode(['error' => '未知操作']);
