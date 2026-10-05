-- ============================================================
-- 后台 AI 小任务（摘要/slug 生成）的超时、渠道偏好与专用模型
-- change: help-content-admin (task 9.2)
-- 说明：不执行本迁移也能用（代码内置同样的默认值），执行后可在 DB 侧调整
--
-- 实测基线（2026-10-05，同一问题"用30字说明什么是区块"，非流式）：
--   本机 Hermes(hermes-agent)      ≈ 9.6s
--   直连方舟(ark-code-latest)      ≈ 91.9s（带 reasoning_content，深度思考模型）
-- ============================================================

INSERT INTO `system_settings` (`setting_key`,`setting_value`) VALUES
-- 单渠道超时（秒）：慢模型调大，但超过 60 也没意义（前端兜底 80s）
('ai_admin_task_timeout',  '30'),
-- 1=直连模型渠道优先、本机 Hermes 殿后（默认，实测长输入下 Hermes 更慢）；0=按后台渠道配置顺序
('ai_admin_prefer_direct', '1'),
-- 后台小任务专用模型，覆盖渠道默认模型；留空=用渠道默认。
-- 若默认渠道模型偏慢（如带思考的 ark-code-latest），可在此填一个快模型名
('ai_admin_task_model',    ''),
-- 前台小帮流式调用的"首字节超时"（秒）：渠道在该时间内一个字都没吐出即判定失败并切换，
-- 避免用户面对长时间思考的模型干等。0=关闭该保护
('ai_chat_first_byte_timeout', '8'),
-- 摘要任务送入模型的正文字数上限：思考型模型耗时随输入增长，
-- 长文整篇送入常导致 30s 超时。默认 1500（范围 300~3000）
('ai_admin_task_max_input', '1500')
ON DUPLICATE KEY UPDATE `setting_key` = VALUES(`setting_key`);
