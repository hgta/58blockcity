# 任务清单

## 阶段一：币种全程携带（解决 6 元 → 6 人气）

- [x] 1.1 新建 `init/migrate-order-currency.sql`：可重复执行；`orders` 加 `currency enum('popularity','cny') NOT NULL DEFAULT 'popularity'`、`auction_id int NULL`、索引 `idx_auction`；`transactions` 加 `currency enum('popularity','cny') NOT NULL DEFAULT 'popularity'`。沿用 `init/migrate-auction-featured.sql` 的 `information_schema` 判存在写法。
- [x] 1.2 `classes/Auction.php` `transferOwnership()`：签名增加 `$currency` 参数；`settle()` 调用处传入 `$a['currency']`。
- [x] 1.3 `classes/Auction.php` `transferOwnership()` product 分支：INSERT `orders` 时写入 `currency` 与 `auction_id`（取代 `buyer_note` 的 `拍卖 LOT XXX 成交` 字符串反查约定）。
- [x] 1.4 `classes/Auction.php` nft 分支：INSERT `nft_transactions` 的 `currency` 由硬编码 `'popularity'` 改为传入的 `$currency`。
- [x] 1.5 `classes/Auction.php` block 分支：修 `seller_id` 取值顺序——先 `getBlockById()` 取 `seller_id`，再 `UPDATE blocks SET owner_id`；写 `transactions` 时带上 `currency`（参照 `classes/BlockListing.php:245-284` 的顺序）。
- [x] 1.6 `classes/Auction.php` 通知文案：卖家 / 买家的成交通知金额改用带货币符号输出，不再裸 `number_format($price, 2)`。实现为类内 `moneyText()`（与 `ac_money()` 同语义），避免业务类依赖拍卖站的渲染辅助文件。
- [x] 1.7 `mall/includes/functions.php`：新增 `order_money($amount, $currency)`，cny 输出 `¥ x.xx`，popularity 输出 `Ⓟ x`。
- [x] 1.8 商城金额展示改为 `order_money()`（实测 16 处，多于规划时的 11 处）：`mall/user/order_detail.php`（318 / 325 / 364）、`mall/user/orders.php`（523 / 536）、`mall/shop/orders.php`（291）、`mall/user/pay.php`（83 通知文案 / 361 / 395 / 401）、`mall/cart/checkout.php`（729 / 781 / 793）。追加发现的 5 处：`mall/shop/ship_order.php:149`、`mall/shop/manage.php:429`、`mall/user/dashboard.php:211`（原硬编码 ¥）、`mall/user/dashboard.php:282`（原硬编码 ¥）、`mall/admin/dashboard.php:123`（原硬编码 ¥）。后三者此前把人气值订单显示成 `¥`，属于反向错误。
- [x] 1.9 `mall/user/pay.php`：契约订单（`auction_id` 非空）302 到 `https://bid.58.tl/user/contract.php?id=X`；仅常规订单保留现有 BCT 引导。
- [x] 1.10 `classes/Order.php`：常规订单创建时 `currency` 留默认 `popularity`；`auction_id` 留 NULL。核对 `createOrder()` 的 INSERT 未列举这两列，列默认值已覆盖，无需改动。附带为 `Shop::getShopRecentOrders()` 补 `o.currency`（该查询是显式列名）。
- [ ] 1.11 验证：服务器执行迁移后，`curl -s https://bid.58.tl/view.php?id=16` 仍显示 `¥ 6.04`；对应 mall 订单详情页显示 `¥ 6.04` 而非 `Ⓟ6 人气值`。

## 阶段二：契约状态机

- [x] 2.1 新建 `init/migrate-auction-contract.sql`：可重复执行；`auctions` 加 `payment_status` / `payment_declared_at` / `settled_at` / `contract_expire_at` + 索引 `idx_contract`。
- [x] 2.2 `classes/Auction.php` 新增 `declarePaid($auctionId, $userId)`：校验调用者是 `current_bidder_id` 且当前 `unpaid` → 置 `buyer_declared` + `payment_declared_at`。
- [x] 2.3 `classes/Auction.php` 新增 `confirmPaid($auctionId, $userId)`：校验调用者是 `seller_id` 且当前 `buyer_declared` → **事务内**执行物权交割 + 写流水 + 置 `settled` + `settled_at`。
- [x] 2.4 `classes/Auction.php` 新增 `voidContract($auctionId, $reason)`：置 `voided`；`product` 额外回滚库存 + 取消 mall 订单。实现说明：**未调用** `Order::autoCancelExpiredOrder()`——它内部 `beginTransaction()`，与本方法事务嵌套；故内联其「回滚库存 + 取消订单」逻辑以保持原子性。
- [x] 2.5 `classes/Auction.php` 新增 `expireOverdueContracts($limit = 200)`：扫 `status='sold' AND payment_status='unpaid' AND contract_expire_at < NOW()`，逐条 `voidContract()`。
- [x] 2.6 `classes/Auction.php` 新增 `getContractsFor($userId, $role)`：`role='buyer'` 按 `current_bidder_id` 查、`role='seller'` 按 `seller_id` 查，过滤 `payment_status IS NOT NULL`，复用私有 `attachItemInfo()` 批量补拍品信息（避免 N+1）。
- [x] 2.7 `classes/Auction.php` `settle()` 改造：落槌成交时置 `payment_status='unpaid'` + `contract_expire_at = +7 天`，**不再立即过户**；`transferOwnership()` 的 product 分支保留（扣库存 + 建订单），block / nft 分支的过户逻辑迁移到 `confirmPaid()`。
- [x] 2.8 `classes/Auction.php` `isItemInActiveAuction()`：条件扩展为 `status IN ('pending','active') OR (status='sold' AND payment_status IN ('unpaid','buyer_declared'))`。
- [x] 2.9 `classes/Auction.php` 新增 `isItemInOpenContract($itemType, $itemId)`，供一口价挂牌侧后续接入。
- [x] 2.10 `classes/Order.php` 新增 `markPaidByContract($orderId)`：置 `status='paid'` + `paid_at=NOW()`，**不写 `payment_block_id`**（人民币契约无收款区块）。附带新增 `getOrderByAuctionId()`，取代按 `buyer_note` 前缀的字符串反查。
- [x] 2.11 新建 `bid/user/contract.php?id=X`：契约详情页。含拍品摘要、成交价（带币种）、履约进度条、收款指引（cny 走「联系卖家」+ 店铺链接；popularity 走 `accept_cities` 城市列表）、买家「我已付款」/ 卖家「确认收到款项」按钮、契约条款。POST 处理置于 HTML 输出之前；非买卖双方 / 无契约的存量记录一律回 `view.php`。
- [x] 2.12 `bid/my.php`：得标视角与发起视角均展示契约状态角标 + 「查看契约」入口，替换原有的「前往订单 →」直达 mall 付款路径。商城订单入口降级为次要按钮，且**仅在契约已完成（settled）后**露出——此时订单已是 paid，不会再被引导去付款。
- [x] 2.13 `bid/view.php`：已落槌拍品展示契约履约状态角标 + 契约面板（待付款 / 待确认 / 已完成 / 已作废），当事人额外有「查看契约」按钮。同步修正侧栏「落槌后系统自动转移物品所有权」与「成交后物品所有权将直接转移」两处过期文案。
- [x] 2.14 `bid/api/lot.php`：轮询响应纳入 `payment_status`；`Auction::getAuctionSnapshot()` 的显式列清单补 `payment_status`。`bid/view.php` 在契约未了结时**继续开启轮询**（原本落槌即停），`auction.js` 在 `payment_status` 变化时整页刷新重排契约面板。
- [x] 2.15 通知：`auction_contract_created` / `auction_contract_buyer_paid` / `auction_contract_settled` / `auction_contract_voided` 四类，链接指向契约页。原 `auction_won_product` / `auction_sold_product` / `auction_won` / `auction_sold` 四个类型停用（`tests/MallProductAuctionTest.php` 的对应断言已同步更新）。
- [x] 2.16 过期驱动挂载：`bid/index.php`、`bid/my.php`、`bid/admin/dashboard.php` 调用 `expireOverdueContracts()`；**不进 `api/lot.php` 的 3 秒轮询路径**。
- [x] 2.17 `bid/admin/dashboard.php` 新增「超时契约」面板：列出 `payment_status='buyer_declared'` 且 `payment_declared_at` 超 7 天未确认的契约，链接到后台 `auction-detail.php`（契约页对管理员不可见，会 302 到 view）。
- [x] 2.18 帮助文案：帮助中心内容为数据库驱动，故新增 `init/migrate-auction-contract-help.sql`（幂等，按 `question` 判重）向 `help_faq` 的「拍卖」分类写 3 条契约结算问答；同时同步 `bid/view.php` 侧栏「拍卖规则」与「卖家」两处规则文案。
- [ ] 2.19 验证：用 0 元起拍商品发一笔 cny 拍卖 → 落槌 → 契约页显示「¥ x.xx 待付款」→ 买家声明 → 卖家确认 → mall 订单转 paid；block / nft 拍卖验证落槌后物权仍在卖家名下、确认收款后才过户、7 天作废后物品解除占用。
