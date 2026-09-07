-- ============================================================================
-- 将 BCT 人气值单价从独立表 city_bct 并入 cities（消冗余、单一事实源）
--
-- 背景：
--   city_bct 每行真正有增量价值的只有 base_price / current_price（decimal），
--   而 circulating_supply 与 cities.popularity 系冗余双写、total_supply 全表
--   固定为 21000000（代码层常量即可），且全仓库无任何对 city_bct 的 INSERT 来源，
--   长期靠人工初始化，导致 421 词条只登记 94 个的断层。
--
-- 本脚本为 cities 增加三列并回填既有价格（幂等，可重复执行）：
--   bct_base_price     decimal(10,2) NOT NULL DEFAULT '0.10'
--   bct_current_price  decimal(10,2) NOT NULL DEFAULT '0.10'
--   bct_price_updated  datetime      NULL
-- 回填后，全部 cities 词条天然拥有 BCT 行情（默认 ¥0.10），
-- 不再需要「开通全部城市行情」入口，也无需再维护独立行情表。
--
-- 流通量不再落库：统一按 cities.popularity - cities.popularity_consume 实时计算，
-- 因此 old city_bct.circulating_supply 不回填（属 stale 双写值）。
-- 全仓库代码总供给量统一视作常量 21000000（CityBCT::TOTAL_SUPPLY）。
--
-- 执行方式（任选其一）：
--   A. mysql CLI：
--        mysql -u<user> -p<pass> <db> < init/migration-merge-city-bct.sql
--   B. phpMyAdmin：将本文件整段粘贴到 SQL 窗口执行。
--      本脚本不使用 DELIMITER / 存储过程 / CALL，纯语句逐条执行，
--      不会触发 #2014 "Commands out of sync"。
--
-- 结构：加列（幂等，已存在则跳过） -> 回填（覆盖同值） -> 核对输出。
-- 即使中途某条语句报错中断，也可直接整段重跑，无需回滚。
--
-- 部署顺序建议：
--   1. 先执行本迁移（加列 + 回填，此时旧代码只读 city_bct 不受影响）；
--   2. 再部署新版代码（不再引用 city_bct）；
--   3. 线上冒烟通过后，再人工备份并停用旧表（命令见文件末尾注释）。
-- ============================================================================

-- ========== 1) cities 增加 BCT 单价列（幂等：列已存在则跳过） ==========

-- 1a) bct_base_price
SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'cities'
      AND COLUMN_NAME = 'bct_base_price'
);
SET @ddl := IF(@col_exists = 0,
    'ALTER TABLE `cities` ADD COLUMN `bct_base_price` decimal(10,2) NOT NULL DEFAULT 0.10 COMMENT ''BCT 基础单价（人气值市场底价）''',
    'DO 0'
);
PREPARE migrate_stmt FROM @ddl;
EXECUTE migrate_stmt;
DEALLOCATE PREPARE migrate_stmt;

-- 1b) bct_current_price
SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'cities'
      AND COLUMN_NAME = 'bct_current_price'
);
SET @ddl := IF(@col_exists = 0,
    'ALTER TABLE `cities` ADD COLUMN `bct_current_price` decimal(10,2) NOT NULL DEFAULT 0.10 COMMENT ''BCT 当前单价（人气值市场实时价）''',
    'DO 0'
);
PREPARE migrate_stmt FROM @ddl;
EXECUTE migrate_stmt;
DEALLOCATE PREPARE migrate_stmt;

-- 1c) bct_price_updated
SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'cities'
      AND COLUMN_NAME = 'bct_price_updated'
);
SET @ddl := IF(@col_exists = 0,
    'ALTER TABLE `cities` ADD COLUMN `bct_price_updated` datetime DEFAULT NULL COMMENT ''BCT 单价最近更新时间''',
    'DO 0'
);
PREPARE migrate_stmt FROM @ddl;
EXECUTE migrate_stmt;
DEALLOCATE PREPARE migrate_stmt;

-- ========== 2) 回填既有 94 城单价（幂等，重复执行覆盖同值无副作用） ==========

UPDATE cities c
JOIN city_bct cb ON cb.city = c.name COLLATE utf8mb4_unicode_ci
SET c.bct_base_price    = cb.base_price,
    c.bct_current_price = cb.current_price,
    c.bct_price_updated = cb.last_updated;

-- ========== 3) 核对输出（期望 total_cities≈421，customized_* 为原 94 城中改过价的数） ==========

SELECT COUNT(*) AS total_cities,
       SUM(bct_base_price    <> 0.10) AS customized_base_price,
       SUM(bct_current_price <> 0.10) AS customized_current_price
FROM cities;

-- ============================================================================
-- 旧表备份/停用（冒烟通过后在服务器手动执行，勿在脚本内自动执行）：
--
--   -- 先改名备份（回滚只需改名回来即可）
--   RENAME TABLE city_bct TO city_bct_deprecated_20260908;
--
--   -- 确认新代码运行一段无异常后，再真正删除
--   DROP TABLE IF EXISTS city_bct_deprecated_20260908;
--
-- 若旧版本曾在此库创建过存储过程 MergeCityBctColumns（phpMyAdmin 早期
-- 版本脚本残留），可无害清理（可选，不影响新代码）：
--   DROP PROCEDURE IF EXISTS `MergeCityBctColumns`;
-- ============================================================================
