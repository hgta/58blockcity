-- 58拍卖子站数据表
-- 拍卖单 + 出价记录

CREATE TABLE IF NOT EXISTS `auctions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `item_type` enum('block','nft') NOT NULL COMMENT '拍卖品类型',
  `item_id` int(11) NOT NULL COMMENT 'blocks.id 或 nft_city_user.id',
  `seller_id` int(11) NOT NULL COMMENT '卖家(原拥有者)',
  `start_price` decimal(20,2) NOT NULL COMMENT '起拍价',
  `reserve_price` decimal(20,2) DEFAULT NULL COMMENT '底价(可选, 低于底价流拍)',
  `bid_increment` decimal(20,2) NOT NULL DEFAULT '1.00' COMMENT '每次加价幅度',
  `start_time` datetime NOT NULL COMMENT '开始时间',
  `end_time` datetime NOT NULL COMMENT '截止时间',
  `current_price` decimal(20,2) DEFAULT NULL COMMENT '当前价',
  `current_bidder_id` int(11) DEFAULT NULL COMMENT '当前最高出价人',
  `currency` enum('popularity','cny') NOT NULL COMMENT '货币: 人气值/人民币',
  `accept_cities` text COMMENT '接受支付的城市人气值(JSON数组, 仅人气值时有效)',
  `status` enum('pending','active','ended','sold','canceled') NOT NULL DEFAULT 'pending',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_status_end` (`status`,`end_time`),
  KEY `idx_item` (`item_type`,`item_id`),
  KEY `idx_seller` (`seller_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='拍卖单';

CREATE TABLE IF NOT EXISTS `auction_bids` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `auction_id` int(11) NOT NULL,
  `bidder_id` int(11) NOT NULL,
  `amount` decimal(20,2) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_auction` (`auction_id`),
  KEY `idx_bidder` (`bidder_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='拍卖出价记录';
