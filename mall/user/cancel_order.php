<?php
// 启动会话
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

// 检查登录
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => '请先登录']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => '请求方式错误']);
    exit();
}

require_once '../../config/database.php';
require_once '../../classes/Order.php';

$order = new Order($pdo);

$orderId = intval($_POST['order_id'] ?? 0);

if ($orderId <= 0) {
    echo json_encode(['success' => false, 'message' => '无效的订单ID']);
    exit();
}

try {
    $result = $order->cancelOrder($orderId, $_SESSION['user_id']);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => '订单已取消']);
    } else {
        echo json_encode(['success' => false, 'message' => '取消订单失败，请检查订单状态']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
