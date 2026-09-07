-- 为 cities 表添加 blockcity.vip 人气值同步相关字段
-- 执行方式：mysql -u<user> -p<pass> <db> < init/migration-city-popularity-fields.sql

ALTER TABLE `cities`
  ADD COLUMN IF NOT EXISTS `official_area_id` int(11) DEFAULT NULL COMMENT '官方 areaId' AFTER `popularity`,
  ADD COLUMN IF NOT EXISTS `popularity_consume` int(11) DEFAULT '0' COMMENT '已消耗人气值' AFTER `official_area_id`,
  ADD COLUMN IF NOT EXISTS `popularity_balance` int(11) DEFAULT '0' COMMENT '剩余可产生人气值' AFTER `popularity_consume`,
  ADD COLUMN IF NOT EXISTS `popularity_balance2` int(11) DEFAULT '0' COMMENT '可领取人气值（balance2）' AFTER `popularity_balance`,
  ADD COLUMN IF NOT EXISTS `popularity_points_id` bigint(20) DEFAULT '0' COMMENT '官方 pointsId' AFTER `popularity_balance2`;

-- 官方 areaId 唯一映射，避免同一 areaId 重复对应两个本地城市
-- MySQL 8 / MariaDB 中允许多个 NULL 值
ALTER TABLE `cities`
  ADD UNIQUE KEY IF NOT EXISTS `official_area_id` (`official_area_id`);
