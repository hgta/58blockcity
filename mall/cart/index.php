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
$totalAmount = 0;

// 计算总金额
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
            <div class="cart-items">
                <?php foreach ($cartItems as $item): ?>
                    <div class="cart-item">
                        <img src="<?php echo htmlspecialchars($item['image_url'] ?: '../assets/images/default-product.jpg'); ?>" 
                             alt="<?php echo htmlspecialchars($item['name']); ?>" 
                             class="item-image">
                        
                        <div class="item-details">
                            <div class="item-name"><?php echo htmlspecialchars($item['name']); ?></div>
                            <div class="item-price">¥<?php echo number_format($item['price'], 2); ?></div>
                            
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
                            ¥<?php echo number_format($item['price'] * $item['quantity'], 2); ?>
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
            
            <div class="cart-summary">
                <div class="summary-row">
                    <span>商品总数:</span>
                    <span><?php echo array_sum(array_column($cartItems, 'quantity')); ?> 件</span>
                </div>
                <div class="summary-row summary-total">
                    <span>总计:</span>
                    <span>¥<?php echo number_format($totalAmount, 2); ?></span>
                </div>
                
                <div class="cart-actions">
                    <a href="../product/list.php" class="btn btn-continue">
                        <i class="fas fa-arrow-left"></i> 继续购物
                    </a>
                    <a href="checkout.php" class="btn btn-checkout">
                        <i class="fas fa-credit-card"></i> 去结算
                    </a>
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
    </script>
    
    <?php include '../includes/footer.php'; ?>
</body>
</html>