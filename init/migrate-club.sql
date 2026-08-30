-- 58区块社区子站数据表
-- 帖子/心情 + 评论 + 点赞 + 通知类型扩展

CREATE TABLE IF NOT EXISTS `posts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL COMMENT '作者',
  `city` varchar(50) DEFAULT NULL COMMENT '城市名(字符串)',
  `type` enum('post','moment') NOT NULL DEFAULT 'post' COMMENT '帖子/一句话心情',
  `title` varchar(100) DEFAULT NULL COMMENT '标题(moment 可为空)',
  `content` text NOT NULL COMMENT '正文/心情内容',
  `images` text COMMENT '配图 JSON 数组',
  `topic` varchar(30) DEFAULT NULL COMMENT '话题: block/nft/bct(城市是板块,非话题)',
  `like_count` int(11) NOT NULL DEFAULT '0',
  `comment_count` int(11) NOT NULL DEFAULT '0',
  `status` enum('active','hidden') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_city_time` (`city`,`created_at`),
  KEY `idx_topic_time` (`topic`,`created_at`),
  KEY `idx_user` (`user_id`),
  KEY `idx_type_time` (`type`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='社区帖子/心情';

CREATE TABLE IF NOT EXISTS `post_comments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `post_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `parent_id` int(11) NOT NULL DEFAULT '0' COMMENT '0=直接评论帖子',
  `content` text NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_post` (`post_id`),
  KEY `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='帖子评论';

CREATE TABLE IF NOT EXISTS `post_likes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `post_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `post_user` (`post_id`,`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='帖子点赞';

-- 扩展通知类型（幂等：MODIFY 可重复执行，enum 值集合不变）
ALTER TABLE `notifications`
  MODIFY COLUMN `type` enum('visit_request','visit_confirm','return_confirm','system','order_paid','order_shipped','order_done','new_review','dm','new_comment','new_reply','new_like') NOT NULL;

-- ============================================================
-- club-redesign-v2ex-seo：置顶/浏览数/关注（幂等，可重复执行）
-- ============================================================

-- posts 新增字段：is_sticky / view_count
-- MySQL 5.7 无 ADD COLUMN IF NOT EXISTS，用存储过程做幂等判断
DROP PROCEDURE IF EXISTS `club_add_columns`;
DELIMITER $$
CREATE PROCEDURE `club_add_columns`()
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'posts' AND COLUMN_NAME = 'is_sticky'
  ) THEN
    ALTER TABLE `posts` ADD COLUMN `is_sticky` TINYINT NOT NULL DEFAULT 0 COMMENT '置顶: 1=置顶';
  END IF;
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'posts' AND COLUMN_NAME = 'view_count'
  ) THEN
    ALTER TABLE `posts` ADD COLUMN `view_count` INT NOT NULL DEFAULT 0 COMMENT '浏览数';
  END IF;
END$$
DELIMITER ;
CALL `club_add_columns`();
DROP PROCEDURE IF EXISTS `club_add_columns`;

-- 社区关注关系
CREATE TABLE IF NOT EXISTS `club_follows` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL COMMENT '关注者',
  `target_id` int(11) NOT NULL COMMENT '被关注者',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_follow` (`user_id`,`target_id`),
  KEY `idx_target` (`target_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='社区关注';
