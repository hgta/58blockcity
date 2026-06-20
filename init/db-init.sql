-- phpMyAdmin SQL Dump
-- version 4.4.15.10
-- https://www.phpmyadmin.net
--
-- Host: localhost
-- Generation Time: 2026-05-30 17:53:59
-- 服务器版本： 5.7.28-log
-- PHP Version: 5.6.40

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `58tl`
--

-- --------------------------------------------------------

--
-- 表的结构 `attribute_definitions`
--

CREATE TABLE IF NOT EXISTS `attribute_definitions` (
  `id` int(11) NOT NULL,
  `attribute_type` varchar(50) NOT NULL,
  `attribute_value` varchar(100) NOT NULL,
  `rarity_weight` smallint(6) DEFAULT '100' COMMENT '稀有度权重',
  `compatible_types` json DEFAULT NULL COMMENT '兼容的其他属性类型',
  `description` text COMMENT '属性描述'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='属性定义表';

-- --------------------------------------------------------

--
-- 表的结构 `bct_orders`
--

CREATE TABLE IF NOT EXISTS `bct_orders` (
  `id` int(11) NOT NULL,
  `order_no` varchar(20) NOT NULL,
  `user_id` int(11) NOT NULL,
  `city` varchar(50) NOT NULL,
  `type` enum('buy','sell') NOT NULL,
  `amount` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `trade_type` enum('platform','mediator','direct') NOT NULL,
  `status` enum('pending','processing','completed','canceled') DEFAULT 'pending',
  `counterparty_id` int(11) DEFAULT NULL,
  `mediator_id` int(11) DEFAULT NULL,
  `contact_info` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- 表的结构 `bct_transactions`
--

CREATE TABLE IF NOT EXISTS `bct_transactions` (
  `id` int(11) NOT NULL,
  `tx_no` varchar(20) NOT NULL,
  `order_id` int(11) NOT NULL,
  `from_user` int(11) NOT NULL,
  `to_user` int(11) NOT NULL,
  `city` varchar(50) NOT NULL,
  `amount` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `fee` decimal(10,2) DEFAULT '0.00',
  `fee_type` varchar(20) DEFAULT NULL,
  `net_amount` decimal(10,2) NOT NULL,
  `tx_type` enum('trade','transfer') NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- 表的结构 `blocks`
--

CREATE TABLE IF NOT EXISTS `blocks` (
  `id` int(11) NOT NULL,
  `city_id` int(11) NOT NULL,
  `zone` enum('A','B','C','D','E','F','G','H','Z') NOT NULL,
  `block_number` varchar(10) NOT NULL,
  `price` decimal(12,2) NOT NULL,
  `owner_id` int(11) DEFAULT NULL,
  `name` varchar(100) DEFAULT NULL,
  `description` text,
  `status` enum('available','sold','reserved') DEFAULT 'available',
  `is_large_block` tinyint(1) DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- 表的结构 `cart_items`
--

CREATE TABLE IF NOT EXISTS `cart_items` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- 表的结构 `circles`
--

CREATE TABLE IF NOT EXISTS `circles` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text,
  `city` varchar(50) NOT NULL,
  `category` varchar(50) DEFAULT NULL,
  `block_count` int(11) DEFAULT '0',
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- 替换视图以便查看 `circle_rankings`
--
CREATE TABLE IF NOT EXISTS `circle_rankings` (
`id` int(11)
,`name` varchar(100)
,`city` varchar(50)
,`block_count` int(11)
,`owner_name` varchar(50)
,`total_visits` bigint(21)
,`completed_visits` bigint(21)
,`unique_visitors` bigint(21)
);

-- --------------------------------------------------------

--
-- 表的结构 `cities`
--

CREATE TABLE IF NOT EXISTS `cities` (
  `id` int(11) NOT NULL,
  `name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '城市名称',
  `pinyin` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '城市拼音',
  `is_hot` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否热门城市',
  `area_code` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '城市区号',
  `rank` int(11) DEFAULT '0' COMMENT '城市排名',
  `resident_count` int(11) DEFAULT '0' COMMENT '现有居民数',
  `activated_blocks` int(11) DEFAULT '0' COMMENT '已开启区块数',
  `total_fund` decimal(20,2) DEFAULT '0.00' COMMENT '基金总体额度',
  `current_balance` decimal(20,2) DEFAULT '0.00' COMMENT '当前余额',
  `popularity` int(11) DEFAULT '0' COMMENT '已产生人气值',
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci DEFAULT 'active' COMMENT '状态',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='城市数据表';

-- --------------------------------------------------------

--
-- 表的结构 `city_bct`
--

CREATE TABLE IF NOT EXISTS `city_bct` (
  `id` int(11) NOT NULL,
  `city` varchar(50) NOT NULL,
  `total_supply` int(11) NOT NULL DEFAULT '21000000',
  `circulating_supply` int(11) NOT NULL DEFAULT '0',
  `base_price` decimal(10,2) NOT NULL DEFAULT '0.10',
  `current_price` decimal(10,2) NOT NULL DEFAULT '0.10',
  `last_updated` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- 替换视图以便查看 `city_rankings`
--
CREATE TABLE IF NOT EXISTS `city_rankings` (
`city` varchar(50)
,`circle_count` bigint(21)
,`user_count` bigint(21)
,`visit_count` bigint(21)
,`completed_visit_count` bigint(21)
);

-- --------------------------------------------------------

--
-- 表的结构 `comments`
--

CREATE TABLE IF NOT EXISTS `comments` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `nft_id` int(11) NOT NULL,
  `content` text NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- 表的结构 `mediators`
--

CREATE TABLE IF NOT EXISTS `mediators` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `contact` varchar(100) NOT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- 表的结构 `merged_blocks`
--

CREATE TABLE IF NOT EXISTS `merged_blocks` (
  `id` int(11) NOT NULL,
  `city_id` int(11) NOT NULL,
  `zone` enum('A','B','C','D','E','F','G','H','Z') NOT NULL,
  `merged_blocks` text NOT NULL COMMENT '合并的区块编号，用逗号分隔',
  `merge_size` varchar(10) NOT NULL COMMENT '合并尺寸，如2x2, 3x3等',
  `owner_id` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- 表的结构 `nft_attributes`
--

CREATE TABLE IF NOT EXISTS `nft_attributes` (
  `id` int(11) NOT NULL,
  `nft_id` int(11) NOT NULL,
  `attribute_type` varchar(50) NOT NULL COMMENT '属性类型(color/species/background等)',
  `attribute_value` varchar(100) NOT NULL COMMENT '属性值',
  `display_order` smallint(6) DEFAULT '0' COMMENT '显示顺序',
  `is_active` tinyint(1) DEFAULT '1' COMMENT '是否激活'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='NFT属性表';

-- --------------------------------------------------------

--
-- 表的结构 `nft_avatars`
--

CREATE TABLE IF NOT EXISTS `nft_avatars` (
  `id` int(11) NOT NULL,
  `code` varchar(255) NOT NULL COMMENT '文件名(不含扩展名)',
  `base_image` varchar(255) NOT NULL COMMENT '完整文件名',
  `avatar_id` varchar(255) NOT NULL COMMENT 'Avatar ID',
  `avatar_key` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- 表的结构 `nft_city_user`
--

CREATE TABLE IF NOT EXISTS `nft_city_user` (
  `id` int(11) NOT NULL,
  `nft_id` int(11) NOT NULL COMMENT 'NFT ID',
  `city_id` int(11) NOT NULL COMMENT '城市ID',
  `user_id` int(11) NOT NULL COMMENT '用户ID',
  `is_current` tinyint(1) DEFAULT '1' COMMENT '是否当前关联',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `block_id` varchar(20) DEFAULT NULL COMMENT '区块ID',
  `is_listed` tinyint(1) NOT NULL DEFAULT '0',
  `transaction_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='NFT-城市-用户关联表';

-- --------------------------------------------------------

--
-- 表的结构 `nft_claim_appeals`
--

CREATE TABLE IF NOT EXISTS `nft_claim_appeals` (
  `id` int(11) NOT NULL,
  `nft_id` int(11) NOT NULL,
  `city_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `evidence_images` text NOT NULL COMMENT '逗号分隔的图片路径',
  `reason` text NOT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `admin_id` int(11) DEFAULT NULL COMMENT '处理管理员ID',
  `admin_comment` text,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `processed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='NFT认领申诉表';

-- --------------------------------------------------------

--
-- 表的结构 `nft_purchase_requests`
--

CREATE TABLE IF NOT EXISTS `nft_purchase_requests` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `nft_id` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL COMMENT '求购价格',
  `currency` enum('popularity','cny') NOT NULL COMMENT '人气值或人民币',
  `transaction_type` enum('platform','intermediary','direct') NOT NULL DEFAULT 'platform',
  `contact_phone` varchar(20) DEFAULT NULL,
  `contact_wechat` varchar(50) DEFAULT NULL,
  `contact_qq` varchar(20) DEFAULT NULL,
  `contact_email` varchar(100) DEFAULT NULL,
  `block_number` varchar(50) DEFAULT NULL COMMENT '区块号',
  `status` enum('pending','completed','canceled') NOT NULL DEFAULT 'pending',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `city_id` int(11) NOT NULL COMMENT '关联 cities 表的城市ID'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- 表的结构 `nft_sales`
--

CREATE TABLE IF NOT EXISTS `nft_sales` (
  `id` int(11) NOT NULL,
  `nft_id` int(11) NOT NULL,
  `city_id` int(11) NOT NULL,
  `seller_id` int(11) NOT NULL,
  `price` decimal(20,2) NOT NULL,
  `currency` enum('popularity','cny') NOT NULL,
  `status` enum('active','pending','completed','canceled') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='NFT销售表';

-- --------------------------------------------------------

--
-- 表的结构 `nft_tags`
--

CREATE TABLE IF NOT EXISTS `nft_tags` (
  `id` int(11) NOT NULL,
  `nft_avatar_id` int(11) NOT NULL,
  `tag_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- 表的结构 `nft_transactions`
--

CREATE TABLE IF NOT EXISTS `nft_transactions` (
  `id` int(11) NOT NULL,
  `nft_id` int(11) NOT NULL,
  `seller_id` int(11) NOT NULL,
  `buyer_id` int(11) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `currency` enum('popularity','cny') NOT NULL,
  `transaction_type` enum('platform','intermediary','direct') NOT NULL DEFAULT 'platform',
  `status` enum('listed','pending','completed','canceled') NOT NULL DEFAULT 'listed',
  `city_id` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `completed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- 表的结构 `notifications`
--

CREATE TABLE IF NOT EXISTS `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `type` enum('visit_request','visit_confirm','return_confirm','system') NOT NULL,
  `related_id` int(11) DEFAULT NULL,
  `content` text NOT NULL,
  `is_read` tinyint(1) DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- 表的结构 `orders`
--

CREATE TABLE IF NOT EXISTS `orders` (
  `id` int(11) NOT NULL,
  `order_no` varchar(20) NOT NULL COMMENT '订单号',
  `user_id` int(11) NOT NULL COMMENT '买家用户ID',
  `shop_id` int(11) NOT NULL COMMENT '店铺ID',
  `total_amount` decimal(10,2) NOT NULL COMMENT '订单总金额',
  `payment_city` varchar(50) NOT NULL COMMENT '支付城市',
  `payment_amount` decimal(10,2) NOT NULL COMMENT '支付人气值数量',
  `payment_block_id` varchar(100) DEFAULT NULL COMMENT '支付区块ID',
  `status` enum('pending','paid','shipped','completed','cancelled','refunded') DEFAULT 'pending',
  `buyer_note` text COMMENT '买家留言',
  `shipping_address` varchar(500) DEFAULT NULL COMMENT '收货地址',
  `seller_note` text COMMENT '卖家备注',
  `paid_at` datetime DEFAULT NULL COMMENT '支付时间',
  `shipped_at` datetime DEFAULT NULL COMMENT '发货时间',
  `completed_at` datetime DEFAULT NULL COMMENT '完成时间',
  `shipping_company` varchar(100) DEFAULT NULL COMMENT '物流公司',
  `tracking_no` varchar(100) DEFAULT NULL COMMENT '运单号',
  `expire_at` datetime DEFAULT NULL COMMENT '订单过期时间',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- 表的结构 `order_items`
--

CREATE TABLE IF NOT EXISTS `order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `product_name` varchar(200) NOT NULL COMMENT '商品名称快照',
  `product_image` varchar(255) NOT NULL COMMENT '商品图片快照',
  `quantity` int(11) NOT NULL COMMENT '购买数量',
  `unit_price` decimal(10,2) NOT NULL COMMENT '单价',
  `total_price` decimal(10,2) NOT NULL COMMENT '总价',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- 表的结构 `products`
--

CREATE TABLE IF NOT EXISTS `products` (
  `id` int(11) NOT NULL,
  `shop_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `name` varchar(200) NOT NULL COMMENT '商品名称',
  `description` text COMMENT '商品描述',
  `main_image` varchar(255) NOT NULL COMMENT '主图',
  `thumb_image` varchar(255) DEFAULT NULL COMMENT '缩略图',
  `images` json DEFAULT NULL COMMENT '商品图集',
  `video_url` varchar(500) DEFAULT NULL COMMENT '商品介绍视频路径或外部链接',
  `price_type` enum('fixed','bct') DEFAULT 'bct' COMMENT '价格类型',
  `price_bct` decimal(10,2) DEFAULT NULL COMMENT '人气值价格',
  `price_cny` decimal(10,2) DEFAULT NULL COMMENT '人民币价格',
  `stock` int(11) DEFAULT '0' COMMENT '库存',
  `sold_count` int(11) DEFAULT '0' COMMENT '销量',
  `status` enum('draft','active','inactive','sold_out') DEFAULT 'draft' COMMENT '商品状态',
  `is_recommended` tinyint(1) DEFAULT '0' COMMENT '是否推荐',
  `view_count` int(11) DEFAULT '0' COMMENT '浏览数',
  `sort_order` int(11) DEFAULT '0' COMMENT '排序',
  `rating` decimal(3,2) DEFAULT '5.00' COMMENT '商品评分',
  `review_count` int(11) DEFAULT '0' COMMENT '评价数',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- 表的结构 `product_categories`
--

CREATE TABLE IF NOT EXISTS `product_categories` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL COMMENT '分类名称',
  `parent_id` int(11) DEFAULT '0' COMMENT '父级分类ID',
  `sort_order` int(11) DEFAULT '0' COMMENT '排序',
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- 表的结构 `product_payment_cities`
--

CREATE TABLE IF NOT EXISTS `product_payment_cities` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `city` varchar(50) NOT NULL COMMENT '支持支付的城市',
  `price_adjust` decimal(5,2) DEFAULT '0.00' COMMENT '价格调整百分比',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- 表的结构 `purchase_requests`
--

CREATE TABLE IF NOT EXISTS `purchase_requests` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `city_id` int(11) NOT NULL,
  `zone` enum('A','B','C','D','E','F','G','H','Z') NOT NULL,
  `block_number` varchar(10) DEFAULT NULL,
  `max_price` decimal(12,2) DEFAULT NULL,
  `status` enum('active','fulfilled','cancelled') DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- 表的结构 `remember_tokens`
--

CREATE TABLE IF NOT EXISTS `remember_tokens` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `token` varchar(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- 表的结构 `shops`
--

CREATE TABLE IF NOT EXISTS `shops` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL COMMENT '店主用户ID',
  `shop_name` varchar(100) NOT NULL COMMENT '店铺名称',
  `shop_logo` varchar(255) DEFAULT NULL COMMENT '店铺Logo',
  `shop_banner` varchar(255) DEFAULT NULL COMMENT '店铺横幅',
  `theme_color` varchar(20) DEFAULT '#ff6b00' COMMENT '主题色',
  `announcement` varchar(500) DEFAULT NULL COMMENT '店铺公告',
  `shop_description` text COMMENT '店铺描述',
  `category_id` int(11) DEFAULT NULL,
  `contact_info` varchar(255) DEFAULT NULL COMMENT '联系方式',
  `status` enum('pending','active','suspended','closed') DEFAULT 'pending' COMMENT '店铺状态',
  `rating` decimal(3,2) DEFAULT '5.00' COMMENT '店铺评分',
  `review_count` int(11) DEFAULT '0' COMMENT '评价数',
  `total_sales` int(11) DEFAULT '0' COMMENT '总销量',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- 表的结构 `shop_payment_settings`
--

CREATE TABLE IF NOT EXISTS `shop_payment_settings` (
  `id` int(11) NOT NULL,
  `shop_id` int(11) NOT NULL,
  `city` varchar(50) NOT NULL COMMENT '城市名称',
  `block_id` varchar(100) DEFAULT NULL COMMENT '区块ID',
  `is_active` tinyint(1) DEFAULT '1' COMMENT '是否启用',
  `min_amount` decimal(10,2) DEFAULT '0.01' COMMENT '最小支付金额',
  `exchange_rate` decimal(10,4) DEFAULT '1.0000' COMMENT '兑换率',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- 表的结构 `system_settings`
--

CREATE TABLE IF NOT EXISTS `system_settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- 表的结构 `tags`
--

CREATE TABLE IF NOT EXISTS `tags` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- 表的结构 `transactions`
--

CREATE TABLE IF NOT EXISTS `transactions` (
  `id` int(11) NOT NULL,
  `block_id` int(11) NOT NULL,
  `seller_id` int(11) DEFAULT NULL,
  `buyer_id` int(11) NOT NULL,
  `price` decimal(12,2) NOT NULL,
  `transaction_type` enum('purchase','resale') NOT NULL,
  `status` enum('pending','completed','cancelled') DEFAULT 'pending',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- 表的结构 `users`
--

CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` varchar(20) DEFAULT 'user',
  `email` varchar(100) NOT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `phone` varchar(20) DEFAULT NULL,
  `city` varchar(50) DEFAULT NULL,
  `avatar` varchar(255) DEFAULT 'default.jpg',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `last_login` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- 表的结构 `user_addresses`
--

CREATE TABLE IF NOT EXISTS `user_addresses` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL COMMENT '收货人姓名',
  `phone` varchar(20) NOT NULL COMMENT '手机号码',
  `province` varchar(50) NOT NULL COMMENT '省份',
  `city` varchar(50) NOT NULL COMMENT '城市',
  `district` varchar(50) NOT NULL COMMENT '区县',
  `detail` varchar(255) NOT NULL COMMENT '详细地址',
  `is_default` tinyint(1) DEFAULT '0' COMMENT '是否默认地址',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- 表的结构 `user_bct_account`
--

CREATE TABLE IF NOT EXISTS `user_bct_account` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `city` varchar(50) NOT NULL,
  `balance` int(11) NOT NULL DEFAULT '0',
  `frozen` int(11) NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- 表的结构 `user_city_popularity`
--

CREATE TABLE IF NOT EXISTS `user_city_popularity` (
  `user_id` int(11) NOT NULL,
  `city` varchar(50) NOT NULL,
  `popularity` int(11) NOT NULL DEFAULT '0',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- 表的结构 `user_nft_collections`
--

CREATE TABLE IF NOT EXISTS `user_nft_collections` (
  `user_id` int(11) NOT NULL,
  `nft_id` int(11) NOT NULL,
  `acquired_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `is_profile_avatar` tinyint(1) DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- 替换视图以便查看 `user_rankings`
--
CREATE TABLE IF NOT EXISTS `user_rankings` (
`id` int(11)
,`username` varchar(50)
,`city` varchar(50)
,`avatar` varchar(255)
,`owned_circles` bigint(21)
,`total_blocks` decimal(32,0)
,`visits_made` bigint(21)
,`visits_received` bigint(21)
);

-- --------------------------------------------------------

--
-- 表的结构 `visits`
--

CREATE TABLE IF NOT EXISTS `visits` (
  `id` int(11) NOT NULL,
  `circle_id` int(11) NOT NULL,
  `visitor_id` int(11) NOT NULL,
  `applicant_circle_id` int(11) DEFAULT NULL,
  `visit_date` date DEFAULT NULL,
  `return_date` date DEFAULT NULL,
  `next_suggest_date` date DEFAULT NULL,
  `status` enum('pending','confirmed','visited','returned','completed') DEFAULT 'pending',
  `notes` text,
  `screenshot_path` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- 视图结构 `circle_rankings`
--
DROP TABLE IF EXISTS `circle_rankings`;

CREATE ALGORITHM=UNDEFINED DEFINER=`db_user`@`localhost` SQL SECURITY DEFINER VIEW `circle_rankings` AS select `c`.`id` AS `id`,`c`.`name` AS `name`,`c`.`city` AS `city`,`c`.`block_count` AS `block_count`,`u`.`username` AS `owner_name`,count(distinct `v`.`id`) AS `total_visits`,count(distinct (case when (`v`.`status` = 'completed') then `v`.`id` end)) AS `completed_visits`,count(distinct `v`.`visitor_id`) AS `unique_visitors` from ((`circles` `c` join `users` `u` on((`c`.`user_id` = `u`.`id`))) left join `visits` `v` on((`c`.`id` = `v`.`circle_id`))) group by `c`.`id`;

-- --------------------------------------------------------

--
-- 视图结构 `city_rankings`
--
DROP TABLE IF EXISTS `city_rankings`;

CREATE ALGORITHM=UNDEFINED DEFINER=`db_user`@`localhost` SQL SECURITY DEFINER VIEW `city_rankings` AS select `u`.`city` AS `city`,count(distinct `c`.`id`) AS `circle_count`,count(distinct `u`.`id`) AS `user_count`,count(distinct `v`.`id`) AS `visit_count`,count(distinct (case when (`v`.`status` = 'completed') then `v`.`id` end)) AS `completed_visit_count` from ((`users` `u` left join `circles` `c` on((`u`.`city` = `c`.`city`))) left join `visits` `v` on((`c`.`id` = `v`.`circle_id`))) group by `u`.`city`;

-- --------------------------------------------------------

--
-- 视图结构 `user_rankings`
--
DROP TABLE IF EXISTS `user_rankings`;

CREATE ALGORITHM=UNDEFINED DEFINER=`db_user`@`localhost` SQL SECURITY DEFINER VIEW `user_rankings` AS select `u`.`id` AS `id`,`u`.`username` AS `username`,`u`.`city` AS `city`,`u`.`avatar` AS `avatar`,coalesce(`circle_stats`.`owned_circles`,0) AS `owned_circles`,coalesce(`circle_stats`.`total_blocks`,0) AS `total_blocks`,coalesce(`visits_made`.`count`,0) AS `visits_made`,coalesce(`visits_received`.`count`,0) AS `visits_received` from (((`users` `u` left join (select `circles`.`user_id` AS `user_id`,count(`circles`.`id`) AS `owned_circles`,sum(`circles`.`block_count`) AS `total_blocks` from `circles` group by `circles`.`user_id`) `circle_stats` on((`u`.`id` = `circle_stats`.`user_id`))) left join (select `visits`.`visitor_id` AS `visitor_id`,count(distinct `visits`.`id`) AS `count` from `visits` group by `visits`.`visitor_id`) `visits_made` on((`u`.`id` = `visits_made`.`visitor_id`))) left join (select `c`.`user_id` AS `user_id`,count(distinct `v`.`id`) AS `count` from (`visits` `v` join `circles` `c` on((`v`.`circle_id` = `c`.`id`))) group by `c`.`user_id`) `visits_received` on((`u`.`id` = `visits_received`.`user_id`)));

--
-- Indexes for dumped tables
--

--
-- Indexes for table `attribute_definitions`
--
ALTER TABLE `attribute_definitions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `type_value` (`attribute_type`,`attribute_value`);

--
-- Indexes for table `bct_orders`
--
ALTER TABLE `bct_orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `order_no` (`order_no`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `city` (`city`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `bct_transactions`
--
ALTER TABLE `bct_transactions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tx_no` (`tx_no`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `from_user` (`from_user`),
  ADD KEY `to_user` (`to_user`);

--
-- Indexes for table `blocks`
--
ALTER TABLE `blocks`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `city_zone_block` (`city_id`,`zone`,`block_number`),
  ADD KEY `owner_id` (`owner_id`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `cart_items`
--
ALTER TABLE `cart_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `user_product` (`user_id`,`product_id`);

--
-- Indexes for table `circles`
--
ALTER TABLE `circles`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `city` (`city`);

--
-- Indexes for table `cities`
--
ALTER TABLE `cities`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`),
  ADD UNIQUE KEY `area_code` (`area_code`),
  ADD KEY `rank` (`rank`),
  ADD KEY `idx_pinyin` (`pinyin`);

--
-- Indexes for table `city_bct`
--
ALTER TABLE `city_bct`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `city` (`city`);

--
-- Indexes for table `comments`
--
ALTER TABLE `comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `nft_id` (`nft_id`);

--
-- Indexes for table `mediators`
--
ALTER TABLE `mediators`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- Indexes for table `merged_blocks`
--
ALTER TABLE `merged_blocks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `city_zone` (`city_id`,`zone`),
  ADD KEY `owner_id` (`owner_id`);

--
-- Indexes for table `nft_attributes`
--
ALTER TABLE `nft_attributes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nft_attribute` (`nft_id`,`attribute_type`,`attribute_value`),
  ADD KEY `attribute_type` (`attribute_type`),
  ADD KEY `attribute_value` (`attribute_value`);

--
-- Indexes for table `nft_avatars`
--
ALTER TABLE `nft_avatars`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_code` (`code`),
  ADD UNIQUE KEY `idx_avatar_id` (`avatar_id`),
  ADD KEY `idx_base_image` (`base_image`);

--
-- Indexes for table `nft_city_user`
--
ALTER TABLE `nft_city_user`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nft_city_user_unique` (`nft_id`,`city_id`,`user_id`),
  ADD KEY `city_id` (`city_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `is_current` (`is_current`);

--
-- Indexes for table `nft_claim_appeals`
--
ALTER TABLE `nft_claim_appeals`
  ADD PRIMARY KEY (`id`),
  ADD KEY `nft_id` (`nft_id`),
  ADD KEY `city_id` (`city_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `nft_purchase_requests`
--
ALTER TABLE `nft_purchase_requests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_nft_unique` (`user_id`,`nft_id`),
  ADD KEY `nft_id` (`nft_id`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `nft_sales`
--
ALTER TABLE `nft_sales`
  ADD PRIMARY KEY (`id`),
  ADD KEY `nft_id` (`nft_id`),
  ADD KEY `city_id` (`city_id`),
  ADD KEY `seller_id` (`seller_id`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `nft_tags`
--
ALTER TABLE `nft_tags`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_nft_tag` (`nft_avatar_id`,`tag_id`),
  ADD KEY `idx_tag` (`tag_id`);

--
-- Indexes for table `nft_transactions`
--
ALTER TABLE `nft_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_nft` (`nft_id`),
  ADD KEY `idx_seller` (`seller_id`),
  ADD KEY `idx_city` (`city_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `is_read` (`is_read`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `order_no` (`order_no`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `shop_id` (`shop_id`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD KEY `shop_id` (`shop_id`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `status` (`status`),
  ADD KEY `is_recommended` (`is_recommended`);

--
-- Indexes for table `product_categories`
--
ALTER TABLE `product_categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `product_payment_cities`
--
ALTER TABLE `product_payment_cities`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `product_city` (`product_id`,`city`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `purchase_requests`
--
ALTER TABLE `purchase_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `city_zone_block` (`city_id`,`zone`,`block_number`);

--
-- Indexes for table `remember_tokens`
--
ALTER TABLE `remember_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token` (`token`),
  ADD KEY `idx_token` (`token`),
  ADD KEY `idx_user_id` (`user_id`);

--
-- Indexes for table `shops`
--
ALTER TABLE `shops`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `shop_name` (`shop_name`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `shop_payment_settings`
--
ALTER TABLE `shop_payment_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `shop_city` (`shop_id`,`city`),
  ADD KEY `shop_id` (`shop_id`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `tags`
--
ALTER TABLE `tags`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_name` (`name`);

--
-- Indexes for table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `block_id` (`block_id`),
  ADD KEY `seller_id` (`seller_id`),
  ADD KEY `buyer_id` (`buyer_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `user_addresses`
--
ALTER TABLE `user_addresses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `is_default` (`is_default`);

--
-- Indexes for table `user_bct_account`
--
ALTER TABLE `user_bct_account`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_city` (`user_id`,`city`),
  ADD KEY `city` (`city`);

--
-- Indexes for table `user_city_popularity`
--
ALTER TABLE `user_city_popularity`
  ADD PRIMARY KEY (`user_id`,`city`),
  ADD KEY `city` (`city`);

--
-- Indexes for table `user_nft_collections`
--
ALTER TABLE `user_nft_collections`
  ADD PRIMARY KEY (`user_id`,`nft_id`),
  ADD KEY `nft_id` (`nft_id`);

--
-- Indexes for table `visits`
--
ALTER TABLE `visits`
  ADD PRIMARY KEY (`id`),
  ADD KEY `circle_id` (`circle_id`),
  ADD KEY `visitor_id` (`visitor_id`),
  ADD KEY `status` (`status`),
  ADD KEY `fk_applicant_circle` (`applicant_circle_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `attribute_definitions`
--
ALTER TABLE `attribute_definitions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `bct_orders`
--
ALTER TABLE `bct_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `bct_transactions`
--
ALTER TABLE `bct_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `blocks`
--
ALTER TABLE `blocks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `cart_items`
--
ALTER TABLE `cart_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `circles`
--
ALTER TABLE `circles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `cities`
--
ALTER TABLE `cities`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `city_bct`
--
ALTER TABLE `city_bct`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `comments`
--
ALTER TABLE `comments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `mediators`
--
ALTER TABLE `mediators`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `merged_blocks`
--
ALTER TABLE `merged_blocks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `nft_attributes`
--
ALTER TABLE `nft_attributes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `nft_avatars`
--
ALTER TABLE `nft_avatars`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `nft_city_user`
--
ALTER TABLE `nft_city_user`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `nft_claim_appeals`
--
ALTER TABLE `nft_claim_appeals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `nft_purchase_requests`
--
ALTER TABLE `nft_purchase_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `nft_sales`
--
ALTER TABLE `nft_sales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `nft_tags`
--
ALTER TABLE `nft_tags`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `nft_transactions`
--
ALTER TABLE `nft_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `product_categories`
--
ALTER TABLE `product_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `product_payment_cities`
--
ALTER TABLE `product_payment_cities`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `purchase_requests`
--
ALTER TABLE `purchase_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `remember_tokens`
--
ALTER TABLE `remember_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `shops`
--
ALTER TABLE `shops`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `shop_payment_settings`
--
ALTER TABLE `shop_payment_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `tags`
--
ALTER TABLE `tags`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `user_addresses`
--
ALTER TABLE `user_addresses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `user_bct_account`
--
ALTER TABLE `user_bct_account`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `visits`
--
ALTER TABLE `visits`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- 限制导出的表
--

--
-- 限制表 `cart_items`
--
ALTER TABLE `cart_items`
  ADD CONSTRAINT `cart_items_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `cart_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);

--
-- 限制表 `remember_tokens`
--
ALTER TABLE `remember_tokens`
  ADD CONSTRAINT `remember_tokens_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- 限制表 `user_addresses`
--
ALTER TABLE `user_addresses`
  ADD CONSTRAINT `fk_user_addresses_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

-- --------------------------------------------------------

--
-- 表的结构 `coupons`
--

CREATE TABLE IF NOT EXISTS `coupons` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `shop_id` int(11) NOT NULL COMMENT '店铺ID',
  `title` varchar(100) NOT NULL COMMENT '优惠券名称',
  `code` varchar(50) DEFAULT NULL COMMENT '优惠码（为空则自动领取）',
  `type` enum('fixed','percent') NOT NULL DEFAULT 'fixed' COMMENT '优惠类型：固定金额/百分比',
  `value` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '优惠值',
  `min_order_amount` decimal(10,2) DEFAULT '0.00' COMMENT '最低订单金额',
  `max_discount` decimal(10,2) DEFAULT NULL COMMENT '最大优惠金额（百分比时有效）',
  `total_quantity` int(11) NOT NULL DEFAULT '0' COMMENT '总发行量，0为不限量',
  `used_quantity` int(11) NOT NULL DEFAULT '0' COMMENT '已使用量',
  `start_date` date DEFAULT NULL COMMENT '生效日期',
  `end_date` date DEFAULT NULL COMMENT '失效日期',
  `status` enum('active','inactive','expired') NOT NULL DEFAULT 'active' COMMENT '状态',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `shop_id` (`shop_id`),
  KEY `code` (`code`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='店铺优惠券';

--
-- 表的结构 `reviews`
--

CREATE TABLE IF NOT EXISTS `reviews` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL COMMENT '关联订单',
  `order_item_id` int(11) NOT NULL COMMENT '关联订单商品项',
  `product_id` int(11) NOT NULL COMMENT '关联商品',
  `user_id` int(11) NOT NULL COMMENT '评价用户',
  `shop_id` int(11) NOT NULL COMMENT '关联店铺',
  `rating` tinyint(4) NOT NULL DEFAULT '5' COMMENT '评分1-5',
  `content` text COMMENT '评价内容',
  `images` json DEFAULT NULL COMMENT '评价图片数组',
  `is_anonymous` tinyint(1) DEFAULT '0' COMMENT '是否匿名',
  `status` enum('pending','approved','rejected') DEFAULT 'approved' COMMENT '审核状态',
  `reply_content` text COMMENT '商家回复',
  `reply_at` datetime DEFAULT NULL COMMENT '商家回复时间',
  `helpful_count` int(11) DEFAULT '0' COMMENT '有用数',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_product` (`product_id`,`status`),
  KEY `idx_shop` (`shop_id`,`status`),
  KEY `idx_user` (`user_id`),
  KEY `idx_order_item` (`order_item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='商品评价';

--
-- 限制表 `visits`
--
ALTER TABLE `visits`
  ADD CONSTRAINT `fk_applicant_circle` FOREIGN KEY (`applicant_circle_id`) REFERENCES `circles` (`id`);

--
-- 限制表 `coupons`
--
ALTER TABLE `coupons`
  ADD CONSTRAINT `fk_coupons_shop` FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`) ON DELETE CASCADE;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
