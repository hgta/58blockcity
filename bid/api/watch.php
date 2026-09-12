<?php
/**
 * 关注 / 取关端点
 * POST /api/watch.php  { auction_id, csrf_token }
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
    echo json_encode(['success' => false, 'need_login' => true, 'message' => '请先登录']);
    exit;
}

if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => '会话已过期，请刷新页面后重试']);
    exit;
}

$auctionId = intval($_POST['auction_id'] ?? 0);
if ($auctionId <= 0) {
    echo json_encode(['success' => false, 'message' => '参数无效']);
    exit;
}

$auction = new Auction($pdo);
$res = $auction->toggleWatch($auctionId, $userId);

if (empty($res['ok'])) {
    echo json_encode(['success' => false, 'message' => $res['msg'] ?? '操作失败'], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode([
    'success' => true,
    'message' => $res['msg'] ?? '已更新',
    'data'    => [
        'watching'    => !empty($res['watching']),
        'watch_count' => intval($res['watch_count'] ?? 0),
    ],
], JSON_UNESCAPED_UNICODE);
