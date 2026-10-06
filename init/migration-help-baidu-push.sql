-- ============================================================
-- help 子站百度主动推送日志 / 死链清单
-- change: help-baidu-indexing
-- 依赖: migration-help-center-ai.sql（help_articles 表）
-- ============================================================
-- 说明：
--   1) 用于「同一 URL 冷却期内不重复推」的节流判据（默认 24h）。
--   2) action='dead' 的记录由 help/deadlinks.php 输出为百度死链清单。
--   3) 未执行本迁移时 help_push_url() 降级为「不节流、直接推」，
--      死链清单输出为空 —— 不影响后台保存与页面渲染。

CREATE TABLE IF NOT EXISTS `help_push_log` (
  `url`       VARCHAR(255) NOT NULL COMMENT '权威域绝对 URL',
  `action`    VARCHAR(16)  NOT NULL DEFAULT 'push' COMMENT 'push=已推送 / dead=死链',
  `pushed_at` DATETIME     NOT NULL COMMENT '最近一次推送 / 登记时间',
  PRIMARY KEY (`url`),
  KEY `idx_pushed` (`pushed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='help 子站推送日志';
