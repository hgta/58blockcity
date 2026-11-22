-- =====================================================================
-- 58拍卖 — 契约联系方式（openspec: contract-contact-info）
--   成交后买卖双方需要直接联系（收款方式/发货沟通），契约页互见联系方式。
--   字段模型沿用 block 出售市场先例（contact_phone / contact_wechat），
--   可见性收紧为「仅契约当事人」——契约本体就是 auctions 行，直接挂 4 列：
--
--   seller_contact_phone / seller_contact_wechat  卖家联系方式
--   buyer_contact_phone  / buyer_contact_wechat   买家联系方式
--
--   全部可空 NULL，无默认值回填，存量契约不受影响。可重复执行。
--
--   执行：
--     mysql -u <user> -p <db> < init/migration-contract-contact.sql
-- =====================================================================

SET NAMES utf8mb4;

-- 1. auctions.seller_contact_phone
SET @sql = (SELECT IF(COUNT(*) = 0,
    'ALTER TABLE `auctions` ADD COLUMN `seller_contact_phone` VARCHAR(20) NULL DEFAULT NULL COMMENT ''卖家契约联系方式-电话'' AFTER `contract_expire_at`',
    'DO 0')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'auctions' AND COLUMN_NAME = 'seller_contact_phone');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 2. auctions.seller_contact_wechat
SET @sql = (SELECT IF(COUNT(*) = 0,
    'ALTER TABLE `auctions` ADD COLUMN `seller_contact_wechat` VARCHAR(64) NULL DEFAULT NULL COMMENT ''卖家契约联系方式-微信'' AFTER `seller_contact_phone`',
    'DO 0')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'auctions' AND COLUMN_NAME = 'seller_contact_wechat');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 3. auctions.buyer_contact_phone
SET @sql = (SELECT IF(COUNT(*) = 0,
    'ALTER TABLE `auctions` ADD COLUMN `buyer_contact_phone` VARCHAR(20) NULL DEFAULT NULL COMMENT ''买家契约联系方式-电话'' AFTER `seller_contact_wechat`',
    'DO 0')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'auctions' AND COLUMN_NAME = 'buyer_contact_phone');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 4. auctions.buyer_contact_wechat
SET @sql = (SELECT IF(COUNT(*) = 0,
    'ALTER TABLE `auctions` ADD COLUMN `buyer_contact_wechat` VARCHAR(64) NULL DEFAULT NULL COMMENT ''买家契约联系方式-微信'' AFTER `buyer_contact_phone`',
    'DO 0')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'auctions' AND COLUMN_NAME = 'buyer_contact_wechat');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
