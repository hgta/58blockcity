-- ============================================================
-- help 子站内容层 SEO 字段迁移
-- change: help-content-seo
-- 依赖: migration-help-center-ai.sql（help_articles 表）
-- ============================================================
-- 说明：
--   1) 只给 help_articles 加 SEO 字段 —— FAQ / 术语表 / 分类为聚合页，
--      title/description 由页面性质决定，逐条维护收益低于成本（见 design D1）。
--   2) 前台一律用 ?? '' 取用这两列，本迁移未执行时页面照常渲染（不报错）。
--   3) MySQL 无 ADD COLUMN IF NOT EXISTS，重复执行会报 duplicate column，
--      属预期行为；执行前可先 SHOW COLUMNS 确认。

ALTER TABLE `help_articles`
  ADD COLUMN `seo_title` VARCHAR(120) NULL COMMENT 'SEO 标题，留空则用 title' AFTER `title`,
  ADD COLUMN `meta_description` VARCHAR(200) NULL COMMENT 'SEO 描述，留空则用 summary 或正文首句' AFTER `summary`;
