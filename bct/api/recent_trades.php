<?php
require_once '../../config/database.php';
require_once '../../classes/BCTOrder.php';

header('Content-Type: application/json; charset=utf-8');

$city = $_GET['city'] ?? null;
$limit = min(50, max(1, (int)($_GET['limit'] ?? 20)));

try {
    $order = new BCTOrder($pdo);
    $trades = $order->getRecentTrades($city, $limit);

    $data = [];
    foreach ($trades as $t) {
        $data[] = [
            'city' => $t['city'],
            'amount' => $t['amount'],
            'price' => $t['price'],
            'order_type' => $t['order_type'] ?? 'buy',
            'time' => date('H:i', strtotime($t['created_at']))
        ];
    }

    echo json_encode(['success' => true, 'trades' => $data]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
