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
require_once '../../classes/User.php';

$order = new Order($pdo);
$user = new User($pdo);

$orderId = intval($_GET['id'] ?? 0);

if ($orderId <= 0) {
    die('无效的订单ID');
}

// 获取订单
$orderInfo = $order->getOrderById($orderId, $_SESSION['user_id']);
if (!$orderInfo) {
    die('订单不存在');
}

// 获取订单商品
$orderItems = $order->getOrderDetails($orderId);

// 状态映射
$statusMap = [
    'pending' => '待付款',
    'paid' => '已付款',
    'shipped' => '已发货',
    'completed' => '已完成',
    'cancelled' => '已取消',
    'refunded' => '已退款'
];
$statusClassMap = [
    'pending' => '#fff3cd',
    'paid' => '#d1ecf1',
    'shipped' => '#d4edda',
    'completed' => '#e2e3e5',
    'cancelled' => '#f8d7da',
    'refunded' => '#f8d7da'
];
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>订单详情 - 58人气值商城</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Microsoft YaHei', Arial, sans-serif; background: #f5f5f5; color: #333; }
        .container { max-width: 900px; margin: 0 auto; padding: 20px; }
        .page-header { margin-bottom: 20px; }
        .page-title { font-size: 24px; font-weight: bold; }
        .back-link { color: #3498db; text-decoration: none; font-size: 14px; margin-bottom: 20px; display: inline-block; }
        
        .status-banner {
            padding: 20px 25px; border-radius: 8px; margin-bottom: 20px;
            font-size: 18px; font-weight: bold; display: flex; align-items: center; gap: 12px;
        }
        .status-banner i { font-size: 28px; }
        
        .card {
            background: white; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            padding: 25px; margin-bottom: 20px;
        }
        .card-title { font-size: 16px; font-weight: bold; border-bottom: 2px solid #f0f0f0; padding-bottom: 10px; margin-bottom: 15px; }
        
        .order-no { color: #666; font-size: 14px; margin-bottom: 5px; }
        .order-time { color: #999; font-size: 13px; }
        
        .product-item { display: flex; align-items: center; padding: 12px 0; border-bottom: 1px solid #f5f5f5; }
        .product-item:last-child { border-bottom: none; }
        .product-img { width: 70px; height: 70px; border-radius: 6px; object-fit: cover; margin-right: 15px; }
        .product-name { font-weight: bold; margin-bottom: 4px; }
        .product-price { color: #e74c3c; font-weight: bold; font-size: 15px; }
        .product-qty { color: #666; font-size: 13px; margin-left: auto; }
        
        .info-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px dotted #eee; font-size: 14px; }
        .info-label { color: #666; }
        .info-value { font-weight: 500; }
        .total-row { font-size: 18px; font-weight: bold; color: #e74c3c; border-bottom: none; padding-top: 12px; margin-top: 8px; border-top: 2px solid #f0f0f0; }
        
        .pay-instruction { background: linear-gradient(135deg, #3498db, #2980b9); color: white; padding: 15px 20px; border-radius: 8px; margin: 10px 0; font-size: 14px; line-height: 1.6; }
        .block-id { font-family: monospace; background: rgba(255,255,255,0.2); padding: 2px 8px; border-radius: 4px; font-weight: bold; }
        
        .btn { padding: 10px 20px; border: none; border-radius: 6px; cursor: pointer; font-size: 14px; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; transition: all 0.3s; }
        .btn-pay { background: #e74c3c; color: white; font-size: 16px; padding: 12px 30px; }
        .btn-pay:hover { background: #c0392b; transform: translateY(-1px); }
        .btn-cancel { background: #95a5a6; color: white; }
        .btn-cancel:hover { background: #7f8c8d; }
        .btn-confirm { background: #27ae60; color: white; font-size: 16px; padding: 12px 30px; }
        .btn-confirm:hover { background: #219a52; transform: translateY(-1px); }
        .btn-back { background: #3498db; color: white; }
        
        .actions { display: flex; gap: 10px; margin-top: 15px; flex-wrap: wrap; }
        
        @media (max-width: 600px) {
            .container { padding: 10px; }
            .product-item { flex-wrap: wrap; }
        }
    </style>
</head>
<body>
<?php include '../includes/header.php'; ?>
<div class="container">
    <a href="orders.php" class="back-link"><i class="fas fa-arrow-left"></i> 返回订单列表</a>
    <div class="page-header">
        <h1 class="page-title">订单详情</h1>
    </div>
    
    <!-- 状态横幅 -->
    <div class="status-banner" style="background: <?php echo $statusClassMap[$orderInfo['status']] ?? '#f0f0f0'; ?>;">
        <i class="fas fa-<?php 
            $icons = ['pending'=>'clock','paid'=>'check-circle','shipped'=>'truck','completed'=>'smile','cancelled'=>'times-circle','refunded'=>'undo'];
            echo $icons[$orderInfo['status']] ?? 'info-circle';
        ?>"></i>
        <span>订单状态：<?php echo $statusMap[$orderInfo['status']] ?? '未知'; ?></span>
    </div>
    
    <!-- 订单信息 -->
    <div class="card">
        <div class="card-title"><i class="fas fa-receipt"></i> 订单信息</div>
        <div class="order-no">订单号：<?php echo htmlspecialchars($orderInfo['order_no']); ?></div>
        <div class="order-time">下单时间：<?php echo date('Y-m-d H:i:s', strtotime($orderInfo['created_at'])); ?></div>
        <?php if ($orderInfo['paid_at']): ?>
            <div class="order-time">付款时间：<?php echo date('Y-m-d H:i:s', strtotime($orderInfo['paid_at'])); ?></div>
        <?php endif; ?>
    </div>
    
    <!-- 商品列表 -->
    <div class="card">
        <div class="card-title"><i class="fas fa-box"></i> 商品信息</div>
        <?php foreach ($orderItems as $item): ?>
            <div class="product-item">
                <img src="<?php echo htmlspecialchars($item['product_image'] ?: '../assets/images/default-product.jpg'); ?>" 
                     alt="<?php echo htmlspecialchars($item['product_name']); ?>" class="product-img"
                     onerror="this.src='../assets/images/default-product.jpg'">
                <div>
                    <div class="product-name"><?php echo htmlspecialchars($item['product_name']); ?></div>
                    <div class="product-price"><span class="bct-symbol">Ⓟ</span><?php echo number_format($item['unit_price'] ?? 0, 0); ?> 人气值</div>
                </div>
                <div class="product-qty">x<?php echo $item['quantity']; ?></div>
            </div>
        <?php endforeach; ?>
    </div>
    
    <!-- 支付信息 -->
    <?php if ($orderInfo['payment_city']): ?>
    <div class="card">
        <div class="card-title"><i class="fas fa-coins"></i> 支付信息</div>
        <div class="info-row">
            <span class="info-label">支付城市</span>
            <span class="info-value"><?php echo htmlspecialchars($orderInfo['payment_city']); ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">BCT金额</span>
            <span class="info-value"><span class="bct-symbol">Ⓟ</span><?php echo number_format($orderInfo['payment_amount'], 0); ?> 人气值</span>
        </div>
        <?php if ($orderInfo['payment_block_id']): ?>
        <div class="info-row">
            <span class="info-label">收款区块ID</span>
            <span class="info-value"><?php echo htmlspecialchars($orderInfo['payment_block_id']); ?></span>
        </div>
        <?php endif; ?>
        <div class="info-row total-row">
            <span>应付总额</span>
            <span><span class="bct-symbol">Ⓟ</span><?php echo number_format($orderInfo['total_amount'], 0); ?> 人气值</span>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- 物流信息 -->
    <?php if (!empty($orderInfo['shipping_company']) && !empty($orderInfo['tracking_no'])): ?>
    <div class="card">
        <div class="card-title"><i class="fas fa-shipping-fast"></i> 物流信息</div>
        <div class="info-row">
            <span class="info-label">物流公司</span>
            <span class="info-value"><?php echo htmlspecialchars($orderInfo['shipping_company']); ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">运单号</span>
            <span class="info-value"><?php echo htmlspecialchars($orderInfo['tracking_no']); ?></span>
        </div>
        <?php if ($orderInfo['shipped_at']): ?>
        <div class="info-row">
            <span class="info-label">发货时间</span>
            <span class="info-value"><?php echo date('Y-m-d H:i:s', strtotime($orderInfo['shipped_at'])); ?></span>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    
    <!-- 操作按钮 -->
    <div class="card">
        <div class="actions">
            <a href="orders.php" class="btn btn-back"><i class="fas fa-list"></i> 订单列表</a>
            
            <?php if ($orderInfo['status'] == 'pending'): ?>
                <a href="pay.php?order_id=<?php echo $orderInfo['id']; ?>" class="btn btn-pay">
                    <i class="fas fa-credit-card"></i> 去支付
                </a>
                <button class="btn btn-cancel" onclick="cancelOrder(<?php echo $orderInfo['id']; ?>)">
                    <i class="fas fa-times"></i> 取消订单
                </button>
            <?php elseif ($orderInfo['status'] == 'shipped'): ?>
                <button class="btn btn-confirm" onclick="confirmReceipt(<?php echo $orderInfo['id']; ?>)">
                    <i class="fas fa-check"></i> 确认收货
                </button>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function cancelOrder(orderId) {
    if (confirm('确定要取消这个订单吗？')) {
        fetch('cancel_order.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'order_id=' + orderId
        })
        .then(r => r.json())
        .then(data => {
            alert(data.message);
            if (data.success) location.reload();
        })
        .catch(e => alert('操作失败'));
    }
}

function confirmReceipt(orderId) {
    if (confirm('确定已经收到商品了吗？')) {
        fetch('confirm_receipt.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'order_id=' + orderId
        })
        .then(r => r.json())
        .then(data => {
            alert(data.message);
            if (data.success) location.reload();
        })
        .catch(e => alert('操作失败'));
    }
}
</script>

<?php include '../includes/footer.php'; ?>
</body>
</html>
