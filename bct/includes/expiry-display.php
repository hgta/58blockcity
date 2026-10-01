<?php
/**
 * BCT 挂单剩余有效期展示（bct-hall-expiry-display）
 *
 * 挂单大厅与个人中心「我的挂单」共用的剩余有效期格式化，
 * 避免两处复制分级阈值。
 *
 * 分级规则（design D1）：
 *   剩余 > 7 天      → 剩 N 天      exp-ok       （默认灰）
 *   剩余 3-7 天      → 剩 N 天      exp-warn     （黄）
 *   剩余 1-3 天      → 剩 N 天      exp-danger    （橙）
 *   剩余 < 1 天      → 剩 N 小时    exp-critical  （红；不足 1 小时显示文案）
 *   expires_at 为空  → 长期         exp-ok       （长期单）
 *   已到期且非交易中 → 已过期       exp-expired  （红+删除线，防御性兜底）
 *   已到期且交易中   → 交易中顺延    exp-trade    （灰，title 说明）
 *
 * 时间口径：expires_at 由 PHP date() 写入，这里同样用 PHP time() 计算，
 * 不引入 SQL NOW()，与既有时区约定一致。
 *
 * @param string|null $expiresAt 'Y-m-d H:i:s' 或 null（长期）
 * @param bool $inTrade 订单是否交易中（有活跃接单）
 * @return array [展示文本, css类, 悬停title|null]
 */
function formatRemainingValidity($expiresAt, $inTrade = false) {
    if ($expiresAt === null || $expiresAt === '' ) {
        return ['长期', 'exp-ok', null];
    }

    $ts = strtotime($expiresAt);
    if ($ts === false) {
        return ['—', 'exp-ok', null];
    }

    $diff = $ts - time();

    if ($diff <= 0) {
        if ($inTrade) {
            return ['交易中顺延', 'exp-trade', '原有效期已过；交易完成后或释放后将按过期规则处理'];
        }
        return ['已过期', 'exp-expired', null];
    }

    if ($diff < 86400) {
        // 不足 1 天：按小时倒数
        $hours = (int)floor($diff / 3600);
        return [$hours >= 1 ? "剩 {$hours} 小时" : '不足 1 小时', 'exp-critical', null];
    }

    $days = (int)floor($diff / 86400);

    if ($days > 7) {
        return ["剩 {$days} 天", 'exp-ok', null];
    }
    if ($days >= 3) {
        return ["剩 {$days} 天", 'exp-warn', null];
    }
    return ["剩 {$days} 天", 'exp-danger', null];
}
