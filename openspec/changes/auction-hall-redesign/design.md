## Context

动机见 `proposal.md - Why`。设计前已确认的现状约束：

- 拍卖站为纯 PHP 页面，无构建链、无常驻进程、无 Redis/WebSocket 基建；每个页面各自内联一段 `<style>`，内容区当前复用 Bootstrap4 + FontAwesome5，品牌色 `#ff6b00`。
- `bid/` 页面已通过 `bid/includes/header.php` 引入全站共享导航，主题须只作用于内容区。
- `classes/Auction.php` 的出价/结算为**惰性状态机**：无 cron，靠访问触发 `tick()`；`settle()` 只依据 `end_time` 判定，`placeBid()` 仅写 `auction_bids` 并更新 `current_price/current_bidder_id`。
- `auctions` 表已有 `reserve_price`、`bid_increment`、`start_time`、`end_time`、`status`、`current_price`、`current_bidder_id`、`currency` 等字段；`reserve_price` 目前界面上完全未使用。
- 平台已沉淀通知能力 `Notification::sendSystemNotify($userId, $type, $relatedId, $content, $relatedUrl)`，以及已完成暗色主题先例 `bct-exchange-redesign`（`bid/` 内容区换主题不违背共享导航统一）。
- 现状缺陷：首页出价次数读 `current_bidder_id` 是否存在（恒为 0/1）；`getBids()` 按金额倒序；`getActiveAuctions()` 在循环内逐条查询关联资产（N+1）；倒计时为直接打印的数据库时间字符串。

## Goals / Non-Goals

**Goals:**

- 用最低基建成本（无新服务、无新依赖）把「时间压力 / 竞争感 / 稀缺仪式」三条情绪引擎全部打开。
- 视觉上形成拍卖站独立的「暗场聚光灯」识别度，与 BCT 暗金同族但可区分，且不动共享导航。
- 机制上做到「能打」：自动延时防狙击、真实统计、关注、即时反馈与通知，同时保持 `settle()` 结算契约不变。

**Non-Goals:**

- 不做资金托管（保证金冻结、中标划转、违约没收）——`currency` 仍为标签语义，本次不接支付、不动余额。
- 不做 WebSocket/SSE 实时推流，不引入常驻进程或 cron 结算服务。
- 不做代理出价（proxy bid）与出价上限自动竞价。
- 不重构拍卖站既有鉴权、发布流程与所有权转移逻辑（仅在出价成功路径上追加副作用）。

## Decisions

### 1. 主题实现：子站级 CSS + CSS 变量，作用域限定内容区

新增 `bid/assets/css/auction.css`，以 CSS 自定义属性定义 token（`--bg #0A0B0F`、`--surface #14161C`、`--surface-2 #1C1F27`、`--line rgba(255,255,255,.08)`、`--text #EDEFF5`、`--muted #8A90A0`、`--brand #FFB020` 价值、`--live #FF4D3D` 紧迫、`--lead #2BD97C` 领先），在 `bid/includes/header.php` 引入，容器加作用域类（如 `.auction-shell`）避免污染共享导航。数字统一 `font-variant-numeric: tabular-nums`。

- 为什么不用内联样式：跨 4 个页面 + 新组件，内联无法形成一致 token 纪律，颜色必然滥用。
- 为什么不用 Bootstrap 自带 dark 主题：`bootstrap.min.css` 来自主站且被其他子站共享，全局切换会波及全站。
- 为什么不沿用 `#ff6b00`：橙色是「电商橙」，与暗场舞台不匹配；方向 C 已定「由方向决定」，改用琥珀金表达价值、红色只表达时间压力、绿色只表达领先，形成语义纪律。

### 2. 倒计时：服务端时间基准 + 前端逐秒自走 + 周期对齐

- 服务端渲染时输出每个拍品的结束时刻（ISO 8601）与页面级 `server_now`；前端计算 `offset = server_now - local_now`，倒计时按 `end_at - (local_now + offset)` 逐秒自走。
- 详情页每 3 秒刷新时重新校准 `offset`，消除漂移；首页卡片仅本地自走 + 页面加载时校准。
- 三档状态由剩余秒数纯前端派生（正常 / <3600s 橙 / <300s 红脉冲），避免为配色再请求服务端。

替代方案：纯服务端渲染剩余时间（会整页跳秒、不可接受）；纯前端本地 `Date.now()`（用户改本机时间即穿帮）。

### 3. 自动延时：在出价成功路径推 `end_time`，复用惰性状态机

在 `placeBid()` 成功写入出价后，若「当前时间距 `end_time` 的剩余 ≤ `extend_window_seconds`」且「`extend_count < max_extend_times`」且「累计延长 ≤ `max_extend_seconds`」，则 `UPDATE auctions SET end_time = end_time + extend_seconds, extend_count = extend_count + 1`。

- 关键理由：`tick()`/`settle()` 只看 `end_time`，往后推结束时间**无需修改结算逻辑**，天然兼容惰性状态机。
- 参数默认：结束前 120s 内出价 → 顺延 120s，单场最多 10 次、累计最多 30 分钟；以列存于 `auctions`（本次写入全局默认值），为将来「卖家自定义」预留而不开放 UI。

替代方案：独立延时表 + cron（引入常驻服务，越界）；在 `tick()` 里判延时（无 cron 时无人触发，等于不生效）。

### 4. 计数：冗余计数字段，写入同事务维护 + 可重算兜底

`auctions` 增 `bid_count`、`bidder_count`、`watch_count`，在 `placeBid()` 事务内递增（首口出价才 `bidder_count+1`，依据是否已存在该 `bidder_id` 的记录判定）。

- 理由：首页卡片墙、即将结束滑轨、每 3 秒轮询都消费这些数字；冗余列把「N 次聚合」降为「读一行」。
- 代价与兜底：存在计数漂移风险，因此提供一条重算 SQL（按 `auction_bids` 分组回写），并保留「计数为 0 但存在出价记录」时的回退聚合逻辑。
- 替代方案：每次都 `COUNT(*)`/`GROUP BY`（无冗余风险，但首页 + 轮询叠加的聚合开销随热度线性上涨）。

### 5. 关注：独立表 `auction_watches(auction_id, user_id)` + 唯一约束 + 计数列

- 唯一键保证同一用户对同一拍品不重复计数；关注/取关在同一事务内增删记录并同步 `watch_count`。
- 为什么用「关注数」而非「浏览计数」：主动关注更真实、天然防刷，且能给用户自己的列表提供用处。

### 6. 实时性：详情页 3 秒 AJAX 轮询，纯增量 JSON

新增只读端点返回 `{ server_now, current_price, bid_count, bidder_count, watch_count, end_at, extend_count, my_state, recent_bids[] }`。前端仅替换变化节点，不整页刷新；`document.hidden` 时暂停轮询，重新可见时立即拉取一次。

- 理由：无新基建、延迟可接受（~3s）、实现与排障成本最低。
- 替代方案：SSE（PHP-FPM 长连接占用 worker，需服务器评估）；WebSocket（需常驻进程，越界）。

### 7. 叫价流排序与统计查询

- `getBids()` 排序由「金额倒序」改为「时间倒序」。**兼容性核对**：需扫描既有调用方（`view.php`、`my.php`、管理端）确认无依赖金额序的展示；若存在，改为显式参数区分。
- 新增出价统计查询，以一条 SQL 同时取「出价条数 + 去重竞拍人数」，供首次渲染与重算兜底。
- `getActiveAuctions()` 修复 N+1：先取拍品列表，再用 `IN (...)` 批量取 `blocks` / `nft_city_user` 关联信息并在内存映射。

### 8. 「被超越」通知：靠 `prev_bidder_id` 精确定位收件人

`auction_bids` 增 `prev_bidder_id`（本次出价前的领先者）。出价成功后，若 `prev_bidder_id` 存在且「新领先者 ≠ 原领先者」且「原领先者 ≠ 卖家」，则向 `prev_bidder_id` 发送 `auction_outbid` 通知并链接回详情页。

- 这样可自然避免「同一用户自行加价」产生的无意义通知。
- 得标/流拍通知在 `settle()` 之后追加（仅通知，不改结算结果）。

## Risks / Trade-offs

- [冗余计数漂移] → 写入同事务递增 + 保留重算 SQL + 读到 0 但有出价时回退聚合；上线后提供一次性回填与对账。
- [自动延时被滥用/无限延长] → 同时设「单场次数上限」与「累计延长上限」双闸，超限即正常结算。
- [轮询放大数据库压力] → 端点只读、单拍品粒度、页面隐藏即暂停；计数走冗余列避免聚合；必要时把轮询周期从 3s 放宽。
- [`getBids()` 改序影响既有页面] → 先扫描调用方；对确有金额序需求处显式传参，避免隐性回归。
- [暗色主题影响可读性与无障碍] → 文字对比度达标（`--text` 对 `--bg` 高对比）、颜色不做唯一信息载体（状态同时有文案/图标），移动端首屏保留关键信息。
- [迁移在旧数据上新增非空列] → 所有新列带默认值（计数默认 0），迁移后先回填再依赖，避免 `NULL` 参与计数。
- [惰性结算与前端倒计时不一致] → 倒计时归零时前端触发一次刷新，以服务端结算结果为准（如发生顺延则展示新剩余时间）。

## Migration Plan

1. 执行 `init/migrate-auction-v2.sql`：新增列（带默认值）、新增 `auction_watches` 表与唯一键、按 `auction_bids` 回填 `bid_count/bidder_count`。迁移为可重复执行（先判存在再 `ALTER`），不改动已有列语义。
2. 部署后端（`Auction.php` 增量修改、通知与统计方法），此时新列尚未被 UI 使用，可独立验证出价路径与计数正确性。
3. 部署前端（`auction.css` / `auction.js` / 页面重写），逐页核对主题作用域不污染共享导航。
4. 回滚策略：前端可整体回退到原页面；后端改动为「追加副作用」，回退代码后新增列闲置不影响旧逻辑；数据层新增列/表为非破坏性，无需回滚。

## Open Questions

- 首页默认排序维度是「热拍中」还是「即将结束」（影响首屏观感，不改变能力契约）。
- 全场主推位的选取规则（临近结束优先 / 热度优先 / 卖家置顶）——先按「进行中且出价最多」实现，后续可调。
- 关注数是否在「即将结束」时触发提醒（本次仅记录关注，不推到站提醒）。
- 加价幅度 `bid_increment` 为空或为 0 时，快捷加价「一档/五档」的兜底档位取值。
