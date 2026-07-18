<?php
// 启动会话
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 检查用户是否登录
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../auth/login.php?redirect=" . urlencode($_SERVER['REQUEST_URI']));
    exit();
}

require_once '../../config/database.php';
 

// 加载订单类
require_once '../../classes/Order.php';
require_once '../../classes/User.php';
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
$user = new User($pdo);

$userId = $_SESSION['user_id'];
$userInfo = $user->getUserById($userId);

// 获取查询参数
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$status = isset($_GET['status']) ? $_GET['status'] : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// 每页显示数量
$itemsPerPage = 10;

// 获取订单列表
$orders = $order->getUserOrders($userId, [
    'status' => $status,
    'search' => $search,
    'page' => $page,
    'per_page' => $itemsPerPage
]);

// 获取订单总数用于分页
$totalOrders = $order->getUserOrderCount($userId, [
    'status' => $status,
    'search' => $search
]);

// 计算总页数
$totalPages = ceil($totalOrders / $itemsPerPage);

// 订单状态映射
$statusMap = [
    'pending' => '待付款',
    'paid' => '已付款',
    'shipped' => '已发货',
    'completed' => '已完成',
    'cancelled' => '已取消',
    'refunded' => '已退款'
];

$statusClassMap = [
    'pending' => 'status-pending',
    'paid' => 'status-paid',
    'shipped' => 'status-shipped',
    'completed' => 'status-completed',
    'cancelled' => 'status-cancelled',
    'refunded' => 'status-refunded'
];
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>我的订单 - 58人气值商城</title>
    <style>
        .orders-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .page-header {
            margin-bottom: 30px;
        }
        
        .page-title {
            font-size: 28px;
            font-weight: bold;
            color: #333;
            margin-bottom: 10px;
        }
        
        .page-description {
            color: #666;
            font-size: 16px;
        }
        
        /* 订单筛选 */
        .orders-filter {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .filter-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .filter-tab {
            padding: 8px 16px;
            border: 1px solid #ddd;
            border-radius: 20px;
            color: #666;
            text-decoration: none;
            transition: all 0.3s ease;
            font-size: 14px;
        }
        
        .filter-tab:hover, .filter-tab.active {
            background: #3498db;
            color: white;
            border-color: #3498db;
        }
        
        .search-box {
            position: relative;
            max-width: 400px;
        }
        
        .search-input {
            width: 100%;
            padding: 10px 45px 10px 15px;
            border: 1px solid #ddd;
            border-radius: 20px;
            font-size: 14px;
            outline: none;
        }
        
        .search-btn {
            position: absolute;
            right: 5px;
            top: 50%;
            transform: translateY(-50%);
            background: #3498db;
            color: white;
            border: none;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        /* 订单列表 */
        .orders-list {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .order-item {
            border-bottom: 1px solid #f0f0f0;
            padding: 20px;
        }
        
        .order-item:last-child {
            border-bottom: none;
        }
        
        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #f8f9fa;
        }
        
        .order-info {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .order-number {
            font-weight: bold;
            color: #333;
            font-size: 16px;
        }
        
        .order-time {
            color: #666;
            font-size: 12px;
        }
        
        .order-status {
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .status-pending { background: #fff3cd; color: #856404; }
        .status-paid { background: #d1ecf1; color: #0c5460; }
        .status-shipped { background: #d4edda; color: #155724; }
        .status-completed { background: #e2e3e5; color: #383d41; }
        .status-cancelled { background: #f8d7da; color: #721c24; }
        .status-refunded { background: #e2e3e5; color: #383d41; }
        
        .order-products {
            margin-bottom: 15px;
        }
        
        .product-item {
            display: flex;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #f8f9fa;
        }
        
        .product-item:last-child {
            border-bottom: none;
        }
        
        .product-image {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 4px;
            margin-right: 15px;
        }
        
        .product-details {
            flex: 1;
        }
        
        .product-name {
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }
        
        .product-spec {
            color: #666;
            font-size: 12px;
            margin-bottom: 5px;
        }
        
        .product-price {
            color: #e74c3c;
            font-weight: bold;
        }
        
        .product-quantity {
            color: #666;
            font-size: 12px;
        }
        
        .order-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 15px;
            border-top: 1px solid #f8f9fa;
        }
        
        .order-total {
            font-size: 18px;
            font-weight: bold;
            color: #e74c3c;
        }
        
        .order-actions {
            display: flex;
            gap: 10px;
        }
        
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }
        
        .btn-detail {
            background: #3498db;
            color: white;
        }
        
        .btn-detail:hover {
            background: #2980b9;
        }
        
        .btn-pay {
            background: #e74c3c;
            color: white;
        }
        
        .btn-pay:hover {
            background: #c0392b;
        }
        
        .btn-cancel {
            background: #6c757d;
            color: white;
        }
        
        .btn-cancel:hover {
            background: #5a6268;
        }
        
        .btn-confirm {
            background: #28a745;
            color: white;
        }
        
        .btn-confirm:hover {
            background: #218838;
        }
        
        /* 分页 */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-top: 30px;
        }
        
        .page-link {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            color: #333;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .page-link:hover, .page-link.active {
            background: #3498db;
            color: white;
            border-color: #3498db;
        }
        
        .page-link.disabled {
            color: #999;
            cursor: not-allowed;
        }
        
        /* 空状态 */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }
        
        .empty-icon {
            font-size: 64px;
            color: #ddd;
            margin-bottom: 20px;
        }
        
        .empty-text {
            font-size: 18px;
            color: #666;
            margin-bottom: 20px;
        }
        
        /* 响应式设计 */
        @media (max-width: 768px) {
            .order-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .order-footer {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }
            
            .order-actions {
                width: 100%;
                justify-content: flex-start;
            }
            
            .filter-tabs {
                justify-content: center;
            }
        }
        @media (max-width: 480px) {
            .order-item { padding: 12px; }
            .order-product { flex-direction: column; align-items: flex-start; }
        }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="orders-container">
        <div class="page-header">
            <h1 class="page-title">我的订单</h1>
            <p class="page-description">管理您的所有订单信息</p>
        </div>
        
        <!-- 订单筛选 -->
        <div class="orders-filter">
            <div class="filter-tabs">
                <a href="?status=all" class="filter-tab <?php echo $status == 'all' ? 'active' : ''; ?>">
                    全部订单
                </a>
                <a href="?status=pending" class="filter-tab <?php echo $status == 'pending' ? 'active' : ''; ?>">
                    待付款
                </a>
                <a href="?status=paid" class="filter-tab <?php echo $status == 'paid' ? 'active' : ''; ?>">
                    待发货
                </a>
                <a href="?status=shipped" class="filter-tab <?php echo $status == 'shipped' ? 'active' : ''; ?>">
                    待收货
                </a>
                <a href="?status=completed" class="filter-tab <?php echo $status == 'completed' ? 'active' : ''; ?>">
                    已完成
                </a>
            </div>
            
            <form method="GET" action="">
                <div class="search-box">
                    <input type="text" name="search" class="search-input" placeholder="搜索订单号或商品名称..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="search-btn">
                        <i class="fas fa-search"></i>
                    </button>
                    <?php if ($status != 'all'): ?>
                        <input type="hidden" name="status" value="<?php echo $status; ?>">
                    <?php endif; ?>
                </div>
            </form>
        </div>
        
        <!-- 订单列表 -->
        <div class="orders-list">
            <?php if (empty($orders)): ?>
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="fas fa-file-invoice"></i>
                    </div>
                    <div class="empty-text">
                        <?php if ($status != 'all'): ?>
                            没有<?php echo $statusMap[$status] ?? ''; ?>的订单
                        <?php else: ?>
                            您还没有任何订单
                        <?php endif; ?>
                    </div>
                    <a href="../product/list.php" class="btn btn-detail">
                        <i class="fas fa-shopping-bag"></i> 去购物
                    </a>
                </div>
            <?php else: ?>
                <?php foreach ($orders as $orderItem): ?>
                    <div class="order-item">
                        <div class="order-header">
                            <div class="order-info">
                                <div class="order-number">
                                    订单号: <?php echo htmlspecialchars($orderItem['order_no']); ?>
                                </div>
                                <div class="order-time">
                                    下单时间: <?php echo date('Y-m-d H:i:s', strtotime($orderItem['created_at'])); ?>
                                </div>
                            </div>
                            <div class="order-status <?php echo $statusClassMap[$orderItem['status']] ?? ''; ?>">
                                <?php echo $statusMap[$orderItem['status']] ?? '未知状态'; ?>
                            </div>
                        </div>
                        
                        <div class="order-products">
                            <?php 
                            // 获取订单商品详情
                            $orderDetails = $order->getOrderDetails($orderItem['id']);
                            foreach ($orderDetails as $detail): 
                            ?>
                                <div class="product-item">
                                    <img src="<?php echo htmlspecialchars(normalizeImageUrl($detail['image_url'])); ?>"
                                         alt="<?php echo htmlspecialchars($detail['product_name']); ?>"
                                         class="product-image">
                                    
                                    <div class="product-details">
                                        <div class="product-name">
                                            <?php echo htmlspecialchars($detail['product_name']); ?>
                                        </div>
                                        <div class="product-spec">
                                            <?php echo htmlspecialchars($detail['specification'] ?: '默认规格'); ?>
                                        </div>
                                        <div class="product-price">
                                            <span class="bct-symbol">Ⓟ</span><?php echo number_format($detail['unit_price'], 0); ?> 人气值
                                        </div>
                                    </div>
                                    
                                    <div class="product-quantity">
                                        x<?php echo $detail['quantity']; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="order-footer">
                            <div class="order-total">
                                实付: <span class="bct-symbol">Ⓟ</span><?php echo number_format($orderItem['total_amount'], 0); ?> 人气值
                            </div>
                            
                            <div class="order-actions">
                                <a href="order_detail.php?id=<?php echo $orderItem['id']; ?>" class="btn btn-detail">
                                    <i class="fas fa-eye"></i> 查看详情
                                </a>
                                
                                <?php if ($orderItem['status'] == 'pending'): ?>
                                    <a href="pay.php?order_id=<?php echo $orderItem['id']; ?>" class="btn btn-pay">
                                        <i class="fas fa-credit-card"></i> 立即付款
                                    </a>
                                    <button class="btn btn-cancel" onclick="cancelOrder(<?php echo $orderItem['id']; ?>)">
                                        <i class="fas fa-times"></i> 取消订单
                                    </button>
                                <?php elseif ($orderItem['status'] == 'shipped'): ?>
                                    <button class="btn btn-confirm" onclick="confirmReceipt(<?php echo $orderItem['id']; ?>)">
                                        <i class="fas fa-check"></i> 确认收货
                                    </button>
                                <?php elseif ($orderItem['status'] == 'completed'): ?>
                                    <a href="review.php?order_id=<?php echo $orderItem['id']; ?>" class="btn btn-confirm" style="background:#e74c3c;color:#fff;border-color:#e74c3c;">
                                        <i class="fas fa-pen"></i> 去评价
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <!-- 分页 -->
        <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <!-- 上一页 -->
                <?php if ($page > 1): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" class="page-link">
                        <i class="fas fa-chevron-left"></i> 上一页
                    </a>
                <?php else: ?>
                    <span class="page-link disabled">
                        <i class="fas fa-chevron-left"></i> 上一页
                    </span>
                <?php endif; ?>
                
                <!-- 页码 -->
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <?php if ($i == 1 || $i == $totalPages || ($i >= $page - 2 && $i <= $page + 2)): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
                           class="page-link <?php echo $i == $page ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php elseif ($i == $page - 3 || $i == $page + 3): ?>
                        <span class="page-link">...</span>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <!-- 下一页 -->
                <?php if ($page < $totalPages): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" class="page-link">
                        下一页 <i class="fas fa-chevron-right"></i>
                    </a>
                <?php else: ?>
                    <span class="page-link disabled">
                        下一页 <i class="fas fa-chevron-right"></i>
                    </span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
        function cancelOrder(orderId) {
            if (confirm('确定要取消这个订单吗？')) {
                fetch('cancel_order.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'order_id=' + orderId
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('订单已取消');
                        location.reload();
                    } else {
                        alert('取消失败: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('取消订单时发生错误');
                });
            }
        }
        
        function confirmReceipt(orderId) {
            if (confirm('确定已经收到商品了吗？')) {
                fetch('confirm_receipt.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'order_id=' + orderId
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('确认收货成功');
                        location.reload();
                    } else {
                        alert('确认失败: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('确认收货时发生错误');
                });
            }
        }
    </script>
    
    <?php include '../includes/footer.php'; ?>
</body>
</html> 