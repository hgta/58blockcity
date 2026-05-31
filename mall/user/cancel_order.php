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
    // 先获取订单商品明细，准备恢复库存
    $orderItems = $order->getOrderDetails($orderId);
    
    // 取消订单（仅 pending 状态可取消）
    $result = $order->cancelOrder($orderId, $_SESSION['user_id']);
    
    if ($result) {
        // 取消成功后恢复商品库存和销量
        foreach ($orderItems as $item) {
            $stmt = $pdo->prepare("UPDATE products SET stock = stock + ?, sold_count = GREATEST(sold_count - ?, 0) WHERE id = ?");
            $stmt->execute([$item['quantity'], $item['quantity'], $item['product_id']]);
        }
        
        echo json_encode(['success' => true, 'message' => '订单已取消']);
    } else {
        echo json_encode(['success' => false, 'message' => '取消订单失败，请检查订单状态']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
