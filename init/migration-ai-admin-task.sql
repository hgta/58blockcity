-- ============================================================
-- 后台 AI 小任务（摘要/slug 生成）的超时与渠道偏好设置
-- change: help-content-admin (task 9.2)
-- 说明：不执行本迁移也能用（代码内置同样的默认值），执行后可在 DB 侧调整
-- ============================================================

INSERT INTO `system_settings` (`setting_key`,`setting_value`) VALUES
-- 单渠道超时（秒）：模型较慢时调大，建议 20~45
('ai_admin_task_timeout',  '30'),
-- 1=后台任务优先直连模型渠道、本机 Hermes 殿后（默认，短任务更快）；0=按后台配置顺序
('ai_admin_prefer_direct', '1')
ON DUPLICATE KEY UPDATE `setting_key` = VALUES(`setting_key`);
