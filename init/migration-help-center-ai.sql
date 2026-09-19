-- ============================================================
-- 帮助中心子站 + AI 助手 数据迁移
-- change: help-center-ai-assistant
-- 依赖: db-init.sql（system_settings 等基础表）
-- ============================================================

-- ------------------------------------------------------------
-- 帮助分类
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `help_categories` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `parent_id` INT(11) NOT NULL DEFAULT 0 COMMENT '父分类，0为顶级',
  `name` VARCHAR(50) NOT NULL COMMENT '分类名',
  `slug` VARCHAR(50) NOT NULL COMMENT 'URL标识',
  `icon` VARCHAR(50) NOT NULL DEFAULT 'book' COMMENT 'Font Awesome图标名',
  `description` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '分类简介',
  `sort_order` INT(11) NOT NULL DEFAULT 0,
  `is_visible` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_slug` (`slug`),
  KEY `idx_parent` (`parent_id`),
  KEY `idx_sort` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='帮助中心分类';

-- ------------------------------------------------------------
-- 帮助文章（富文本 / 步骤化图文 两种模式）
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `help_articles` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `category_id` INT(11) NOT NULL,
  `title` VARCHAR(200) NOT NULL,
  `slug` VARCHAR(200) NOT NULL COMMENT 'URL标识',
  `summary` VARCHAR(500) NOT NULL DEFAULT '',
  `content_type` ENUM('richtext','steps') NOT NULL DEFAULT 'richtext',
  `content_richtext` MEDIUMTEXT COMMENT '富文本HTML',
  `content_steps` JSON COMMENT '[{title,text,image}]',
  `cover_image` VARCHAR(255) NOT NULL DEFAULT '',
  `legacy_file` VARCHAR(100) NOT NULL DEFAULT '' COMMENT '旧静态页文件名，用于301映射',
  `view_count` INT(11) NOT NULL DEFAULT 0,
  `helpful_count` INT(11) NOT NULL DEFAULT 0,
  `is_pinned` TINYINT(1) NOT NULL DEFAULT 0,
  `is_ai_generated` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'AI草稿标记',
  `status` ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
  `created_by` INT(11) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_slug` (`slug`),
  KEY `idx_category` (`category_id`),
  KEY `idx_status` (`status`),
  KEY `idx_legacy` (`legacy_file`),
  KEY `idx_pinned_updated` (`is_pinned`,`updated_at`),
  FULLTEXT KEY `ft_search` (`title`,`summary`,`content_richtext`) WITH PARSER ngram
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='帮助中心文章';

-- ------------------------------------------------------------
-- FAQ（按子站分组）
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `help_faq` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `category_id` INT(11) NOT NULL COMMENT '所属分类（子站Tab）',
  `question` VARCHAR(500) NOT NULL,
  `answer` TEXT NOT NULL,
  `related_article_id` INT(11) DEFAULT NULL,
  `source` ENUM('manual','ai_draft','from_chat') NOT NULL DEFAULT 'manual' COMMENT 'from_chat=未命中问题一键转FAQ',
  `status` ENUM('draft','published') NOT NULL DEFAULT 'draft',
  `sort_order` INT(11) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_category` (`category_id`),
  KEY `idx_status` (`status`),
  KEY `idx_sort` (`sort_order`),
  FULLTEXT KEY `ft_search` (`question`,`answer`) WITH PARSER ngram
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='帮助FAQ';

-- ------------------------------------------------------------
-- 术语表
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `help_glossary` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `term` VARCHAR(100) NOT NULL,
  `pinyin` VARCHAR(200) NOT NULL DEFAULT '' COMMENT '拼音串，用于字母序',
  `definition` TEXT NOT NULL,
  `related_article_id` INT(11) DEFAULT NULL,
  `sort_order` INT(11) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_term` (`term`),
  KEY `idx_pinyin` (`pinyin`),
  FULLTEXT KEY `ft_search` (`term`,`definition`) WITH PARSER ngram
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='平台术语表';

-- ------------------------------------------------------------
-- 文章反馈（防刷）
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `help_article_feedback` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `article_id` INT(11) NOT NULL,
  `helpful` TINYINT(1) NOT NULL COMMENT '1有帮助 0没帮助',
  `visitor_hash` CHAR(64) NOT NULL COMMENT '用户ID或IP哈希',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_article_visitor` (`article_id`,`visitor_hash`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='文章有帮助反馈';

-- ------------------------------------------------------------
-- AI Provider 渠道
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ai_providers` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(50) NOT NULL COMMENT '渠道名',
  `endpoint` VARCHAR(255) NOT NULL COMMENT 'OpenAI兼容端点，如 https://api.deepseek.com/v1',
  `api_key_cipher` TEXT NOT NULL COMMENT 'AES-256-GCM加密后的Key',
  `model` VARCHAR(100) NOT NULL DEFAULT '' COMMENT '默认模型',
  `preset` VARCHAR(30) NOT NULL DEFAULT 'custom' COMMENT '预置模板: deepseek/qwen/kimi/zhipu/openai/hermes/custom',
  `sort_order` INT(11) NOT NULL DEFAULT 0 COMMENT '故障切换顺序',
  `is_enabled` TINYINT(1) NOT NULL DEFAULT 1,
  `is_default` TINYINT(1) NOT NULL DEFAULT 0,
  `daily_limit` INT(11) NOT NULL DEFAULT 0 COMMENT '每日调用限额，0不限',
  `call_count_today` INT(11) NOT NULL DEFAULT 0,
  `count_date` DATE DEFAULT NULL COMMENT '限额计数所属日期',
  `failed_at` DATETIME DEFAULT NULL COMMENT '最近失败时间',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_route` (`is_enabled`,`sort_order`),
  KEY `idx_default` (`is_default`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='AI大模型渠道配置';

-- ------------------------------------------------------------
-- AI 对话日志
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ai_chat_logs` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) DEFAULT NULL,
  `visitor_ip_hash` CHAR(64) NOT NULL DEFAULT '',
  `source_page` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '来源页面URL',
  `question` TEXT NOT NULL COMMENT '用户问题（已截断脱敏）',
  `answer_digest` TEXT COMMENT '回答摘要',
  `matched` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '是否命中知识库',
  `provider_id` INT(11) DEFAULT NULL,
  `tokens_used` INT(11) NOT NULL DEFAULT 0,
  `status` ENUM('ok','unmatched','error','limited') NOT NULL DEFAULT 'ok',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_created` (`created_at`),
  KEY `idx_status` (`status`),
  KEY `idx_provider` (`provider_id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_iphash_time` (`visitor_ip_hash`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='AI对话日志';

-- ------------------------------------------------------------
-- 留言工单
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ai_feedback_tickets` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) DEFAULT NULL,
  `visitor_ip_hash` CHAR(64) NOT NULL DEFAULT '',
  `contact` VARCHAR(200) NOT NULL DEFAULT '' COMMENT '联系方式',
  `question` TEXT NOT NULL,
  `chat_log_id` INT(11) DEFAULT NULL COMMENT '关联的AI对话',
  `status` ENUM('open','done') NOT NULL DEFAULT 'open',
  `admin_reply` TEXT,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `handled_at` DATETIME DEFAULT NULL,
  `handled_by` INT(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='AI助手留言工单';

-- ------------------------------------------------------------
-- 初始分类数据
-- ------------------------------------------------------------
INSERT INTO `help_categories` (`id`,`parent_id`,`name`,`slug`,`icon`,`description`,`sort_order`) VALUES
(1, 0, '新手上路',   'getting-started',  'rocket',        '10分钟了解平台全貌，完成第一次上手', 1),
(2, 0, '区块交易',   'block',            'cubes',         '认领虚拟地块、买卖区块与地块合并', 2),
(3, 0, 'BCT 人气值', 'bct',              'coins',         'BCT 的获取、交易与大宗交易规则', 3),
(4, 0, 'NFT 头像',   'nft',              'palette',       '数字收藏头像的铸造与交易', 4),
(5, 0, '人气商城',   'mall',             'shopping-bag',  '开店、上架商品、BCT 支付购物', 5),
(6, 0, '互访圈',     'club',             'handshake',     '同城社交互访玩法与礼仪', 6),
(7, 0, '拍卖',       'bid',              'gavel',         '区块/NFT 竞拍出价规则', 7),
(8, 0, '任务广场',   'task',             'tasks',         '悬赏发布与众包接单', 8),
(9, 0, '账户与安全', 'account',          'user-shield',   '注册登录、密码与账号安全', 9),
(10, 0, '支付与提现', 'payment',         'wallet',        '充值、支付与提现流程说明', 10),
(11, 0, '规则与FAQ', 'rules',            'book',          '平台规则与常见问题', 11)
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`);

-- ------------------------------------------------------------
-- 场景引导锚点指向的文章（占位基础版，后台可继续完善图文）
-- ------------------------------------------------------------
INSERT INTO `help_articles` (`category_id`,`title`,`slug`,`summary`,`content_type`,`content_richtext`,`status`,`is_pinned`)
VALUES
(5, '商城购物与下单指南', 'mall-shopping-guide', '如何在人气商城挑选商品、加入购物车并用 BCT 完成支付。',
 'richtext', '<h2>购物流程</h2><ol><li>进入<b>人气商城</b>，浏览或搜索想要的商品；</li><li>打开商品详情页，选择数量后<b>加入购物车</b>或<b>立即购买</b>；</li><li>在购物车页确认商品与金额，点击<b>去结算</b>；</li><li>使用 <b>BCT 人气值</b>完成支付，支付成功后可在"我的订单"查看。</li></ol><h2>常见问题</h2><ul><li><b>支付失败怎么办？</b>请确认 BCT 余额充足，稍后重试；多次失败可到帮助中心留言。</li><li><b>如何申请退款？</b>在订单详情页发起退款申请，等待卖家处理。</li></ul>', 'published', 0),
(7, '拍卖出价指南', 'bid-guide', '拍卖的出价规则、顺延机制与竞拍注意事项。',
 'richtext', '<h2>出价规则</h2><ol><li>出价需<b>不低于下一口价</b>，价高者得；</li><li>拍卖结束前最后几分钟内的出价会<b>自动顺延</b>拍卖时间，防止最后时刻狙击；</li><li>出价即代表接受拍卖规则，请谨慎操作。</li></ol><h2>注意事项</h2><ul><li>出价前请确认账户<b> BCT 余额</b>充足；</li><li>被他人超过出价后，可再次出价；</li><li>竞拍成功后请及时完成后续支付/交割流程。</li></ul>', 'published', 0)
ON DUPLICATE KEY UPDATE `title`=VALUES(`title`);

-- ------------------------------------------------------------
-- AI 全局设置（system_settings KV）
-- ------------------------------------------------------------
INSERT INTO `system_settings` (`setting_key`,`setting_value`) VALUES
('ai_assistant_enabled', '1'),
('ai_widget_enabled',    '1'),
('ai_rate_window',       '60'),
('ai_rate_max_per_window','3'),
('ai_daily_user_limit',  '30'),
('ai_daily_total_limit', '0'),
('ai_rag_topn',          '3'),
('ai_max_context_rounds','6')
ON DUPLICATE KEY UPDATE `setting_key`=VALUES(`setting_key`);
