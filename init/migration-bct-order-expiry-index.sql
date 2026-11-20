-- BCT 挂单有效期过滤索引
--
-- 所有挂单列表/统计/撮合查询都要按 status + expires_at 过滤，
-- 没有索引时会随订单量增长退化成全表扫描。
-- 本语句可重复执行；若提示 Duplicate key name 'idx_status_expires' 说明已建好，忽略即可。

ALTER TABLE `bct_orders`
  ADD INDEX `idx_status_expires` (`status`, `expires_at`);
