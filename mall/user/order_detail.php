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

$order = new Order($pdo);
$shop = new Shop($pdo);

$userId = $_SESSION['user_id'];
$orderId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($orderId <= 0) {
    header("Location: orders.php");
    exit();
}

// 获取订单信息
$orderInfo = $order->getOrderById($orderId, $userId);
if (!$orderInfo) {
    header("Location: orders.php?error=" . urlencode("订单不存在"));
    exit();
}

// 获取订单商品
$orderItems = $order->getOrderDetails($orderId);

// 获取店铺信息
$shopInfo = $shop->getShopById($orderInfo['shop_id'] ?? 0);

// 状态映射
$statusMap = [
    'pending' => ['label' => '待付款', 'color' => '#f39c12', 'bg' => '#fff8e1'],
    'paid' => ['label' => '已付款', 'color' => '#3498db', 'bg' => '#e3f2fd'],
    'shipped' => ['label' => '已发货', 'color' => '#27ae60', 'bg' => '#e8f5e9'],
    'completed' => ['label' => '已完成', 'color' => '#7f8c8d', 'bg' => '#f5f5f5'],
    'cancelled' => ['label' => '已取消', 'color' => '#e74c3c', 'bg' => '#ffebee'],
    'refunded' => ['label' => '已退款', 'color' => '#7f8c8d', 'bg' => '#f5f5f5'],
];
$currentStatus = $statusMap[$orderInfo['status']] ?? $statusMap['pending'];
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>订单详情 - <?php echo htmlspecialchars($orderInfo['order_no']); ?> - 58人气值商城</title>
    <style>
        .order-detail-container {
            max-width: 900px;
            margin: 0 auto;
            padding: 20px;
        }
        .page-header {
            margin-bottom: 20px;
        }
        .page-title {
            font-size: 24px;
            font-weight: bold;
            color: #333;
        }
        .back-link {
            color: #3498db;
            text-decoration: none;
            font-size: 14px;
        }
        .status-banner {
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .status-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            background: white;
        }
        .status-info h2 {
            font-size: 20px;
            margin: 0 0 5px;
        }
        .status-info p {
            margin: 0;
            font-size: 13px;
            opacity: 0.8;
        }
        .card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        .card-title {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #f0f0f0;
            color: #333;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            font-size: 14px;
        }
        .info-label {
            color: #666;
        }
        .info-value {
            color: #333;
            font-weight: 500;
        }
        .product-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        .product-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 12px;
            background: #fafafa;
            border-radius: 8px;
        }
        .product-item img {
            width: 70px;
            height: 70px;
            object-fit: cover;
            border-radius: 8px;
        }
        .product-meta {
            flex: 1;
        }
        .product-meta .name {
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }
        .product-meta .spec {
            font-size: 12px;
            color: #999;
        }
        .product-price-qty {
            text-align: right;
        }
        .product-price-qty .price {
            color: #e74c3c;
            font-weight: bold;
            font-size: 15px;
        }
        .product-price-qty .qty {
            color: #666;
            font-size: 13px;
        }
        .total-section {
            text-align: right;
            padding-top: 15px;
            border-top: 1px solid #f0f0f0;
            margin-top: 10px;
        }
        .total-section .total {
            font-size: 20px;
            font-weight: bold;
            color: #e74c3c;
        }
        .actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 20px;
        }
        .btn {
            padding: 10px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.3s;
        }
        .btn-primary {
            background: #e74c3c;
            color: white;
        }
        .btn-primary:hover {
            background: #c0392b;
        }
        .btn-secondary {
            background: #3498db;
            color: white;
        }
        .btn-secondary:hover {
            background: #2980b9;
        }
        .btn-default {
            background: #f0f0f0;
            color: #333;
        }
        .btn-default:hover {
            background: #e0e0e0;
        }
        .shipping-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            font-size: 14px;
            line-height: 1.6;
        }
        @media (max-width: 600px) {
            .product-item {
                flex-direction: column;
                align-items: flex-start;
            }
            .product-price-qty {
                text-align: left;
                width: 100%;
                display: flex;
                justify-content: space-between;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="order-detail-container">
        <div class="page-header">
            <a href="orders.php" class="back-link"><i class="fas fa-arrow-left"></i> 返回订单列表</a>
            <h1 class="page-title" style="margin-top:10px;">订单详情</h1>
        </div>
        
        <!-- 状态横幅 -->
        <div class="status-banner" style="background: <?php echo $currentStatus['bg']; ?>; color: <?php echo $currentStatus['color']; ?>;">
            <div class="status-icon" style="color: <?php echo $currentStatus['color']; ?>;">
                <i class="fas fa-<?php
                    $statusIcon = 'info-circle';
                    switch ($orderInfo['status']) {
                        case 'pending': $statusIcon = 'clock'; break;
                        case 'paid': $statusIcon = 'check-circle'; break;
                        case 'shipped': $statusIcon = 'truck'; break;
                        case 'completed': $statusIcon = 'check-double'; break;
                        case 'cancelled': $statusIcon = 'times-circle'; break;
                    }
                    echo $statusIcon;
                ?>"></i>
            </div>
            <div class="status-info">
                <h2><?php echo $currentStatus['label']; ?></h2>
                <p>订单号: <?php echo htmlspecialchars($orderInfo['order_no']); ?></p>
            </div>
        </div>
        
        <!-- 物流信息 (已发货/已完成时显示) -->
        <?php if (in_array($orderInfo['status'], ['shipped', 'completed']) && !empty($orderInfo['shipping_company'])): ?>
        <div class="card">
            <div class="card-title"><i class="fas fa-truck"></i> 物流信息</div>
            <div class="shipping-info">
                <div class="info-row">
                    <span class="info-label">物流公司</span>
                    <span class="info-value"><?php echo htmlspecialchars($orderInfo['shipping_company']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">物流单号</span>
                    <span class="info-value"><?php echo htmlspecialchars($orderInfo['tracking_no'] ?? '暂无'); ?></span>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- 商品列表 -->
        <div class="card">
            <div class="card-title"><i class="fas fa-box"></i> 商品信息</div>
            <div class="product-list">
                <?php foreach ($orderItems as $item): ?>
                <div class="product-item">
                    <img src="<?php echo htmlspecialchars(normalizeImageUrl($item['image_url'])); ?>" alt="">
                    <div class="product-meta">
                        <div class="name"><?php echo htmlspecialchars($item['product_name']); ?></div>
                        <div class="spec">默认规格</div>
                    </div>
                    <div class="product-price-qty">
                        <div class="price">¥<?php echo number_format($item['unit_price'], 2); ?></div>
                        <div class="qty">x<?php echo $item['quantity']; ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="total-section">
                实付总额: <span class="total">¥<?php echo number_format($orderInfo['total_amount'], 2); ?></span>
            </div>
        </div>
        
        <!-- 订单信息 -->
        <div class="card">
            <div class="card-title"><i class="fas fa-file-invoice"></i> 订单信息</div>
            <div class="info-row">
                <span class="info-label">订单编号</span>
                <span class="info-value"><?php echo htmlspecialchars($orderInfo['order_no']); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">下单时间</span>
                <span class="info-value"><?php echo date('Y-m-d H:i:s', strtotime($orderInfo['created_at'])); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">店铺名称</span>
                <span class="info-value"><?php echo htmlspecialchars($shopInfo['shop_name'] ?? '平台自营'); ?></span>
            </div>
            <?php if (!empty($orderInfo['buyer_note'])): ?>
            <div class="info-row">
                <span class="info-label">买家备注</span>
                <span class="info-value"><?php echo htmlspecialchars($orderInfo['buyer_note']); ?></span>
            </div>
            <?php endif; ?>
            <?php if (!empty($orderInfo['seller_note'])): ?>
            <div class="info-row">
                <span class="info-label">卖家备注</span>
                <span class="info-value"><?php echo htmlspecialchars($orderInfo['seller_note']); ?></span>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- 支付信息 -->
        <?php if (in_array($orderInfo['status'], ['paid', 'shipped', 'completed'])): ?>
        <div class="card">
            <div class="card-title"><i class="fas fa-credit-card"></i> 支付信息</div>
            <div class="info-row">
                <span class="info-label">支付城市</span>
                <span class="info-value"><?php echo htmlspecialchars($orderInfo['payment_city'] ?? '-'); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">支付金额</span>
                <span class="info-value">¥<?php echo number_format($orderInfo['payment_amount'] ?? $orderInfo['total_amount'], 2); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">支付时间</span>
                <span class="info-value"><?php echo !empty($orderInfo['paid_at']) ? date('Y-m-d H:i:s', strtotime($orderInfo['paid_at'])) : '-'; ?></span>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- 操作按钮 -->
        <div class="actions">
            <a href="orders.php" class="btn btn-default"><i class="fas fa-arrow-left"></i> 返回列表</a>
            
            <?php if ($orderInfo['status'] == 'pending'): ?>
                <a href="pay.php?order_id=<?php echo $orderInfo['id']; ?>" class="btn btn-primary"><i class="fas fa-credit-card"></i> 立即付款</a>
                <button class="btn btn-default" onclick="cancelOrder(<?php echo $orderInfo['id']; ?>)"><i class="fas fa-times"></i> 取消订单</button>
            <?php elseif ($orderInfo['status'] == 'shipped'): ?>
                <button class="btn btn-primary" onclick="confirmReceipt(<?php echo $orderInfo['id']; ?>)"><i class="fas fa-check"></i> 确认收货</button>
            <?php elseif ($orderInfo['status'] == 'completed'): ?>
                <a href="review.php?order_id=<?php echo $orderInfo['id']; ?>" class="btn btn-primary"><i class="fas fa-pen"></i> 去评价</a>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        function cancelOrder(orderId) {
            if (confirm('确定要取消这个订单吗？')) {
                fetch('cancel_order.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'order_id=' + orderId
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        alert('订单已取消');
                        location.reload();
                    } else {
                        alert(data.message);
                    }
                });
            }
        }
        function confirmReceipt(orderId) {
            if (confirm('确定已经收到商品了吗？')) {
                fetch('confirm_receipt.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'order_id=' + orderId
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        alert('确认收货成功');
                        location.reload();
                    } else {
                        alert(data.message);
                    }
                });
            }
        }
    </script>
    
    <?php include '../includes/footer.php'; ?>
</body>
</html>
