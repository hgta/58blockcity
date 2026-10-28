## ADDED Requirements

### Requirement: 订单须携带币种

系统 SHALL 在 `orders` 表记录每笔订单的 `currency`（`popularity` / `cny`），并在全部金额展示处按币种渲染符号与精度（`cny`→`¥` 两位小数，`popularity`→`Ⓟ` 零位小数）。

#### Scenario: 人民币拍卖落槌后商城订单显示人民币

- **WHEN** 一笔 `auctions.currency='cny'` 的拍卖以 6.04 落槌
- **THEN** 生成的 `orders` 记录 `currency='cny'`，且 `mall/user/order_detail.php` 显示「¥ 6.04」而非「Ⓟ6 人气值」

#### Scenario: 存量常规订单不受影响

- **WHEN** 迁移执行完毕
- **THEN** 全部存量订单 `currency='popularity'`，展示与迁移前完全一致

#### Scenario: 人民币订单不引导 BCT 转账

- **WHEN** 买家访问一笔 `currency='cny'` 且 `auction_id` 非空的订单的 `mall/user/pay.php`
- **THEN** 系统 302 重定向到 `https://bid.58.tl/user/contract.php?id=<auction_id>`，不展示 blockcity.vip 的 BCT 转账引导

### Requirement: 落槌即成立契约

系统 SHALL 在拍卖落槌成交（无论 `block` / `nft` / `product`）时，在同一事务内置 `auctions.payment_status='unpaid'` 并写入 `contract_expire_at = NOW() + 7 天`。

#### Scenario: 区块拍卖落槌成立契约

- **WHEN** 一笔 block 拍卖落槌
- **THEN** `auctions.status='sold'`、`payment_status='unpaid'`、`contract_expire_at` 为 7 天后，且 `blocks.owner_id` **仍为卖家**

#### Scenario: 流拍不进契约流程

- **WHEN** 拍卖流拍（无人出价或未达底价）
- **THEN** `auctions.status='ended'`、`payment_status` 保持 NULL

### Requirement: 物权交割发生在卖家确认收款之后

系统 SHALL 仅在契约进入 `settled` 时执行物权交割。`block` 与 `nft` 拍品在落槌时 SHALL NOT 过户。

#### Scenario: 区块契约确认收款后过户

- **WHEN** 卖家对一笔 `unpaid`→`buyer_declared` 的 block 契约执行「确认收到款项」
- **THEN** 在同一事务内 `blocks.owner_id` 更新为买家、写入 `transactions` 流水（`currency` 取自拍卖）、契约置 `settled` + `settled_at`

#### Scenario: 人民币 NFT 契约写入真实币种

- **WHEN** 一笔 `currency='cny'` 的 nft 契约进入 `settled`
- **THEN** 写入的 `nft_transactions.currency` 为 `'cny'`，不再被硬编码为 `'popularity'`

#### Scenario: 商品契约确认收款后订单转已付款

- **WHEN** 卖家对一笔 product 契约执行「确认收到款项」
- **THEN** 关联 mall 订单置 `status='paid'` 且不写入 `payment_block_id`

### Requirement: 契约须经双方确认推进

系统 SHALL 要求契约按 `unpaid → buyer_declared → settled` 单向推进：仅 `current_bidder_id` 可将 `unpaid` 置为 `buyer_declared`，仅 `seller_id` 可将 `buyer_declared` 置为 `settled`。

#### Scenario: 非得标者无法声明付款

- **WHEN** 用户 C（非得标者）对他人契约调用「我已付款」
- **THEN** 操作被拒绝

#### Scenario: 买家虚假声明不能自动获得物品

- **WHEN** 契约处于 `buyer_declared` 且卖家超过 7 天未确认
- **THEN** 契约保持 `buyer_declared`，不自动推进；该契约出现在后台「超时契约」列表

### Requirement: 契约作废时物权无需回滚

系统 SHALL 在契约作废时仅置 `payment_status='voided'`；因物权尚未交割，`block` / `nft` SHALL NOT 执行任何回滚。

#### Scenario: 到期未付款的区块契约作废

- **WHEN** 一笔 `unpaid` 的 block 契约超过 `contract_expire_at`
- **THEN** 契约置 `voided`，`blocks.owner_id` 仍为卖家，且该区块解除占用可被再次拍卖

#### Scenario: 到期未付款的商品契约作废并回滚库存

- **WHEN** 一笔 `unpaid` 的 product 契约超过 `contract_expire_at`
- **THEN** 契约置 `voided`，关联 mall 订单取消，`products.stock` 回滚 +1、`sold_count` 减 1

### Requirement: 契约未了结期间物品不可被二次处置

系统 SHALL 阻止对处于 `pending` / `active` 拍卖中、或处于未了结契约（`status='sold'` 且 `payment_status IN ('unpaid','buyer_declared')`）中的物品再发起拍卖。

#### Scenario: 契约期一物二卖被拒绝

- **WHEN** 区块 B 已有一笔 `status='sold'`、`payment_status='unpaid'` 的拍卖，卖家尝试用 B 再发起拍卖
- **THEN** 创建被拒，错误信息为「该物品已有未完成的成交契约」

#### Scenario: 契约作废后物品可再次拍卖

- **WHEN** 同一区块的契约已置 `voided`
- **THEN** 卖家可正常发起新拍卖

### Requirement: 契约页展示收款指引

系统 SHALL 在契约页展示与币种匹配的收款指引，且不提供在线支付通道。

#### Scenario: 人民币契约的收款指引

- **WHEN** 买家或卖家打开一笔 `currency='cny'` 的契约
- **THEN** 页面显示「请与卖家联系约定收款方式（微信 / 支付宝 / 银行转账）」+ 卖家用户名；`product` 契约额外显示店铺主页链接 `https://mall.58.tl/shop/view.php?id=X`

#### Scenario: 人气值契约的收款指引

- **WHEN** 打开一笔 `currency='popularity'` 的契约
- **THEN** 页面显示应转账的人气值金额与拍卖时设置的「接受城市」列表

### Requirement: 契约金额不做币种折算

系统 SHALL NOT 在落槌或契约推进过程中对金额做任何币种换算。

#### Scenario: 人民币成交价原样进入契约

- **WHEN** 一笔 `currency='cny'` 的拍卖以 6.04 落槌
- **THEN** 契约与关联订单中的金额均为 6.04，不按任何汇率折算成人气值

### Requirement: 落槌与履约通知须带货币符号

系统 SHALL 在落槌、买家声明、卖家确认、契约作废四类通知中使用带货币符号的金额文案。

#### Scenario: 人民币拍卖的成交通知

- **WHEN** 一笔 `currency='cny'` 的拍卖落槌
- **THEN** 卖家收到的通知金额为「¥ 6.04」而非「6.04」

### Requirement: 契约过期须惰性清扫

系统 SHALL 在 `bid/index.php`、`bid/my.php`、`bid/admin/dashboard.php` 惰性调用过期清扫，且 SHALL NOT 在 `bid/api/lot.php` 的 3 秒轮询路径中执行写入型清扫。

#### Scenario: 过期契约被惰性作废

- **WHEN** 用户访问 `bid/my.php` 且存在 `unpaid` 且已过期的契约
- **THEN** 该契约被置为 `voided`，并向双方发送作废通知
