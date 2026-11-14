## Context

- `Auction::updateAuction()`（classes/Auction.php:812）现状：仅 `status='pending'` 可编辑；全字段保存，且 `current_price = 新起拍价`（pending 下无人出价，安全）；保存后按 start_time 重算 `pending/active`。
- `bid/create.php` 已有 `?edit=X` 编辑模式（物品不可换已有校验），但表单不感知拍卖状态——pending 时全字段可改，active 时根本进不来（my.php 按钮不显示，服务端也拒绝）。
- `bid/my.php:130` `$canCancel` 要求 pending 或 active 且无人出价——有人出价后卖家只剩「等拍卖走完」。
- 防狙击机制：末 2 分钟出价自动顺延 +2 分钟（最多 10 次），作用于 `end_time`。
- 契约期（sold + payment_status 未了结）：`bid/user/contract.php` 的收款指引读落槌时的 `accept_cities`。
- 项目为纯 PHP 页面体系（无构建链），拍卖站共享 `bid/assets/css/auction.css` 与 `auction.js`。

## Goals / Non-Goals

**Goals:**

- active 拍卖开放安全字段白名单：描述（任意）、接受城市（只增不减）、落槌时间（只延不缩）、底价（只降不升）
- 服务端做方向校验（前端 disable 只是体验，不作为防线）
- 编辑表单按状态渲染锁定字段，操作入口覆盖 active
- pending / 已结束行为与现状完全一致（不回归）

**Non-Goals:**

- 不放开 `start_price` / `bid_increment` / `currency` / 拍品的 active 编辑（公平性风险）
- 不做编辑历史审计（auction_edit_log 之类的表）——若后续有纠纷取证需求再立项
- 不做「修改通知已出价买家」（Notification 基建可用，但本期克制范围；见 Open Questions）
- 不动契约期行为：sold 状态继续全字段锁定

## Decisions

### D1. 状态分叉而非单一函数改造

`updateAuction()` 内部按读取到的 `status` 分两条路径：

```
pending 分支（现状保留）
  校验全字段 → UPDATE 全字段 + current_price=start_price + 重算 status

active 分支（新增）
  校验白名单四字段 + 方向约束 → UPDATE 仅这四列（description/accept_cities/end_time/reserve_price）
  绝不触碰 current_price / status / start_price / bid_increment / currency
```

理由：现 SQL 把 `current_price=?` 与全列写在一个 UPDATE 里（:862-873）。active 分支若复用同一条 UPDATE，等于一键清零最高价与出价成果——这是本 change 最大的坑，必须物理隔离（两条独立 SQL），而不是靠参数规避。

### D2. 方向约束的判定基准 = 数据库现值，而非表单传入值

- accept_cities：`new_ids ⊇ old_ids`（现值 JSON 解析后逐个包含；空数组现值 = 不限城市，此时新值任意合法）
- end_time：`strtotime(new) >= strtotime(old)`
- reserve_price：`new <= old`（且仍 ≥ 起拍价的通用校验保留；若起拍价锁定而底价下调到底价<起拍价，按现有校验拒绝即可，提示文案沿用「底价不能低于起拍价」）
- 违反方向时返回明确错误文案（如「竞拍中的拍卖只能延长落槌时间」），不静默修正

### D3. 编辑入口统一为「编辑拍卖」

my.php 按钮从「仅 pending 显示编辑描述」改为 `pending || active` 显示「编辑拍卖」（描述为空时 pending 仍显示「写描述」以引导补全）。active 与 pending 走同一个 `create.php?edit=X`，由表单按状态渲染。

### D4. create.php 编辑模式的状态感知渲染

- 进入编辑时读取拍卖 `status`：
  - `pending`：全字段可改（现状）
  - `active`：description / accept_cities / end_time / reserve_price 可改；start_price、bid_increment、currency、时间起拍侧、物品选择 disabled + 行内灰字「竞拍中不可修改」
  - 其他状态：重定向回 my.php（不该出现在编辑页）
- 服务端 `updateAuction()` 是真正防线；表单 disable 仅提升体验

### D5. 契约期与已结束状态维持全锁

`accept_cities` 在落槌时固化进契约收款指引（contract.php），中途可变会让买卖双方对不上账；sold/ended/canceled 维持现状拒绝。

### D6. 延长落槌时间与防狙击机制的叠加

手动延长只改 `end_time`，不触碰 `extend_count` / 自动顺延窗口。两者叠加语义自然：卖家延长 30 分钟后，末 2 分钟出价照常再顺延。轮询端 `api/lot.php` 的 `end_ts` 每次实时读取 `end_time`，详情页倒计时 3 秒内自动跟随，无需协议变更。

## Risks / Trade-offs

- [active 分支误触 current_price 导致最高价清零] → 两条独立 UPDATE 物理隔离（D1），并加测试断言：active 编辑后 current_price 与 auction_bids 不变
- [只增不减校验被并发出价窗口绕过] → 校验在同一请求内读库现值比对，active 编辑不涉及 FOR UPDATE 行锁也能接受：accept_cities 只在落槌后使用，中途快照无资金影响
- [卖家滥用「延长落槌」反复拖延消耗买家耐心] → 本期不设延长次数上限（防狙击已有 10 次上限兜底自动化）；观察实际使用，若被滥用再加「每场最多手动延长 N 次」
- [表单 disable 与服务端校验不一致造成「为什么改不了」的困惑] → disabled 控件旁给固定说明文案，错误提示文案与约束一一对应（D2）
- [pending 分支 current_price=start_price 的既有语义被误改] → pending 分支代码路径保持原样，不重构合并

## Migration Plan

纯代码变更，无 schema 变更、无数据迁移。部署顺序无依赖（前后端同仓同发布）。回滚 = 回退该次发布即可，旧数据完全兼容。

## Open Questions

- active 时修改 `end_time` / `accept_cities` 是否要通知已出价买家（`Notification::sendSystemNotify` 现成）？倾向二期再加，避免本期膨胀。
- 「手动延长次数上限」是否需要？默认不加，依赖观察。
