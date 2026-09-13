-- ============================================================
-- 模特子站（model.58.tl）迁移脚本
-- 内容：
--   1. models 增列：video_url / video_cover / intro / drama_count
--   2. 新增 dramas 表（短剧主表）
--   3. 新增 model_dramas 表（模特参演关联）
--   4. 索引补充与 drama_count 回填
-- 特性：幂等，可重复执行（判存在再 ALTER）
-- ============================================================

-- ------------------------------------------------------------
-- 1. models 增列
-- ------------------------------------------------------------

-- video_url：管理员挂的视频地址（直链 mp4/webm 或第三方外链）
SET @ddl := (
  SELECT IF(
    EXISTS(
      SELECT 1 FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'models' AND COLUMN_NAME = 'video_url'
    ),
    'SELECT 1',
    'ALTER TABLE `models` ADD COLUMN `video_url` varchar(500) DEFAULT NULL COMMENT ''模特视频地址（直链或外链，管理员维护）'' AFTER `avatar`'
  )
);
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- video_cover：视频封面 / 无视频时的 Hero 兜底图
SET @ddl := (
  SELECT IF(
    EXISTS(
      SELECT 1 FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'models' AND COLUMN_NAME = 'video_cover'
    ),
    'SELECT 1',
    'ALTER TABLE `models` ADD COLUMN `video_cover` varchar(255) DEFAULT NULL COMMENT ''视频封面图（Hero 兜底）'' AFTER `video_url`'
  )
);
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- intro：一句话个人简介（区别于 hobbies 的「爱好」）
SET @ddl := (
  SELECT IF(
    EXISTS(
      SELECT 1 FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'models' AND COLUMN_NAME = 'intro'
    ),
    'SELECT 1',
    'ALTER TABLE `models` ADD COLUMN `intro` varchar(255) DEFAULT NULL COMMENT ''一句话个人简介'' AFTER `hobbies`'
  )
);
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- drama_count：参演短剧数（冗余计数，关联增删时维护）
SET @ddl := (
  SELECT IF(
    EXISTS(
      SELECT 1 FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'models' AND COLUMN_NAME = 'drama_count'
    ),
    'SELECT 1',
    'ALTER TABLE `models` ADD COLUMN `drama_count` int(11) NOT NULL DEFAULT 0 COMMENT ''参演短剧数（冗余）'' AFTER `review_count`'
  )
);
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ------------------------------------------------------------
-- 2. dramas 短剧主表
-- ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `dramas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(200) NOT NULL COMMENT '剧名',
  `slug` varchar(200) DEFAULT NULL COMMENT 'URL slug',
  `cover` varchar(255) DEFAULT NULL COMMENT '封面图',
  `episodes` int(11) DEFAULT NULL COMMENT '总集数',
  `tags` text DEFAULT NULL COMMENT '题材标签 JSON 数组',
  `hg_url` varchar(500) DEFAULT NULL COMMENT '红果等外部观看地址',
  `synopsis` text DEFAULT NULL COMMENT '剧情简介',
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_title` (`title`),
  KEY `idx_updated_at` (`updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='短剧主表';

-- ------------------------------------------------------------
-- 3. model_dramas 参演关联表
-- ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `model_dramas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `model_id` int(11) NOT NULL,
  `drama_id` int(11) NOT NULL,
  `role_name` varchar(100) DEFAULT NULL COMMENT '饰演角色名',
  `is_lead` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否主演',
  `sort_order` int(11) NOT NULL DEFAULT 0 COMMENT '排序（小在前）',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `model_drama` (`model_id`, `drama_id`),
  KEY `idx_drama_id` (`drama_id`),
  KEY `idx_model_id` (`model_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='模特参演短剧关联';

-- ------------------------------------------------------------
-- 4. 索引补充（排序维度）
-- ------------------------------------------------------------

SET @ddl := (
  SELECT IF(
    EXISTS(
      SELECT 1 FROM information_schema.STATISTICS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'models' AND INDEX_NAME = 'idx_follower_count'
    ),
    'SELECT 1',
    'ALTER TABLE `models` ADD KEY `idx_follower_count` (`follower_count`)'
  )
);
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @ddl := (
  SELECT IF(
    EXISTS(
      SELECT 1 FROM information_schema.STATISTICS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'models' AND INDEX_NAME = 'idx_drama_count'
    ),
    'SELECT 1',
    'ALTER TABLE `models` ADD KEY `idx_drama_count` (`drama_count`)'
  )
);
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ------------------------------------------------------------
-- 5. drama_count 回填（对账用，可重复执行）
-- ------------------------------------------------------------

UPDATE `models` m
SET m.`drama_count` = (
  SELECT COUNT(*) FROM `model_dramas` md WHERE md.`model_id` = m.`id`
);
