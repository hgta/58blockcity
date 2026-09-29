-- ============================================================
-- 微信公众号扫码登录（未认证个人订阅号方案）
-- 依赖：无（独立执行）
--
-- 1) users 表新增 wechat_openid（一人一号，唯一）
-- 2) email 改为可空：扫码注册且只补手机号的用户 email 存 NULL
--    （MySQL 唯一索引允许多个 NULL，'' 只能存一条，所以必须用 NULL）
-- 3) 新建 wechat_login_codes 表：关注/发消息后下发的 6 位验证码
-- ============================================================

ALTER TABLE `users`
  ADD COLUMN `wechat_openid` VARCHAR(64) DEFAULT NULL COMMENT '微信公众号 openid' AFTER `avatar`;

ALTER TABLE `users`
  ADD UNIQUE KEY `uniq_wechat_openid` (`wechat_openid`);

ALTER TABLE `users`
  MODIFY `email` VARCHAR(100) NULL COMMENT '邮箱（扫码注册仅补手机号时为 NULL）';

CREATE TABLE IF NOT EXISTS `wechat_login_codes` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` CHAR(6) NOT NULL COMMENT '6位数字验证码',
  `openid` VARCHAR(64) NOT NULL COMMENT '公众号 openid',
  `purpose` ENUM('login','reset') NOT NULL DEFAULT 'login' COMMENT 'login=扫码登录 reset=重置密码',
  `is_used` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '是否已核销（一次性）',
  `expires_at` DATETIME NOT NULL COMMENT '过期时间（签发后5分钟）',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_code` (`code`),
  KEY `idx_openid` (`openid`),
  KEY `idx_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='公众号验证码（登录/重置密码）';
