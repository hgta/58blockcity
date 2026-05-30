<?php
require_once '../config/database.php';
require_once 'includes/auth.php';
require_once '../classes/UserBCTAccount.php';
require_once '../classes/BCTOrder.php';
require_once '../classes/CityBCT.php';

checkLogin();

// 验证CSRF令牌 
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    die("非法请求");
}

// 获取并验证输入数据
$type = $_POST['type'] ?? '';
$city = $_POST['city'] ?? '';
$amount = intval($_POST['amount'] ?? 0);
$tradeType = $_POST['trade_type'] ?? '';
$mediatorId = intval($_POST['mediator_id'] ?? 0);
$contactInfo = $_POST['contact_info'] ?? '';

// 验证基本输入
if (!in_array($type, ['buy', 'sell']) || empty($city) || $amount <= 0) {
    die("无效的请求参数");
}

// 验证交易类型
if (!in_array($tradeType, ['platform', 'mediator', 'direct'])) {
    die("无效的交易类型");
}

// 中介交易必须选择中介
if ($tradeType === 'mediator' && $mediatorId <= 0) {
    die("请选择中介");
}

// 直接交易必须提供联系方式
if ($tradeType === 'direct' && empty($contactInfo)) {
    die("请提供联系方式");
}

// 平台交易限制500BCT以下
if ($tradeType === 'platform' && $amount > 500) {
    die("平台交易限制500BCT以下，请选择其他交易方式");
}

// 检查城市是否存在
$cityBCT = new CityBCT($pdo);
$cityInfo = $cityBCT->getCityBCT($city);
if (!$cityInfo) {
    die("无效的城市");
}

// 如果是出售订单，检查用户余额
if ($type === 'sell') {
    $account = new UserBCTAccount($pdo);
    $userAccount = $account->getAccount($_SESSION['user_id'], $city);
    
    if (!$userAccount || ($userAccount['balance'] - $userAccount['frozen']) < $amount) {
        die("可用余额不足");
    }
}

// 创建订单
$order = new BCTOrder($pdo);
$orderId = $order->createOrder(
    $_SESSION['user_id'],
    $city,
    $type,
    $amount,
    $tradeType,
    $tradeType === 'direct' ? $contactInfo : null
);

if (!$orderId) {
    die("创建订单失败");
}

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