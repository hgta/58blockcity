-- =====================================================================
-- 58拍卖子站 — 拍品描述迁移（auction-item-description）
--   + 新增 `auctions.description` MEDIUMTEXT 字段（卖家专写 Markdown）
--
-- 设计原则：
--   * 可重复执行：所有变更先判存在再执行，连续跑两次均不报错
--   * 存量数据零影响：默认 NULL，无需回填
--   * 索引零影响：description 为大字段，不建索引
--
-- 执行方式：
--   mysql -u <user> -p <db> < init/migrate-auction-description.sql
-- =====================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- 1. auctions 增列 description（拍品描述，Markdown 源文）
-- ---------------------------------------------------------------------

SET @sql = (SELECT IF(COUNT(*) = 0,
    'ALTER TABLE `auctions` ADD COLUMN `description` MEDIUMTEXT NULL COMMENT ''拍品描述（Markdown，卖家专写）''',
    'DO 0')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'auctions' AND COLUMN_NAME = 'description');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ---------------------------------------------------------------------
-- 2. 已有数据无影响
-- ---------------------------------------------------------------------
-- description 列默认 NULL，存量拍卖记录无需回填；前端详情页对
-- description 为 NULL/空串时不展示「卖家描述」区块。
--
-- 反向回滚（如需）：
--   ALTER TABLE `auctions` DROP COLUMN `description`;
-- =====================================================================