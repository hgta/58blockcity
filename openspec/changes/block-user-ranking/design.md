# Design：block 子站用户排行榜

## Context

- block.58.tl 现有唯一榜单是「城市排行」`block/top200city.php`（服务端 `?sort=` Tab + `.rank-num r1/r2/r3` 徽章 + 公共 header/footer），无用户维度；nft/hufang/mall/model 子站均已有用户榜，先例分别是 `classes/NFTRanking.php::getTopUsersBy*` / `hufang/rankings/user.php` / `classes/MallRanking.php::getModelRanking`。
- 真实数据规模（线上实测 2026-10-01）：城市 421、已认领区块（`blocks` 表 `status IN ('sold','reserved')`）1,366 行、注册用户 105。`blocks` 行仅在认领时写入、释放时改回 `available` 保留行（`classes/Block.php:216,333`）。**聚合对象为千级行，任何维度都可实时 SQL，无需缓存/物化。**
- 口径现状：全站对「区块数」存在两套口径——实际口径（合并组算 1 块，`Block::getUserActualBlockStats` `classes/Block.php:585`）与投票数口径（合并组拆开逐块计，`block/top200city.php:47`、`classes/Block.php:506` 注释）；两套口径目前埋在「我拥有的」Tab 实现里，从未产品化。
- 已知数据风险：`classes/Block.php:397-402` 被注释的旧 `createBlock()` 写入 `price=0.00`，历史行可能有 0 值；`classes/Auction.php:449-451` 拍卖单块成交只 `UPDATE blocks.owner_id`，疑似不同步 `merged_blocks.owner_id`，可能造成合并组归属漂移。

## Goals / Non-Goals

**Goals:**
- block 子站新增用户排行榜页 `block/user-ranking.php`，七个维度（5 持有 + 2 交易）。
- 把"实际区块数 vs 投票数"两套口径显性化为并列 Tab，同时科普合并玩法。
- 提供登录用户的"我的名次"定位。
- 口径收敛到 `classes/BlockRanking.php`，不再在页面里复制聚合逻辑。

**Non-Goals:**
- 跨子站维度（人气值持仓、BCT 市值、NFT 持仓）——V2。
- 成交额/买入额榜（缺防刷规则，自买自卖可刷）——V2。
- 城市内用户榜（`/city/{pinyin}` 下的本城持有者 Top N）——V2。
- 任何写路径改动、新表、物化表、cron、缓存层。
- 修复 `top200city.php`「我拥有的」Tab 的既有口径复制（新代码不依赖它，重构留给后续）。

## Decisions

### D1 独立页面 + 双向切换入口（而非并入 top200city.php）

- 新增 `block/user-ranking.php`；`top200city.php` 页头加「用户排行」入口，用户榜加「城市排行」入口；导航「排行」仍指向城市榜。
- 备选：并入 `top200city.php` 加一级 Tab——被否，理由：URL 语义自伤（城市榜 URL 放用户榜不利于 SEO）、该文件已 27KB、nft/hufang 先例均为独立页面；且新增文件不依赖 nginx rewrite（block 域名伪静态未部署），上线即生效。

### D2 数据层 `classes/BlockRanking.php`（对齐 NFTRanking/MallRanking 习惯）

- 方法族：`getTopUsers($sort, $limit)`（TOP 列表）、`getUserRank($userId, $sort)`（我的名次）、`getParticipantCount($sort)`。
- 聚合基表：`blocks`（`owner_id`，`status='sold'`）JOIN `users`；`merged_blocks`、`block_listings`、`purchase_requests` 各自按 owner/user 关联。
- 合并组去重用恒等式留在 SQL：`actual_blocks = COUNT(sold行) − Σ(child_count − 1)`，`child_count = CHAR_LENGTH(merged_blocks) − CHAR_LENGTH(REPLACE(merged_blocks,',','')) + 1`；`SUM(price)` 同理天然正确（子块各有价格行）。避免把行捞进 PHP 逐个 `normalizeBlockKey`（`top200city.php:29-73` 的做法不复制）。

### D3 七维度定义（Tab 即 ORDER BY）

| Tab | 列名 | 口径 |
|---|---|---|
| 区块数（默认） | 实际口径 | `COUNT(sold) − Σ(child−1)` |
| 投票数 | 拆开口径 | `COUNT(sold)` 不去重 |
| 城市数 | 版图 | `COUNT(DISTINCT city_id)` |
| 总价值 | 持有市值 | `SUM(blocks.price)`（校验门 G1 通过时；否则回退 PHP 端 `calculateBlockPriceNew()` 逐块计价，千级行可承受） |
| 合并组 | 玩法成就 | `COUNT(merged_blocks by owner)` |
| 挂牌中 | 流量 | `COUNT(block_listings WHERE seller AND status='listed')` |
| 求购中 | 流量 | `COUNT(purchase_requests WHERE user AND status='active')` |

### D4 过滤与名次规则

- 只算 `status='sold'`（排除 `reserved`，与城市榜口径一致）；Tab 维度值为 0 的用户不出现。
- 默认纳入全部持有账号，不做 `role`/`status` 过滤（见下方「上线后修订」）；如需屏蔽测试号，用
  `BlockRanking::setHiddenUserIds([...])` 显式指定。
- 并列名次用 RANK() 语义（同分同名次、后续跳号），tie-break 用 `user_id` 升序保证稳定。
- 列表上限 TOP 50；不足则全显；显示"共 N 位上榜"。

### D5 我的名次条

- 登录后在表格上方显示：当前维度排名、数值、与上一名差值；榜外显示"距上榜还差 N"；表格中"我"的行高亮（榜外时表格末尾追加我的行）。
- 实现复用 `NFTRanking.php:429-516` 的 `ROW_NUMBER() OVER (ORDER BY ...)` 定位模式（MySQL 8 窗口函数，nft 子站已验证可用）。
- 未登录不显示，不引导登录（避免榜单页变成转化页）。

### D6 SEO

- `user-ranking.php` 走 `block/includes/header.php` 的既有 canonical 逻辑（`SeoHelper::canonicalTargetUrl()`），与 `top200city.php` 对 `?sort=` 的处理保持一致，不引入新策略。
- `block/sitemap.php` 在城市榜条目（`:27`）旁加一条 `user-ranking.php`；`block/llms.txt` 同步。
- 页面 title/description 与城市榜同风格（"用户排行 - BlockCity区块市场"）。

### D7 前置校验门（实施第一步，只读）

- G1 价格完整性：`SELECT COUNT(*) FROM blocks WHERE status='sold' AND (price IS NULL OR price=0)`，并抽样与 `calculateBlockPriceNew()` 比对 → 决定 D3 总价值的实现路径。
- G2 合并组一致性：找 `merged_blocks.owner_id` 与组内子块 `blocks.owner_id` 不一致的组 → 若存在，先决定数据修复方案再上线"区块数/投票数"两个 Tab（否则榜是错的）。
- G3 canonical 行为确认：验证 `canonicalTargetUrl()` 是否保留 `?sort=`，与城市榜对齐。

## Risks / Trade-offs

- [G1 发现 `blocks.price` 大量 0 值] → 总价值退回 PHP 计价路径（已设计为可切换），并考虑后续数据修复任务。
- [G2 发现合并组归属漂移] → "区块数/投票数/合并组"三个 Tab 延后，先上"城市数/挂牌/求购"，或同步提供数据修复脚本。
- [挂牌数可被低价垃圾挂牌刷高] → V1 接受（计数维度、无经济回报）；在 design 留防刷升级位（如按挂单价过滤）。
- [榜单稀疏：105 用户、持有者可能仅数十人] → 非零过滤 + "共 N 位上榜" + 我的名次条对冲；不做空态占位假数据。
- [username 即展示名，公开上榜即公开用户名] → 与 nft 用户榜先例一致（直接展示），V1 不做 opt-out/打码；如需可后续加。
- [HTTP 头部/样式不一致风险] → 复用 `block/includes/header.php`/`footer.php` 与城市榜的表格 CSS，不新造组件。

## Migration Plan

- 纯新增页面 + 两处入口链接 + sitemap/llms.txt 各一行；无 DB 变更、无配置变更。
- 部署顺序：跑 G1-G3 校验 → 合并代码 → 上线。
- 回滚：删除 `user-ranking.php`/`BlockRanking.php`，移除入口与 sitemap 条目即可，无残留状态。

## Open Questions

- 城市榜标题「TOP 200 城市 · 共 200 个」与库中 421 城市不一致——本变更不处理，留给站方确认（历史遗留或有意为之）。
- G2 若发现脏数据，修复方案（脚本修复 vs 榜单口径容错）在实施时依据数量级决定。

## 实施期修订（2026-10-01）

- **D2 修订——聚合在类内以 PHP 完成**：原设计倾向单条 SQL + 恒等式去重；实施时因 G1（`blocks.price`
  历史脏数据）无法离线验证，总价值必须走 `calculateBlockPriceNew()` 权威计价（无法下推 SQL），千级
  数据规模下顺带全维度 PHP 聚合（`blocks sold` 约 1.4k 行），性能无虞且与 SQLite 功能测试兼容。
  口径收敛的目标不变（集中在 `classes/BlockRanking.php`，页面零复制）。
- **D5 修订——名次为 PHP RANK 计算**：数据规模（用户百级）下无需 SQL 窗口函数，`rankedList()` 直接
  实现 RANK() 并列语义 + `user_id` tie-break。
- **页面 title 沿用默认**：`block/includes/header.php:8` 无条件赋值会覆盖预置 title，用户榜与城市榜
  一致使用默认标题（`top200city.php` 同样未设置）。`block/city.php:265` 设置的 `SeoHelper::title`
  实际也被覆盖（现存现象），建议另行立项修复 header 的 title 透传。

## 上线后修订（2026-10-01，D4 变更）

现象：榜单上线后，站长本人（`users.role='admin'`，实际持有 505 块）在用户榜上完全不出现，
页面连"我的名次条"都不渲染（`getUserRank()` 返回 null）。

原因：D4 原定的「排除 `role='admin'` 与 `status<>'active'`」把站长自己的账号过滤掉了。当时的
动机是"避免榜首常驻自己人"，但站长的区块是**真实认领**的，排除它反而让榜单与事实矛盾
（"我的区块"页显示 505 块，榜单上却查无此人）。

修订：
- 取消 `role`/`status` 过滤——持有区块是客观事实，管理员与任意状态账号均参与排名。
- 新增显式隐藏名单 `BlockRanking::$hiddenUserIds` + `setHiddenUserIds()`，用于"确实想屏蔽测试号/
  系统号"的场景；名单非空时页面脚注自动说明有账号未纳入。
- 登录账号未被统计时，页面显示"当前账号未纳入排行统计"，避免同类困惑再次发生（静默消失是
  本次问题的放大器）。

遗留口径差异（本次不处理，已在 tasks 记录）：`block/user/blocks.php`「我的区块」的**投票数/覆盖城市/
总价值**统计的是 `blocks` 全部状态（含 `reserved`），而榜单只算 `sold`；`实际拥有区块`两处一致
（均为 sold）。若线上出现个位数差异，来源即此。当前代码库无任何写入 `reserved` 的路径
（仅读取与后台筛选），故实际影响应可忽略。
