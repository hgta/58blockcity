<?php
/**
 * 批量发布交易 - 提交处理
 *
 * 逐条调用 BCTOrder::createOrder()，允许部分成功：
 *  - 成功行入库并记录订单号
 *  - 失败行记录原因后继续下一条，不影响已创建的订单
 *
 * 批量模式仅支持 direct / mediator，不提供 platform，且方向整体统一。
 */
require_once '../config/database.php';
require_once 'includes/auth.php';
require_once '../classes/BatchOrderParser.php';
require_once '../classes/BCTOrder.php';
require_once '../classes/CityBCT.php';

checkLogin();

// 校验失败回到批量模式
function batchFail($msg) {
    $_SESSION['error'] = $msg;
    header("Location: trade.php?mode=batch");
    exit;
}

// CSRF
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
    batchFail("非法请求");
}

$type = $_POST['type'] ?? '';
$tradeType = $_POST['trade_type'] ?? '';
$contactInfo = trim($_POST['contact_info'] ?? '');
$mediatorId = (int)($_POST['mediator_id'] ?? 0);
$text = (string)($_POST['batch_text'] ?? '');
// 有效期：整批共用；非法或缺失时回退到默认选项
$duration = $_POST['duration'] ?? '';
if (!BCTOrder::isValidDuration($duration)) {
    $duration = BCTOrder::DEFAULT_DURATION;
}

// 方向：整体统一，不混合
if (!in_array($type, ['buy', 'sell'], true)) {
    batchFail("无效的交易方向");
}
// 批量不提供 platform
if (!in_array($tradeType, ['direct', 'mediator'], true)) {
    batchFail("批量模式仅支持直接交易或中介交易");
}
if ($tradeType === 'mediator' && $mediatorId <= 0) {
    batchFail("请选择中介");
}
if ($tradeType === 'direct' && $contactInfo === '') {
    batchFail("请提供联系方式");
}

$parser = new BatchOrderParser($pdo);
$resolved = $parser->parseAndResolve($text);

if (empty($resolved['orders'])) {
    batchFail("没有解析到有效的挂单，请检查输入格式");
}

$order = new BCTOrder($pdo);
$cityBCT = new CityBCT($pdo);

$results = [];
$successCount = 0;
$failCount = 0;

foreach ($resolved['orders'] as $o) {
    $city = $o['city'];
    $amount = (int)$o['amount'];
    $price = (float)$o['price'];

    // 提交前复检（并发下城市信息可能已变化）
    // 注：不校验余额 —— 系统已简化流程，发布订单（含出售）无需验证余额
    $reason = null;

    if ($amount < BatchOrderParser::MIN_AMOUNT || $amount > BatchOrderParser::MAX_AMOUNT) {
        $reason = '数量超出允许范围';
    } elseif ($price <= 0) {
        $reason = '价格必须为正数';
    } elseif (!$cityBCT->getCityBCT($city)) {
        $reason = '无效的城市';
    }

    if ($reason === null) {
        $orderId = $order->createOrder(
            $_SESSION['user_id'],
            $city,
            $type,
            $amount,
            $tradeType,
            $tradeType === 'direct' ? $contactInfo : null,
            $price,
            $duration,
            $tradeType === 'mediator' ? $mediatorId : null
        );
        if ($orderId) {
            $successCount++;
            $results[] = [
                'city' => $city,
                'amount' => $amount,
                'price' => $price,
                'ok' => true,
                'order_id' => $orderId,
                'message' => '已发布',
            ];
            continue;
        }
        $reason = '创建订单失败';
    }

    $failCount++;
    $results[] = [
        'city' => $city,
        'amount' => $amount,
        'price' => $price,
        'ok' => false,
        'order_id' => null,
        'message' => $reason,
    ];
}

// 汇总结果存入 session，供页面展示逐条状态
$_SESSION['batch_publish_result'] = [
    'type' => $type,
    'trade_type' => $tradeType,
    'success' => $successCount,
    'failed' => $failCount,
    'items' => $results,
    'missing' => $resolved['missing'],
    'invalid' => $resolved['invalid'],
];

if ($successCount > 0) {
    $_SESSION['message'] = "批量发布完成：成功 {$successCount} 条，失败 {$failCount} 条";
} else {
    $_SESSION['error'] = "批量发布失败：{$failCount} 条均未创建";
}

header("Location: trade.php?mode=batch");
exit();
