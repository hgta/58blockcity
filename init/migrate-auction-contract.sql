-- =====================================================================
-- 58拍卖 — 落槌契约
--   落槌 = 契约成立，钱由买卖双方线下自行结算，平台不碰资金。
--   契约本体就是 auctions 行本身，新增 4 个履约字段：
--
--   payment_status       NULL = 未落槌 / 流拍，不进契约流程
--                        unpaid        契约成立，等待买家付款
--                        buyer_declared 买家已声明付款，等待卖家确认
--                        settled       卖家已确认收款（物权在此刻交割）
--                        voided        契约作废（7 天未付款 / 双方取消）
--   payment_declared_at  买家声明付款时间
--   settled_at          卖家确认收款时间
--   contract_expire_at  契约付款期限（落槌 +7 天）
--
--   全部为 ADD COLUMN / ADD INDEX，可重复执行。
--
--   执行：
--     mysql -u <user> -p <db> < init/migrate-auction-contract.sql
-- =====================================================================

SET NAMES utf8mb4;

-- 1. auctions.payment_status
SET @sql = (SELECT IF(COUNT(*) = 0,
    'ALTER TABLE `auctions` ADD COLUMN `payment_status` ENUM(''unpaid'',''buyer_declared'',''settled'',''voided'') NULL DEFAULT NULL COMMENT ''契约履约状态(NULL=未落槌)'' AFTER `status`',
    'DO 0')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'auctions' AND COLUMN_NAME = 'payment_status');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 2. auctions.payment_declared_at
SET @sql = (SELECT IF(COUNT(*) = 0,
    'ALTER TABLE `auctions` ADD COLUMN `payment_declared_at` DATETIME NULL DEFAULT NULL COMMENT ''买家声明付款时间''',
    'DO 0')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'auctions' AND COLUMN_NAME = 'payment_declared_at');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 3. auctions.settled_at
SET @sql = (SELECT IF(COUNT(*) = 0,
    'ALTER TABLE `auctions` ADD COLUMN `settled_at` DATETIME NULL DEFAULT NULL COMMENT ''卖家确认收款时间''',
    'DO 0')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'auctions' AND COLUMN_NAME = 'settled_at');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 4. auctions.contract_expire_at
SET @sql = (SELECT IF(COUNT(*) = 0,
    'ALTER TABLE `auctions` ADD COLUMN `contract_expire_at` DATETIME NULL DEFAULT NULL COMMENT ''契约付款期限''',
    'DO 0')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'auctions' AND COLUMN_NAME = 'contract_expire_at');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 5. 索引（契约清扫 + 契约期互斥校验）
SET @sql = (SELECT IF(COUNT(*) = 0,
    'ALTER TABLE `auctions` ADD INDEX `idx_contract` (`status`,`payment_status`,`contract_expire_at`)',
    'DO 0')
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'auctions' AND INDEX_NAME = 'idx_contract');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
