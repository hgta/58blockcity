-- ============================================================
-- 短剧参演关系泛化：支持「非模特演员」
--
-- 背景：原 model_dramas.model_id 为 NOT NULL，只能登记模特库成员。
--       短剧常有未入驻模特库的普通演员，需要按姓名登记。
--
-- 变更：
--   1) model_id 改为可空（非模特演员时为 NULL）
--   2) 新增 actor_name 字段（非模特演员姓名，纯文本展示）
--   3) 新增 UNIQUE(drama_id, actor_name) 防同剧重名重复登记
--   4) 原 UNIQUE(model_id, drama_id) 保留（MySQL 允许多行 NULL，不影响）
--
-- 约束：model_id 与 actor_name 至少填一个（应用层校验）
-- 本脚本幂等，可重复执行。
-- ============================================================

SET @db := DATABASE();

-- 1) model_id 改为可空
SET @sql := IF(
    (SELECT IS_NULLABLE FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'model_dramas' AND COLUMN_NAME = 'model_id') = 'NO',
    'ALTER TABLE `model_dramas` MODIFY COLUMN `model_id` int(11) NULL COMMENT ''关联模特ID（非模特演员时为NULL）''',
    'SELECT ''model_dramas.model_id 已是可空'' AS msg'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2) 新增 actor_name
SET @sql := IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'model_dramas' AND COLUMN_NAME = 'actor_name') = 0,
    'ALTER TABLE `model_dramas` ADD COLUMN `actor_name` varchar(100) DEFAULT NULL COMMENT ''非模特演员姓名（纯文本展示，不跳转）'' AFTER `model_id`',
    'SELECT ''model_dramas.actor_name 已存在'' AS msg'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 3) 新增 UNIQUE(drama_id, actor_name)：同剧不允许重名演员
SET @sql := IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'model_dramas' AND INDEX_NAME = 'uniq_drama_actor') = 0,
    'ALTER TABLE `model_dramas` ADD UNIQUE KEY `uniq_drama_actor` (`drama_id`, `actor_name`)',
    'SELECT ''uniq_drama_actor 已存在'' AS msg'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 4) 新增「是否主要演员」语义保持不变（is_lead 已存在），补充可按角色排序的注释无需 DDL
-- 5) 便捷索引：按剧取演员（含非模特）
SET @sql := IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'model_dramas' AND INDEX_NAME = 'idx_drama_lead_sort') = 0,
    'ALTER TABLE `model_dramas` ADD KEY `idx_drama_lead_sort` (`drama_id`, `is_lead`, `sort_order`)',
    'SELECT ''idx_drama_lead_sort 已存在'' AS msg'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 6) 回填：历史记录 model_id 非空，actor_name 保持 NULL 即可，无需处理
SELECT '短剧参演关系泛化迁移完成' AS result;
