<?php
require_once '../../config/database.php';
require_once '../includes/auth.php';

// 检查登录
checkLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => '方法不允许']);
    exit;
}

// CSRF验证
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode(['success' => false, 'message' => '非法请求']);
    exit;
}

// 获取订单ID
$orderId = $_POST['order_id'] ?? 0;
if (!$orderId) {
    echo json_encode(['success' => false, 'message' => '订单ID不能为空']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    // 获取订单信息
    $stmt = $pdo->prepare("SELECT * FROM bct_orders WHERE id = ? AND user_id = ?");
    $stmt->execute([$orderId, $_SESSION['user_id']]);
    $order = $stmt->fetch();
    
    if (!$order) {
        throw new Exception('订单不存在或无权操作');
    }
    
    // 检查订单状态
    if ($order['status'] !== 'pending') {
        throw new Exception('只能取消待成交状态的订单');
    }
    
    // 如果是出售订单，需要解冻冻结的余额
    if ($order['type'] === 'sell') {
        $stmt = $pdo->prepare("UPDATE user_bct_account SET frozen = frozen - ? WHERE user_id = ? AND city = ?");
        $stmt->execute([$order['amount'], $_SESSION['user_id'], $order['city']]);
    }
    
    // 更新订单状态为已取消
    $stmt = $pdo->prepare("UPDATE bct_orders SET status = 'canceled', updated_at = NOW() WHERE id = ?");
    $stmt->execute([$orderId]);
    
    $pdo->commit();
    
    echo json_encode(['success' => true, 'message' => '订单取消成功']);
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>