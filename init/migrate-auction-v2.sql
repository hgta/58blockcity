-- =====================================================================
-- 58拍卖子站 v2 迁移（auction-hall-redesign）
--   真实统计 / 自动延时防狙击 / 关注
--
-- 设计原则：
--   * 可重复执行：所有变更先判存在再执行，连续跑两次均不报错
--   * 与旧惰性状态机兼容：不改 settle() 语义，只新增字段
--   * 结尾含「计数重算 / 对账」段，可单独反复执行以纠正偏差
--
-- 执行方式（推荐 mysql CLI，整个文件一次执行）：
--   mysql -u <user> -p <db> < init/migrate-auction-v2.sql
-- =====================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- 1. auctions 增列
-- ---------------------------------------------------------------------

-- 1.1 真实出价次数
SET @sql = (SELECT IF(COUNT(*) = 0,
    'ALTER TABLE `auctions` ADD COLUMN `bid_count` INT NOT NULL DEFAULT 0 COMMENT ''出价次数(冗余统计)''',
    'DO 0')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'auctions' AND COLUMN_NAME = 'bid_count');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 1.2 去重竞拍人数
SET @sql = (SELECT IF(COUNT(*) = 0,
    'ALTER TABLE `auctions` ADD COLUMN `bidder_count` INT NOT NULL DEFAULT 0 COMMENT ''竞拍人数(去重, 冗余统计)''',
    'DO 0')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'auctions' AND COLUMN_NAME = 'bidder_count');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 1.3 关注数
SET @sql = (SELECT IF(COUNT(*) = 0,
    'ALTER TABLE `auctions` ADD COLUMN `watch_count` INT NOT NULL DEFAULT 0 COMMENT ''关注数(冗余统计)''',
    'DO 0')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'auctions' AND COLUMN_NAME = 'watch_count');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 1.4 最终成交价
SET @sql = (SELECT IF(COUNT(*) = 0,
    'ALTER TABLE `auctions` ADD COLUMN `final_price` DECIMAL(20,2) DEFAULT NULL COMMENT ''最终成交价(仅 sold)''',
    'DO 0')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'auctions' AND COLUMN_NAME = 'final_price');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 1.5 落槌时间
SET @sql = (SELECT IF(COUNT(*) = 0,
    'ALTER TABLE `auctions` ADD COLUMN `sold_at` DATETIME DEFAULT NULL COMMENT ''落槌时间(仅 sold)''',
    'DO 0')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'auctions' AND COLUMN_NAME = 'sold_at');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 1.6 已顺延次数
SET @sql = (SELECT IF(COUNT(*) = 0,
    'ALTER TABLE `auctions` ADD COLUMN `extend_count` INT NOT NULL DEFAULT 0 COMMENT ''自动延时已顺延次数''',
    'DO 0')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'auctions' AND COLUMN_NAME = 'extend_count');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 1.7 单次顺延时长（秒）
SET @sql = (SELECT IF(COUNT(*) = 0,
    'ALTER TABLE `auctions` ADD COLUMN `auto_extend_seconds` INT NOT NULL DEFAULT 120 COMMENT ''单次顺延时长(秒)''',
    'DO 0')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'auctions' AND COLUMN_NAME = 'auto_extend_seconds');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 1.8 触发窗口（结束前 N 秒内出价才顺延）
SET @sql = (SELECT IF(COUNT(*) = 0,
    'ALTER TABLE `auctions` ADD COLUMN `extend_window_seconds` INT NOT NULL DEFAULT 120 COMMENT ''触发窗口:结束前N秒内出价才顺延''',
    'DO 0')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'auctions' AND COLUMN_NAME = 'extend_window_seconds');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 1.9 单场最多顺延次数
SET @sql = (SELECT IF(COUNT(*) = 0,
    'ALTER TABLE `auctions` ADD COLUMN `max_extend_times` INT NOT NULL DEFAULT 10 COMMENT ''单场最多顺延次数''',
    'DO 0')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'auctions' AND COLUMN_NAME = 'max_extend_times');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 1.10 单场累计最长顺延（秒）
SET @sql = (SELECT IF(COUNT(*) = 0,
    'ALTER TABLE `auctions` ADD COLUMN `max_extend_seconds` INT NOT NULL DEFAULT 1800 COMMENT ''单场累计最长顺延(秒)''',
    'DO 0')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'auctions' AND COLUMN_NAME = 'max_extend_seconds');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ---------------------------------------------------------------------
-- 2. auction_bids 增列：本次出价"夺走"的原领先者（用于被超越通知 / 叫价流回溯）
-- ---------------------------------------------------------------------
SET @sql = (SELECT IF(COUNT(*) = 0,
    'ALTER TABLE `auction_bids` ADD COLUMN `prev_bidder_id` INT DEFAULT NULL COMMENT ''被本次出价超越的原领先者''',
    'DO 0')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'auction_bids' AND COLUMN_NAME = 'prev_bidder_id');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ---------------------------------------------------------------------
-- 3. 关注表 auction_watches
--    唯一键 uk_auction_user 保证同一用户同一拍品只关注一次（幂等）
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `auction_watches` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `auction_id` int(11) NOT NULL COMMENT '拍卖单 id',
  `user_id` int(11) NOT NULL COMMENT '关注者',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_auction_user` (`auction_id`,`user_id`),
  KEY `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='拍卖关注';

-- ---------------------------------------------------------------------
-- 4. 辅助索引（判存在再建）
-- ---------------------------------------------------------------------
SET @sql = (SELECT IF(COUNT(*) = 0,
    'ALTER TABLE `auctions` ADD INDEX `idx_status_bid` (`status`,`bid_count`)',
    'DO 0')
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'auctions' AND INDEX_NAME = 'idx_status_bid');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql = (SELECT IF(COUNT(*) = 0,
    'ALTER TABLE `auctions` ADD INDEX `idx_status_sold` (`status`,`sold_at`)',
    'DO 0')
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'auctions' AND INDEX_NAME = 'idx_status_sold');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- =====================================================================
-- 5. 计数重算 / 对账（本段可单独、反复执行以纠正任何偏差）
--    口径：
--      bid_count    = auction_bids 行数
--      bidder_count = auction_bids 去重 bidder_id
--      watch_count  = auction_watches 行数
--      final_price / sold_at：已 sold 但为空时补齐
-- =====================================================================
UPDATE `auctions` a
LEFT JOIN (
    SELECT auction_id,
           COUNT(*)                      AS c,
           COUNT(DISTINCT bidder_id)     AS d
    FROM `auction_bids`
    GROUP BY auction_id
) t ON t.auction_id = a.id
SET a.bid_count    = COALESCE(t.c, 0),
    a.bidder_count = COALESCE(t.d, 0);

UPDATE `auctions` a
LEFT JOIN (
    SELECT auction_id, COUNT(*) AS c
    FROM `auction_watches`
    GROUP BY auction_id
) w ON w.auction_id = a.id
SET a.watch_count = COALESCE(w.c, 0);

UPDATE `auctions`
SET final_price = COALESCE(final_price, current_price),
    sold_at     = COALESCE(sold_at, updated_at)
WHERE status = 'sold';

-- =====================================================================
-- 6. 校验（可选，人工核对用；不影响迁移结果）
-- =====================================================================
-- SELECT a.id, a.bid_count, t.c AS real_bid_count, a.bidder_count, t.d AS real_bidder_count
-- FROM auctions a
-- LEFT JOIN (SELECT auction_id, COUNT(*) c, COUNT(DISTINCT bidder_id) d
--            FROM auction_bids GROUP BY auction_id) t ON t.auction_id = a.id
-- WHERE a.bid_count <> COALESCE(t.c,0) OR a.bidder_count <> COALESCE(t.d,0);
