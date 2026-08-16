# 设计：58拍卖子站（bid.58.tl）

## 1. 数据模型（2 张新表）

```sql
-- 拍卖单
CREATE TABLE `auctions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `item_type` enum('block','nft') NOT NULL COMMENT '拍卖品类型',
  `item_id` int(11) NOT NULL COMMENT 'blocks.id 或 nft_city_user.id',
  `seller_id` int(11) NOT NULL COMMENT '卖家(原拥有者)',
  `start_price` decimal(20,2) NOT NULL COMMENT '起拍价',
  `reserve_price` decimal(20,2) DEFAULT NULL COMMENT '底价(可选, 低于底价流拍)',
  `bid_increment` decimal(20,2) NOT NULL DEFAULT '1.00' COMMENT '每次加价幅度',
  `start_time` datetime NOT NULL COMMENT '开始时间',
  `end_time` datetime NOT NULL COMMENT '截止时间',
  `current_price` decimal(20,2) DEFAULT NULL COMMENT '当前价(无人出价时=起拍价)',
  `current_bidder_id` int(11) DEFAULT NULL COMMENT '当前最高出价人',
  `currency` enum('popularity','cny') NOT NULL COMMENT '货币: 人气值/人民币',
  `accept_cities` text COMMENT '接受支付的城市人气值(JSON数组, 仅人气值时有效)',
  `status` enum('pending','active','ended','sold','canceled') NOT NULL DEFAULT 'pending',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_status_end` (`status`,`end_time`),
  KEY `idx_item` (`item_type`,`item_id`),
  KEY `idx_seller` (`seller_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 出价记录
CREATE TABLE `auction_bids` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `auction_id` int(11) NOT NULL,
  `bidder_id` int(11) NOT NULL,
  `amount` decimal(20,2) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_auction` (`auction_id`),
  KEY `idx_bidder` (`bidder_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 关键设计点

- **NFT 的 item_id 指向 `nft_city_user.id`**（NFT-城市-用户三元组持有记录），而非 `nft_avatars.id`。因为同一 NFT 头像可在多城市被不同用户持有。区块的 item_id 直接指向 `blocks.id`。
- **status 状态机**：
  ```
  pending → active → sold    （有出价且 ≥ 底价）
                  ↘ ended    （流拍：无出价或 < 底价）
  pending/active → canceled  （卖家撤销，仅限无人出价时）
  ```

## 2. 拍卖业务类 `classes/Auction.php`

```php
class Auction {
    // 发布拍卖（校验归属 + 互斥校验 + 写入）
    createAuction($sellerId, $itemType, $itemId, $data): int|false

    // 出价（校验登录、拍卖 active、加价幅度、货币）
    placeBid($auctionId, $bidderId, $amount): array  // ['ok'=>bool,'msg'=>...]

    // 惰性结算：结算所有已到期的 active 拍卖
    settleExpired(): void

    // 单笔结算（成交转移所有权 / 流拍）
    settle($auctionId): void

    // 查询
    getActiveAuctions($page, $perPage, $itemType, $currency): array
    getAuctionById($id): ?array
    getBids($auctionId, $limit): array
    getMyAuctions($userId): array   // 我发布的
    getMyBids($userId): array       // 我出价的

    // 校验辅助
    isItemInActiveAuction($itemType, $itemId): bool  // 供挂牌时互斥校验
}
```

## 3. 联动逻辑

### 3.1 发布拍卖时的归属校验

| 类型 | 校验方法 | 条件 |
|------|---------|------|
| block | `Block::getBlockById($item_id)` | `owner_id == 当前用户` 且 `status='sold'` |
| nft | `NFT::verifyOwnership($nft_id, $city_id, $userId)` | 返回 true |

### 3.2 互斥校验（决策 #1）

- **发布拍卖前**：检查该物品是否已有 active 一口价挂牌。
  - block：查 `block_listings` 是否 `block_id/merged_block_id = item_id AND status IN ('listed','pending')`
  - nft：查 `nft_sales` 是否 `status IN ('active','pending')`
- **挂牌出售前**（反向）：在 `BlockListing::createListing` 和 `NFT::listForSale` 中调用 `Auction::isItemInActiveAuction()`，若已有 active 拍卖则拒绝挂牌。

### 3.3 成交时的所有权转移（决策 #2）

结算 `sold` 时：
- **block**：`UPDATE blocks SET owner_id = 买家, status='sold' WHERE id = item_id`
- **nft**：`UPDATE nft_city_user SET user_id = 买家, is_current = 1, is_listed = 0 WHERE id = item_id`（原持有记录转给买家）

同时写入 `transactions` / `nft_transactions` 流水（复用现有表），并给买卖双方发通知（复用 `Notification` 类）。

## 4. 自动结算机制（惰性结算，无需 cron）

- 在以下入口调用 `$auction->settleExpired()`：
  - 拍卖首页 / 列表页加载时
  - 拍卖详情页加载时
  - 出价前
- `settleExpired()` 查询 `status='active' AND end_time < NOW()` 的所有拍卖单，逐个 `settle()`。
- 用 `status='ended'` 兜底标记，避免重复结算（settle 内先 `SELECT ... FOR UPDATE` 或乐观锁检查状态）。

## 5. 出价逻辑

- 必须登录（决策 #5）。
- 校验拍卖 `status='active'` 且 `NOW() BETWEEN start_time AND end_time`。
- 校验出价 ≥ `current_price + bid_increment`（首次出价 ≥ start_price）。
- 校验不能自己拍自己的物品（`bidder_id != seller_id`）。
- 写入 `auction_bids`，更新 `auctions.current_price / current_bidder_id`。
- 事务包裹，防并发重复出价。

## 6. 子站目录结构（仿照 block/nft）

```
bid/
├── .htaccess            # RewriteBase + 伪静态
├── index.php            # 首页：拍卖中列表（区块+NFT 混排）
├── create.php           # 发布拍卖（选区块 或 选NFT）
├── view.php             # 拍卖详情 + 出价
├── my.php               # 我的拍卖（我发布的 / 我出价的）
├── includes/
│   ├── auth.php         # 代理到 ../../includes/auth.php
│   ├── header.php       # logo_sub='拍卖'
│   └── footer.php
```

## 7. 共享配置调整

- `config/seo.php` 的 `subdomains` 增加 `'bid' => 'https://bid.58.tl'`。
- `sitemap.php` 增加拍卖列表页 URL。
- `docs/nginx-rewrite.conf` 增加 bid.58.tl 的 server 块。

## 8. 风险与注意

- **并发**：出价和结算都要事务 + 行锁，避免同一时刻多个人出价覆盖。
- **惰性结算的触发时机**：如果长时间无人访问，过期拍卖不会立刻结算，直到下次有人访问。可接受（第一版不做 cron）。
- **NFT 所有权模型复杂**：务必用 `nft_city_user.id` 作为 item_id，并复用 `verifyOwnership` 而非自行判断。
- **撤销拍卖**：仅当无人出价时可撤销（`current_bidder_id IS NULL`），有人出价后不可撤销。
