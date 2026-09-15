<?php
/**
 * 批量发布交易 - 预校验接口（全程只读）
 *
 * 接收多行文本「城市 数量 价格」与批次参数，返回逐条挂单的校验状态：
 *  - 城市是否匹配（名称 / 拼音）
 *  - 数量、价格是否合法
 *  - 卖单时可用余额是否充足
 *
 * 本接口不创建任何订单、不修改任何余额。
 */
require_once '../../config/database.php';
require_once '../includes/auth.php';
require_once '../../classes/BatchOrderParser.php';

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => '请先登录'], JSON_UNESCAPED_UNICODE);
    exit;
}

$type = $_POST['type'] ?? $_GET['type'] ?? 'buy';
$tradeType = $_POST['trade_type'] ?? $_GET['trade_type'] ?? 'direct';
$text = $_POST['text'] ?? $_GET['text'] ?? '';

// 批量模式只允许 buy / sell，不混合
if (!in_array($type, ['buy', 'sell'], true)) {
    echo json_encode(['success' => false, 'message' => '批量方向只能是买入或卖出'], JSON_UNESCAPED_UNICODE);
    exit;
}
// 批量模式不提供 platform
if (!in_array($tradeType, ['direct', 'mediator'], true)) {
    echo json_encode(['success' => false, 'message' => '批量模式仅支持直接交易或中介交易'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $parser = new BatchOrderParser($pdo);
    $resolved = $parser->parseAndResolve((string)$text);

    $orders = [];
    $okCount = 0;
    $failCount = 0;

    foreach ($resolved['orders'] as $o) {
        $issues = [];

        // 数量上限（合并累加后可能超出）
        if ($o['amount'] < BatchOrderParser::MIN_AMOUNT || $o['amount'] > BatchOrderParser::MAX_AMOUNT) {
            $issues[] = '数量超出 ' . number_format(BatchOrderParser::MIN_AMOUNT) . ' - ' . number_format(BatchOrderParser::MAX_AMOUNT) . ' 范围';
        }
        // 价格
        if ($o['price'] <= 0) {
            $issues[] = '价格必须为正数';
        }

        // 注：不对余额做校验 —— 系统已简化流程，发布订单（含出售）无需验证余额
        $ok = empty($issues);
        if ($ok) { $okCount++; } else { $failCount++; }

        $orders[] = [
            'city' => $o['city'],
            'price' => $o['price'],
            'amount' => $o['amount'],
            'lines' => $o['lines'],
            'total' => round($o['amount'] * $o['price'], 2),
            'ok' => $ok,
            'issues' => $issues,
            'merged' => count($o['lines']) > 1,
        ];
    }

    echo json_encode([
        'success' => true,
        'type' => $type,
        'trade_type' => $tradeType,
        'orders' => $orders,
        'missing' => $resolved['missing'],
        'invalid' => $resolved['invalid'],
        'duplicate' => $resolved['duplicate'],
        'summary' => [
            'total' => count($orders),
            'ok' => $okCount,
            'failed' => $failCount,
        ],
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
