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

 

// 加载相关类
require_once '../../classes/Cart.php';
require_once '../../classes/Product.php';
require_once '../../classes/Order.php';
require_once '../../classes/Address.php';
require_once '../../classes/User.php';

$cart = new Cart($pdo);
$product = new Product($pdo);
$order = new Order($pdo);
$address = new Address($pdo);
$user = new User($pdo);

$userId = $_SESSION['user_id'];
$userInfo = $user->getUserById($userId);

// 获取选中的购物车项
$selectedItemIds = $_GET['selected_items'] ?? [];
if (!is_array($selectedItemIds)) {
    $selectedItemIds = [$selectedItemIds];
}
$selectedItemIds = array_map('intval', $selectedItemIds);

// 获取购物车商品
$allCartItems = $cart->getCartItems($userId);

// 过滤出选中的商品（无选中时默认全部，适用于立即购买场景）
$cartItems = [];
if (empty($selectedItemIds)) {
    $cartItems = $allCartItems;
} else {
    foreach ($allCartItems as $item) {
        if (in_array($item['id'], $selectedItemIds)) {
            $cartItems[] = $item;
        }
    }
}

// 验证购物车商品
$validItems = [];
$invalidItems = [];
foreach ($cartItems as $item) {
    $productInfo = $product->getProductById($item['product_id']);
    if (!$productInfo || $productInfo['status'] != 'active') {
        $invalidItems[] = ['item' => $item, 'reason' => '商品已下架'];
    } elseif ($productInfo['stock'] < $item['quantity']) {
        $invalidItems[] = ['item' => $item, 'reason' => '库存不足，当前库存: ' . $productInfo['stock']];
    } else {
        $item['payment_city'] = $productInfo['payment_city'] ?? '';
        $validItems[] = $item;
    }
}

// 按店铺分组
$shopGroups = [];
foreach ($validItems as $item) {
    $shopId = $item['shop_id'] ?: 0;
    if (!isset($shopGroups[$shopId])) {
        $shopGroups[$shopId] = [
            'shop_id' => $shopId,
            'shop_name' => $item['shop_name'] ?: '未知店铺',
            'items' => [],
            'total' => 0
        ];
    }
    $shopGroups[$shopId]['items'][] = $item;
    $shopGroups[$shopId]['total'] += $item['price'] * $item['quantity'];
}

// 计算总金额
$totalAmount = 0;
foreach ($validItems as $item) {
    $totalAmount += $item['price'] * $item['quantity'];
}

// 获取用户地址
$userAddresses = $address->getUserAddresses($userId);
$defaultAddress = $address->getDefaultAddress($userId);

// 处理结账请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selectedAddressId = intval($_POST['address_id']);
    $paymentMethod = $_POST['payment_method'] ?? 'bct';
    $remark = trim($_POST['remark'] ?? '');
    
    // 验证地址
    if (!$address->validateAddressOwnership($selectedAddressId, $userId)) {
        $error = "请选择有效的收货地址";
    } elseif (empty($validItems)) {
        $error = "购物车中没有有效商品";
    } else {
        try {
            $createdOrderIds = [];
            
            // 获取地址信息
            $selectedAddress = $address->getAddressById($selectedAddressId, $userId);
            $shippingAddress = $selectedAddress['province'] . $selectedAddress['city'] . $selectedAddress['district'] . $selectedAddress['detail'];
            
            // 按店铺分别创建订单
            foreach ($shopGroups as $shopId => $group) {
                $pdo->beginTransaction();
                
                try {
                    // 创建订单
                    $orderData = [
                        'user_id' => $userId,
                        'shop_id' => $shopId,
                        'total_amount' => $group['total'],
                        'payment_city' => ($paymentMethod == 'bct') ? ($group['items'][0]['payment_city'] ?? '') : '',
                        'payment_amount' => ($paymentMethod == 'bct') ? $group['total'] : 0,
                        'payment_method' => $paymentMethod,
                        'shipping_address' => $shippingAddress,
                        'buyer_note' => $remark
                    ];
                    
                    $orderId = $order->createOrder($orderData);
                    
                    if (!$orderId) {
                        throw new Exception("创建订单失败");
                    }
                    
                    // 添加订单详情
                    foreach ($group['items'] as $item) {
                        $orderDetailData = [
                            'product_id' => $item['product_id'],
                            'product_name' => $item['name'],
                            'price' => $item['price'],
                            'quantity' => $item['quantity'],
                            'image_url' => $item['image_url']
                        ];
                        
                        if (!$order->addOrderDetail($orderId, $orderDetailData)) {
                            throw new Exception("添加订单详情失败");
                        }
                        
                        // 更新商品库存
                        if (!$product->updateStock($item['product_id'], $item['quantity'])) {
                            throw new Exception("商品库存更新失败");
                        }
                        
                        // 从购物车移除该商品
                        $cart->removeItem($item['id'], $userId);
                    }
                    
                    $pdo->commit();
                    $createdOrderIds[] = $orderId;
                    
                } catch (Exception $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    throw $e;
                }
            }
            
            // 跳转到订单列表或订单详情
            if (count($createdOrderIds) === 1) {
                header("Location: ../user/order_detail.php?id=" . $createdOrderIds[0]);
            } else {
                header("Location: ../user/orders.php?success=" . urlencode("成功创建 " . count($createdOrderIds) . " 个订单"));
            }
            exit();
            
        } catch (Exception $e) {
            try {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
            } catch (Exception $rollbackException) {
                error_log("事务回滚失败: " . $rollbackException->getMessage());
            }
            
            $error = "订单创建失败: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>订单结算 - 58人气值商城</title>
    <style>
        .bct-symbol {
            font-family: Arial, sans-serif;
            font-weight: bold;
            color: #e74c3c;
        }
        
        .checkout-container {
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
        
        .checkout-layout {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
        }
        
        /* 消息样式 */
        .message {
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        .error-message {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .warning-message {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        
        /* 地址选择 */
        .address-section {
            background: white;
            border-radius: 8px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .section-title {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 20px;
            color: #333;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 10px;
        }
        
        .address-list {
            display: grid;
            gap: 15px;
        }
        
        .address-item {
            border: 2px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .address-item:hover {
            border-color: #3498db;
        }
        
        .address-item.selected {
            border-color: #e74c3c;
            background: #fff8f8;
        }
        
        .address-item.default {
            border-left: 4px solid #e74c3c;
        }
        
        .address-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 10px;
        }
        
        .address-name {
            font-weight: bold;
            font-size: 16px;
        }
        
        .address-phone {
            color: #666;
            margin-bottom: 8px;
        }
        
        .address-detail {
            color: #333;
            line-height: 1.5;
        }
        
        .address-tag {
            background: #e74c3c;
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 12px;
            margin-left: 10px;
        }
        
        .add-address-btn {
            display: block;
            width: 100%;
            padding: 15px;
            border: 2px dashed #ddd;
            border-radius: 8px;
            background: white;
            color: #666;
            text-align: center;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .add-address-btn:hover {
            border-color: #3498db;
            color: #3498db;
        }
        
        /* 商品列表 */
        .products-section {
            background: white;
            border-radius: 8px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .product-item {
            display: flex;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .product-item:last-child {
            border-bottom: none;
        }
        
        .product-image {
            width: 80px;
            height: 80px;
            border-radius: 8px;
            margin-right: 15px;
            object-fit: cover;
        }
        
        .product-info {
            flex: 1;
        }
        
        .product-name {
            font-weight: bold;
            margin-bottom: 5px;
            color: #333;
        }
        
        .product-price {
            color: #e74c3c;
            font-weight: bold;
        }
        
        .product-quantity {
            color: #666;
            font-size: 14px;
        }
        
        /* 支付方式 */
        .payment-section {
            background: white;
            border-radius: 8px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .payment-methods {
            display: grid;
            gap: 10px;
        }
        
        .payment-method {
            display: flex;
            align-items: center;
            padding: 15px;
            border: 2px solid #ddd;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .payment-method:hover {
            border-color: #3498db;
        }
        
        .payment-method.selected {
            border-color: #e74c3c;
            background: #fff8f8;
        }
        
        .payment-icon {
            width: 40px;
            height: 40px;
            background: #f8f9fa;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            color: #3498db;
            font-size: 18px;
        }
        
        .payment-info {
            flex: 1;
        }
        
        .payment-name {
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .payment-desc {
            color: #666;
            font-size: 14px;
        }
        
        /* 订单摘要 */
        .order-summary {
            background: white;
            border-radius: 8px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: sticky;
            top: 20px;
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .summary-total {
            font-size: 20px;
            font-weight: bold;
            color: #e74c3c;
            border-top: 2px solid #f0f0f0;
            padding-top: 10px;
            margin-top: 10px;
        }
        
        /* 备注 */
        .remark-section {
            background: white;
            border-radius: 8px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .remark-input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            resize: vertical;
            min-height: 80px;
        }
        
        .remark-input:focus {
            border-color: #3498db;
            outline: none;
        }
        
        /* 提交按钮 */
        .submit-section {
            background: white;
            border-radius: 8px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .btn-submit {
            width: 100%;
            padding: 15px;
            background: #e74c3c;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 18px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-submit:hover {
            background: #c0392b;
            transform: translateY(-2px);
        }
        
        .btn-submit:disabled {
            background: #bdc3c7;
            cursor: not-allowed;
            transform: none;
        }
        
        /* 响应式设计 */
        @media (max-width: 768px) {
            .checkout-layout {
                grid-template-columns: 1fr;
            }
            
            .order-summary {
                position: static;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="checkout-container">
        <div class="page-header">
            <h1 class="page-title">订单结算</h1>
        </div>
        
        <!-- 错误消息 -->
        <?php if (isset($error)): ?>
            <div class="message error-message">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <!-- 无效商品警告 -->
        <?php if (!empty($invalidItems)): ?>
            <div class="message warning-message">
                <i class="fas fa-exclamation-triangle"></i> 
                以下商品无法购买：
                <?php 
                $invalidNames = [];
                foreach ($invalidItems as $item) {
                    $invalidNames[] = $item['item']['name'] . ' (' . $item['reason'] . ')';
                }
                echo implode('，', $invalidNames);
                ?>
            </div>
        <?php endif; ?>
        
        <?php if (empty($validItems)): ?>
            <div class="message warning-message">
                <i class="fas fa-shopping-cart"></i> 
                购物车中没有可购买的商品，<a href="../product/list.php">去购物</a>
            </div>
        <?php else: ?>
            <form method="POST" action="" id="checkout-form">
                <div class="checkout-layout">
                    <!-- 左侧：表单区域 -->
                    <div class="checkout-main">
                        <!-- 地址选择 -->
                        <div class="address-section">
                            <h3 class="section-title">选择收货地址</h3>
                            <div class="address-list">
                                <?php if (empty($userAddresses)): ?>
                                    <a href="../user/address.php" class="add-address-btn">
                                        <i class="fas fa-plus"></i> 添加收货地址
                                    </a>
                                <?php else: ?>
                                    <?php foreach ($userAddresses as $addr): ?>
                                        <div class="address-item <?php echo $addr['is_default'] ? 'default' : ''; ?> 
                                            <?php echo ($defaultAddress && $addr['id'] == $defaultAddress['id']) ? 'selected' : ''; ?>" 
                                            onclick="selectAddress(<?php echo $addr['id']; ?>)">
                                            <div class="address-header">
                                                <div class="address-name">
                                                    <?php echo htmlspecialchars($addr['name']); ?>
                                                    <?php if ($addr['is_default']): ?>
                                                        <span class="address-tag">默认</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="address-phone"><?php echo htmlspecialchars($addr['phone']); ?></div>
                                            <div class="address-detail">
                                                <?php echo htmlspecialchars($addr['province'] . $addr['city'] . $addr['district'] . $addr['detail']); ?>
                                            </div>
                                            <input type="radio" name="address_id" value="<?php echo $addr['id']; ?>" 
                                                <?php echo ($defaultAddress && $addr['id'] == $defaultAddress['id']) ? 'checked' : ''; ?>
                                                style="display: none;">
                                        </div>
                                    <?php endforeach; ?>
                                    <a href="../user/address.php" class="add-address-btn">
                                        <i class="fas fa-plus"></i> 管理地址
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- 商品列表 -->
                        <div class="products-section">
                            <h3 class="section-title">确认商品信息</h3>
                            <div class="products-list">
                                <?php foreach ($validItems as $item): ?>
                                    <div class="product-item">
                                        <img src="<?php echo htmlspecialchars($item['image_url'] ?: '../assets/images/default-product.jpg'); ?>" 
                                             alt="<?php echo htmlspecialchars($item['name']); ?>" 
                                             class="product-image">
                                        <div class="product-info">
                                            <div class="product-name"><?php echo htmlspecialchars($item['name']); ?></div>
                                            <div class="product-price"><span class="bct-symbol">Ⓟ</span><?php echo number_format($item['price'], 0); ?> 人气值</div>
                                        </div>
                                        <div class="product-quantity">x<?php echo $item['quantity']; ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <!-- 支付方式 -->
                        <div class="payment-section">
                            <h3 class="section-title">选择支付方式</h3>
                            <div class="payment-methods">
                                <div class="payment-method selected" onclick="selectPayment('bct')">
                                    <div class="payment-icon">
                                        <i class="fas fa-coins"></i>
                                    </div>
                                    <div class="payment-info">
                                        <div class="payment-name">BCT支付</div>
                                        <div class="payment-desc">使用人气值支付</div>
                                    </div>
                                    <input type="radio" name="payment_method" value="bct" checked style="display: none;">
                                </div>
                                <div class="payment-method" style="opacity:0.5;cursor:not-allowed;">
                                    <div class="payment-icon"><i class="fab fa-weixin"></i></div>
                                    <div class="payment-info">
                                        <div class="payment-name">微信支付 <small style="color:#999;">(即将开放)</small></div>
                                    </div>
                                </div>
                                <div class="payment-method" style="opacity:0.5;cursor:not-allowed;">
                                    <div class="payment-icon"><i class="fab fa-alipay"></i></div>
                                    <div class="payment-info">
                                        <div class="payment-name">支付宝 <small style="color:#999;">(即将开放)</small></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- 订单备注 -->
                        <div class="remark-section">
                            <h3 class="section-title">订单备注</h3>
                            <textarea name="remark" class="remark-input" placeholder="选填：备注信息（如：配送时间要求等）"></textarea>
                        </div>
                    </div>
                    
                    <!-- 右侧：订单摘要 -->
                    <div class="checkout-sidebar">
                        <div class="order-summary">
                            <h3 class="section-title">订单摘要</h3>
                            <div class="summary-row">
                                <span>商品总价</span>
                                <span><span class="bct-symbol">Ⓟ</span><?php echo number_format($totalAmount, 0); ?> 人气值</span>
                            </div>
                            <div class="summary-row">
                                <span>运费</span>
                                <span style="color:#27ae60;">暂免</span>
                            </div>
                            <div class="summary-row">
                                <span>优惠</span>
                                <span style="color:#999;">暂无</span>
                            </div>
                            <div class="summary-row summary-total">
                                <span>应付总额</span>
                                <span><span class="bct-symbol">Ⓟ</span><?php echo number_format($totalAmount, 0); ?> 人气值</span>
                            </div>
                            
                            <button type="submit" class="btn-submit" id="submit-btn">
                                <i class="fas fa-credit-card"></i> 提交订单
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        <?php endif; ?>
    </div>
    
    <script>
        // 选择地址
        function selectAddress(addressId) {
            // 移除所有选中状态
            document.querySelectorAll('.address-item').forEach(item => {
                item.classList.remove('selected');
            });
            
            // 设置选中状态
            const selectedItem = document.querySelector(`.address-item input[value="${addressId}"]`).parentElement;
            selectedItem.classList.add('selected');
            
            // 更新radio选中状态
            document.querySelectorAll('input[name="address_id"]').forEach(radio => {
                radio.checked = radio.value == addressId;
            });
        }
        
        // 选择支付方式
        function selectPayment(method) {
            // 移除所有选中状态
            document.querySelectorAll('.payment-method').forEach(item => {
                item.classList.remove('selected');
            });
            
            // 设置选中状态
            const selectedItem = document.querySelector(`.payment-method input[value="${method}"]`).parentElement;
            selectedItem.classList.add('selected');
            
            // 更新radio选中状态
            document.querySelectorAll('input[name="payment_method"]').forEach(radio => {
                radio.checked = radio.value == method;
            });
        }
        
        // 表单验证
        document.getElementById('checkout-form').addEventListener('submit', function(e) {
            const addressSelected = document.querySelector('input[name="address_id"]:checked');
            const submitBtn = document.getElementById('submit-btn');
            
            if (!addressSelected) {
                e.preventDefault();
                alert('请选择收货地址');
                return;
            }
            
            // 禁用提交按钮防止重复提交
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 处理中...';
        });
        
        // 页面加载时初始化
        document.addEventListener('DOMContentLoaded', function() {
            // 如果没有默认地址，选择第一个地址
            if (!document.querySelector('input[name="address_id"]:checked')) {
                const firstAddress = document.querySelector('input[name="address_id"]');
                if (firstAddress) {
                    firstAddress.checked = true;
                    firstAddress.parentElement.classList.add('selected');
                }
            }
        });
    </script>
    
    <?php include '../includes/footer.php'; ?>
</body>
</html> 