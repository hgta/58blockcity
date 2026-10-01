# Design: bct-direct-trade-claim-flow

## Context

当前 BCT 市场（`bct/`）的订单模型：

- `bct_orders`：`type`(buy/sell)、`trade_type`(platform/direct/mediator)、`status`(pending/processing/completed/canceled/expired)、`counterparty_id`（预留未用）、`contact_info`（直接交易单的联系方式）
- 三种交易方式中只有**平台交易**闭环：发布后 `autoMatchPlatformOrder()` 系统内撮合并划转 `user_bct_account` 余额
- **直接交易单**：发布后无任何成交路径，撮合只挑 `trade_type='platform'`，只能挂到过期；`bct/api/execute_trade.php` 是历史遗留的订单-订单撮合 API，全站无调用方
- 挂单大厅（`bct/orders.php`，近期新增）展示挂单明细与联系方式，但「交易」按钮只是跳转 `trade.php` 发布新单
- 站内信系统现成（`classes/Message.php`，`Message::send(from, to, text)`）
- 现有 cron：`bct/cron/expire_orders.php`（超期订单置 expired）

业务定位约束（已与需求方确认）：
- 平台**不托管资金、不划转余额**：交易完全线下进行（微信/支付宝等），平台只做状态机与留证
- 人气值（BCT）持仓是用户自管记录，"真实交易以 blockcity.vip 为准"
- 无部分成交，只有整单接单

## Goals / Non-Goals

**Goals:**
- G1 直接交易单可被接单（意向锁）：接单后进入"交易中"，其他人不可接、看不到联系方式
- G2 双向确认闭环：买方确认已付款 → 卖方确认已收款 → 完成
- G3 完成时写入 `bct_transactions` 留证（手续费 0），进入最新成交/24h 统计
- G4 放弃/超时释放：订单回到"在挂"重新开放；接单历史保留
- G5 每个环节站内信通知对方
- G6 求购单与挂售单**对称支持**：求购单由卖家接，挂售单由买家接；"买方/卖方"角色由订单方向推导

**Non-Goals:**
- 资金托管、平台余额划转（`user_bct_account` 不动）
- 部分成交、按数量拆单
- 平台撮合（`autoMatchPlatformOrder`）与中介协调流程的改动
- 信用体系、评价、申诉仲裁（阶段 2/3，本次仅为其预留 claim 历史数据）
- 中介交易单/平台交易单的接单支持（本次仅 `trade_type='direct'`）

## Decisions

### D1 状态复用现有枚举，不新增 `bct_orders.status` 值

"交易中"直接复用 `processing`（当前语义"部分成交"在直接交易单上从未发生过，不冲突），"在挂"用 `pending`。真相明细放 claim 表，`bct_orders.status` 只作为大厅/列表的聚合展示态。

- 备选：新增枚举值（如 `matched`）→ 需要 ALTER TABLE 改枚举，且现有代码大量 `status IN ('pending','processing')` 判断需要逐一排查，收益低
- 语义冲突说明：`processing` 在平台撮合路径仍是"部分成交"，在接单路径是"交易中"，由 claim 表的存在与否区分，展示层据 claim 状态渲染文案

### D2 新增 `bct_order_claims` 流水表，而不是在 `bct_orders` 上加时间戳字段

放弃可能反复发生（接了→放弃→换人再接），在订单表上加减字段会丢历史，且 claim 表天然是将来信用体系（放弃次数、成交历史）的数据底座。

```sql
CREATE TABLE bct_order_claims (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  order_id      INT NOT NULL,
  buyer_side_user_id  INT NOT NULL,   -- 买方一侧（挂售单=接单人；求购单=挂单人）
  seller_side_user_id INT NOT NULL,   -- 卖方一侧
  status        ENUM('matched','buyer_confirmed','completed','abandoned','released') NOT NULL DEFAULT 'matched',
  buyer_confirmed_at  DATETIME NULL,
  seller_confirmed_at DATETIME NULL,
  finished_at   DATETIME NULL,        -- completed/abandoned/released 的终态时间
  ended_by      INT NULL,             -- 放弃/释放的发起人（released 时为 NULL 表示系统）
  reason        VARCHAR(255) NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_order_active (order_id, status),
  INDEX idx_user (buyer_side_user_id, seller_side_user_id)
) COMMENT='BCT 直接交易接单流水';
```

一条订单同一时刻至多一条非终态 claim，由 `claim` 事务内 `SELECT ... FOR UPDATE` 订单行保证。

### D3 角色由订单方向推导，确认动作绑定"一侧"而非具体人

```
挂售单(sell)：挂单人=卖方一侧，接单人=买方一侧
求购单(buy) ：挂单人=买方一侧，接单人=卖方一侧
```

- `buyer_confirm` 只能由 buyer_side_user_id 发起，`seller_confirm` 只能由 seller_side_user_id 发起
- 这样两种方向的订单共用同一套状态机与文案，无需 if/else 分叉业务逻辑

### D4 单一 API 入口 `bct/api/trade_flow.php`，按 action 分发

```
POST api/trade_flow.php
  action=claim          {order_id}                 接单
  action=buyer_confirm  {order_id}                 买方确认已付款
  action=seller_confirm {order_id}                 卖方确认已收款 → 完成
  action=abandon         {order_id, reason?}        任一方放弃 → 释放回 pending
GET  api/trade_flow.php?action=preview&order_id=X   接单前预览（对方用户名/城市/数量/单价/总价）
```

统一横切面：`assertLogin()`（JSON 模式）+ CSRF 校验 + 当事人权限校验 + 写事务 + 站内信通知。

- 备选：改造孤儿 API `execute_trade.php` → **否**。其核心是"找对方挂单做订单-订单撮合"，语义与接单模型相反；`matchAndExecute()` 服务于撮合模型且属于平台交易路径，保持不动。直接删除 `execute_trade.php`

### D5 确认完成后写 `bct_transactions` 留证（不划余额）

完成时调用 `BCTTransaction::create()` 写一条 `type='trade'`、手续费 0 的记录（from=卖方、to=买方、订单单价与整单数量）。收益：进入"最新成交"、24h 成交量、价格走势——线下真实成交开始参与行情。

明确不做：`UserBCTAccount::transfer()` 不调用。理由：余额是自管参考记录，卖方余额里未必"有"这些币，划转会制造假账。

### D6 联系方式可见性规则

| 订单状态 | 大厅中联系方式 |
|---|---|
| pending | 登录用户可见（现状不变） |
| processing（交易中） | 仅买卖双方可见（经个人中心交易详情），大厅对所有人隐藏，显示"交易中"徽标 |
| completed/其他 | 不展示（现状不变） |

### D7 超时释放：只保护"接单后无人推进"的场景

- 接单后 **24h** 买方未确认付款 → 系统自动释放（claim → released，ended_by=系统），订单回 pending，双方收通知
- **买方已确认付款后绝不自动释放**：钱可能已线下支付，自动释放会让买方失去留证链路。此时只发站内信提醒卖方确认；纠纷处理留给阶段 2 的申诉机制
- 实现挂入现有 cron 体系（新增 `bct/cron/release_stale_claims.php`，与 expire_orders.php 同模式），页面上沿用"惰性推进"策略：大厅/个人中心加载时顺带释放当前用户相关的超时 claim

### D8 站内信通知矩阵（复用 `Message::send`）

| 事件 | 通知 | 文案要点 |
|---|---|---|
| 接单成功 | 挂单人 | 用户 X 接了你的[求购/挂售]单（城市/数量/价格），请线下沟通转账 |
| 买方确认付款 | 卖方 | 对方已确认付款，请核实到账后确认收款 |
| 完成 | 双方 | 交易完成，已计入成交记录 |
| 放弃 | 对方 | 用户 X 放弃了交易，挂单已重新开放 |
| 超时释放 | 双方 | 24 小时未确认付款，交易已自动释放 |

## Risks / Trade-offs

- [意向锁防不了线下"一币多卖"] → 意向锁只约束平台内可见性；缓解：放弃/成交历史公开可见（阶段 2 加"累计放弃 N 次"展示），大宗交易引导走中介
- [卖方收到钱不确认，订单永久卡在 buyer_confirmed] → 站内信重复提醒；不在 v1 自动完成（无托管，自动完成=平台替卖方背书）；阶段 2 提供申诉升级中介通道，claim 历史完整留证
- [双方乱挂价格对倒污染行情] → 成交价进入行情是特性也是风险；缓解留待阶段 2 加偏离度过滤，v1 接受
- [processing 语义双载（部分成交 vs 交易中）] → 展示层根据是否存在活跃 claim 渲染"交易中/部分成交"；`expireOverdueOrders` 对 processing 的过期处理需检查：活跃 claim 存在时跳过过期，避免"交易中"订单被 cron 置 expired
- [删除 execute_trade.php] → 已确认全站无调用（仅归档变更文档引用），风险极低；删除即可，无需兼容期

## Migration Plan

1. 上线顺序：先执行 `bct_order_claims` 建表 SQL（无破坏性）→ 部署后端 API/类改动 → 部署前端页面改动 → 删除 `execute_trade.php`
2. 存量数据：现有 pending 的直接交易单即刻可被接单，无需迁移；`counterparty_id` 历史值恒为 NULL，无脏数据
3. 回滚：新表独立、API 新增、页面按钮回滚即可恢复原状；唯一不可逆项是 `execute_trade.php` 删除（git 可恢复）

## Open Questions

- 超时释放的 24h 是否需要做成可配置（`mediators` 式的管理后台配置）？v1 先硬编码常量
- 站内信文案是否有品牌/客服话术要求？v1 用设计稿文案，上线前可替换
