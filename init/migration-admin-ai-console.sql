-- ============================================================
-- AI 训练台 数据迁移
-- change: admin-ai-training-console
-- 依赖: migration-help-center-ai.sql（system_settings / ai_chat_logs 等）
-- 说明: 仅新增 settings 初始项，无表结构变更
-- ============================================================

-- ------------------------------------------------------------
-- 小帮人设 system prompt（空 = 回退内置默认文案，api/ai/chat.php 读取）
-- ------------------------------------------------------------
INSERT INTO `system_settings` (`setting_key`,`setting_value`) VALUES
('ai_assistant_system_prompt', '')
ON DUPLICATE KEY UPDATE `setting_key`=`setting_key`;
