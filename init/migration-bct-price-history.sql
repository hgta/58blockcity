-- BCT 城市价格历史表
--
-- 背景：订单有效期的涨跌统计原先依赖 bct_transactions（成交流水），但该表仅由
--       平台交易（platform）撮合写入，而平台交易限 500 BCT 以下，用户实际发布的
--       大额挂单只能走 direct/mediator，永不进入撮合，导致该表结构性为空，
--       「涨跌城市」「涨跌榜」「24h 高低价」「价格走势」全部恒为 0。
--
-- 方案：价格历史改由 cities.bct_current_price 产生。CityBCT::updatePrice() 是所有
--       改价途径（后台单行保存、后台批量设置、自动调价）的唯一收口，在该处埋点
--       即可覆盖全部路径。此方案与 archive/2026-09-08-city-prices-pagination 确立的
--       「单一事实源」原则一致。
--
-- 兼容 MySQL 5.7/8.0 与 MariaDB，可重复执行
-- 执行方式：mysql -u<user> -p<pass> <db> < init/migration-bct-price-history.sql

DELIMITER $$

DROP PROCEDURE IF EXISTS `CreateBctPriceHistory`$$

CREATE PROCEDURE `CreateBctPriceHistory`()
BEGIN
    -- 1. 建表（幂等）
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'bct_price_history'
    ) THEN
        CREATE TABLE `bct_price_history` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            -- 显式对齐 cities.name 的排序规则（utf8mb4_unicode_ci），
            -- 否则与 cities 表 JOIN/子查询比较时会报 #1267 Illegal mix of collations
            `city` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '城市/词条名',
            `price` decimal(10,2) NOT NULL COMMENT '变更后的当前价',
            `base_price` decimal(10,2) DEFAULT NULL COMMENT '变更时的基础价',
            `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '记录时间',
            PRIMARY KEY (`id`),
            KEY `idx_city_created` (`city`, `created_at`),
            KEY `idx_created` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='BCT 城市价格历史（由 updatePrice 埋点写入）';
    END IF;

    -- 2. 为存量城市补初始快照（仅当该城市尚无任何历史记录时），
    --    使历史表上线即有数据，避免冷启动期指标长期为空。
    --    显式 COLLATE 对齐排序规则，避免 #1267 Illegal mix of collations。
    INSERT INTO `bct_price_history` (`city`, `price`, `base_price`, `created_at`)
    SELECT c.`name`, c.`bct_current_price`, c.`bct_base_price`, NOW()
    FROM `cities` c
    WHERE c.`name` IS NOT NULL
      AND c.`bct_current_price` IS NOT NULL
      AND NOT EXISTS (
          SELECT 1 FROM `bct_price_history` h
          WHERE h.`city` = c.`name` COLLATE utf8mb4_unicode_ci
      );
END$$

DELIMITER ;

CALL `CreateBctPriceHistory`();

DROP PROCEDURE IF EXISTS `CreateBctPriceHistory`;
