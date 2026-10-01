# Spec Delta: bct/trading/claim-flow

## ADDED Requirements

### Requirement: 直接交易单可被接单（意向锁）
挂单大厅中，`trade_type='direct'` 且 `status='pending'` 的订单 SHALL 可被登录用户接单。接单成功后系统 MUST 将订单置为 `processing`，在 `bct_order_claims` 写入一条 `matched` 记录（买方一侧/卖方一侧按订单方向推导：挂售单接单人为买方，求购单接单人为卖方），并将 `bct_orders.counterparty_id` 置为接单人。接单 MUST 为整单，不支持部分数量。

#### Scenario: 买家接挂售单
- **WHEN** 登录用户在挂单大厅对一张待成交的挂售单点击「接单」
- **THEN** 订单状态变为"交易中"（processing），接单人成为买方一侧，双方进入线下交易流程

#### Scenario: 卖家接求购单
- **WHEN** 登录用户对一张待成交的求购单点击「接单」
- **THEN** 订单状态变为"交易中"，接单人成为卖方一侧，流程与接挂售单对称

#### Scenario: 不能接自己的单
- **WHEN** 用户尝试接自己发布的订单
- **THEN** 系统拒绝并提示"不能接自己的挂单"

#### Scenario: 交易中订单不可再接
- **WHEN** 用户尝试接一张已有活跃 claim（未终态）的订单
- **THEN** 系统拒绝并提示"该订单交易中"

#### Scenario: 仅直接交易单可接
- **WHEN** 用户对 `trade_type` 为 platform 或 mediator 的订单尝试接单
- **THEN** 系统拒绝并提示该订单类型不适用接单流程

### Requirement: 接单后联系方式仅双方可见
订单处于"交易中"时，挂单大厅 MUST 对所有非当事人隐藏联系方式并显示"交易中"标记；买卖双方 MUST 能在个人中心查看对方联系方式。

#### Scenario: 其他人看不到联系方式
- **WHEN** 非当事人登录用户浏览挂单大厅
- **THEN** 交易中订单显示"交易中"徽标，联系方式不展示

#### Scenario: 当事人可见对方联系方式
- **WHEN** 买方或卖方一侧用户在个人中心查看进行中的交易
- **THEN** 可见对方用户名与该单登记的联系方式

### Requirement: 买方确认已付款
交易中（`matched`）的 claim，买方一侧用户 SHALL 可点「确认已付款」。确认后 claim 状态 MUST 更新为 `buyer_confirmed` 并记录 `buyer_confirmed_at`；卖方一侧用户不可执行此动作。

#### Scenario: 买方确认
- **WHEN** 买方一侧用户线下付款完成后点击「确认已付款」
- **THEN** 交易进入"待卖家确认收款"状态，卖方收到站内信提醒

#### Scenario: 卖方无法触发买方确认
- **WHEN** 卖方一侧用户尝试执行 buyer_confirm
- **THEN** 系统拒绝并提示无权操作

### Requirement: 卖方确认已收款后交易完成
`buyer_confirmed` 的 claim，卖方一侧用户 SHALL 可点「确认已收款」。确认后系统 MUST 将订单置为 `completed`、claim 置为 `completed`，并写入一条 `bct_transactions` 记录（type=trade、手续费 0、from=卖方、to=买方、订单单价与整单数量）。系统 MUST NOT 划转 `user_bct_account` 余额。

#### Scenario: 卖方确认完成交易
- **WHEN** 卖方核实线下款项到账后点击「确认已收款」
- **THEN** 订单完成，双方收到站内信，该笔交易进入最新成交与 24h 统计

#### Scenario: 未付款确认时卖方不可确认
- **WHEN** claim 仍处于 `matched`（买方未确认付款）时卖方尝试确认收款
- **THEN** 系统拒绝并提示需等待对方确认付款

### Requirement: 放弃交易释放挂单
完成前（`matched` 或 `buyer_confirmed`），买卖任一方 SHALL 可放弃交易。放弃后 claim MUST 置为 `abandoned`（记录发起人与时间），订单回退为 `pending`、`counterparty_id` 清空，重新在大厅开放接单；对方 MUST 收到站内信通知。

#### Scenario: 一方放弃
- **WHEN** 任一当事人点击「放弃交易」
- **THEN** 订单回到待成交并重新开放接单，接单历史保留该次放弃记录

#### Scenario: 放弃后他人可接
- **WHEN** 订单被放弃后回到 pending
- **THEN** 其他登录用户可在挂单大厅正常接单

### Requirement: 接单超时自动释放
接单后 24 小时内买方未确认付款的 claim，系统 MUST 自动置为 `released` 并将订单回退为 `pending`，双方收到通知。买方已确认付款后系统 MUST NOT 自动释放。

#### Scenario: 超时释放
- **WHEN** claim 处于 `matched` 超过 24 小时且买方未确认
- **THEN** 系统释放该 claim，订单重新开放，双方收到站内信

#### Scenario: 已付款确认不自动释放
- **WHEN** claim 处于 `buyer_confirmed` 超过 24 小时
- **THEN** 订单保持交易中，不自动释放，卖方仅收到提醒通知

### Requirement: 交易状态在个人中心可见可操作
用户个人中心 SHALL 提供"进行中的交易"视图，列出自己参与的活跃 claim（含城市、数量、单价、总价、对方用户名、联系方式、当前阶段），并提供接单后的「确认已付款/确认已收款/放弃交易」操作按钮。

#### Scenario: 买方视角操作
- **WHEN** 买方一侧用户打开个人中心进行中的交易
- **THEN** 可见交易详情与对方联系方式，可执行「确认已付款」与「放弃交易」

#### Scenario: 卖方视角操作
- **WHEN** 卖方一侧用户打开个人中心进行中的交易
- **THEN** 在买方确认付款后可见「确认已收款」与「放弃交易」

### Requirement: 交易动作接口安全校验
所有接单/确认/放弃动作 MUST 校验登录态、CSRF 令牌与当事人身份（仅活跃 claim 的买卖两侧用户可操作），并以事务保证订单与 claim 状态一致性。

#### Scenario: 非当事人操作被拒
- **WHEN** 与交易无关的用户调用交易动作
- **THEN** 系统拒绝并提示无权操作

#### Scenario: 未登录调用被拒
- **WHEN** 未登录用户调用交易动作
- **THEN** 返回登录要求，不产生任何状态变更

## REMOVED Requirements

### Requirement: 订单-订单撮合执行接口
**Reason**: `bct/api/execute_trade.php` 的语义是"指定对手用户找其挂单做订单-订单撮合"，与本次建立的"人接单"模型相反；该接口自 2026-05-31 变更后已无任何页面调用，属于死代码。
**Migration**: 直接删除该文件。接单/确认/放弃由 `bct/api/trade_flow.php` 承接；平台交易自动撮合继续使用 `BCTOrder::autoMatchPlatformOrder()`（不受影响）。
