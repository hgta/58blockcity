-- =====================================================================
-- 58拍卖子站 — 商品拍品迁移（bid-mall-product-auction）
--   + `auctions.item_type` 枚举扩展：('block','nft') → ('block','nft','product')
--
-- 设计原则：
--   * 可重复执行：先判枚举是否已含 'product' 再 MODIFY，连续跑两次均不报错
--   * 存量数据零影响：现存 item_type ∈ {block, nft}，扩展枚举不改写任何行
--   * 索引零影响：idx_item(item_type, item_id) 为普通复合索引，
--     枚举扩展后自动覆盖新值 'product'，无需重建
--
-- 执行方式：
--   mysql -u <user> -p <db> < init/migrate-auction-product.sql
--
-- 验证：
--   SELECT COLUMN_TYPE FROM information_schema.COLUMNS
--   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'auctions' AND COLUMN_NAME = 'item_type';
--   -- 期望：enum('block','nft','product')
--
--   EXPLAIN SELECT * FROM auctions WHERE item_type = 'product' AND item_id = 1;
--   -- 期望：key = idx_item
-- =====================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- 1. item_type 枚举扩展（判存在再执行）
-- ---------------------------------------------------------------------

SET @sql = (SELECT IF(COUNT(*) = 0,
    'ALTER TABLE `auctions` MODIFY `item_type` ENUM(''block'',''nft'',''product'') NOT NULL COMMENT ''拍卖品类型: 区块/NFT头像/商城商品''',
    'DO 0')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'auctions'
    AND COLUMN_NAME = 'item_type'
    AND COLUMN_TYPE LIKE '%''product''%');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ---------------------------------------------------------------------
-- 2. 索引检查
-- ---------------------------------------------------------------------
-- idx_item(item_type, item_id) 是普通 B-tree 复合索引，枚举值扩展后
-- 无需任何操作即可命中 'product' 查询；上电后用 EXPLAIN 验证即可。

-- ---------------------------------------------------------------------
-- 3. 存量数据零影响
-- ---------------------------------------------------------------------
-- 迁移前：SELECT item_type, COUNT(*) FROM auctions GROUP BY item_type;
--   仅应含 block / nft。枚举扩展不触碰行数据，迁移后分布不变。
-- 商品拍品为纯增量链路：block / NFT 拍品的发布、出价、落槌、转移
-- 所有权路径完全不变。
--
-- ---------------------------------------------------------------------
-- 回滚（如需）
-- ---------------------------------------------------------------------
-- 回滚前必须先清理所有商品拍品记录，否则 MODIFY 会因枚举截断报错：
--   DELETE FROM auctions WHERE item_type = 'product';
--   （同时按业务需要清理关联的 auction_bids / auction_watches）
-- 然后收窄枚举：
--   ALTER TABLE `auctions` MODIFY `item_type` ENUM('block','nft') NOT NULL COMMENT '拍卖品类型';
-- =====================================================================
