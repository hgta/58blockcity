<?php
/**
 * BCT 挂单过期清理定时任务
 *
 * 将已超期且仍在待成交状态（pending / processing）的订单置为 expired，
 * 使其退出待撮合集合，不再污染行情。
 *
 * 已成交（completed）与用户主动取消（canceled）的订单不会被处理；
 * expires_at 为空的「长期」订单永不处理。
 *
 * 触发方式：
 *   Linux Cron:  建议每 10 分钟执行一次
 *                (cron 五个字段依次为: 分 时 日 月 周；
 *                 每 10 分钟可写成 斜杠10 加 空格 加 四个星号)
 *                命令: php /path/to/bct/cron/expire_orders.php
 *   手动触发:    php bct/cron/expire_orders.php
 *
 * 并发保护：
 *   使用 flock 独占锁，保证同一时间只有一个清理进程在运行。
 *
 * 补充说明：
 *   用户订单页亦会做「惰性推进」（只处理该用户），本任务负责全站兜底，
 *   确保长期无用户访问的订单也能被清理。两条路径均具备幂等性。
 */

// ---------- 并发保护：独占锁，防止重叠执行 ----------
$lockFile = sys_get_temp_dir() . '/bct_expire_orders.lock';
$lockHandle = @fopen($lockFile, 'c');
$lockAcquired = false;

if ($lockHandle) {
    $lockAcquired = @flock($lockHandle, LOCK_EX | LOCK_NB);
}

if (!$lockAcquired) {
    echo '[' . date('Y-m-d H:i:s') . "] 上一次过期清理任务尚未结束，本次跳过\n";
    if ($lockHandle) {
        @fclose($lockHandle);
    }
    return;
}

// ---------- 加载依赖 ----------
$root = dirname(__DIR__, 2);
require_once $root . '/config/database.php';
require_once $root . '/classes/BCTOrder.php';

$log = [];
$log[] = '[' . date('Y-m-d H:i:s') . '] BCT订单过期清理开始';

try {
    $bctOrder = new BCTOrder($pdo);

    $totalExpired = 0;
    $rounds = 0;

    // 单次上限 500，若仍有积压则继续下一轮，最多 20 轮（1 万单）
    // 防止积压过多时长时间占用锁
    do {
        $expired = $bctOrder->expireOverdueOrders(null, null, 500);
        $totalExpired += $expired;
        $rounds++;
    } while ($expired >= 500 && $rounds < 20);

    if ($totalExpired > 0) {
        $log[] = "  已置为过期（expired）的订单数: {$totalExpired}";
    } else {
        $log[] = '  没有需要处理的超期订单';
    }

    // 仍有剩超过阈值的说明积压严重，提示关注
    $remaining = $bctOrder->countOverdueOrders();
    if ($remaining > 0) {
        $log[] = "  仍有 {$remaining} 笔超期订单待处理（已达单次处理上限，下次继续）";
    }
} catch (Exception $e) {
    $log[] = '  [错误] ' . $e->getMessage();
} finally {
    $log[] = '[' . date('Y-m-d H:i:s') . '] BCT订单过期清理结束';
    if ($lockHandle && $lockAcquired) {
        @flock($lockHandle, LOCK_UN);
    }
    if ($lockHandle) {
        @fclose($lockHandle);
    }
}

echo implode("\n", $log) . "\n";
