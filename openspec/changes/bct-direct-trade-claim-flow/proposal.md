# Proposal: bct-direct-trade-claim-flow

## Why

BCT 市场的直接交易订单发布后没有任何成交路径：撮合逻辑只处理 `trade_type='platform'` 的订单，直接/中介交易单只能挂到过期为止；挂单大厅的「交易」按钮跳转到发布新订单页面，无法成交所点击的那一单。用户期望的 OTC 流程（接单 → 线下转账 → 双方确认 → 完成）在系统中完全缺失，`counterparty_id` 字段自建库以来从未被写入。

## What Changes

- **接单（claim）动作**：挂单大厅中直接交易单的「交易」按钮改为「接单」——点击后订单进入"交易中"（意向锁），记录接单人，其他用户不可再接、不再看到联系方式
- **双向确认完成**：买方在个人中心点「确认已付款」→ 卖方点「确认已收款」→ 订单完成；全程线下转账，平台不划转余额、不托管资金，仅记录状态与交易留证
- **放弃/释放机制**：完成前任一方可放弃交易，订单回到"在挂"状态重新开放接单；接单后 24h 买方未确认付款则自动释放
- **接单历史表**：新增 `bct_order_claims` 流水表，保留每次接单/放弃记录（为后续信用体系打底）
- **通知联动**：接单、买方确认、完成、放弃/超时释放各环节向对方发送站内信
- ****BREAKING** 废弃孤儿 API**：删除 `bct/api/execute_trade.php`（订单-订单撮合语义，全站无调用方），由新的 `bct/api/trade_flow.php`（claim / buyer_confirm / seller_confirm / abandon）替代
- 仅支持**直接交易单**且**整单成交**（无部分成交）；平台交易撮合与中介交易流程不在本次范围内

## Capabilities

### New Capabilities
- `bct/trading/claim-flow`: 直接交易订单的接单-确认-完成状态机，包括意向锁与联系方式可见性控制、双向确认、放弃与超时释放、交易留证与站内信通知

### Modified Capabilities

（无——现有 specs 中没有覆盖交易流程的能力；挂单大厅为近期新增，尚未建立 spec，本变更不修改既有 spec 的 requirement）

## Impact

- **数据库**：新增 `bct_order_claims` 表；`bct_orders.status` 复用现有枚举（pending/processing/completed），`counterparty_id` 开始被真实写入；无需改枚举
- **后端**：新增 `bct/api/trade_flow.php`（登录断言 + CSRF + 当事人权限校验）；`BCTOrder` 新增接单/确认/放弃相关方法；删除 `bct/api/execute_trade.php`
- **前端**：`bct/orders.php` 挂单大厅（交易按钮→接单、交易中标记、联系方式可见性规则）；`bct/user/dashboard.php` 新增"进行中的交易"区块（确认/放弃按钮、对方联系方式）
- **定时任务**：超时释放逻辑挂入 `bct/cron/`（与现有 expire_orders.php 同类机制）
- **数据影响**：完成的交易写入 `bct_transactions`（手续费 0），将进入"最新成交"与 24h 成交统计——线下成交价开始参与行情展示
- **不做的事**：不划转 `user_bct_account` 余额、不做资金托管、不处理部分成交、不动平台撮合（`autoMatchPlatformOrder`）与中介协调逻辑
