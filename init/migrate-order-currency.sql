-- =====================================================================
-- 拍卖契约 — 订单 / 成交流水 币种
--   orders.currency      订单币种（人气值 / 人民币）。默认 popularity，
--                        存量订单全部是人气值语义，向下兼容。
--   orders.auction_id    来源拍卖单。取代现有「按 buyer_note 前缀
--                        「拍卖 LOT XXX 成交」反查」的脆弱字符串约定。
--   transactions.currency 区块成交流水的币种（区块挂牌 block_listings
--                        本就有该字段，流水此前缺失）。
--
--   本脚本全部为 ADD COLUMN / ADD INDEX，可重复执行。
--
--   执行：
--     mysql -u <user> -p <db> < init/migrate-order-currency.sql
-- =====================================================================

SET NAMES utf8mb4;

-- 1. orders.currency
SET @sql = (SELECT IF(COUNT(*) = 0,
    'ALTER TABLE `orders` ADD COLUMN `currency` ENUM(''popularity'',''cny'') NOT NULL DEFAULT ''popularity'' COMMENT ''订单币种(人气值/人民币)''',
    'DO 0')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'currency');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 2. orders.auction_id
SET @sql = (SELECT IF(COUNT(*) = 0,
    'ALTER TABLE `orders` ADD COLUMN `auction_id` INT(11) NULL DEFAULT NULL COMMENT ''来源拍卖单(NULL=常规订单)''',
    'DO 0')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'auction_id');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 3. 索引 idx_auction（契约页按拍卖单反查订单）
SET @sql = (SELECT IF(COUNT(*) = 0,
    'ALTER TABLE `orders` ADD INDEX `idx_auction` (`auction_id`)',
    'DO 0')
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND INDEX_NAME = 'idx_auction');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 4. transactions.currency
SET @sql = (SELECT IF(COUNT(*) = 0,
    'ALTER TABLE `transactions` ADD COLUMN `currency` ENUM(''popularity'',''cny'') NOT NULL DEFAULT ''popularity'' COMMENT ''成交币种(人气值/人民币)''',
    'DO 0')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'transactions' AND COLUMN_NAME = 'currency');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
