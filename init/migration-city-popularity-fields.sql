-- 为 cities 表添加 blockcity.vip 人气值同步相关字段
-- 兼容 MySQL 5.7/8.0 与 MariaDB，可重复执行
-- 执行方式：mysql -u<user> -p<pass> <db> < init/migration-city-popularity-fields.sql

DELIMITER $$

DROP PROCEDURE IF EXISTS `AddCityPopularityColumns`$$

CREATE PROCEDURE `AddCityPopularityColumns`()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'cities'
          AND COLUMN_NAME = 'official_area_id'
    ) THEN
        ALTER TABLE `cities`
            ADD COLUMN `official_area_id` int(11) DEFAULT NULL COMMENT '官方 areaId' AFTER `popularity`;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'cities'
          AND COLUMN_NAME = 'popularity_consume'
    ) THEN
        ALTER TABLE `cities`
            ADD COLUMN `popularity_consume` int(11) DEFAULT '0' COMMENT '已消耗人气值' AFTER `official_area_id`;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'cities'
          AND COLUMN_NAME = 'popularity_balance'
    ) THEN
        ALTER TABLE `cities`
            ADD COLUMN `popularity_balance` int(11) DEFAULT '0' COMMENT '剩余可产生人气值' AFTER `popularity_consume`;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'cities'
          AND COLUMN_NAME = 'popularity_balance2'
    ) THEN
        ALTER TABLE `cities`
            ADD COLUMN `popularity_balance2` int(11) DEFAULT '0' COMMENT '可领取人气值（balance2）' AFTER `popularity_balance`;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'cities'
          AND COLUMN_NAME = 'popularity_points_id'
    ) THEN
        ALTER TABLE `cities`
            ADD COLUMN `popularity_points_id` bigint(20) DEFAULT '0' COMMENT '官方 pointsId' AFTER `popularity_balance2`;
    END IF;

    -- 官方 areaId 唯一映射，避免同一 areaId 重复对应两个本地城市
    -- MySQL 8 / MariaDB 中允许多个 NULL 值
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'cities'
          AND INDEX_NAME = 'official_area_id'
    ) THEN
        ALTER TABLE `cities` ADD UNIQUE KEY `official_area_id` (`official_area_id`);
    END IF;
END$$

DELIMITER ;

CALL `AddCityPopularityColumns`();

DROP PROCEDURE IF EXISTS `AddCityPopularityColumns`;
