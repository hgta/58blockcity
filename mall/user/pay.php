<?php
// 启动会话
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 检查登录
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../auth/login.php?redirect=" . urlencode($_SERVER['REQUEST_URI']));
    exit();
}

require_once '../../config/database.php';
require_once '../../classes/Order.php';

$order = new Order($pdo);

$orderId = intval($_GET['order_id'] ?? 0);

if ($orderId <= 0) {
    die('无效的订单ID');
}

$orderInfo = $order->getOrderById($orderId, $_SESSION['user_id']);
if (!$orderInfo) {
    die('订单不存在');
}

if ($orderInfo['status'] != 'pending') {
    die('订单状态不可支付，当前状态：' . $orderInfo['status']);
}

// 获取订单商品
$orderItems = $order->getOrderDetails($orderId);

// 获取店铺支付设置
$shopBlockId = '';
if ($orderInfo['payment_city'] && $orderInfo['shop_id']) {
    try {
        $stmt = $pdo->prepare("SELECT block_id FROM shop_payment_settings WHERE shop_id = ? AND city = ? AND is_active = 1");
        $stmt->execute([$orderInfo['shop_id'], $orderInfo['payment_city']]);
        $result = $stmt->fetch();
        $shopBlockId = $result['block_id'] ?? '';
    } catch (Exception $e) {}
}

// 处理"我已支付"提交
$success = false;
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $txHash = trim($_POST['tx_hash'] ?? '');
    
    try {
        $notes = '用户确认已在blockcity.vip完成支付';
        if ($txHash) $notes .= ' | 交易号: ' . $txHash;
        
        $stmt = $pdo->prepare("UPDATE orders SET status = 'paid', payment_block_id = ?, buyer_note = ?, paid_at = NOW(), updated_at = NOW() WHERE id = ? AND user_id = ? AND status = 'pending'");
        $stmt->execute([$shopBlockId, $notes, $orderId, $_SESSION['user_id']]);
        
        if ($stmt->rowCount() > 0) {
            $success = true;
            $orderInfo['status'] = 'paid';
        } else {
            $error = '确认失败，订单状态可能已变更';
        }
    } catch (Exception $e) {
        $error = '确认支付失败：' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>支付确认 - 58人气值商城</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Microsoft YaHei', Arial, sans-serif; background: #f5f5f5; color: #333; }
        .container { max-width: 700px; margin: 0 auto; padding: 20px; }
        .page-title { font-size: 24px; font-weight: bold; margin-bottom: 20px; }
        
        .card { background: white; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); padding: 25px; margin-bottom: 20px; }
        .card-title { font-size: 16px; font-weight: bold; border-bottom: 2px solid #f0f0f0; padding-bottom: 10px; margin-bottom: 15px; }
        
        .success-banner { background: #d4edda; color: #155724; padding: 20px; border-radius: 8px; text-align: center; font-size: 18px; font-weight: bold; margin-bottom: 20px; }
        .error-banner { background: #f8d7da; color: #721c24; padding: 20px; border-radius: 8px; text-align: center; font-size: 16px; margin-bottom: 20px; }
        
        .pay-instruction {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white; padding: 25px; border-radius: 10px; margin-bottom: 15px;
        }
        .pay-step { margin-bottom: 12px; font-size: 15px; line-height: 1.7; }
        .block-id {
            font-family: 'Courier New', monospace; background: rgba(255,255,255,0.2);
            padding: 4px 14px; border-radius: 6px; font-size: 20px; font-weight: bold; letter-spacing: 1px;
        }
        .pay-amount { font-size: 32px; font-weight: bold; color: #f39c12; margin: 8px 0; }
        
        .order-summary { font-size: 14px; }
        .summary-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px dotted #eee; }
        .summary-label { color: #666; }
        .summary-value { font-weight: 500; }
        .summary-total { font-size: 20px; font-weight: bold; color: #e74c3c; border-bottom: none; padding-top: 12px; border-top: 2px solid #eee; }
        
        .product-item { display: flex; align-items: center; padding: 10px 0; border-bottom: 1px solid #f5f5f5; font-size: 14px; }
        .product-item:last-child { border-bottom: none; }
        .product-name { flex: 1; }
        .product-qty { color: #666; margin: 0 15px; }
        .product-price { color: #e74c3c; font-weight: bold; }
        
        .form-group { margin-bottom: 15px; }
        .form-label { display: block; font-size: 14px; color: #666; margin-bottom: 6px; }
        .form-input { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; outline: none; }
        .form-input:focus { border-color: #3498db; }
        .form-hint { font-size: 12px; color: #999; margin-top: 4px; }
        
        .btn { padding: 12px 30px; border: none; border-radius: 6px; cursor: pointer; font-size: 16px; font-weight: bold; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; transition: all 0.3s; }
        .btn-pay { background: #e74c3c; color: white; width: 100%; justify-content: center; font-size: 18px; padding: 15px; }
        .btn-pay:hover { background: #c0392b; transform: translateY(-2px); box-shadow: 0 4px 15px rgba(231,76,60,0.3); }
        .btn-back { background: #3498db; color: white; margin-bottom: 20px; font-size: 14px; padding: 8px 16px; }
        
        @media (max-width: 500px) {
            .container { padding: 10px; }
            .pay-instruction { padding: 15px; }
        }
    </style>
</head>
<body>
<?php include '../includes/header.php'; ?>
<div class="container">
    <a href="order_detail.php?id=<?php echo $orderId; ?>" class="btn btn-back"><i class="fas fa-arrow-left"></i> 返回订单</a>
    <h1 class="page-title">支付确认</h1>
    
    <?php if ($success): ?>
        <div class="success-banner">
            <i class="fas fa-check-circle"></i> 支付确认成功！
            <div style="font-size: 14px; font-weight: normal; margin-top: 8px;">订单已更新为"已付款"，即将跳转...</div>
        </div>
        <meta http-equiv="refresh" content="2;url=order_detail.php?id=<?php echo $orderId; ?>">
    <?php elseif ($error): ?>
        <div class="error-banner"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <?php if (!$success && $orderInfo['status'] == 'pending'): ?>
    <!-- 支付引导 -->
    <div class="pay-instruction">
        <div class="pay-step"><i class="fas fa-info-circle"></i> <strong>BCT支付说明</strong></div>
        <div class="pay-step">请在 <strong>blockcity.vip</strong> 完成人气值(BCT)转账：</div>
        <?php if ($orderInfo['payment_city']): ?>
        <div class="pay-step"><i class="fas fa-map-marker-alt"></i> 支付城市：<?php echo htmlspecialchars($orderInfo['payment_city']); ?></div>
        <?php endif; ?>
        <div class="pay-step">
            <i class="fas fa-coins"></i> 支付金额：
            <span class="pay-amount"><?php echo number_format($orderInfo['payment_amount'], 2); ?> BCT</span>
        </div>
        <?php if ($shopBlockId): ?>
        <div class="pay-step">
            <i class="fas fa-link"></i> 收款区块ID：<span class="block-id"><?php echo htmlspecialchars($shopBlockId); ?></span>
        </div>
        <?php endif; ?>
        <div class="pay-step" style="margin-top: 15px; padding-top: 15px; border-top: 1px solid rgba(255,255,255,0.3);">
            转账完成后，点击下方<strong>"我已支付"</strong>按钮确认即可
        </div>
    </div>
    
    <!-- 订单摘要 -->
    <div class="card">
        <div class="card-title"><i class="fas fa-receipt"></i> 订单信息</div>
        <div class="order-summary">
            <div class="summary-row">
                <span class="summary-label">订单号</span>
                <span class="summary-value"><?php echo htmlspecialchars($orderInfo['order_no']); ?></span>
            </div>
            <?php foreach ($orderItems as $item): ?>
            <div class="product-item">
                <span class="product-name"><?php echo htmlspecialchars($item['product_name']); ?></span>
                <span class="product-qty">x<?php echo $item['quantity']; ?></span>
                <span class="product-price">¥<?php echo number_format($item['unit_price'] ?? 0, 2); ?></span>
            </div>
            <?php endforeach; ?>
            <div class="summary-row summary-total">
                <span>应付总额</span>
                <span>¥<?php echo number_format($orderInfo['total_amount'], 2); ?></span>
            </div>
        </div>
    </div>
    
    <!-- 确认支付 -->
    <div class="card">
        <div class="card-title"><i class="fas fa-check-circle"></i> 确认支付</div>
        <form method="POST" action="">
            <div class="form-group">
                <label class="form-label">交易号/转账截图链接（可选）</label>
                <input type="text" name="tx_hash" class="form-input" placeholder="blockcity.vip 上的交易号或转账记录链接">
                <div class="form-hint">填写后可方便卖家核对，不填也可以直接确认</div>
            </div>
            <button type="submit" class="btn btn-pay" onclick="return confirm('确认已经在blockcity.vip完成支付了吗？')">
                <i class="fas fa-check-circle"></i> 我已支付，确认订单
            </button>
        </form>
    </div>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>
</body>
</html>
