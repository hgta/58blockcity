## Why

`bid.58.tl` 当前只支持拍卖 `block`（区块）和 `nft`（NFT 头像）。商城（`mall.58.tl`）虽是平台的核心交易场景之一，但**店铺内的实物商品从未接入拍卖**——买家在商城只有"加入购物车 / 立即购买"两种拿货方式，无法对稀缺、热销、限量商品发起价格博弈。

接入后：
- 店主可对本店内的 `active` 商品发起拍卖，对稀缺品做限量首发 / 价格发现 / 清尾货
- 买家可在 `bid.58.tl` 用熟悉的英式增价拍卖体验抢商城商品（倒计时、防狙击、关注）
- 落槌自动落单进入 mall 现有订单 / 付款 / 发货体系，不绕开线下交付流程

> 这是 Change `auction-item-description` 之后的第二个 change；它假定 `auctions.description` 已可用（详见前序 change），但不强依赖——本 change 可独立交付，仅在 UI 上顺手展示 description。

## What Changes

- **`auctions.item_type` 枚举扩展**：从 `ENUM('block','nft')` 改为 `ENUM('block','nft','product')`。
- **新增 `Auction::createAuction()` 的 `product` 分支**：
  - 归属校验改为「`shops.user_id === seller_id` + `shops.status='active'`」
  - 商品校验：`products.status='active'` 且 `stock >= 1` 且未在 active 拍卖中
- **新增 `Auction::transferOwnership()` 的 `product` 分支**：
  - 原子事务内：`products.stock -= 1`（条件 `WHERE stock >= 1`）+ `sold_count += 1`
  - 创建 `orders(status='pending', expire_at=+7天, buyer_note='拍卖 LOT-XXXX 成交')` + `order_items(product_id, quantity=1, unit_price=成交价)`
  - 通知得标者 + 卖家
- **`Auction::getAuctionById()` / `attachItemInfo()` 新增 `product` 分支**：
  - 取 `products.name / main_image / images` 作为拍品展示素材
  - 跨站链接指向 `https://mall.58.tl/product/detail.php?id=X`
- **创建表单新增"商城商品"单选 + 商品选择网格**：
  - 取该卖家店铺下所有可拍卖商品（active + stock>=1 + 不在 active 拍卖/挂牌中）
- **详情页适配**：
  - 大图区改为商品图集（`images` JSON 解析为轮播）；若有 `description`（前序 change 产物）则在下方折叠展示
  - 加「商品原价参考」+「查看商品详情 →」跨站按钮
- **首页加"商品"筛选芯片 + 卡片缩略图适配**
- **"我的拍卖"商品视图**：竞价视角下显示得标订单链接（`https://mall.58.tl/order/...`）
- **不接支付**：落槌仅创建 `pending` 订单 + 扣 stock；付款走 mall 现有流程

## Capabilities

### New Capabilities

- `auctions/item-type-product`: 拍卖支持商城商品作为新物品类型；包含完整的发布、出价、落槌、订单创建、跨站链接闭环。

### Modified Capabilities

- `auctions/seller-description`（来自前序 change `auction-item-description`）：商品拍品同样可写描述；本 change 在商品视图下展示，但不依赖前序 change 已上线（NULL 视为无描述）。

## Impact

- **数据库**：
  - 迁移 `init/migrate-auction-product.sql`：`ALTER TABLE auctions MODIFY item_type ENUM('block','nft','product') NOT NULL`；可重复执行。
  - 现有存量数据 0 风险（已存在的 item_type 都是 block/nft，扩展枚举不影响）。
  - 索引 `idx_item(item_type, item_id)` 自动适配新值。
- **后端类**：
  - `classes/Auction.php`：
    - `createAuction()`：依据 `item_type` 分发校验（block→原逻辑；nft→原逻辑；product→新逻辑）
    - `verifyOwnership()`：增加 product 分支
    - `isItemListed()`：增加 product 分支（检查是否有 pending/paid/unshipped 单）
    - `transferOwnership()`：增加 product 分支（事务内扣 stock + 建订单）
    - `getAuctionById()` / `attachItemInfo()`：增加 product 分支
  - `classes/Order.php`：可选新增 `createAuctionWonOrder()` 包装方法，便于转移逻辑独立测试（若不愿直接复用 createOrder，事务由 Auction 类掌控）。
  - `classes/Product.php`：可选新增 `decrementStockAtomic()` 方法，封装 `UPDATE ... WHERE stock >= 1` 原子操作，便于测试。
- **前端页面**：
  - `bid/create.php`：加"商城商品"单选按钮 + 商品选择网格 + （若有 description）复用 change1 编辑器
  - `bid/view.php`：商品图集轮播组件（CSS + JS 复用现有 .ac-stage-media 容器）；商品原价 + 跨站链接
  - `bid/index.php`：筛选加"商品"芯片；卡片缩略图适配
  - `bid/my.php`：商品得标者展示订单链接
- **跨站链接**：约定 `https://mall.58.tl/product/detail.php?id=X`（`mall/product/detail.php` 当前以 `?id=` 接参），保持跨子站跳转单一形态。
- **接口**：无新接口；复用现有 `/api/lot.php` 轮询。
- **依赖/基建**：无新增依赖。CSS / JS 在 `bid/assets/css/auction.css` 与 `bid/assets/js/auction.js` 增量扩展。
- **现有业务不变**：block / NFT 拍品链路完全不动；商品链路是纯增量。