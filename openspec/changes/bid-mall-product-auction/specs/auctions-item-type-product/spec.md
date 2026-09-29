## ADDED Requirements

### Requirement: auctions.item_type 枚举扩展为含 product

系统 SHALL 将 `auctions.item_type` 字段的枚举从 `('block','nft')` 扩展为 `('block','nft','product')`；存量拍卖（item_type ∈ {block, nft}）SHALL NOT 受影响。索引 `idx_item(item_type, item_id)` SHALL 继续覆盖新枚举值。

#### Scenario: 枚举扩展后存量数据完整

- **WHEN** 执行迁移脚本扩展枚举
- **THEN** `SELECT COUNT(*) FROM auctions WHERE item_type IN ('block','nft')` 与迁移前一致，且所有现存记录的 `item_type` 字段保持原值

#### Scenario: 枚举已包含 product

- **WHEN** 查询 `information_schema.COLUMNS`
- **THEN** `auctions.item_type` 列的枚举定义包含 `'block'`, `'nft'`, `'product'` 三个值

### Requirement: 商品作为拍品须经归属校验

系统 SHALL 在 `Auction::verifyOwnership('product', $productId, $sellerId)` 内通过 JOIN products + shops 校验以下条件全部满足：

- 商品所属店铺 `shops.user_id === sellerId`
- 店铺 `shops.status = 'active'`
- 商品 `products.status = 'active'`
- 商品 `products.stock >= 1`

任一不满足 SHALL 返回 false 并附明确错误信息（区分店铺非本人 / 店铺未营业 / 商品下架 / 库存不足）。

#### Scenario: 本人店主上架商品通过校验

- **WHEN** 用户 A 创建一笔拍卖，`item_type='product'`，`item_id` 指向 A 自己店铺下架状态 active 且 stock >= 1 的商品
- **THEN** `verifyOwnership` 返回 true，拍卖创建成功

#### Scenario: 非本人店主商品被拒绝

- **WHEN** 用户 A 尝试以 `item_id` 指向店铺主人为 B 的商品
- **THEN** 拍卖创建失败，错误信息为「您不拥有该商品所属店铺」

#### Scenario: 商品下架被拒绝

- **WHEN** 用户 A 尝试拍卖自己店铺下 `status='inactive'` 的商品
- **THEN** 拍卖创建失败，错误信息为「该商品当前不可拍（下架/售罄/草稿）」

#### Scenario: 库存不足被拒绝

- **WHEN** 用户 A 尝试拍卖自己店铺下 `stock=0` 的商品
- **THEN** 拍卖创建失败，错误信息为「该商品当前库存不足」

### Requirement: 商品拍品须与现有拍卖/订单互斥

系统 SHALL 在商品拍卖创建前校验：该商品 SHALL NOT 同时处于 `pending` / `active` 状态的拍卖中（沿用 `Auction::isItemInActiveAuction`），且 SHALL NOT 有关联常规订单处于 `pending` / `paid` / `shipped` 状态（沿用 `Auction::isItemListed` 的 product 分支）。

#### Scenario: 商品已在拍卖中

- **WHEN** 商品 P 已有一笔 `auctions.item_type='product'` 且 `status='active'` 的拍卖
- **THEN** 用户 A 尝试用 P 再发起拍卖时被拒

#### Scenario: 商品有关联常规订单

- **WHEN** 商品 P 在 `orders` / `order_items` 中存在一笔 `status='paid'` 的常规订单
- **THEN** 用户 A 尝试用 P 发起拍卖时被拒，避免一件商品被两次成交

#### Scenario: 商品无任何冲突

- **WHEN** 商品 P 未在任何 active 拍卖中，无任何 active 常规订单
- **THEN** 拍卖创建成功

### Requirement: 落槌成交创建 pending 订单并扣减库存

系统 SHALL 在 `Auction::transferOwnership('product', ...)` 内的同一事务中执行以下动作，**任一失败 MUST 整体回滚至流拍**：

- 原子扣库存：`UPDATE products SET stock = stock - 1, sold_count = sold_count + 1 WHERE id = ? AND stock >= 1`；`rowCount === 0` 时抛 `product_unavailable` 异常
- 创建订单：`orders` 记录 status='pending'，expire_at = NOW() + INTERVAL 7 DAY，buyer_note 含拍卖 LOT 编号，user_id 为得标者，shop_id 来自商品所属店铺，total_amount / payment_amount 为成交价
- 写 `order_items`：quantity=1，unit_price=成交价，product_name / product_image 取商品当前快照
- 通知得标者（链接到 mall 订单详情）与卖家（链接到 bid 详情页）

#### Scenario: 落槌成功扣库存并下单

- **WHEN** 拍卖落槌，`products.stock >= 1`，且得标者已登录
- **THEN** 同一事务内：products.stock 减 1、orders 新增一条 status='pending' 且 expire_at=+7 天、order_items 新增一条 quantity=1、sold_count 增 1、两条系统通知发出

#### Scenario: 落槌时库存不足视为流拍

- **WHEN** 拍卖落槌瞬间，`products.stock` 已被店主改成 0 或商品已被下架
- **THEN** 原子扣库存 rowCount=0，事务回滚，拍卖进入 `ended`（流拍）状态，无订单生成，向卖家发送流拍通知

#### Scenario: 落槌后订单 expire_at 为 +7 天

- **WHEN** 落槌成交创建订单
- **THEN** `orders.expire_at = NOW() + INTERVAL 7 DAY`（不复用 Order::createOrder 默认的 30 分钟）

### Requirement: 过期订单自动取消并还原库存

系统 SHALL 在订单过期清理逻辑（`Order::expireOverdueOrders()` 之类）扫描到 `status='pending' AND expire_at < NOW()` 的订单时：

- 对每个过期订单的 `order_items`，执行 `UPDATE products SET stock = stock + oi.quantity WHERE id = oi.product_id`
- 将 `orders.status` 改为 `cancelled`
- 向买家发送订单过期通知（链接到 mall 订单详情）

#### Scenario: 拍卖订单过期取消并还原库存

- **WHEN** 一笔拍卖订单 expire_at 已过且 status 仍为 pending
- **THEN** 触发扫描后，orders.status='cancelled'，关联商品的 stock 复原，向买家发送过期通知

#### Scenario: 已付款订单不受过期扫描影响

- **WHEN** 一笔订单 status='paid'，expire_at 已过
- **THEN** 不被扫描清理，stock 不变（订单已在付款状态，库存已扣）

### Requirement: 商品拍品详情页展示图集与商品元信息

系统 SHALL 在 `bid/view.php` 详情页对 `item_type='product'` 的拍卖渲染以下元素：
- 大图区为商品图集（`products.images` JSON 解析后的多图轮播），空图集退化为单图（`products.main_image`）
- 元信息侧栏展示商品原价（同时显示 `price_bct` 与 `price_cny`）、店铺名（链接到 mall 店铺页）、"查看商品详情 →" 跨站按钮
- 顶部 LOT 编号后追加类型徽章"商品"

跨站链接 SHALL 指向 `https://mall.58.tl/product/detail.php?id=<product_id>`（由 `ac_item_cross_url()` 统一派生）。

#### Scenario: 商品详情页显示图集轮播

- **WHEN** 商品 `images` 字段为 JSON 数组（含 ≥ 2 张图）
- **THEN** 详情页大图区显示图集组件，初始展示第一张，提供切换控件

#### Scenario: 商品图集空时退化为单图

- **WHEN** 商品 `images` 为 NULL 或空数组
- **THEN** 详情页大图区退化为仅显示 `products.main_image` 单图，无轮播控件

#### Scenario: 元信息侧栏与跨站按钮

- **WHEN** 详情页 item_type='product'
- **THEN** 元信息侧栏展示 `price_bct` 与 `price_cny`、`shop_name`、以及一个指向 `https://mall.58.tl/product/detail.php?id=X` 的"查看商品详情 →"按钮

#### Scenario: 跨站按钮对非商品拍品隐藏

- **WHEN** 详情页 item_type='block' 或 'nft'
- **THEN** 元信息侧栏不展示，"查看商品详情 →"按钮不出现

### Requirement: 商品拍品列表/筛选与首页展示

系统 SHALL 在 `bid/index.php` 竞价大厅的筛选区加"商品"芯片（`?type=product`），并 SHALL 让商品拍品与 block/nft 拍品共同参与"热拍中 / 即将结束 / 价格 / 最新"四种排序。

#### Scenario: 商品筛选芯片过滤列表

- **WHEN** 用户点击"商品"芯片
- **THEN** 拍卖列表仅展示 `item_type='product'` 的拍品，URL 含 `type=product`

#### Scenario: 商品拍品展示商品名

- **WHEN** 拍卖列表中包含一笔 item_type='product' 的拍品
- **THEN** 卡片标题展示 `products.name`，缩略图展示 `products.main_image`

### Requirement: 创建表单新增商品选择

系统 SHALL 在 `bid/create.php` 表单新增 item_type='product' 单选按钮，以及商品选择网格：

- 数据来源：JOIN `products` + `shops`，筛选 `shops.user_id = 当前用户` AND `products.status='active'` AND `products.stock >= 1` 且不在 active 拍卖/订单中
- 每张卡展示 main_image + 商品名 + price_bct/cny + 库存数
- 点击选中后写入隐藏 `item_id`
- 边缘场景：无店铺 / 店铺非 active / 无可拍商品时显示对应引导文案

#### Scenario: 店主选择商品发起拍卖

- **WHEN** 店主在 bid/create.php 选择 item_type='product'，从网格中点击一件 active 且 stock>=1 的商品
- **THEN** 隐藏 item_id 写入商品 id，提交后拍卖创建成功

#### Scenario: 无店铺时显示引导

- **WHEN** 用户从未创建店铺
- **THEN** 商品选择网格显示"您还没有店铺，前往 mall 创建"，不渲染商品卡

#### Scenario: 无可拍商品时显示引导

- **WHEN** 店主登录但本店所有商品均为 inactive 或 stock=0 或已在拍卖/订单中
- **THEN** 商品选择网格显示"暂无可拍卖商品，前往 mall 后台上架"，不渲染商品卡

### Requirement: 我的拍卖展示商品拍品得标订单链接

系统 SHALL 在 `bid/my.php`「竞价视角」下对商品拍品的得标者额外展示「前往订单 →」按钮，链接到 mall 订单详情（实际路由 `https://mall.58.tl/user/order_detail.php?id=<order_id>`，order_id 来自落槌时创建的订单；反查不到时回退 mall 订单中心）。

#### Scenario: 商品拍品得标者展示订单链接

- **WHEN** 用户赢得一笔 item_type='product' 的拍卖（拍卖 status='sold' 且 current_bidder_id=当前用户）
- **THEN** my.php 竞价视角下该拍品卡片显示"前往订单 →"按钮，点击跳转 mall 订单详情

#### Scenario: block/nft 拍品不展示订单链接

- **WHEN** 用户赢得一笔 item_type='block' 或 'nft' 的拍卖
- **THEN** my.php 不展示"前往订单 →"按钮（block/nft 走转移所有权路径，不创建订单）