<?php
// 启动会话
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

// 检查登录
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => '请先登录']);
    exit();
}

require_once '../../config/database.php';
require_once '../../classes/Order.php';

$userId = $_SESSION['user_id'];
$orderId = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;

if ($orderId <= 0) {
    echo json_encode(['success' => false, 'message' => '订单ID无效']);
    exit();
}

$order = new Order($pdo);

// 获取订单并验证属主
$orderInfo = $order->getOrderById($orderId, $userId);
if (!$orderInfo) {
    echo json_encode(['success' => false, 'message' => '订单不存在或无权操作']);
    exit();
}

if ($orderInfo['status'] != 'shipped') {
    echo json_encode(['success' => false, 'message' => '只有已发货订单可以确认收货']);
    exit();
}

try {
    $result = $order->confirmReceipt($orderId, $userId);
    if ($result) {
        echo json_encode(['success' => true, 'message' => '确认收货成功']);
    } else {
        echo json_encode(['success' => false, 'message' => '确认收货失败']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => '确认收货失败: ' . $e->getMessage()]);
}
