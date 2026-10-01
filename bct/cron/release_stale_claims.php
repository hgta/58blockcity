<?php
/**
 * BCT 接单超时释放定时任务（bct-direct-trade-claim-flow）
 *
 * 释放接单后超过 24 小时（CLAIM_STALE_HOURS）仍未确认付款的接单：
 *   claim 置为 released，订单回到 pending 重新开放接单，双方收到站内信。
 *
 * 买方已确认付款（buyer_confirmed）的接单【绝不】自动释放——
 * 款项可能已线下支付，自动释放会切断买方的留证链路，纠纷走后续申诉通道。
 *
 * 触发方式：
 *   Linux Cron:  建议每 30 分钟执行一次
 *                命令: php /path/to/bct/cron/release_stale_claims.php
 *   手动触发:    php bct/cron/release_stale_claims.php
 *
 * 并发保护：flock 独占锁（与 expire_orders.php 同模式）。
 * 页面侧（挂单大厅/个人中心）另有"惰性推进"只处理当前用户相关数据，本任务负责全站兜底，两路均幂等。
 */

// ---------- 并发保护：独占锁，防止重叠执行 ----------
$lockFile = sys_get_temp_dir() . '/bct_release_stale_claims.lock';
$lockHandle = @fopen($lockFile, 'c');
$lockAcquired = false;

if ($lockHandle) {
    $lockAcquired = @flock($lockHandle, LOCK_EX | LOCK_NB);
}

if (!$lockAcquired) {
    echo '[' . date('Y-m-d H:i:s') . "] 上一次释放任务尚未结束，本次跳过\n";
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
$log[] = '[' . date('Y-m-d H:i:s') . '] BCT接单超时释放开始';

try {
    $bctOrder = new BCTOrder($pdo);

    $totalReleased = 0;
    $rounds = 0;

    // 单批 100 条，分批推进，最多 50 轮（5000 条）
    do {
        $released = $bctOrder->releaseStaleClaims(null, 100);
        $totalReleased += $released;
        $rounds++;
    } while ($released >= 100 && $rounds < 50);

    if ($totalReleased > 0) {
        $log[] = "  已释放的接单数: {$totalReleased}";
    } else {
        $log[] = '  没有需要释放的超时接单';
    }
} catch (Exception $e) {
    $log[] = '  [错误] ' . $e->getMessage();
} finally {
    $log[] = '[' . date('Y-m-d H:i:s') . '] BCT接单超时释放结束';
    if ($lockHandle && $lockAcquired) {
        @flock($lockHandle, LOCK_UN);
    }
    if ($lockHandle) {
        @fclose($lockHandle);
    }
}

echo implode("\n", $log) . "\n";
