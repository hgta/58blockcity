-- =====================================================================
-- 58拍卖子站 — 官方推荐位（首页顶部）
--   新增字段 featured_at，记录被管理员推荐到首页顶部的时间。
--   NULL 表示未推荐；非 NULL 表示推荐时间，用于排序与审计。
--   与现有状态机、字段完全兼容，可重复执行。
--
--   执行：
--     mysql -u <user> -p <db> < init/migrate-auction-featured.sql
-- =====================================================================

SET NAMES utf8mb4;

-- 1. 加列
SET @sql = (SELECT IF(COUNT(*) = 0,
    'ALTER TABLE `auctions` ADD COLUMN `featured_at` DATETIME NULL DEFAULT NULL COMMENT ''官方推荐时间(NULL=未推荐)''',
    'DO 0')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'auctions' AND COLUMN_NAME = 'featured_at');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 2. 加索引（首页按 featured_at DESC 拉推荐拍）
SET @sql = (SELECT IF(COUNT(*) = 0,
    'ALTER TABLE `auctions` ADD INDEX `idx_featured_status` (`featured_at`, `status`)',
    'DO 0')
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'auctions' AND INDEX_NAME = 'idx_featured_status');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;