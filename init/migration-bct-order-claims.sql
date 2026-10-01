-- BCT 直接交易接单流水表
-- 变更: bct-direct-trade-claim-flow
-- 说明: 直接交易单被接单（意向锁）后，每次接单/确认/放弃/释放都记录在此表；
--       bct_orders.status 仅作大厅展示的聚合态（pending/processing/completed），
--       交易过程的真相（谁接单、双方各自确认到哪一步）以本表为准。
-- 上线: 直接执行本文件即可（幂等，可重复执行）。

CREATE TABLE IF NOT EXISTS `bct_order_claims` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL COMMENT '关联 bct_orders.id',
  `buyer_side_user_id` int(11) NOT NULL COMMENT '买方一侧用户（挂售单=接单人；求购单=挂单人）',
  `seller_side_user_id` int(11) NOT NULL COMMENT '卖方一侧用户',
  `status` enum('matched','buyer_confirmed','completed','abandoned','released') NOT NULL DEFAULT 'matched' COMMENT 'matched=已接单待付款确认 / buyer_confirmed=买方已确认付款 / completed=已完成 / abandoned=用户放弃 / released=超时系统释放',
  `buyer_confirmed_at` datetime DEFAULT NULL COMMENT '买方确认已付款时间',
  `seller_confirmed_at` datetime DEFAULT NULL COMMENT '卖方确认已收款时间',
  `finished_at` datetime DEFAULT NULL COMMENT '终态时间（completed/abandoned/released）',
  `ended_by` int(11) DEFAULT NULL COMMENT '放弃/释放的发起人用户ID；系统释放时为 NULL',
  `reason` varchar(255) DEFAULT NULL COMMENT '放弃原因（可选）',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '接单时间',
  PRIMARY KEY (`id`),
  KEY `idx_order_active` (`order_id`,`status`),
  KEY `idx_user` (`buyer_side_user_id`,`seller_side_user_id`),
  KEY `idx_status_created` (`status`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='BCT直接交易接单流水';
