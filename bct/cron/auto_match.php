<?php
/**
 * BCT 自动撮合定时任务
 *
 * 遍历所有 pending 状态的平台交易订单，尝试自动匹配。
 * 仅在该城市实际发生成交时才调整城市 BCT 价格。
 *
 * 触发方式：
 *   Linux Cron:  建议每 5 分钟执行一次
 *                (cron 五个字段依次为: 分 时 日 月 周；
 *                 每 5 分钟可写成 斜杠5 加 空格 加 四个星号)
 *                命令: php /path/to/bct/cron/auto_match.php
 *   手动触发:    php bct/cron/auto_match.php
 *                后台「触发匹配」页亦会引入本文件执行
 *
 * 并发保护：
 *   使用 flock 独占锁，保证同一时间只有一个撮合进程在运行。
 *   若上一次执行尚未结束，本次直接跳过，避免重复撮合。
 */

// ---------- 并发保护：独占锁，防止重叠执行 ----------
$lockFile = sys_get_temp_dir() . '/bct_auto_match.lock';
$lockHandle = @fopen($lockFile, 'c');
$lockAcquired = false;

if ($lockHandle) {
    // LOCK_NB：拿不到锁立即返回，不阻塞等待
    $lockAcquired = @flock($lockHandle, LOCK_EX | LOCK_NB);
}

if (!$lockAcquired) {
    // 上一次任务尚未结束：输出提示后直接返回。
    // 使用 return 而非 exit，以便被后台「触发匹配」页 include 时
    // 仍能正常回到调用方并捕获输出。
    echo '[' . date('Y-m-d H:i:s') . "] 上一次撮合任务尚未结束，本次跳过\n";
    if ($lockHandle) {
        @fclose($lockHandle);
    }
    return;
}

// ---------- 加载依赖 ----------
$root = dirname(__DIR__, 2);
require_once $root . '/config/database.php';
require_once $root . '/classes/BCTOrder.php';
require_once $root . '/classes/CityBCT.php';
require_once $root . '/classes/UserBCTAccount.php';
require_once $root . '/classes/BCTTransaction.php';

$log = [];
$log[] = '[' . date('Y-m-d H:i:s') . '] BCT自动撮合任务开始';

try {
    $stmt = $pdo->prepare("SELECT DISTINCT city FROM bct_orders 
        WHERE status = 'pending' AND trade_type = 'platform' 
        ORDER BY city");
    $stmt->execute();
    $cities = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $totalMatched = 0;
    $totalAttempted = 0;

    $bctOrder = new BCTOrder($pdo);
    $cityBCT = new CityBCT($pdo);

    // 按城市处理（城市之间相互独立）
    foreach ($cities as $city) {
        // 注意：订单状态会在撮合过程中被改变（pending -> processing/completed），
        // 因此每轮都重新查询候选集，不能一次性缓存 ID 列表。
        //
        // 单轮内记录「已尝试但暂无对手盘」的订单并排除，避免最早的一笔
        // 无法成交时反复被选中，导致后续可成交订单永远轮不到。
        // 最多扫描 $maxRounds 笔，防止极端数据下长时间占用锁。
        $maxRounds = 200;
        $cityMatched = 0;
        $cityAttempted = 0;
        $triedIds = [];

        for ($i = 0; $i < $maxRounds; $i++) {
            $excludeSql = '';
            $params = [$city];
            if ($triedIds) {
                $placeholders = implode(',', array_fill(0, count($triedIds), '?'));
                $excludeSql = " AND id NOT IN ($placeholders)";
                $params = array_merge($params, $triedIds);
            }

            $stmt = $pdo->prepare("SELECT id FROM bct_orders 
                WHERE city = ? AND status = 'pending' AND trade_type = 'platform'
                $excludeSql
                ORDER BY created_at ASC, id ASC
                LIMIT 1");
            $stmt->execute($params);
            $orderId = $stmt->fetchColumn();

            // 没有更多未尝试的待撮合订单，结束该城市
            if (!$orderId) {
                break;
            }

            $orderId = (int)$orderId;
            $cityAttempted++;

            if ($bctOrder->autoMatchPlatformOrder($orderId)) {
                $cityMatched++;
            } else {
                // 暂无对手盘：本轮内不再重复尝试该订单
                $triedIds[] = $orderId;
            }
        }

        $totalMatched += $cityMatched;
        $totalAttempted += $cityAttempted;

        // 仅在该城市本轮有实际成交时才调整价格
        if ($cityMatched > 0) {
            $newPrice = $cityBCT->autoAdjustPrice($city);
            $log[] = "  城市 [$city]: {$cityMatched} 笔撮合成功，当前价格 ¥" . number_format($newPrice, 2);
        } elseif ($cityAttempted > 0) {
            $log[] = "  城市 [$city]: 0 笔撮合成功（{$cityAttempted} 笔无对手盘）";
        }
    }

    $log[] = "  总计: 尝试 {$totalAttempted} 笔订单，成功撮合 {$totalMatched} 笔";
} catch (Exception $e) {
    $log[] = '  [错误] ' . $e->getMessage();
} finally {
    $log[] = '[' . date('Y-m-d H:i:s') . '] BCT自动撮合任务结束';
    // 释放锁
    if ($lockHandle && $lockAcquired) {
        @flock($lockHandle, LOCK_UN);
    }
    if ($lockHandle) {
        @fclose($lockHandle);
    }
}

echo implode("\n", $log) . "\n";
