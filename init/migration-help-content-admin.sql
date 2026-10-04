-- ============================================================
-- 帮助内容运营后台：评测标注样本 + 重建锁
-- change: help-content-admin (task 1.1)
-- 依赖: migration-help-center-ai.sql / migration-help-semantic-rag.sql
-- ============================================================

-- ------------------------------------------------------------
-- 检索评测标注样本：把「问题的期望条目」持久化，避免只落在 CSV 里
-- expected 编码（与 tools/eval-retrieval.php 一致）：
--   a10 = 文章 #10 / g4 = 术语 #4 / f7 = FAQ #7 / 空或 0 = 知识库无对应内容
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `help_eval_samples` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `log_id` INT(11) DEFAULT NULL COMMENT '关联的 ai_chat_logs.id',
  `question` VARCHAR(500) NOT NULL COMMENT '用户问题原文',
  `expected` VARCHAR(32) NOT NULL DEFAULT '' COMMENT '期望条目编码（a#/g#/f#），空=未标注，0=无对应内容',
  `note` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '备注',
  `annotated_by` INT(11) DEFAULT NULL COMMENT '标注人（管理员 user_id）',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_log` (`log_id`),
  KEY `idx_expected` (`expected`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='小帮检索评测标注样本';

-- ------------------------------------------------------------
-- 全量重建并发锁（值为时间戳；与当前时间差 < 600s 视为进行中）
-- ------------------------------------------------------------
INSERT INTO `system_settings` (`setting_key`,`setting_value`) VALUES
('help_rebuild_locked_at', '0')
ON DUPLICATE KEY UPDATE `setting_key` = VALUES(`setting_key`);
