-- ============================================================
-- 模特留言板（公开留言，支持回复）
--
-- 背景：此前模特页留言误用 Message 类（私信双向会话），
--       导致用户只能看到自己与模特之间的消息，且未登录完全不可见。
--       模特主页的留言本质是「公开留言板」，应所有人可见。
--
-- 复用仓库中已存在但未被任何代码使用的 model_messages 表，
-- 扩展为支持「回复」与「软删除」。
--
-- 设计：
--   · 未登录可浏览（读取不加登录校验），登录才可发帖/回复
--   · 先发后审：发布即显示，后台可删除（status = deleted）
--   · 回复采用单层结构：回复关系挂在 parent_id 上，
--     不做无限嵌套（与微博/抖音评论一致，只分「主楼」和「回复」）
--
-- 幂等，可重复执行。
-- ============================================================

SET @db := DATABASE();

-- 1) 表不存在则创建（兼容全新环境）
CREATE TABLE IF NOT EXISTS `model_messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `model_id` int(11) NOT NULL COMMENT '被留言的模特ID',
  `user_id` int(11) NOT NULL COMMENT '留言者用户ID',
  `message` text NOT NULL COMMENT '留言内容',
  `parent_id` int(11) DEFAULT NULL COMMENT '回复的主留言ID（NULL=主留言）',
  `reply_to_user_id` int(11) DEFAULT NULL COMMENT '被回复的用户ID（渲染「回复 @某人」）',
  `like_count` int(11) NOT NULL DEFAULT 0 COMMENT '点赞数',
  `status` enum('active','deleted') NOT NULL DEFAULT 'active',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_model_status` (`model_id`, `status`, `created_at`),
  KEY `idx_parent` (`parent_id`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='模特公开留言板';

-- 2) 老环境补列：parent_id
SET @sql := IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'model_messages' AND COLUMN_NAME = 'parent_id') = 0,
    'ALTER TABLE `model_messages` ADD COLUMN `parent_id` int(11) DEFAULT NULL COMMENT ''回复的主留言ID（NULL=主留言）'' AFTER `message`',
    'SELECT ''model_messages.parent_id 已存在'' AS msg'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 3) reply_to_user_id
SET @sql := IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'model_messages' AND COLUMN_NAME = 'reply_to_user_id') = 0,
    'ALTER TABLE `model_messages` ADD COLUMN `reply_to_user_id` int(11) DEFAULT NULL COMMENT ''被回复的用户ID'' AFTER `parent_id`',
    'SELECT ''model_messages.reply_to_user_id 已存在'' AS msg'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 4) like_count
SET @sql := IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'model_messages' AND COLUMN_NAME = 'like_count') = 0,
    'ALTER TABLE `model_messages` ADD COLUMN `like_count` int(11) NOT NULL DEFAULT 0 COMMENT ''点赞数'' AFTER `reply_to_user_id`',
    'SELECT ''model_messages.like_count 已存在'' AS msg'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 5) status
SET @sql := IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'model_messages' AND COLUMN_NAME = 'status') = 0,
    'ALTER TABLE `model_messages` ADD COLUMN `status` enum(''active'',''deleted'') NOT NULL DEFAULT ''active'' AFTER `like_count`',
    'SELECT ''model_messages.status 已存在'' AS msg'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 6) 复合索引：按模特取有效留言并倒序
SET @sql := IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'model_messages' AND INDEX_NAME = 'idx_model_status') = 0,
    'ALTER TABLE `model_messages` ADD KEY `idx_model_status` (`model_id`, `status`, `created_at`)',
    'SELECT ''idx_model_status 已存在'' AS msg'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 7) 回复查询索引
SET @sql := IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'model_messages' AND INDEX_NAME = 'idx_parent') = 0,
    'ALTER TABLE `model_messages` ADD KEY `idx_parent` (`parent_id`)',
    'SELECT ''idx_parent 已存在'' AS msg'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 8) 历史表 comment 字段修正：老表用 message text NOT NULL，已兼容，无需处理
--    老表无 status 时默认 active，历史留言直接可见

SELECT '模特留言板迁移完成' AS result;
