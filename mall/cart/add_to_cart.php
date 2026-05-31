<?php
// 启动会话
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

// 检查登录
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => '请先登录']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => '请求方式错误']);
    exit();
}

require_once '../../config/database.php';
require_once '../../classes/Cart.php';

$cart = new Cart($pdo);

$productId = intval($_POST['product_id'] ?? 0);
$quantity = intval($_POST['quantity'] ?? 1);

if ($productId <= 0) {
    echo json_encode(['success' => false, 'message' => '无效的商品ID']);
    exit();
}

try {
    $result = $cart->addItem($_SESSION['user_id'], $productId, $quantity);
    
    // 获取购物车数量
    $cartCount = count($cart->getCartItems($_SESSION['user_id']));
    
    echo json_encode([
        'success' => true,
        'message' => '已添加到购物车',
        'cart_count' => $cartCount
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
