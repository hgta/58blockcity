-- ============================================================
-- 演员表（actors）：独立演职人员档案，支持跨短剧复用
--
-- 背景：此前非模特演员以 model_dramas.actor_name 纯文本登记，
--       无法复用、无头像。主要短剧演员来来回回是同一批人，
--       需要维护一次、多剧复用，并展示头像。
--
-- 设计取舍：
--   actors 与 models 保持两套独立实体
--     · models 有完整主页 / 粉丝 / 关注 / 作品，用于模特
--     · actors 仅为演职人员档案（姓名 + 头像 + 简介），无主页
--   同一人既可是模特也是演员；同剧关联时优先用 model_id（可跳主页）
--
-- 变更：
--   1) 新建 actors 表
--   2) model_dramas 新增 actor_id（关联 actors，可空）
--   3) 把历史纯文本 actor_name 自动转换为 actors 记录并回填 actor_id
--   4) actor_name 保留（作为无档案演员的兜底展示，不再新增使用）
--
-- 头像字段 avatar 支持两种来源：
--   · 上传 → 相对路径（assets/uploads/actors/YYYYMM/xxx.jpg）
--   · 外链 → 完整 http(s) URL
--   展示层统一由 model_media() 归一化
--
-- 本脚本幂等，可重复执行。
-- ============================================================

SET @db := DATABASE();

-- 1) 新建 actors 表
CREATE TABLE IF NOT EXISTS `actors` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nickname` varchar(100) NOT NULL COMMENT '演员姓名/艺名',
  `avatar` varchar(500) DEFAULT NULL COMMENT '头像：上传相对路径 或 完整外链URL',
  `gender` enum('男','女','保密') DEFAULT '保密',
  `city` varchar(100) DEFAULT NULL COMMENT '所在城市',
  `bio` varchar(500) DEFAULT NULL COMMENT '一句话简介',
  `drama_count` int(11) DEFAULT 0 COMMENT '参演短剧数（冗余，关联增删时维护）',
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_nickname` (`nickname`),
  KEY `idx_status` (`status`),
  KEY `idx_drama_count` (`drama_count`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='短剧演员表（跨剧复用的演职人员档案）';

-- 2) model_dramas 新增 actor_id
SET @sql := IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'model_dramas' AND COLUMN_NAME = 'actor_id') = 0,
    'ALTER TABLE `model_dramas` ADD COLUMN `actor_id` int(11) DEFAULT NULL COMMENT ''关联演员表ID（普通演员）'' AFTER `model_id`',
    'SELECT ''model_dramas.actor_id 已存在'' AS msg'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 3) actor_id 索引
SET @sql := IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'model_dramas' AND INDEX_NAME = 'idx_actor_id') = 0,
    'ALTER TABLE `model_dramas` ADD KEY `idx_actor_id` (`actor_id`)',
    'SELECT ''idx_actor_id 已存在'' AS msg'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 4) 防重复：同一部剧同一演员只能一条（actor_id 非空时生效）
SET @sql := IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'model_dramas' AND INDEX_NAME = 'uniq_drama_actor_id') = 0,
    'ALTER TABLE `model_dramas` ADD UNIQUE KEY `uniq_drama_actor_id` (`drama_id`, `actor_id`)',
    'SELECT ''uniq_drama_actor_id 已存在'' AS msg'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 5) 历史数据转换：把 model_dramas 中已有的纯文本 actor_name
--    去重写入 actors，再回填 actor_id
INSERT INTO `actors` (`nickname`, `status`)
SELECT DISTINCT md.actor_name, 'active'
FROM `model_dramas` md
WHERE md.actor_name IS NOT NULL
  AND md.actor_name <> ''
  AND md.actor_id IS NULL
  AND NOT EXISTS (
      SELECT 1 FROM `actors` a WHERE a.nickname = md.actor_name
  );

UPDATE `model_dramas` md
JOIN `actors` a ON a.nickname = md.actor_name
SET md.actor_id = a.id
WHERE md.actor_id IS NULL
  AND md.actor_name IS NOT NULL
  AND md.actor_name <> '';

-- 6) 回填 actors.drama_count（按有效关联统计）
UPDATE `actors` a
SET a.drama_count = (
    SELECT COUNT(*) FROM `model_dramas` md
    WHERE md.actor_id = a.id
);

SELECT '演员表迁移完成' AS result;
