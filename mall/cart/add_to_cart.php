<?php
// 启动会话
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

// 检查登录
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => '请先登录']);
    exit();
}

require_once '../../config/database.php';
require_once '../../classes/Cart.php';
require_once '../../classes/Product.php';

$userId = $_SESSION['user_id'];
$productId = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
$quantity = isset($_POST['quantity']) ? max(1, intval($_POST['quantity'])) : 1;

if ($productId <= 0) {
    echo json_encode(['success' => false, 'message' => '商品ID无效']);
    exit();
}

$cart = new Cart($pdo);
$product = new Product($pdo);

// 检查商品
$productInfo = $product->getProductById($productId);
if (!$productInfo) {
    echo json_encode(['success' => false, 'message' => '商品不存在']);
    exit();
}

if ($productInfo['status'] != 'active') {
    echo json_encode(['success' => false, 'message' => '商品已下架']);
    exit();
}

if ($productInfo['stock'] < $quantity) {
    echo json_encode(['success' => false, 'message' => '商品库存不足，剩余 ' . $productInfo['stock'] . ' 件']);
    exit();
}

try {
    // 立即购买模式：先清空购物车再加入当前商品
    $clearFirst = isset($_POST['clear_first']) && $_POST['clear_first'] == '1';
    if ($clearFirst) {
        $cart->clearCart($userId);
    }

    $result = $cart->addItem($userId, $productId, $quantity);
    if ($result) {
        $cartStats = $cart->getCartStats($userId);
        echo json_encode([
            'success' => true,
            'message' => '已加入购物车',
            'cart_count' => $cartStats['item_count'] ?? 0
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => '加入购物车失败']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
