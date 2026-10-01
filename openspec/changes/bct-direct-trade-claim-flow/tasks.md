# Tasks: bct-direct-trade-claim-flow

## 1. 数据库与数据层

- [x] 1.1 新增 `bct_order_claims` 建表 SQL（含索引）并更新 `init/db-init.sql`
- [x] 1.2 `BCTOrder` 新增接单方法 `claimOrder($orderId, $userId)`：事务 + `FOR UPDATE` 锁订单行，校验 direct/pending/非本人/无活跃 claim，写入 claim（买方/卖方一侧按方向推导），订单置 processing 并写 counterparty_id
- [x] 1.3 `BCTOrder` 新增确认/放弃方法：`buyerConfirmClaim`、`sellerConfirmClaim`（完成时写 `bct_transactions` 留证，不划余额）、`abandonClaim`（回退 pending、清 counterparty_id）
- [x] 1.4 `BCTOrder` 新增查询：`getActiveClaim($orderId)`、`getUserActiveClaims($userId)`、`countUserClaimStats($userId)`（放弃/成交计数，为信用体系预留）
- [x] 1.5 `expireOverdueOrders` 增加保护：存在活跃 claim 的订单跳过过期处理

## 2. API

- [x] 2.1 新建 `bct/api/trade_flow.php`：POST action=claim/buyer_confirm/seller_confirm/abandon 分发，统一 assertLogin + CSRF + 当事人权限校验，JSON 响应
- [x] 2.2 实现 GET action=preview：返回订单与对方用户名、城市、数量、单价、总价、当前可执行动作
- [x] 2.3 各动作成功后发送站内信（复用 `Message::send`，按设计 D8 文案矩阵；通知文案内聚在 BCTOrder 状态流转方法中，API/页面/cron 三端共用）
- [x] 2.4 删除孤儿 API `bct/api/execute_trade.php`

## 3. 挂单大厅前端

- [x] 3.1 `bct/orders.php`：direct 单「交易」按钮改为「接单」，点击先弹预览确认（preview 接口），再 POST claim
- [x] 3.2 交易中订单渲染：显示"交易中"徽标，隐藏联系方式（非当事人），「接单」按钮隐藏
- [x] 3.3 我的订单标记：自己发布的单在交易中时显示"对方已接单"状态并可跳转个人中心交易详情

## 4. 个人中心

- [x] 4.1 `bct/user/dashboard.php` 新增"进行中的交易"卡片：列出当前用户参与的活跃 claim（城市/数量/单价/总价/对方用户名/联系方式/当前阶段）
- [x] 4.2 按角色渲染操作按钮：买方一侧「确认已付款」（matched 时）与卖方一侧「确认已收款」（buyer_confirmed 时），双方均有「放弃交易」（带确认弹窗）
- [x] 4.3 操作 POST 回 dashboard.php 调用 BCTOrder 方法（通知内聚共用），成功后带提示重定向回 #claims；完成/放弃后列表自动移除

## 5. 超时释放

- [x] 5.1 新增 `bct/cron/release_stale_claims.php`：释放 matched 超 24h 的 claim（置 released、订单回 pending、双发通知）
- [x] 5.2 大厅（全站小批量）与个人中心（限当前用户）加载时惰性推进超时 claim（与 expireOverdueOrders 惰性策略一致）

## 6. 验证

- [ ] 6.1 全链路手测：接挂售单 → 买方确认 → 卖方确认 → completed 且进入最新成交；接求购单对称验证
- [ ] 6.2 放弃链路：任一方放弃 → 订单重新可被第三人接单，claim 历史保留
- [ ] 6.3 超时链路：构造 24h 前 matched 的 claim，验证自动释放与通知
- [ ] 6.4 权限与安全：未登录、非当事人、自己接自己单、platform/mediator 单接单、CSRF 缺失均被拒
- [ ] 6.5 过期保护：交易中订单不会被 expire cron 误置 expired

> 6.x 为运行时验证，需部署到线上（含执行 `init/migration-bct-order-claims.sql` 建表）后按场景手测。静态验证（php -l / lint / 残留引用检索）已通过。
