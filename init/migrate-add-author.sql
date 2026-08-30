-- 作者库（Author）：为商品图案原创作者提供独立的展示与互动体系
-- 平行模特（Model）功能，差异：无三围/体重/身高，新增 bio（简介）与 style（创作风格），
-- 并多一个独立上传的"原创作品图集"（author_works JSON，方案同模特 daily_photos）
-- 部署时执行一次即可；重复执行会报重复列/重复键错误，可忽略（幂等保护见下方条件判断）

-- --------------------------------------------------------
-- 1. authors 表（作者档案）
--    follower_count 直接使用 int（规避模特历史遗留的 varchar 问题）
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `authors` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL COMMENT '关联站内用户（可选）',
  `nickname` varchar(100) NOT NULL COMMENT '作者名/艺名（必填）',
  `gender` enum('男','女','保密') DEFAULT '保密',
  `city` varchar(100) DEFAULT NULL COMMENT '所在城市',
  `zodiac` varchar(20) DEFAULT NULL COMMENT '星座',
  `style` varchar(50) DEFAULT NULL COMMENT '创作领域/风格标签',
  `bio` text DEFAULT NULL COMMENT '作者简介',
  `qq` varchar(20) DEFAULT NULL,
  `weixin` varchar(100) DEFAULT NULL,
  `weibo` varchar(200) DEFAULT NULL,
  `xiaohongshu` varchar(200) DEFAULT NULL COMMENT '小红书',
  `avatar` varchar(255) DEFAULT NULL COMMENT '头像',
  `author_works` text DEFAULT NULL COMMENT '原创作品图集 JSON',
  `follower_count` int(11) DEFAULT 0 COMMENT '粉丝数（整数，展示走 formatFollower）',
  `like_count` int(11) DEFAULT 0 COMMENT '点赞数（冗余）',
  `product_count` int(11) DEFAULT 0 COMMENT '关联商品数（冗余）',
  `review_count` int(11) DEFAULT 0 COMMENT '关联评论数（冗余）',
  `view_count` int(11) DEFAULT 0 COMMENT '访问量（冗余，详情页 +1）',
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  KEY `idx_like_count` (`like_count`),
  KEY `idx_product_count` (`product_count`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 2. author_follows 表（关注，平行 model_follows）
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `author_follows` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `author_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `author_user` (`author_id`, `user_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_author_id` (`author_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 3. author_likes 表（点赞，平行 model_likes）
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `author_likes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `author_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `author_user` (`author_id`, `user_id`),
  KEY `idx_author_id` (`author_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 4. products 表扩展：author_id（单一作者，可空，AFTER model_id）
--    幂等：仅在列不存在时添加
-- --------------------------------------------------------
SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND COLUMN_NAME = 'author_id'
);
SET @ddl = IF(@col_exists = 0,
  'ALTER TABLE `products` ADD COLUMN `author_id` int(11) DEFAULT NULL COMMENT ''关联图案作者（可选）'' AFTER `model_id`, ADD KEY `idx_author_id` (`author_id`)',
  'SELECT 1'
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
