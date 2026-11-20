-- --------------------------------------------------------
-- BCT 挂单有效期：扩展订单状态枚举，加入 expired
--
-- 说明：
--   bct_orders.expires_at 字段在 db-init.sql 中已定义；本迁移只需扩展
--   status 枚举，使其能表达「因超期被系统取消」。
--   (status, expires_at) 复合索引另见 migration-bct-order-expiry-index.sql。
--
--   canceled = 用户主动取消
--   expired  = 系统因超期自动取消
--
-- 注意：在数据量较大时 MODIFY 枚举可能锁表，建议低峰期执行。
-- --------------------------------------------------------

ALTER TABLE `bct_orders`
  MODIFY COLUMN `status` ENUM('pending','processing','completed','canceled','expired')
  DEFAULT 'pending';

-- 过期清理的查询条件为：
--   status IN ('pending','processing') AND expires_at IS NOT NULL AND expires_at < ?
-- 所有挂单查询都按 status + expires_at 过滤，建议执行：
--   mysql -u 用户名 -p 库名 < init/migration-bct-order-expiry-index.sql
