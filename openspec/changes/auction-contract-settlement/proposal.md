## Why

`bid.58.tl/view.php?id=16` 以 **¥ 6.04** 落槌，进入商城后被渲染成 **Ⓟ 6 人气值**。这不是显示笔误，是币种信息在落槌那一刻被丢弃。

### 1. 币种在 settle 边界丢失

`auctions.currency` 忠实记录 `cny` / `popularity`，但 `Auction::settle()` 调用 `transferOwnership()` 时**只传金额、不传币种**：

```
auctions.currency = 'cny'               ✅ 记录
   ↓  transferOwnership(..., $price)     ❌ 币种被丢
orders.total_amount = 6.04              ❌ orders 无 currency 列
   ↓
mall 展示层 11 处硬编码「Ⓟ … 人气值」    ❌ 渲染成 Ⓟ6 人气值
```

下游三张表全部丢失币种：

| 表 | currency 状况 |
|---|---|
| `orders` | **无此列**（`payment_amount` 注释即「支付人气值数量」） |
| `transactions` | **无此列** |
| `nft_transactions` | 有此列，但被 `Auction.php:526` **硬编码成 `'popularity'`** |

该商品自身标价为 Ⓟ350 / ¥58.00 —— 按商品自带的双价关系，6.04 元实际价值约 Ⓟ36。所以错的还不只是标签，是数量级。

### 2. 落槌 = 契约成立，但 block / nft 契约没有载体

平台对拍卖的定位已确认：**拍卖只产生契约，钱由买卖双方线下自行结算；平台不碰资金、不做汇率折算**。在这个定位下：

- `product` 拍品：有履约流程（mall 订单 + 支付页）
- `block` / `nft` 拍品：**落槌即过户、随即结束**。买家拿到物品，系统从未要求付款，也没有任何记录出口

### 3. 契约期一物二卖

`Auction::isItemInActiveAuction()` 的互斥校验只看 `status IN ('pending','active')`。落槌后 `status='sold'`，卖家可立即对同一物品**再发起拍卖 / 再挂牌**——契约期没有任何占用保护。

## What Changes

本 change 把落槌之后的链路在「契约 + 线下结算」定位下补齐，分两个阶段：

**阶段一：币种全程携带（解决 6 元 → 6 人气）**

- 迁移给 `orders` 加 `currency` 与 `auction_id`，给 `transactions` 加 `currency`
- `Auction::transferOwnership()` 接收 `currency` 并写入
- 商城新增 `order_money()` helper，11 处展示按币种渲染 `Ⓟ` / `¥`
- `mall/user/pay.php` 分流：契约订单重定向到拍卖站契约页（单一入口），不再展示 BCT 转账引导
- 顺带修 2 个数据错误（见下）

**阶段二：三种拍品统一契约状态机**

- `auctions` 行即契约本体，新增 `payment_status` / `payment_declared_at` / `settled_at` / `contract_expire_at` 四列
- 契约状态机 `unpaid → buyer_declared → settled`（+ `voided`），操作统一在 `bid/user/contract.php`
- **物权交割改到「卖家确认收款之后」**：block / nft 落槌时不再过户
- 契约未了结期间锁定物品，堵住一物二卖
- 契约作废时物权仍在卖家手中，**无需回滚**

### 顺带修的两个数据错误（与业务决策无关，可先行）

- `classes/Auction.php:458-465` block 分支先 `UPDATE blocks SET owner_id=buyer` 再 `getBlockById()` 读 `owner_id`，流水被记成「买家把区块卖给自己」，真实卖家永久丢失。改为在转移前取值（参照 `BlockListing.php:245-284`）
- `classes/Auction.php:526` nft 分支 `currency` 硬编码 `'popularity'`，导致人民币 NFT 拍卖被记成人气值

## Capabilities

### New Capabilities

- `auction-contract-settlement`：落槌后契约的币种携带、状态机、物权交割时机、作废与占用锁定。

### Modified Capabilities

- `auctions/item-type-product`（来自 `bid-mall-product-auction`）：商品契约改为由契约状态驱动 mall 订单付款，订单详情页不再是付款入口。
- `auctions/hall`：契约期互斥校验扩展。

## Impact

- **数据库**：两个可重复执行的迁移（见 design.md §1）。全部为 `ADD COLUMN` + `ADD INDEX`，存量数据 0 风险。
- **后端**：`classes/Auction.php`（契约方法 + `transferOwnership()` 改造 + 互斥扩展）、`classes/Order.php`（`markPaidByContract()`）、`mall/includes/functions.php`（`order_money()`）。
- **前端**：`bid/user/contract.php`（新增）、`bid/my.php`（契约入口）、`bid/view.php`（契约状态展示）；`mall` 侧 11 处展示 + `pay.php` 分流。
- **接口**：`bid/api/lot.php` 契约状态纳入轮询响应。
- **现有业务**：
  - 人气值商品拍卖的 BCT 线下转账路径不变，只是声明入口从 mall 迁到契约页
  - **block / nft 落槌不再即时过户，是行为变更**，需在上線前于帮助中心与拍卖规则中同步说明
