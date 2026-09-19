-- ============================================================
-- BCT 城市价格历史表
-- ============================================================
--
-- 背景：
--   涨跌城市/涨跌榜/24h 高低价/价格走势原先依赖 bct_transactions（成交流水），
--   而该表仅由平台交易（platform）撮合写入，平台交易限 500 BCT 以下，
--   用户实际发布的大额挂单只能走 direct/mediator，永不进入撮合，
--   导致该表结构性为空，上述指标恒为 0。
--
-- 方案：
--   价格历史改由 cities.bct_current_price 产生。CityBCT::updatePrice() 是所有
--   改价途径（后台单行保存、后台批量设置、自动调价）的唯一收口，在该处埋点
--   即可覆盖全部路径。此方案与 archive/2026-09-08-city-prices-pagination
--   确立的「单一事实源」原则一致。
--
-- 排序规则（重要）：
--   city 列必须与 cities.name 完全一致，均为 varchar(50) COLLATE utf8mb4_unicode_ci。
--   否则两者比较会报 #1267 Illegal mix of collations，导致首页行情整体报错。
--   本脚本在结尾会主动校正该列，因此无论表是否已存在、是否为早期错误结构，
--   执行后都能收敛到正确状态。
--
-- 兼容 MySQL 5.7/8.0 与 MariaDB，可重复执行。
--
-- 注意：本脚本刻意不使用存储过程与 DELIMITER ——
--   phpMyAdmin 的解析器对「DELIMITER + 存储过程 + 语句内注释」的组合处理不稳定，
--   曾导致解析错位。此处改为最朴素的语句序列。
--
-- 执行方式：
--   mysql -u<user> -p<pass> <db> < init/migration-bct-price-history.sql
-- ============================================================


-- 1. 建表（幂等）
CREATE TABLE IF NOT EXISTS `bct_price_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `city` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '城市/词条名',
  `price` decimal(10,2) NOT NULL COMMENT '变更后的当前价',
  `base_price` decimal(10,2) DEFAULT NULL COMMENT '变更时的基础价',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '记录时间',
  PRIMARY KEY (`id`),
  KEY `idx_city_created` (`city`,`created_at`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='BCT 城市价格历史（由 updatePrice 埋点写入）';


-- 2. 校正 city 列的排序规则与长度（幂等且必需）
--    若该表由早期脚本创建，city 可能是 varchar(100) + utf8mb4_general_ci，
--    与 cities.name 不匹配会触发 #1267。此句把已有表也纠正过来。
ALTER TABLE `bct_price_history`
  MODIFY COLUMN `city` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '城市/词条名';


-- 3. 为存量城市补初始快照（仅当该城市尚无任何历史记录时）
--    使历史表上线即有数据，避免冷启动期指标长期为空。
INSERT INTO `bct_price_history` (`city`, `price`, `base_price`, `created_at`)
SELECT c.`name`, c.`bct_current_price`, c.`bct_base_price`, NOW()
FROM `cities` c
WHERE c.`name` IS NOT NULL
  AND c.`bct_current_price` IS NOT NULL
  AND NOT EXISTS (
    SELECT 1 FROM `bct_price_history` h
    WHERE h.`city` = c.`name`
  );


-- 4. 自检：确认排序规则已对齐（应返回 2 行且 collation 均为 utf8mb4_unicode_ci）
SELECT TABLE_NAME, COLUMN_NAME, CHARACTER_MAXIMUM_LENGTH, COLLATION_NAME
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND ( (TABLE_NAME = 'cities' AND COLUMN_NAME = 'name')
     OR (TABLE_NAME = 'bct_price_history' AND COLUMN_NAME = 'city') )
ORDER BY TABLE_NAME;
