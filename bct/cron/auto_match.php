<?php
/**
 * BCT 自动撮合定时任务
 * 
 * 遍历所有 pending 状态的平台交易订单，尝试自动匹配。
 * 处理完成后更新城市 BCT 价格。
 * 
 * 触发方式：
 *   Linux Cron:  */5 * * * * php /path/to/bct/cron/auto_match.php
 *   手动触发:    php bct/cron/auto_match.php
 */

// 加载数据库连接
require_once dirname(__DIR__, 1) . '/../config/database.php';

// 加载业务类
require_once dirname(__DIR__, 1) . '/../classes/BCTOrder.php';
require_once dirname(__DIR__, 1) . '/../classes/CityBCT.php';
require_once dirname(__DIR__, 1) . '/../classes/UserBCTAccount.php';
require_once dirname(__DIR__, 1) . '/../classes/BCTTransaction.php';

$log = [];
$log[] = '[' . date('Y-m-d H:i:s') . '] BCT自动撮合任务开始';

// 查询所有 pending 状态的平台交易订单
$stmt = $pdo->prepare("SELECT DISTINCT city FROM bct_orders 
    WHERE status = 'pending' AND trade_type = 'platform' 
    ORDER BY city");
$stmt->execute();
$cities = $stmt->fetchAll(PDO::FETCH_COLUMN);

$totalMatched = 0;
$totalProcessed = 0;

$bctOrder = new BCTOrder($pdo);
$cityBCT = new CityBCT($pdo);

// 按城市处理（每个城市独立撮合）
foreach ($cities as $city) {
    // 获取该城市所有 pending 平台订单
    $stmt = $pdo->prepare("SELECT id FROM bct_orders 
        WHERE city = ? AND status = 'pending' AND trade_type = 'platform'
        ORDER BY created_at ASC");
    $stmt->execute([$city]);
    $orderIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $cityMatches = 0;
    foreach ($orderIds as $orderId) {
        $result = $bctOrder->autoMatchPlatformOrder($orderId);
        if ($result) {
            $cityMatches++;
        }
        $totalProcessed++;
    }
    
    $totalMatched += $cityMatches;
    
    if ($cityMatches > 0 || count($orderIds) > 0) {
        // 更新该城市 BCT 价格
        $newPrice = $cityBCT->autoAdjustPrice($city);
        $log[] = "  城市 [$city]: {$cityMatches}/" . count($orderIds) . " 笔撮合成功，当前价格 ¥" . number_format($newPrice, 2);
    }
}

$log[] = "  总计: 处理 {$totalProcessed} 笔订单，成功撮合 {$totalMatched} 笔";
$log[] = '[' . date('Y-m-d H:i:s') . '] BCT自动撮合任务结束';

echo implode("\n", $log) . "\n";
