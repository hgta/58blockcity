<?php
/**
 * 拍卖详情轮询端点（只读）
 * GET /api/lot.php?id=123
 */
require_once '../config/database.php';
require_once '../classes/Auction.php';
require_once '../includes/auth.php';
require_once __DIR__ . '/../includes/lot_helpers.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => '参数无效']);
    exit;
}

$auction  = new Auction($pdo);
$viewerId = intval($_SESSION['user_id'] ?? 0);

$snapshot = $auction->getAuctionSnapshot($id, $viewerId);
if (!$snapshot) {
    echo json_encode(['success' => false, 'message' => '拍卖不存在']);
    exit;
}

$symbol = ac_currency_symbol($snapshot['currency']);
$bids = [];
foreach (($snapshot['bids'] ?? []) as $b) {
    $bids[] = [
        'id'             => intval($b['id']),
        'bidder_id'      => intval($b['bidder_id']),
        'bidder_name'    => $b['bidder_name'] ?? '',
        'bidder_avatar'  => $b['bidder_avatar'] ?? '',
        'amount'         => floatval($b['amount']),
        'created_ts'     => strtotime($b['created_at']),
    ];
}

echo json_encode([
    'success'    => true,
    'server_now' => time(),
    'data'       => [
        'id'             => $snapshot['id'],
        'status'         => $snapshot['status'],
        'currency_symbol'=> $symbol,
        'current_price'  => $snapshot['current_price'],
        'start_price'    => $snapshot['start_price'],
        'bid_increment'  => $snapshot['bid_increment'],
        'next_min'       => $snapshot['next_min'],
        'reserve_price'  => $snapshot['reserve_price'],
        'end_ts'         => strtotime($snapshot['end_time']),
        'extend_count'   => $snapshot['extend_count'],
        'bid_count'      => $snapshot['bid_count'],
        'bidder_count'   => $snapshot['bidder_count'],
        'watch_count'    => $snapshot['watch_count'],
        'my_state'       => $snapshot['my_state'],
        'bids'           => $bids,
    ],
], JSON_UNESCAPED_UNICODE);
