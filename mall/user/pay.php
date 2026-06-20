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
require_once '../../classes/Shop.php';
require_once '../includes/functions.php';

// 兜底：如果公共函数库未部署，在此文件内也定义一次 normalizeImageUrl
if (!function_exists('normalizeImageUrl')) {
    function normalizeImageUrl($imageUrl) {
        if (empty($imageUrl)) {
            return '/assets/images/default-product.jpg';
        }
        $imageUrl = trim($imageUrl);
        if (preg_match('#^(https?:)?//#i', $imageUrl) || substr($imageUrl, 0, 1) === '/') {
            return $imageUrl;
        }
        return '/' . ltrim($imageUrl, '/');
    }
}

$order = new Order($pdo);
$shop = new Shop($pdo);

$userId = $_SESSION['user_id'];
$orderId = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;

if ($orderId <= 0) {
    header("Location: orders.php");
    exit();
}

// 获取订单
$orderInfo = $order->getOrderById($orderId, $userId);
if (!$orderInfo) {
    header("Location: orders.php?error=" . urlencode("订单不存在"));
    exit();
}

if ($orderInfo['status'] != 'pending') {
    header("Location: order_detail.php?id=" . $orderId);
    exit();
}

// 获取店铺支付设置
$paymentSettings = $shop->getShopPaymentSettings($orderInfo['shop_id'] ?? 0);
$defaultPayment = $paymentSettings[0] ?? null;

// 处理"我已支付"
$successMsg = '';
$errorMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $txHash = trim($_POST['tx_hash'] ?? '');
    try {
        // 更新订单为已支付
        $blockId = $defaultPayment['block_id'] ?? '';
        $result = $order->updatePaymentInfo($orderId, $blockId);
        if ($result) {
            $successMsg = '支付确认成功，订单状态已更新为已付款';
            // 重新获取订单
            $orderInfo = $order->getOrderById($orderId, $userId);
        } else {
            $errorMsg = '支付确认失败，请稍后重试';
        }
    } catch (Exception $e) {
        $errorMsg = '处理失败: ' . $e->getMessage();
    }
}

// 获取订单商品
$orderItems = $order->getOrderDetails($orderId);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>支付订单 - <?php echo htmlspecialchars($orderInfo['order_no']); ?> - 58人气值商城</title>
    <style>
        .pay-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        .page-header {
            margin-bottom: 20px;
        }
        .back-link {
            color: #3498db;
            text-decoration: none;
            font-size: 14px;
        }
        .page-title {
            font-size: 24px;
            font-weight: bold;
            color: #333;
            margin-top: 10px;
        }
        .card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        .card-title {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 15px;
            color: #333;
        }
        /* 支付引导 */
        .pay-guide {
            background: linear-gradient(135deg, #1e3a5f, #2563eb);
            color: white;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 20px;
        }
        .pay-guide h2 {
            margin: 0 0 10px;
            font-size: 18px;
        }
        .pay-guide p {
            margin: 0 0 15px;
            opacity: 0.9;
            font-size: 14px;
            line-height: 1.6;
        }
        .block-info {
            background: rgba(255,255,255,0.15);
            border-radius: 8px;
            padding: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        .block-info .label {
            font-size: 13px;
            opacity: 0.85;
        }
        .block-info .value {
            font-size: 18px;
            font-weight: bold;
        }
        .amount-highlight {
            font-size: 28px;
            font-weight: bold;
            color: #f59e0b;
        }
        .product-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .product-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px;
            background: #fafafa;
            border-radius: 8px;
        }
        .product-item img {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 6px;
        }
        .product-meta {
            flex: 1;
            font-size: 14px;
        }
        .product-meta .name {
            font-weight: bold;
            color: #333;
        }
        .product-price {
            color: #e74c3c;
            font-weight: bold;
        }
        .total-row {
            display: flex;
            justify-content: space-between;
            padding-top: 15px;
            border-top: 1px solid #f0f0f0;
            margin-top: 10px;
            font-size: 16px;
        }
        .total-row .amount {
            font-size: 22px;
            font-weight: bold;
            color: #e74c3c;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-size: 14px;
            color: #333;
            font-weight: 500;
        }
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            outline: none;
        }
        .form-group input:focus,
        .form-group textarea:focus {
            border-color: #3498db;
        }
        .hint {
            font-size: 12px;
            color: #999;
            margin-top: 4px;
        }
        .btn-pay {
            width: 100%;
            padding: 14px;
            background: #e74c3c;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: background 0.3s;
        }
        .btn-pay:hover {
            background: #c0392b;
        }
        .btn-pay:disabled {
            background: #bdc3c7;
            cursor: not-allowed;
        }
        .success-msg {
            background: #d4edda;
            color: #155724;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 15px;
            font-size: 14px;
        }
        .error-msg {
            background: #f8d7da;
            color: #721c24;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 15px;
            font-size: 14px;
        }
        .steps {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            margin-top: 15px;
        }
        .step {
            text-align: center;
            padding: 12px;
            background: rgba(255,255,255,0.1);
            border-radius: 8px;
            font-size: 12px;
        }
        .step-number {
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 4px;
        }
        @media (max-width: 600px) {
            .steps {
                grid-template-columns: 1fr;
            }
            .block-info {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="pay-container">
        <div class="page-header">
            <a href="order_detail.php?id=<?php echo $orderId; ?>" class="back-link"><i class="fas fa-arrow-left"></i> 返回订单详情</a>
            <h1 class="page-title">支付订单</h1>
        </div>
        
        <?php if ($successMsg): ?>
            <div class="success-msg"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($successMsg); ?></div>
        <?php endif; ?>
        <?php if ($errorMsg): ?>
            <div class="error-msg"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($errorMsg); ?></div>
        <?php endif; ?>
        
        <?php if ($orderInfo['status'] == 'pending'): ?>
        <!-- 支付引导 -->
        <div class="pay-guide">
            <h2><i class="fas fa-info-circle"></i> BCT 支付引导</h2>
            <p>请在 <strong>blockcity.vip</strong> 完成 BCT 人气值转账后，回到本页面点击下方"我已支付"按钮确认。</p>
            <div class="block-info">
                <div>
                    <div class="label">收款区块ID</div>
                    <div class="value"><?php echo htmlspecialchars($defaultPayment['block_id'] ?? '暂未设置'); ?></div>
                </div>
                <div style="text-align:right;">
                    <div class="label">应付金额</div>
                    <div class="amount-highlight"><?php echo number_format($orderInfo['total_amount'], 0); ?> 人气值</div>
                </div>
            </div>
            <div class="steps">
                <div class="step">
                    <div class="step-number">1</div>
                    <div>登录 blockcity.vip</div>
                </div>
                <div class="step">
                    <div class="step-number">2</div>
                    <div>向收款区块转账</div>
                </div>
                <div class="step">
                    <div class="step-number">3</div>
                    <div>返回点击"我已支付"</div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- 订单摘要 -->
        <div class="card">
            <div class="card-title"><i class="fas fa-file-invoice"></i> 订单摘要</div>
            <div class="info-row" style="display:flex;justify-content:space-between;margin-bottom:10px;font-size:14px;">
                <span style="color:#666;">订单号</span>
                <span style="font-weight:500;"><?php echo htmlspecialchars($orderInfo['order_no']); ?></span>
            </div>
            <div class="product-list">
                <?php foreach ($orderItems as $item): ?>
                <div class="product-item">
                    <img src="<?php echo htmlspecialchars(normalizeImageUrl($item['image_url'])); ?>" alt="" onerror="this.src='/assets/images/default-product.jpg'">
                    <div class="product-meta">
                        <div class="name"><?php echo htmlspecialchars($item['product_name']); ?></div>
                    </div>
                    <div class="product-price">¥<?php echo number_format($item['unit_price'], 2); ?> x<?php echo $item['quantity']; ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="total-row">
                <span>应付总额</span>
                <span class="amount">¥<?php echo number_format($orderInfo['total_amount'], 2); ?></span>
            </div>
        </div>
        
        <?php if ($orderInfo['status'] == 'pending'): ?>
        <!-- 确认支付表单 -->
        <div class="card">
            <div class="card-title"><i class="fas fa-check-circle"></i> 确认支付</div>
            <form method="POST" action="" onsubmit="return confirmSubmit()">
                <div class="form-group">
                    <label>交易哈希/交易号（选填）</label>
                    <input type="text" name="tx_hash" placeholder="可选填写，便于卖家核对">
                    <div class="hint">在 blockcity.vip 完成转账后，可填写交易哈希以便快速核对</div>
                </div>
                <button type="submit" class="btn-pay" id="payBtn">
                    <i class="fas fa-check"></i> 我已支付，确认付款
                </button>
            </form>
        </div>
        <?php else: ?>
        <div class="card" style="text-align:center;padding:40px;">
            <div style="font-size:48px;color:#27ae60;margin-bottom:15px;"><i class="fas fa-check-circle"></i></div>
            <div style="font-size:18px;font-weight:bold;color:#333;margin-bottom:8px;">该订单已支付</div>
            <a href="order_detail.php?id=<?php echo $orderId; ?>" style="color:#3498db;text-decoration:none;">查看订单详情</a>
        </div>
        <?php endif; ?>
    </div>
    
    <script>
        function confirmSubmit() {
            if (!confirm('请确认您已在 blockcity.vip 完成转账支付？')) {
                return false;
            }
            document.getElementById('payBtn').disabled = true;
            document.getElementById('payBtn').innerHTML = '<i class="fas fa-spinner fa-spin"></i> 处理中...';
            return true;
        }
    </script>
    
    <?php include '../includes/footer.php'; ?>
</body>
</html>
