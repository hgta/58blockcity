-- 区块售卖市场重构：数据库迁移脚本
-- 适用于已在运行的数据库（db-init.sql 仅对全新安装生效，已在库需手动执行本文件）
--
-- 执行方式（在 MySQL 客户端，USE 对应数据库后）：
--   source /path/to/migration-block-sale-market.sql;
-- 或：
--   mysql -u<user> -p<db> < init/migration-block-sale-market.sql

-- 1) blocks 表新增区块皮肤展示字段
ALTER TABLE `blocks`
  ADD COLUMN `display_type` enum('none','image','text') NOT NULL DEFAULT 'none' COMMENT '区块皮肤: none=默认, image=图片, text=文字' AFTER `description`,
  ADD COLUMN `display_image` varchar(255) DEFAULT NULL COMMENT '图片模式下的图片路径' AFTER `display_type`,
  ADD COLUMN `display_text` varchar(50) DEFAULT NULL COMMENT '文字模式下的文字内容' AFTER `display_image`,
  ADD COLUMN `display_color` enum('red','green','blue') DEFAULT NULL COMMENT '文字模式背景色' AFTER `display_text`;

-- 2) 新建区块挂牌表
CREATE TABLE IF NOT EXISTS `block_listings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `city_id` int(11) NOT NULL,
  `seller_id` int(11) NOT NULL,
  `block_id` int(11) DEFAULT NULL COMMENT '单块挂牌时指向 blocks.id',
  `merged_block_id` int(11) DEFAULT NULL COMMENT '合并块整体挂牌时指向 merged_blocks.id',
  `price` decimal(20,2) NOT NULL,
  `currency` enum('popularity','cny') NOT NULL COMMENT '人气值 / 人民币',
  `status` enum('listed','pending','completed','canceled') NOT NULL DEFAULT 'listed',
  `buyer_id` int(11) DEFAULT NULL,
  `contact_phone` varchar(20) DEFAULT NULL,
  `contact_wechat` varchar(50) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `completed_at` datetime DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_city_status` (`city_id`,`status`),
  KEY `idx_seller` (`seller_id`),
  KEY `idx_block` (`block_id`),
  KEY `idx_merged` (`merged_block_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='区块挂牌表';
