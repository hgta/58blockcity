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

/*try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("数据库连接失败: " . $e->getMessage());
}*/

// 加载购物车类
require_once '../../classes/Cart.php';
require_once '../../classes/Product.php';

$cart = new Cart($pdo);
$product = new Product($pdo);

$userId = $_SESSION['user_id'];
$cartItems = $cart->getCartItems($userId);

// 按店铺分组
$groupedItems = [];
foreach ($cartItems as $item) {
    $shopId = $item['shop_id'] ?: 0;
    $shopName = $item['shop_name'] ?: '未知店铺';
    if (!isset($groupedItems[$shopId])) {
        $groupedItems[$shopId] = [
            'shop_name' => $shopName,
            'items' => []
        ];
    }
    $groupedItems[$shopId]['items'][] = $item;
}

// 计算总金额（全部）
$totalAmount = 0;
foreach ($cartItems as $item) {
    $totalAmount += $item['price'] * $item['quantity'];
}

// 处理购物车操作
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_quantity'])) {
        $cartItemId = $_POST['cart_item_id'];
        $quantity = intval($_POST['quantity']);
        
        if ($quantity > 0) {
            $cart->updateQuantity($cartItemId, $quantity, $userId);
        } else {
            $cart->removeItem($cartItemId, $userId);
        }
        header("Location: index.php");
        exit();
    }
    
    if (isset($_POST['remove_item'])) {
        $cartItemId = $_POST['cart_item_id'];
        $cart->removeItem($cartItemId, $userId);
        header("Location: index.php");
        exit();
    }
    
    if (isset($_POST['clear_cart'])) {
        $cart->clearCart($userId);
        header("Location: index.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>购物车 - 58人气值商城</title>
    <style>
        .bct-symbol {
            font-family: Arial, sans-serif;
            font-weight: bold;
            color: #e74c3c;
        }
        
        .cart-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .cart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 15px;
        }
        
        .cart-title {
            font-size: 24px;
            font-weight: bold;
            color: #333;
        }
        
        .cart-items {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .cart-item {
            display: flex;
            align-items: center;
            padding: 20px;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .cart-item:last-child {
            border-bottom: none;
        }
        
        .item-image {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 8px;
            margin-right: 20px;
        }
        
        .item-details {
            flex: 1;
        }
        
        .item-name {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 5px;
            color: #333;
        }
        
        .item-price {
            font-size: 16px;
            color: #e74c3c;
            font-weight: bold;
        }
        
        .quantity-controls {
            display: flex;
            align-items: center;
            margin: 10px 0;
        }
        
        .quantity-btn {
            width: 30px;
            height: 30px;
            border: 1px solid #ddd;
            background: #f8f9fa;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .quantity-input {
            width: 50px;
            height: 30px;
            border: 1px solid #ddd;
            text-align: center;
            margin: 0 5px;
        }
        
        .item-total {
            font-size: 18px;
            font-weight: bold;
            color: #333;
            margin-left: 20px;
        }
        
        .remove-btn {
            background: #e74c3c;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            margin-left: 20px;
        }
        
        .remove-btn:hover {
            background: #c0392b;
        }
        
        .cart-summary {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 20px;
            margin-top: 20px;
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            font-size: 16px;
        }
        
        .summary-total {
            font-size: 20px;
            font-weight: bold;
            color: #e74c3c;
            border-top: 2px solid #f0f0f0;
            padding-top: 10px;
            margin-top: 10px;
        }
        
        .cart-actions {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }
        
        .btn-continue {
            background: #6c757d;
            color: white;
        }
        
        .btn-continue:hover {
            background: #5a6268;
        }
        
        .btn-checkout {
            background: #28a745;
            color: white;
        }
        
        .btn-checkout:hover {
            background: #218838;
        }
        
        .btn-clear {
            background: #dc3545;
            color: white;
        }
        
        .btn-clear:hover {
            background: #c82333;
        }
        
        .empty-cart {
            text-align: center;
            padding: 60px 20px;
        }
        
        .empty-cart-icon {
            font-size: 64px;
            color: #ddd;
            margin-bottom: 20px;
        }
        
        .empty-cart-message {
            font-size: 18px;
            color: #666;
            margin-bottom: 20px;
        }
        
        .shop-group {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
            margin-bottom: 20px;
        }
        
        .shop-header {
            background: #f8f9fa;
            padding: 12px 20px;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: bold;
            color: #333;
        }
        
        .shop-header input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        
        .cart-item {
            display: flex;
            align-items: center;
            padding: 20px;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .cart-item:last-child {
            border-bottom: none;
        }
        
        .item-checkbox {
            margin-right: 15px;
        }
        
        .item-checkbox input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="cart-container">
        <div class="cart-header">
            <h1 class="cart-title">我的购物车</h1>
            <?php if (!empty($cartItems)): ?>
                <form method="POST" style="display: inline;">
                    <button type="submit" name="clear_cart" class="btn btn-clear" onclick="return confirm('确定要清空购物车吗？')">
                        <i class="fas fa-trash"></i> 清空购物车
                    </button>
                </form>
            <?php endif; ?>
        </div>
        
        <?php if (empty($cartItems)): ?>
            <div class="empty-cart">
                <div class="empty-cart-icon">
                    <i class="fas fa-shopping-cart"></i>
                </div>
                <div class="empty-cart-message">
                    您的购物车是空的
                </div>
                <a href="../product/list.php" class="btn btn-continue">
                    <i class="fas fa-shopping-bag"></i> 继续购物
                </a>
            </div>
        <?php else: ?>
            <form method="GET" action="checkout.php" id="checkout-form">
                <?php foreach ($groupedItems as $shopId => $group): ?>
                <div class="shop-group">
                    <div class="shop-header">
                        <input type="checkbox" class="shop-checkbox" data-shop="<?php echo $shopId; ?>" onchange="toggleShop(<?php echo $shopId; ?>)">
                        <i class="fas fa-store"></i> <?php echo htmlspecialchars($group['shop_name']); ?>
                    </div>
                    <?php foreach ($group['items'] as $item): ?>
                    <div class="cart-item">
                        <div class="item-checkbox">
                            <input type="checkbox" name="selected_items[]" value="<?php echo $item['id']; ?>" class="item-cb shop-<?php echo $shopId; ?>" data-price="<?php echo $item['price']; ?>" data-qty="<?php echo $item['quantity']; ?>" onchange="updateSelectedTotal()">
                        </div>
                        <img src="<?php echo htmlspecialchars($item['image_url'] ?: '../assets/images/default-product.jpg'); ?>" 
                             alt="<?php echo htmlspecialchars($item['name']); ?>" 
                             class="item-image">
                        
                        <div class="item-details">
                            <div class="item-name"><?php echo htmlspecialchars($item['name']); ?></div>
                            <div class="item-price"><span class="bct-symbol">Ⓟ</span><?php echo number_format($item['price'], 0); ?> 人气值</div>
                            
                            <form method="POST" class="quantity-controls">
                                <input type="hidden" name="cart_item_id" value="<?php echo $item['id']; ?>">
                                <button type="button" class="quantity-btn" onclick="updateQuantity(<?php echo $item['id']; ?>, -1)">-</button>
                                <input type="number" name="quantity" value="<?php echo $item['quantity']; ?>" 
                                       min="1" class="quantity-input" id="quantity-<?php echo $item['id']; ?>">
                                <button type="button" class="quantity-btn" onclick="updateQuantity(<?php echo $item['id']; ?>, 1)">+</button>
                                <button type="submit" name="update_quantity" style="display: none;"></button>
                            </form>
                        </div>
                        
                        <div class="item-total">
                            <span class="bct-symbol">Ⓟ</span><?php echo number_format($item['price'] * $item['quantity'], 0); ?> 人气值
                        </div>
                        
                        <form method="POST">
                            <input type="hidden" name="cart_item_id" value="<?php echo $item['id']; ?>">
                            <button type="submit" name="remove_item" class="remove-btn" onclick="return confirm('确定要移除这个商品吗？')">
                                <i class="fas fa-times"></i>
                            </button>
                        </form>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endforeach; ?>
            </form>
            
            <div class="cart-summary">
                <div class="summary-row">
                    <span>已选商品:</span>
                    <span id="selected-count">0 件</span>
                </div>
                <div class="summary-row summary-total">
                    <span>选中总计:</span>
                    <span><span class="bct-symbol">Ⓟ</span><span id="selected-total">0</span> 人气值</span>
                </div>
                
                <div class="cart-actions">
                    <a href="../product/list.php" class="btn btn-continue">
                        <i class="fas fa-arrow-left"></i> 继续购物
                    </a>
                    <button type="button" class="btn btn-checkout" onclick="goCheckout()">
                        <i class="fas fa-credit-card"></i> 去结算
                    </button>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
        function updateQuantity(itemId, change) {
            const input = document.getElementById('quantity-' + itemId);
            let newQuantity = parseInt(input.value) + change;
            
            if (newQuantity < 1) newQuantity = 1;
            
            input.value = newQuantity;
            
            // 自动提交表单
            input.form.querySelector('button[type="submit"]').click();
        }
        
        // 全选/取消全选某店铺
        function toggleShop(shopId) {
            const shopCb = document.querySelector('.shop-checkbox[data-shop="' + shopId + '"]');
            const items = document.querySelectorAll('.shop-' + shopId);
            items.forEach(function(cb) {
                cb.checked = shopCb.checked;
            });
            updateSelectedTotal();
        }
        
        // 更新选中商品的总金额和数量
        function updateSelectedTotal() {
            let total = 0;
            let count = 0;
            document.querySelectorAll('input[name="selected_items[]"]:checked').forEach(function(cb) {
                const price = parseFloat(cb.dataset.price);
                const qty = parseInt(cb.dataset.qty);
                total += price * qty;
                count += qty;
            });
            document.getElementById('selected-total').textContent = total.toLocaleString();
            document.getElementById('selected-count').textContent = count + ' 件';
        }
        
        // 去结算
        function goCheckout() {
            const selected = document.querySelectorAll('input[name="selected_items[]"]:checked');
            if (selected.length === 0) {
                alert('请至少选择一件商品');
                return;
            }
            document.getElementById('checkout-form').submit();
        }
        
        // 页面加载时默认全选
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.shop-checkbox').forEach(function(cb) {
                cb.checked = true;
                toggleShop(cb.dataset.shop);
            });
        });
    </script>
    
    <?php include '../includes/footer.php'; ?>
</body>
</html>