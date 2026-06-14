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
require_once '../../classes/Product.php';

$userId = $_SESSION['user_id'];
$orderId = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;

if ($orderId <= 0) {
    echo json_encode(['success' => false, 'message' => '订单ID无效']);
    exit();
}

$order = new Order($pdo);
$product = new Product($pdo);

// 获取订单并验证属主
$orderInfo = $order->getOrderById($orderId, $userId);
if (!$orderInfo) {
    echo json_encode(['success' => false, 'message' => '订单不存在或无权操作']);
    exit();
}

if ($orderInfo['status'] != 'pending') {
    echo json_encode(['success' => false, 'message' => '只有待付款订单可以取消']);
    exit();
}

try {
    $pdo->beginTransaction();

    // 恢复库存
    $items = $order->getOrderDetails($orderId);
    foreach ($items as $item) {
        $rollbackSql = "UPDATE products 
                        SET stock = stock + ?, sold_count = GREATEST(sold_count - ?, 0), updated_at = NOW() 
                        WHERE id = ?";
        $rollbackStmt = $pdo->prepare($rollbackSql);
        $rollbackStmt->execute([$item['quantity'], $item['quantity'], $item['product_id']]);
    }

    // 取消订单
    $result = $order->cancelOrder($orderId, $userId);

    if ($result) {
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => '订单已取消']);
    } else {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => '取消订单失败']);
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => '取消订单失败: ' . $e->getMessage()]);
}
