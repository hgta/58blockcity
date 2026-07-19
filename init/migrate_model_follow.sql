-- 模特关注表 + follower_count 改造为 INT（如已存在则忽略错误）

CREATE TABLE IF NOT EXISTS `model_follows` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `model_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `model_user` (`model_id`, `user_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_model_id` (`model_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- follower_count 原为手填 varchar(可能含 "1.2w" 等非数值)，改为 INT 并由关注行为维护
-- MODIFY 对非数值会自动 coerce 为 0，幂等可重复执行
ALTER TABLE `models` MODIFY COLUMN `follower_count` int(11) NOT NULL DEFAULT 0 COMMENT '粉丝数(由关注行为维护)';
