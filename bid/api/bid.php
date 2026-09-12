<?php
/**
 * 出价端点
 * POST /api/bid.php  { auction_id, amount, csrf_token }
 */
require_once '../config/database.php';
require_once '../classes/Auction.php';
require_once '../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => '请求方式不正确']);
    exit;
}

$userId = intval($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    echo json_encode(['success' => false, 'need_login' => true, 'message' => '请先登录后再出价']);
    exit;
}

if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => '会话已过期，请刷新页面后重试']);
    exit;
}

$auctionId = intval($_POST['auction_id'] ?? 0);
$amount    = floatval($_POST['amount'] ?? 0);
if ($auctionId <= 0) {
    echo json_encode(['success' => false, 'message' => '参数无效']);
    exit;
}

$auction = new Auction($pdo);
// 先推进状态机，避免对已到期但尚未结算的拍品出价
$auction->tick();

$res = $auction->placeBid($auctionId, $userId, $amount);

if (empty($res['ok'])) {
    echo json_encode([
        'success'    => false,
        'message'    => $res['msg'] ?? '出价失败',
        'server_now' => time(),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode([
    'success'    => true,
    'message'    => $res['msg'] ?? '出价成功',
    'server_now' => time(),
    'data'       => [
        'current_price' => $res['current_price'],
        'next_min'      => $res['next_min'],
        'end_time'      => $res['end_time'],
        'end_ts'        => strtotime($res['end_time']),
        'extended'      => !empty($res['extended']),
    ],
], JSON_UNESCAPED_UNICODE);
