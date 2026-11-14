## Why

拍卖一旦开拍（`status='active'`），卖家对所有字段完全失去修改能力（`updateAuction()` 仅接受 `pending`），而「取消重发」在有人出价后也被禁止。卖家把拍品描述写错、接受城市选错、落槌时间定在无人时段、底价定得过高时，只能眼睁睁看着拍卖按错误参数走完，商品拍卖还会沉淀一份错误契约。这是 my.php 反复被提及的自救缺口。

## What Changes

- **新增「分级编辑」能力**：`active` 拍卖开放一个字段白名单，且每个字段带方向约束（只增不减/只延不缩/只降不升），保证对已出价买家零不公平：
  - `description`（拍品描述）：任意修改
  - `accept_cities`（接受人气值结算的城市）：只增不减
  - `end_time`（落槌时间）：只延不缩
  - `reserve_price`（底价）：只降不升
- **锁定字段**：`start_price`、`bid_increment`、`currency`、拍品本身（item_type/item_id）在 active 后永久锁定；`sold/ended/canceled` 全字段锁定（现状不变）
- `updateAuction()` 按 `status` 分叉执行：pending 分支保持现有行为（全字段 + `current_price=起拍价` 重置）；active 分支仅更新白名单字段，**绝不触碰 `current_price` / `status` / 出价数据**
- `bid/create.php?edit=X` 编辑模式感知拍卖状态：active 进入时锁定字段渲染为 disabled + 提示「竞拍中不可修改」
- `bid/my.php` 操作区：active 拍卖也显示「编辑拍卖」按钮（替换现在仅 pending 的「编辑描述/写描述」语义，pending 按钮同样升级为「编辑拍卖」）

## Capabilities

### New Capabilities

- `auction-editing`: 拍卖单的卖家编辑规则——按拍卖状态分级（pending 全字段、active 字段白名单+方向约束、已结束全锁），以及服务端方向校验与前端锁定渲染

### Modified Capabilities

（无——现有 specs 不涉及拍卖编辑行为）

## Impact

- `classes/Auction.php`：`updateAuction()` 状态分叉与方向校验（核心改动）
- `bid/create.php`：编辑模式按状态渲染锁定字段
- `bid/my.php`：操作按钮放开至 active
- 不涉及数据库结构变更、API 轮询协议变更；`api/lot.php` 的 `end_ts` 实时读取，延长落槌后倒计时 3 秒内自动跟随
- 不影响 `Auction::cancelAuction()`、契约流程（契约期即 sold 状态，继续全锁——收款城市在落槌时已固化）
