<?php
/**
 * BCT 交易确认 API
 * 
 * POST /api/execute_trade.php  — 执行交易
 * GET  /api/execute_trade.php?action=preview  — 预览匹配信息
 */

require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../classes/BCTOrder.php';
require_once '../../classes/BCTTransaction.php';
require_once '../../classes/UserBCTAccount.php';
require_once '../../classes/CityBCT.php';

// 设置 JSON 响应头
header('Content-Type: application/json; charset=utf-8');

// 断言登录（API 模式）
$userId = assertLogin();

// GET 请求：预览匹配
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'preview') {
    $orderId = (int)($_GET['order_id'] ?? 0);
    $counterpartyId = (int)($_GET['counterparty_id'] ?? 0);
    
    if (!$orderId) {
        echo json_encode(['success' => false, 'message' => '缺少订单ID']);
        exit;
    }
    
    // 查询订单信息
    $stmt = $pdo->prepare("SELECT * FROM bct_orders WHERE id = ? AND status = 'pending'");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();
    
    if (!$order) {
        echo json_encode(['success' => false, 'message' => '订单不存在或已不可交易']);
        exit;
    }
    
    // 查询对方订单
    if ($counterpartyId) {
        $stmt = $pdo->prepare("SELECT * FROM bct_orders WHERE user_id = ? AND city = ? 
            AND type = ? AND status = 'pending' AND id != ?
            ORDER BY created_at ASC LIMIT 1");
        $matchType = $order['type'] == 'buy' ? 'sell' : 'buy';
        $stmt->execute([$counterpartyId, $order['city'], $matchType, $orderId]);
        $counterOrder = $stmt->fetch();
    } else {
        // 查找最佳匹配
        $matchType = $order['type'] == 'buy' ? 'sell' : 'buy';
        if ($order['type'] == 'buy') {
            $stmt = $pdo->prepare("SELECT * FROM bct_orders WHERE city = ? AND type = ? 
                AND status = 'pending' AND trade_type = 'platform' AND price <= ?
                ORDER BY price ASC, created_at ASC LIMIT 1");
            $stmt->execute([$order['city'], $matchType, $order['price']]);
        } else {
            $stmt = $pdo->prepare("SELECT * FROM bct_orders WHERE city = ? AND type = ? 
                AND status = 'pending' AND trade_type = 'platform' AND price >= ?
                ORDER BY price DESC, created_at ASC LIMIT 1");
            $stmt->execute([$order['city'], $matchType, $order['price']]);
        }
        $counterOrder = $stmt->fetch();
    }
    
    if ($counterOrder) {
        $matchType = $order['type'] == 'buy' ? 'sell' : 'buy';
        $buyOrder = $order['type'] == 'buy' ? $order : $counterOrder;
        $sellOrder = $order['type'] == 'sell' ? $order : $counterOrder;
        
        // 检查价格交叉
        $priceMatch = $buyOrder['price'] >= $sellOrder['price'];
        
        echo json_encode([
            'success' => true,
            'match' => $priceMatch,
            'match_order_id' => $counterOrder['id'],
            'match_user_id' => $counterOrder['user_id'],
            'match_price' => $counterOrder['price'],
            'match_amount' => $counterOrder['amount'],
            'match_type' => $counterOrder['type'],
            'max_amount' => min($order['amount'], $counterOrder['amount']),
            'trade_price' => $sellOrder['price']
        ]);
    } else {
        echo json_encode(['success' => true, 'match' => false, 'message' => '暂无匹配订单']);
    }
    exit;
}

// POST 请求：执行交易
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 验证 CSRF
    $token = $_POST['csrf_token'] ?? '';
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        echo json_encode(['success' => false, 'message' => 'CSRF 验证失败，请刷新页面重试']);
        exit;
    }
    
    $orderId = (int)($_POST['order_id'] ?? 0);
    $counterpartyId = (int)($_POST['counterparty_id'] ?? 0);
    $amount = (int)($_POST['amount'] ?? 0);
    
    if (!$orderId || !$amount) {
        echo json_encode(['success' => false, 'message' => '缺少必要参数']);
        exit;
    }
    
    if ($amount <= 0) {
        echo json_encode(['success' => false, 'message' => '交易数量必须大于0']);
        exit;
    }
    
    // 查询当前订单
    $stmt = $pdo->prepare("SELECT * FROM bct_orders WHERE id = ? AND status = 'pending'");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();
    
    if (!$order) {
        echo json_encode(['success' => false, 'message' => '订单不存在或已不可交易']);
        exit;
    }
    
    if ($amount > $order['amount']) {
        echo json_encode(['success' => false, 'message' => '交易数量超过订单剩余数量']);
        exit;
    }
    
    $bctOrder = new BCTOrder($pdo);
    
    if ($counterpartyId) {
        // 有指定对手订单：查找该用户同城市、相反方向的订单
        $matchType = $order['type'] == 'buy' ? 'sell' : 'buy';
        $stmt = $pdo->prepare("SELECT * FROM bct_orders 
            WHERE user_id = ? AND city = ? AND type = ? AND status = 'pending' 
            AND id != ?
            ORDER BY created_at ASC LIMIT 1");
        $stmt->execute([$counterpartyId, $order['city'], $matchType, $orderId]);
        $counterOrder = $stmt->fetch();
        
        if (!$counterOrder) {
            echo json_encode(['success' => false, 'message' => '对方暂无符合条件的挂单']);
            exit;
        }
        
        // 执行手动匹配交易
        $result = $bctOrder->matchAndExecute($orderId, $counterOrder['id'], min($amount, $counterOrder['amount']), $order['trade_type']);
    } else {
        // 平台自动匹配
        $matched = $bctOrder->autoMatchPlatformOrder($orderId);
        $result = $matched 
            ? ['success' => true, 'message' => '平台撮合交易成功']
            : ['success' => false, 'message' => '暂无匹配订单或交易失败'];
    }
    
    echo json_encode($result);
    exit;
}

// 不支持的请求方法
echo json_encode(['success' => false, 'message' => '不支持的请求方法']);
