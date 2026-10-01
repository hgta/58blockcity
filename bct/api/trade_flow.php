<?php
/**
 * BCT 直接交易流程 API（bct-direct-trade-claim-flow）
 *
 * 单一入口按 action 分发：
 *   GET  ?action=preview&order_id=X          接单前预览（对方用户名/数量/单价/总价/可执行动作）
 *   POST action=claim          {order_id}                       接单（意向锁）
 *   POST action=buyer_confirm  {order_id}                       买方确认已付款
 *   POST action=seller_confirm {order_id}                       卖方确认已收款 -> 完成
 *   POST action=abandon        {order_id, reason?}               任一方放弃 -> 释放回 pending
 *
 * 统一横切面：登录断言 + CSRF 校验 + 当事人权限校验（数据层）+ 站内信通知（数据层内聚）。
 * 通知文案与状态流转集中在 classes/BCTOrder.php，本入口只做参数校验与分发。
 */

require_once '../../config/database.php';
require_once '../includes/auth.php';
require_once '../../classes/BCTOrder.php';

header('Content-Type: application/json; charset=utf-8');

function jsonOut($data, $httpCode = 200) {
    http_response_code($httpCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// ---------------- GET：预览 ----------------
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
    $action = $_GET['action'] ?? '';
    if ($action !== 'preview') {
        jsonOut(['success' => false, 'message' => '未知操作'], 400);
    }

    $userId = assertLogin();
    $orderId = (int)($_GET['order_id'] ?? 0);
    if ($orderId <= 0) jsonOut(['success' => false, 'message' => '参数错误'], 400);

    $bctOrder = new BCTOrder($pdo);
    try {
        $order = $bctOrder->getClaimPreview($orderId);
    } catch (Exception $e) {
        jsonOut(['success' => false, 'message' => '订单查询失败'], 500);
    }
    if (!$order) jsonOut(['success' => false, 'message' => '订单不存在'], 404);

    $claim = $order['active_claim'];
    $isPoster = ((int)$order['user_id'] === $userId);
    $isParty = $claim && ((int)$claim['buyer_side_user_id'] === $userId || (int)$claim['seller_side_user_id'] === $userId);

    $canClaim = !$claim
        && !$isPoster
        && $order['trade_type'] === 'direct'
        && $order['status'] === 'pending';

    jsonOut([
        'success'      => true,
        'order_id'     => (int)$order['id'],
        'order_no'     => $order['order_no'],
        'city'         => $order['city'],
        'type'         => $order['type'],
        'type_text'    => $order['type'] === 'buy' ? '求购' : '挂售',
        'amount'       => (int)$order['amount'],
        'price'        => (float)$order['price'],
        'total'        => round((int)$order['amount'] * (float)$order['price'], 2),
        'trade_type'   => $order['trade_type'],
        'poster_name'  => $order['username'] ?: ('用户#' . (int)$order['user_id']),
        'claim_status' => $claim['status'] ?? null,
        'in_trade'     => (bool)$claim,
        'is_party'     => (bool)$isParty,
        'can_claim'    => $canClaim,
    ]);
}

// ---------------- POST：状态动作 ----------------
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    jsonOut(['success' => false, 'message' => '不支持的请求方式'], 405);
}

$userId = assertLogin();

// JSON 输出的 CSRF 校验（validateCsrfToken 失败时输出纯文本，不适合 API）
$token = $_POST['csrf_token'] ?? '';
if (!verifyCsrfToken($token)) {
    jsonOut(['success' => false, 'message' => 'CSRF 校验失败，请刷新页面后重试'], 403);
}

$action = $_POST['action'] ?? '';
$orderId = (int)($_POST['order_id'] ?? 0);
if ($orderId <= 0) jsonOut(['success' => false, 'message' => '参数错误'], 400);

$bctOrder = new BCTOrder($pdo);

switch ($action) {
    case 'claim':
        $res = $bctOrder->claimOrder($orderId, $userId);
        break;

    case 'buyer_confirm':
        $res = $bctOrder->buyerConfirmClaim($orderId, $userId);
        break;

    case 'seller_confirm':
        $res = $bctOrder->sellerConfirmClaim($orderId, $userId);
        break;

    case 'abandon':
        $reason = trim((string)($_POST['reason'] ?? ''));
        $res = $bctOrder->abandonClaim($orderId, $userId, $reason !== '' ? $reason : null);
        break;

    default:
        jsonOut(['success' => false, 'message' => '未知操作'], 400);
}

// 通知由数据层内聚发送；此处只透传结果
jsonOut([
    'success' => (bool)($res['success'] ?? false),
    'message' => (string)($res['message'] ?? '操作失败'),
], ($res['success'] ?? false) ? 200 : 400);
