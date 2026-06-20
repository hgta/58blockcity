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

// 加载购物车类
require_once '../../classes/Cart.php';
require_once '../../classes/Product.php';
require_once '../../classes/Shop.php';

$cart = new Cart($pdo);
$product = new Product($pdo);
$shop = new Shop($pdo);

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
            'items' => [],
            'payment_settings' => []
        ];
    }
    $groupedItems[$shopId]['items'][] = $item;
}

// 加载每个店铺支持的支付城市/区块
foreach ($groupedItems as $shopId => &$group) {
    $group['payment_settings'] = $shop->getShopPaymentSettings($shopId);
}
unset($group);

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

// 格式化支付城市/区块显示
function formatPaymentSetting($settings) {
    if (empty($settings)) {
        return '<span class="payment-empty">暂未设置人气值收款</span>';
    }
    $parts = [];
    foreach ($settings as $s) {
        $city = htmlspecialchars($s['city_name'] ?? ($s['city'] ?? '-'));
        $zone = htmlspecialchars($s['block_zone'] ?? '');
        $number = htmlspecialchars($s['block_number'] ?? '');
        $blockText = $zone || $number ? ($zone ? $zone . '区' : '') . ($number ? ' #' . $number : '') : '未绑定区块';
        $parts[] = '<span class="payment-badge">' . $city . ' ' . $blockText . '</span>';
    }
    return implode('', $parts);
}

// 统一处理商品图片路径
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
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>购物车 - 58人气值商城</title>
    <style>
        :root {
            --primary: #e74c3c;
            --primary-dark: #c0392b;
            --secondary: #3498db;
            --success: #27ae60;
            --bg: #f5f7fa;
            --card: #ffffff;
            --text: #333333;
            --text-muted: #666666;
            --border: #e8e8e8;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            background: var(--bg);
            color: var(--text);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }

        .bct-symbol {
            font-family: Arial, sans-serif;
            font-weight: bold;
            color: var(--primary);
            margin-right: 2px;
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
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 2px solid var(--border);
        }

        .cart-title {
            font-size: 26px;
            font-weight: bold;
            color: var(--text);
            margin: 0;
        }

        .cart-subtitle {
            color: var(--text-muted);
            font-size: 14px;
            margin-top: 4px;
        }

        .cart-layout {
            display: grid;
            grid-template-columns: 1fr 340px;
            gap: 24px;
            align-items: start;
        }

        .cart-main {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .shop-group {
            background: var(--card);
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
            overflow: hidden;
        }

        .shop-header {
            background: #f8f9fa;
            padding: 14px 20px;
            border-bottom: 1px solid var(--border);
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 12px;
        }

        .shop-info {
            display: flex;
            align-items: center;
            gap: 10px;
            flex: 1;
            min-width: 200px;
        }

        .shop-header input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
            flex-shrink: 0;
        }

        .shop-name {
            font-weight: bold;
            color: var(--text);
            font-size: 15px;
        }

        .shop-payment {
            flex: 2;
            min-width: 280px;
            font-size: 13px;
            color: var(--text-muted);
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 6px;
        }

        .shop-payment-label {
            color: var(--text-muted);
            white-space: nowrap;
        }

        .payment-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: #fff7ed;
            color: #c2410c;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            border: 1px solid #fed7aa;
            white-space: nowrap;
        }

        .payment-empty {
            color: #999;
            font-style: italic;
        }

        .cart-item {
            display: flex;
            align-items: center;
            padding: 18px 20px;
            border-bottom: 1px solid var(--border);
            gap: 16px;
        }

        .cart-item:last-child {
            border-bottom: none;
        }

        .item-checkbox {
            flex-shrink: 0;
        }

        .item-checkbox input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .item-image {
            width: 90px;
            height: 90px;
            object-fit: cover;
            border-radius: 10px;
            flex-shrink: 0;
            background: #f0f0f0;
        }

        .item-details {
            flex: 1;
            min-width: 0;
        }

        .item-name {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--text);
            line-height: 1.4;
        }

        .item-price {
            font-size: 15px;
            color: var(--primary);
            font-weight: bold;
            margin-bottom: 10px;
        }

        .quantity-controls {
            display: flex;
            align-items: center;
            gap: 0;
        }

        .quantity-btn {
            width: 32px;
            height: 32px;
            border: 1px solid #ddd;
            background: #f8f9fa;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            color: #555;
            transition: all 0.2s;
        }

        .quantity-btn:hover {
            background: #e9ecef;
        }

        .quantity-btn:first-of-type {
            border-radius: 6px 0 0 6px;
        }

        .quantity-plus {
            border-radius: 0 6px 6px 0;
        }

        .quantity-input {
            width: 54px;
            height: 32px;
            border: 1px solid #ddd;
            border-left: none;
            border-right: none;
            text-align: center;
            font-size: 14px;
            color: var(--text);
        }

        .item-total {
            font-size: 17px;
            font-weight: bold;
            color: var(--text);
            text-align: right;
            min-width: 110px;
            flex-shrink: 0;
        }

        .remove-btn {
            background: transparent;
            color: #999;
            border: 1px solid #ddd;
            width: 34px;
            height: 34px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
            flex-shrink: 0;
        }

        .remove-btn:hover {
            background: #fee;
            color: var(--primary);
            border-color: var(--primary);
        }

        .cart-summary {
            background: var(--card);
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
            padding: 24px;
            position: sticky;
            top: 20px;
        }

        .summary-title {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 20px;
            color: var(--text);
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 14px;
            font-size: 15px;
            color: var(--text-muted);
        }

        .summary-row.summary-total {
            font-size: 20px;
            font-weight: bold;
            color: var(--primary);
            border-top: 2px solid var(--border);
            padding-top: 16px;
            margin-top: 16px;
            margin-bottom: 20px;
        }

        .cart-actions {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .btn {
            padding: 14px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 15px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
            font-weight: 500;
        }

        .btn i { margin-right: 8px; }

        .btn-checkout {
            background: var(--success);
            color: white;
            width: 100%;
        }

        .btn-checkout:hover {
            background: #219150;
        }

        .btn-continue {
            background: #f0f0f0;
            color: var(--text);
            width: 100%;
        }

        .btn-continue:hover {
            background: #e0e0e0;
        }

        .btn-clear {
            background: transparent;
            color: #999;
            border: 1px solid #ddd;
            padding: 10px 16px;
            font-size: 14px;
        }

        .btn-clear:hover {
            background: #fee;
            color: var(--primary);
            border-color: var(--primary);
        }

        .empty-cart {
            text-align: center;
            padding: 80px 20px;
            background: var(--card);
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
        }

        .empty-cart-icon {
            font-size: 72px;
            color: #ddd;
            margin-bottom: 20px;
        }

        .empty-cart-message {
            font-size: 18px;
            color: var(--text-muted);
            margin-bottom: 24px;
        }

        .cart-note {
            background: #fff8e1;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 12px 16px;
            font-size: 13px;
            color: #856404;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* 响应式设计 */
        @media (max-width: 900px) {
            .cart-layout {
                grid-template-columns: 1fr;
            }
            .cart-summary {
                position: static;
            }
        }

        @media (max-width: 600px) {
            .cart-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
            }
            .cart-title {
                font-size: 22px;
            }
            .shop-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }
            .shop-info {
                width: 100%;
            }
            .shop-payment {
                width: 100%;
                padding-left: 28px;
            }
            .cart-item {
                flex-wrap: wrap;
                gap: 12px;
                padding: 16px;
            }
            .item-checkbox {
                order: 0;
            }
            .item-image {
                width: 70px;
                height: 70px;
                order: 1;
            }
            .item-details {
                order: 2;
                width: calc(100% - 110px);
                min-width: 0;
            }
            .item-total {
                order: 3;
                margin-left: auto;
                text-align: right;
                min-width: auto;
            }
            .remove-btn {
                order: 4;
                margin-left: 12px;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="cart-container">
        <div class="cart-header">
            <div>
                <h1 class="cart-title">我的购物车</h1>
                <div class="cart-subtitle">共 <?php echo count($cartItems); ?> 件商品</div>
            </div>
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
            <div class="cart-note">
                <i class="fas fa-info-circle"></i>
                <span>人气值结算需转至店主设定的对应城市区块，各店铺支持的收款城市/区块如下所示。</span>
            </div>

            <div class="cart-layout">
                <div class="cart-main">
                    <form method="GET" action="checkout.php" id="checkout-form">
                        <?php foreach ($groupedItems as $shopId => $group): ?>
                        <div class="shop-group">
                            <div class="shop-header">
                                <div class="shop-info">
                                    <input type="checkbox" class="shop-checkbox" data-shop="<?php echo $shopId; ?>" onchange="toggleShop(<?php echo $shopId; ?>)">
                                    <i class="fas fa-store"></i>
                                    <span class="shop-name"><?php echo htmlspecialchars($group['shop_name']); ?></span>
                                </div>
                                <div class="shop-payment">
                                    <span class="shop-payment-label"><i class="fas fa-coins"></i> 支持人气值结算：</span>
                                    <?php echo formatPaymentSetting($group['payment_settings']); ?>
                                </div>
                            </div>
                            <?php foreach ($group['items'] as $item): ?>
                            <div class="cart-item">
                                <div class="item-checkbox">
                                    <input type="checkbox" name="selected_items[]" value="<?php echo $item['id']; ?>" class="item-cb shop-<?php echo $shopId; ?>" data-price="<?php echo $item['price']; ?>" data-qty="<?php echo $item['quantity']; ?>" onchange="updateSelectedTotal()">
                                </div>
                                <img src="<?php echo htmlspecialchars(normalizeImageUrl($item['image_url'])); ?>" 
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
                                        <button type="button" class="quantity-btn quantity-plus" onclick="updateQuantity(<?php echo $item['id']; ?>, 1)">+</button>
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
                </div>

                <div class="cart-summary">
                    <div class="summary-title">结算明细</div>
                    <div class="summary-row">
                        <span>已选商品</span>
                        <span id="selected-count">0 件</span>
                    </div>
                    <div class="summary-row">
                        <span>选中总计</span>
                        <span><span class="bct-symbol">Ⓟ</span><span id="selected-total">0</span> 人气值</span>
                    </div>
                    <div class="summary-row summary-total">
                        <span>应付总额</span>
                        <span><span class="bct-symbol">Ⓟ</span><span id="pay-total">0</span> 人气值</span>
                    </div>
                    
                    <div class="cart-actions">
                        <button type="button" class="btn btn-checkout" onclick="goCheckout()">
                            <i class="fas fa-credit-card"></i> 去结算
                        </button>
                        <a href="../product/list.php" class="btn btn-continue">
                            <i class="fas fa-arrow-left"></i> 继续购物
                        </a>
                    </div>
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
            document.getElementById('pay-total').textContent = total.toLocaleString();
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
