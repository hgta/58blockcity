-- =============================================================
-- 任务广场子站 (task.58.tl) 数据表
-- 通用悬赏撮合：发布 / 众包领取 / 凭证交付验收 / 直接划转结算 / 争议仲裁 / 双向评价 / 技能卡
-- 统一 utf8mb4 + utf8mb4_unicode_ci，幂等可重复执行（IF NOT EXISTS / INSERT IGNORE）
-- 若与老 general_ci 表 JOIN 需在关联处显式 COLLATE 对齐
-- =============================================================

-- 任务类别（后台可维护，发布/广场/技能卡共用启用集合）
CREATE TABLE IF NOT EXISTS `task_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL COMMENT '类别名称',
  `sort_order` int(11) NOT NULL DEFAULT 0 COMMENT '排序（小在前）',
  `status` enum('active','inactive') NOT NULL DEFAULT 'active' COMMENT 'active=启用可被选择; inactive=停用',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='任务类别（预设，后台可维护）';

-- 种子类别：代互访 / 代打卡 / 代做市长 / 其他
INSERT IGNORE INTO `task_categories` (`name`, `sort_order`, `status`) VALUES
  ('代互访', 10, 'active'),
  ('代打卡', 20, 'active'),
  ('代做市长', 30, 'active'),
  ('其他', 99, 'active');

-- 任务
CREATE TABLE IF NOT EXISTS `tasks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `employer_id` int(11) NOT NULL COMMENT '发布者 users.id',
  `category_id` int(11) NOT NULL COMMENT 'task_categories.id',
  `title` varchar(120) NOT NULL COMMENT '任务标题',
  `description` text NOT NULL COMMENT '任务说明',
  `accept_desc` text NULL COMMENT '验收说明（接单人交付标准）',
  `city` varchar(50) DEFAULT NULL COMMENT '关联城市名（人气值任务必填，作为该任务结算账本城市）',
  `target_type` enum('block','circle') DEFAULT NULL COMMENT 'L1 导流对象类型: 区块 / 互访圈',
  `target_id` varchar(64) DEFAULT NULL COMMENT '对象标识（区块号或互访圈ID/别名）',
  `reward_type` enum('popularity','cash') NOT NULL COMMENT '赏金类型: popularity=人气值; cash=现金(线下)',
  `reward_amount` int(11) NOT NULL COMMENT '赏金: 人气值=整数个; 现金=分(展示转元,不产生站内资金变动)',
  `quota` int(11) NOT NULL DEFAULT 1 COMMENT '名额 N（众包；1=单人）',
  `claimed_count` int(11) NOT NULL DEFAULT 0 COMMENT '已领取计数（事务内递增）',
  `review_days` int(11) NOT NULL DEFAULT 3 COMMENT '交付后雇主验收期限(天)',
  `expire_at` datetime DEFAULT NULL COMMENT '失效时间（到期不再接受新领取）',
  `status` enum('open','closed') NOT NULL DEFAULT 'open' COMMENT 'open=进行中; closed=雇主手动关闭/已到期',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_employer` (`employer_id`),
  KEY `idx_category` (`category_id`),
  KEY `idx_city` (`city`),
  KEY `idx_status_expire` (`status`,`expire_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='悬赏任务';

-- 认领（每个名额一条）
CREATE TABLE IF NOT EXISTS `task_claims` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `task_id` int(11) NOT NULL COMMENT 'tasks.id',
  `worker_id` int(11) NOT NULL COMMENT '接单人 users.id',
  `status` enum('accepted','submitted','rejected','settling','completed','cancelled','disputed') NOT NULL DEFAULT 'accepted' COMMENT 'accepted=已领取待交付; submitted=已提交待验收; rejected=被驳回可补交; settling=验收通过待结算; completed=已结算; cancelled=已取消; disputed=争议中',
  `proof_text` text NULL COMMENT '交付凭证文字说明',
  `proof_image` varchar(255) DEFAULT NULL COMMENT '交付凭证截图(相对路径)',
  `employer_note` varchar(255) DEFAULT NULL COMMENT '验收意见/驳回原因',
  `review_due_at` datetime DEFAULT NULL COMMENT '交付后验收截止 = submitted_at + tasks.review_days',
  `claimed_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '领取时间',
  `submitted_at` datetime DEFAULT NULL COMMENT '最近一次提交时间',
  `reviewed_at` datetime DEFAULT NULL COMMENT '最近一次验收时间',
  `settled_at` datetime DEFAULT NULL COMMENT '结算完成时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_task_worker` (`task_id`,`worker_id`) COMMENT '一人一份',
  KEY `idx_worker` (`worker_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='任务认领（每名额一份，独立交付验收）';

-- 技能卡（求职侧：我能承接）
CREATE TABLE IF NOT EXISTS `task_skills` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL COMMENT '用户 users.id',
  `description` varchar(500) NOT NULL COMMENT '我能承接的说明',
  `ref_price` varchar(50) DEFAULT NULL COMMENT '参考价（自由文本，现金/人气值皆可）',
  `status` enum('active','inactive') NOT NULL DEFAULT 'active' COMMENT 'active=展示中; inactive=下架',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user` (`user_id`) COMMENT '每人一张技能卡'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户技能卡（求职侧）';

-- 技能卡 ↔ 可承接类别（多对多）
CREATE TABLE IF NOT EXISTS `task_skill_categories` (
  `skill_id` int(11) NOT NULL COMMENT 'task_skills.id',
  `category_id` int(11) NOT NULL COMMENT 'task_categories.id',
  PRIMARY KEY (`skill_id`,`category_id`),
  KEY `idx_category` (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='技能卡可选承接类别';

-- 评价（认领结算后双向，各一次）
CREATE TABLE IF NOT EXISTS `task_reviews` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `task_id` int(11) NOT NULL COMMENT 'tasks.id',
  `claim_id` int(11) NOT NULL COMMENT 'task_claims.id',
  `from_user_id` int(11) NOT NULL COMMENT '评价方 users.id',
  `to_user_id` int(11) NOT NULL COMMENT '被评价方 users.id',
  `rating` tinyint(4) NOT NULL DEFAULT 5 COMMENT '星级 1-5',
  `content` varchar(500) NOT NULL COMMENT '评价内容',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_claim_from` (`claim_id`,`from_user_id`) COMMENT '一方对一份认领仅一次',
  KEY `idx_to_user` (`to_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='任务双向评价';

-- 争议（admin 仲裁）
CREATE TABLE IF NOT EXISTS `task_disputes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `claim_id` int(11) NOT NULL COMMENT 'task_claims.id',
  `initiator_id` int(11) NOT NULL COMMENT '发起方 users.id',
  `reason` varchar(500) NOT NULL COMMENT '争议事由',
  `status` enum('open','resolved') NOT NULL DEFAULT 'open' COMMENT 'open=待仲裁; resolved=已裁决',
  `resolution` enum('settle','cancel') DEFAULT NULL COMMENT '裁决: settle=完成结算; cancel=取消认领',
  `admin_id` int(11) DEFAULT NULL COMMENT '仲裁管理员 users.id',
  `admin_note` varchar(500) DEFAULT NULL COMMENT '仲裁意见/原因',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `resolved_at` datetime DEFAULT NULL COMMENT '裁决时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_claim` (`claim_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='任务争议（后台仲裁）';
