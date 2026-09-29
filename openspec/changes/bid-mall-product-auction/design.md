## Context

动机见 `proposal.md - Why`。设计前已确认的现状约束：

- `mall.58.tl` 商品模型：`products(id, shop_id, name, description, main_image, images[json], price_bct, price_cny, stock, sold_count, status)`，归属通过 `shops.user_id` 间接指向店主。
- `Order::createOrder()` 当前签名：`(array $data) => int $orderId`，必填 `user_id, shop_id, total_amount, payment_city, payment_amount, status='pending'`；会在事务内 INSERT `orders` 并返回 id。**不**自动写 `order_items`——当前各调用点自行处理（见 `mall/cart/...`）。
- `orders.status` 枚举 `('pending','paid','shipped','completed','cancelled','refunded')`；`expire_at` 当前为订单创建后 30 分钟过期（拍卖订单不应此规则，需独立设 +7 天）。
- 商品被拍卖期间**不**拦截加购物车 / 立即购买（与用户决策一致）；但订单落槌后 `current_bidder_id` 需保证未在同一商品的常规订单中"已拍下付款"——通过 `Order::createAuctionWonOrder` 内前置校验规避。
- 平台已沉淀通知基建 `Notification::sendSystemNotify()`。
- 跨子站跳转现状：区块 `https://block.58.tl/block/view.php?id=X`、NFT `https://nft.58.tl/nft/view.php?id=X`；商品约定为 `https://mall.58.tl/product/detail.php?id=X`。
- 设计假设前序 change `auction-item-description` 已合并提供 `description` 字段与 `MarkdownSafe::render()`；但不强制依赖（NULL 视为无描述）。

## Goals / Non-Goals

**Goals:**

- 商品拍卖对卖家友好：仅需在「我的店铺」后台拍品列表里选一件 active 商品，几步即可发起。
- 落槌链路稳定：原子扣 stock + 建 pending 订单 + 通知得标者；任何环节失败回滚至流拍。
- 跨站跳转顺畅：买家在 `bid.58.tl/view.php` 一键跳到 `mall.58.tl/product/detail.php` 看商品完整信息。
- 订单 7 天过期：超时自动取消订单 + 还原 stock + 通知买家。
- 不动 block/nft 拍品链路、不动 mall 下单流程、不动 cart。
- 「商品编辑期间」拍卖依旧显示原标题（取快照，不与商品变化耦合）。

**Non-Goals:**

- 不接 mall 端付款逻辑（保持线下交付不变）。
- 不做保证金、不做信用分。
- 不在拍卖期间锁定商品字段（与用户决策一致——允许店主改价/改库存）。
- 不做"商品被拍期间隐藏"（与用户决策一致——加购物车/下单不拦截）。
- 不做多件拍卖（amount 字段）。**仅 1 件 1 拍**。
- 不做拍卖商品的预扣库存（落槌才扣）。
- 不做"店主对本店所有商品批量发起拍卖"。
- 不做 admin 后台批量管理商品拍卖（沿用现有 auction admin 路径）。

## Decisions

### 1. `item_type` 枚举扩展：单条 `MODIFY` 而非破坏性变更

`ALTER TABLE auctions MODIFY item_type ENUM('block','nft','product') NOT NULL;`

- 已有 `block` / `nft` 数据不被破坏；现有查询（`WHERE item_type IN ('block','nft')`）继续返回原集。
- 新查询需明确枚举：`WHERE item_type IN ('block','nft','product')` 或 `WHERE item_type = 'product'`。
- 不加 `NOT NULL DEFAULT 'block'` 之类默认值，避免历史数据被改写。

### 2. 商品校验：归属 + 商品 + 互斥 三层

`Auction::verifyOwnership('product', $productId, $sellerId)` 新分支：

```sql
SELECT p.id, p.stock, p.status, s.user_id AS shop_owner_id, s.status AS shop_status
FROM products p
JOIN shops s ON p.shop_id = s.id
WHERE p.id = ?
```

通过条件：`shop_owner_id === sellerId` AND `shop_status='active'` AND `p.status='active'` AND `p.stock >= 1`。

不通过时返回错误信息（区别提示是商品下架 / 库存不足 / 店铺非本人）。

互斥校验复用 `Auction::isItemInActiveAuction('product', $id)`：沿用同一 `WHERE item_type=? AND item_id=? AND status IN ('pending','active')` 逻辑，无需新代码。

`Auction::isItemListed('product', $id)` 新分支：检查该商品是否有未完成的常规订单（pending/paid/shipped）。SQL：

```sql
SELECT COUNT(*) FROM order_items oi
JOIN orders o ON oi.order_id = o.id
WHERE oi.product_id = ? AND o.status IN ('pending','paid','shipped')
```

> 用户决策"不拦截加购物车/立即购买"，但仍可在落槌时校验"商品未在常规订单中"——避免一件商品同时被拍卖拿走又被常规单拿走。
>
> **注**：当前决策下，本分支实际可宽松——但保留以防极端并发。建议实现时加，但拦截条件仅"该商品对应订单的 status='paid' 或 'shipped'"——给 pending 订单（未付款）留机会。

### 3. 落槌履约：`transferOwnership('product', ...)` 事务结构

`Auction::settle()` 当前已是事务化；`transferOwnership` 内嵌其中（同一事务）。新增 product 分支：

```php
// 伪代码，事务内：
// 3.1 原子扣 stock
$ok = $pdo->prepare("UPDATE products SET stock = stock - 1, sold_count = sold_count + 1, updated_at = NOW() WHERE id = ? AND stock >= 1")
           ->execute([$productId]);
if ($ok->rowCount() === 0) {
    // 商品被下架 / 库存被改 / 库存<1，视为流拍
    throw new \Exception('product_unavailable');
}
// 3.2 取商品 + 店铺信息（用于订单字段）
$prod = Product::getProductById($productId, includeInactive=true);
// 3.3 建订单
$orderData = [
    'user_id'      => $buyerId,
    'shop_id'      => $prod['shop_id'],
    'total_amount' => $finalPrice,
    'payment_city' => '',         // 拍卖场景无需指定
    'payment_amount' => $finalPrice,
    'payment_block_id' => '',     // 拍卖场景无区块支付
    'buyer_note'   => '拍卖 ' . ac_lot_no($auctionId) . ' 成交',
    'shipping_address' => '',     // 卖家后续补充
    'status'       => 'pending',
    'expire_at'    => date('Y-m-d H:i:s', strtotime('+7 days')),
];
$orderId = Order::createOrder($orderData);
// 3.4 写 order_items（quantity=1, unit_price=final_price, snapshot product_name/image）
$oiStmt = $pdo->prepare("
    INSERT INTO order_items (order_id, product_id, product_name, product_image, quantity, unit_price, total_price, created_at)
    VALUES (?, ?, ?, ?, 1, ?, ?, NOW())
");
$oiStmt->execute([$orderId, $productId, $prod['name'], $prod['main_image'], $finalPrice, $finalPrice]);
// 3.5 通知得标者 + 卖家
Notification::sendSystemNotify($buyerId, 'auction_won_product', $auctionId, '...', 'https://mall.58.tl/user/order_detail.php?id=' . $orderId);
Notification::sendSystemNotify($sellerId, 'auction_sold_product', $auctionId, '...', '...');
```

**关键点**：

- `UPDATE products ... WHERE stock >= 1` 是原子条件更新；`rowCount=0` 即"商品不可再拍"，整体抛 `product_unavailable`，事务回滚，落槌视为"商品不可用导致的流拍"。
- 订单 `expire_at = +7 天` 由本路径独立设置（不复用 `Order::createOrder` 默认的 30 分钟）；可在 `createOrder` 上加一个 `expire_offset` 可选参数，或在 `Order` 新增 `createAuctionWonOrder($data)` 包装方法。
- 卖家通知里附 mall 订单详情链接（实际路由 `https://mall.58.tl/user/order_detail.php?id=X`；规划期写作 `order/detail.php`，实施时已按现行 mall 代码修正）。
- `buyer_note` 自动写入 "拍卖 LOT-XXXX 成交"，卖家在 mall 后台一眼能识别这是拍卖单。

### 4. 订单过期清理（7 天）：复用现有 expire 扫描脚本

- 平台已有 cron-like 惰性扫描机制（在订单中心 `mall/order/` 调用处一般会触发 `Order::expireOverdueOrders()` 之类的方法——本 change 不主动重写）。
- 订单 `expire_at` 字段已存在；本 change 仅确保拍卖订单 `expire_at` 字段写入 +7 天。
- 过期清理副作用：需要"还原 stock"。在 `Order::expireOverdueOrders()` 内增加 `JOIN order_items` 拿 product_id，对每个过期订单执行 `UPDATE products SET stock = stock + oi.quantity WHERE id = oi.product_id`（quantity=1）。
- 兼容现有 30 分钟过期：脚本对所有过期未付款订单统一还原 stock，无类型差异。

### 5. 商品展示信息：`attachItemInfo` product 分支

```php
// 批量取
$stmt = $this->pdo->prepare("
    SELECT p.id, p.shop_id, p.name, p.main_image, p.thumb_image, p.images, p.price_bct, p.price_cny,
           s.shop_name
    FROM products p
    LEFT JOIN shops s ON p.shop_id = s.id
    WHERE p.id IN (...)
");
```

- `$a['item_title'] = $p['name']`（**实时取**，不是创建时快照——按用户决策"允许商品编辑，auctions 展示用最新"）。
- `$a['item_image'] = 完整 URL 拼接 products.main_image`。
- `$a['item_images'] = JSON decode p.images`（数组），详情页轮播用。
- `$a['item_price_bct'] / ['item_price_cny']` —— 用于详情页"商品原价参考"。
- `$a['item_shop_name'] = p['shop_name']` —— 详情页展示"店铺：xxx"。
- `$a['item_cross_url'] = 'https://mall.58.tl/product/detail.php?id=' . $p['id']`。

### 6. 创建表单：商品选择网格

- 新增第三个 item_type 单选按钮"商城商品"。
- 选中后展示"我的可拍卖商品"网格：
  - 数据来源：`SELECT p.* FROM products p JOIN shops s ON p.shop_id=s.id WHERE s.user_id=? AND p.status='active' AND p.stock>=1 AND NOT EXISTS (SELECT 1 FROM auctions a WHERE a.item_type='product' AND a.item_id=p.id AND a.status IN ('pending','active'))`
  - 每张卡：缩略图 + 商品名 + 原价（price_bct / price_cny）+ 库存数；点击选中 → 写入隐藏 `item_id`。
- 无店铺 / 无可拍卖商品：显示"暂无可拍卖商品"+ 跳转 mall 后台上架入口。

### 7. 详情页 UI 适配

- 大图区 `.ac-stage-media`：当前是单图。product 分支改为：
  - `<div class="ac-stage-gallery">`：主图 + 缩略图序列；左右切换按钮；移动端滑动手势（可后续优化）。
  - 图集来源：`products.images`（JSON）数组；空则退化单图（仅 main_image）。
- 新增"商品元信息"区块（在拍品信息侧栏）：
  - 商品原价（同时显示 price_bct 与 price_cny）
  - 店铺名（链接到 `https://mall.58.tl/shop/view.php?id=SHOP_ID`）
  - "查看商品详情 →" 跨站按钮
- 顶部加 LOT 编号 + 类型徽章（"商品"）

### 8. 跨站链接规范化

- 约定常量映射（在 `bid/includes/lot_helpers.php`）：
  - `ac_item_cross_url($auction)`：根据 `item_type` 返回对应子站 URL；fallback `''`。
- 商品链接：`'https://mall.58.tl/product/detail.php?id=' . intval($auction['item_id'])`
- 验证 `mall/product/detail.php?id=X` 的链接形态正确（已确认现状为 `?id=` 接参）。

### 9. 编辑/取消语义对商品拍品的兼容

- 取消拍卖：`cancelAuction()` 现有逻辑对商品拍品直接生效（不扣过 stock，回滚 N/A）。
- 编辑拍卖：`updateAuction()` 当前已校验"仅 pending 可编辑"。商品拍品的"商品不可更换"约束已通过 enum + 校验保证（现有 `verifyOwnership` 调用链），但 description / 起拍价 / 加价幅度可改。
- 若商品被下架，编辑尝试时 `verifyOwnership('product', ...)` 会拒绝（用户决策"允许编辑，但落槌时再校验"）。

### 10. 「我的拍卖」商品视图

- 卖家视角：商品拍品的 `myAuctions` 中展示商品名 + 缩略图（`item_image`） + 当前价/出价次数。
- 竞价视角：商品拍品得标者额外展示"前往订单 →"按钮（链接到 mall 订单详情）。
- 关注列表：商品拍品与 block/nft 一致。

## Risks / Open Questions

- **数据脏风险**：商品编辑期间（用户决策允许），`products.name` 可能被改；`item_title` 实时取会变。**接受**：用户已决策允许；但卖家体验上需在创建时提示"商品改名后拍卖详情页会同步更新"。
- **并发兜底**：若多个 active 拍卖同一商品（极端异常），落槌 `WHERE stock >= 1` 锁住第一个，其他流拍。建议在 `attachItemInfo` 内前置去重，但仍以 `verifyOwnership` 创建时校验为主防线。
- **mall 端 Order::expireOverdueOrders 兼容**：本 change 假设 mall 端已有此方法；若未实现，需在该 change 实施时一并补齐。任务里明确列出。
- **跨子站链接失效**：mall 商品详情页若未来改路由，需同步更新 `ac_item_cross_url`。
- **支付语义模糊**：拍卖订单 `payment_city` / `payment_amount` 写入何种货币？建议沿用拍卖成交的 `currency`（popularity → payment_city=空，payment_amount=成交价的人气值；cny 类似）。买家付款时由 mall 端决定实际支付通道。