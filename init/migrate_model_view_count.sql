-- 模特个人页浏览统计（幂等，可重复执行）
-- 若字段已存在则跳过；不存在则新增 view_count 列

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'models'
    AND COLUMN_NAME = 'view_count');

SET @sql = IF(@col_exists = 0,
  'ALTER TABLE `models` ADD COLUMN `view_count` int(11) NOT NULL DEFAULT 0 COMMENT ''模特个人页访问次数'' AFTER `follower_count`',
  'SELECT ''view_count column already exists, skipping'' AS msg');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
