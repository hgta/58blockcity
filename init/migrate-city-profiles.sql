-- --------------------------------------------------------
-- 统一城市门户：city_profiles 真实城市资料表（幂等迁移脚本）
-- 与 cities 1:1（city_id 唯一）。数据来自离线采集器
--   tools/crawl-city-profiles.php -> data/city-profiles/*.json
--   -> tools/sync-city-profiles.php 入库
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `city_profiles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `city_id` int(11) NOT NULL COMMENT '关联 cities.id',
  `admin_area` varchar(50) DEFAULT NULL COMMENT '行政面积(含单位,如 16410.54平方公里)',
  `population` varchar(50) DEFAULT NULL COMMENT '常住人口(如 2185.8万人)',
  `gdp` varchar(50) DEFAULT NULL COMMENT 'GDP(含年份前缀,如 2023年 43760.7亿元)',
  `gdp_per_capita` varchar(50) DEFAULT NULL COMMENT '人均GDP(如 20.2万元)',
  `urbanization_rate` varchar(20) DEFAULT NULL COMMENT '城镇化率(如 87.8%)',
  `universities` varchar(30) DEFAULT NULL COMMENT '高校数量(如 92所)',
  `feature_tags` varchar(255) DEFAULT NULL COMMENT '特色标签(JSON数组: ["国家首都","历史文化名城"])',
  `slogan` varchar(120) DEFAULT NULL COMMENT '城市口号(副标题)',
  `position` text COMMENT '城市定位(特色亮点-定位)',
  `landmarks` text COMMENT '地标(特色亮点-地标)',
  `food` text COMMENT '特色美食(特色亮点-美食)',
  `potential` text COMMENT '发展潜力(特色亮点-发展潜力)',
  `districts` text COMMENT '区块-现实行政区映射 JSON:[{zone:"A",area:"东城区",note:"核心城区"}]',
  `intro` text COMMENT '详细介绍 JSON:[{h:"地理位置",p:"..."},...]',
  `data_year` varchar(10) DEFAULT NULL COMMENT '统计数据年份(用于标注,缺省留空)',
  `status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '1=已完善,0=待补充',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_city` (`city_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='城市真实资料表(统一城市门户)';
