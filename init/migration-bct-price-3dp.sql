-- 人气值(BCT)单价精度放宽到 3 位小数
-- 涉及列全部由 decimal(10,2) -> decimal(10,3)。
-- MODIFY COLUMN 幂等，可重复执行。
-- 2026-10-11

-- 城市单价（后台改价、行情展示的数据源）
ALTER TABLE `cities`
    MODIFY `bct_base_price` decimal(10,3) NOT NULL DEFAULT '0.100' COMMENT 'BCT 基础单价（人气值市场底价）',
    MODIFY `bct_current_price` decimal(10,3) NOT NULL DEFAULT '0.100' COMMENT 'BCT 当前单价（人气值市场实时价）';

-- 挂单单价
ALTER TABLE `bct_orders`
    MODIFY `price` decimal(10,3) NOT NULL;

-- 成交留证单价
ALTER TABLE `bct_transactions`
    MODIFY `price` decimal(10,3) NOT NULL;

-- 单价历史（走势图数据源）
ALTER TABLE `bct_price_history`
    MODIFY `price` decimal(10,3) NOT NULL COMMENT '变更后的当前价',
    MODIFY `base_price` decimal(10,3) DEFAULT NULL COMMENT '变更时的基础价';
