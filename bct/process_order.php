<?php
require_once '../config/database.php';
require_once 'includes/auth.php';
require_once '../classes/UserBCTAccount.php';
require_once '../classes/BCTOrder.php';
require_once '../classes/CityBCT.php';

checkLogin();

// 辅助：返回错误到trade.php
function fail($msg, $city) {
    $_SESSION['error'] = $msg;
    header("Location: ../trade.php?city=" . urlencode($city));
    exit;
}

// 验证CSRF令牌 
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    fail("非法请求", '');
}

// 获取并验证输入数据
$type = $_POST['type'] ?? '';
$city = $_POST['city'] ?? '';
$amount = intval($_POST['amount'] ?? 0);
$price = floatval($_POST['price'] ?? 0);
$tradeType = $_POST['trade_type'] ?? '';
$mediatorId = intval($_POST['mediator_id'] ?? 0);
$contactInfo = $_POST['contact_info'] ?? '';

if (!in_array($type, ['buy', 'sell']) || empty($city) || $amount <= 0) fail("无效的请求参数", $city);
if (!in_array($tradeType, ['platform', 'mediator', 'direct'])) fail("无效的交易类型", $city);
if ($tradeType === 'mediator' && $mediatorId <= 0) fail("请选择中介", $city);
if ($tradeType === 'direct' && empty($contactInfo)) fail("请提供联系方式", $city);
if ($tradeType === 'platform' && $amount > 500) fail("平台交易限制500BCT以下，请选择其他交易方式", $city);

$cityBCT = new CityBCT($pdo);
if (!$cityBCT->getCityBCT($city)) fail("无效的城市", $city);

if ($type === 'sell') {
    $account = new UserBCTAccount($pdo);
    $userAccount = $account->getAccount($_SESSION['user_id'], $city);
    if (!$userAccount || ($userAccount['balance'] - $userAccount['frozen']) < $amount) fail("可用余额不足", $city);
}

$order = new BCTOrder($pdo);
$orderId = $order->createOrder(
    $_SESSION['user_id'], $city, $type, $amount, $tradeType,
    $tradeType === 'direct' ? $contactInfo : null,
    $price > 0 ? $price : null
);

if (!$orderId) fail("创建订单失败", $city);

// 如果是平台交易，尝试自动匹配
if ($tradeType === 'platform') {
    $order->autoMatchPlatformOrder($orderId);
}

// 根据订单类型跳转到不同页面
if ($type === 'buy') {
    $_SESSION['message'] = "购买订单创建成功";
    header("Location: ../user/orders.php?type=buy");
} else {
    $_SESSION['message'] = "出售订单创建成功";
    header("Location: ../user/orders.php?type=sell");
}
exit();
?>