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

// 获取参数
$orderId = $_POST['order_id'] ?? 0;
$amount = (int)($_POST['amount'] ?? 0);
$price = floatval($_POST['price'] ?? 0);
$tradeType = $_POST['trade_type'] ?? '';
$contactInfo = $_POST['contact_info'] ?? '';

if (!$orderId) {
    echo json_encode(['success' => false, 'message' => '订单ID不能为空']);
    exit;
}

if ($amount <= 0 || $price < 0.01) {
    echo json_encode(['success' => false, 'message' => '数量或价格无效']);
    exit;
}

if (!in_array($tradeType, ['platform', 'mediator', 'direct'])) {
    echo json_encode(['success' => false, 'message' => '交易方式无效']);
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
        throw new Exception('只能编辑待成交状态的订单');
    }
    
    // 计算新的总金额
    $totalAmount = $amount * $price;
    
    // 如果是出售订单，需要调整冻结的余额
    if ($order['type'] === 'sell') {
        $amountDiff = $amount - $order['amount'];
        if ($amountDiff != 0) {
            $stmt = $pdo->prepare("UPDATE user_bct_account SET frozen = frozen + ? WHERE user_id = ? AND city = ?");
            $stmt->execute([$amountDiff, $_SESSION['user_id'], $order['city']]);
        }
    }
    
    // 更新订单信息
    $stmt = $pdo->prepare("
        UPDATE bct_orders 
        SET amount = ?, price = ?, total_amount = ?, trade_type = ?, contact_info = ?, updated_at = NOW() 
        WHERE id = ?
    ");
    $stmt->execute([$amount, $price, $totalAmount, $tradeType, $contactInfo, $orderId]);
    
    $pdo->commit();
    
    echo json_encode(['success' => true, 'message' => '订单更新成功']);
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>