# 变更提案：block 子站用户排行榜（block-user-ranking）

## Why

block.58.tl 目前只有「城市排行」（`block/top200city.php`），没有用户维度的排行，是全站唯一缺"人"维度的子站（nft/hufang/mall/model 均有用户榜）。用户在区块城市的核心行为——认领、合并、挂牌、求购——无处比较，持有进度和玩法成就（如合并组）无法被看见，缺少参与激励。数据规模极小（已认领区块 1,366 行、注册用户 105），聚合零压力，可以纯实时 SQL 实现，无需缓存与物化。

## What Changes

- 新增 `block/user-ranking.php` 用户排行榜页面：
  - 七个排序维度 Tab：区块数（实际口径，合并组算 1 块）、投票数（合并组拆开逐块计）、城市数、区块总价值、合并组数、挂牌中、求购中。
  - 默认排序为区块数；所有统计列常显，当前排序维度高亮；仅显示非零参与者，上限 TOP 50。
  - 登录用户显示「我的名次条」：当前维度排名、数值、距上一名差距；榜外显示"距上榜还差 N"。
- 新增 `classes/BlockRanking.php` 数据层，集中用户维度聚合口径（对齐 `NFTRanking`/`MallRanking` 的命名与结构习惯）。
- `block/top200city.php` 与用户榜互加切换入口（城市排行 ↔ 用户排行）。
- `block/sitemap.php`、`block/llms.txt` 收录新页面。
- 前置数据校验（只读）：`blocks.price` 历史脏数据核对、`merged_blocks` 与子块归属一致性核对、`SeoHelper::canonicalTargetUrl()` 对 query 的行为确认。

不在本期范围（V2 候选）：跨子站维度（人气值持仓/BCT 市值/NFT 持仓）、成交额/买入额榜（防刷规则未定）、城市内用户榜、综合名人堂。

## Capabilities

### New Capabilities
- `block-user-ranking`: block 子站用户排行榜——七个持有/流量维度的用户聚合、排序、名次定位（含"我的名次"）与口径规则（sold-only、合并组双口径、账号排除、并列名次）。

### Modified Capabilities
<!-- 无：现有 specs（bct / city-data-sync / popularity）的需求不受影响 -->

## Impact

- **新增文件**：`block/user-ranking.php`、`classes/BlockRanking.php`。
- **修改文件**：`block/top200city.php`（加切换入口）、`block/sitemap.php`、`block/llms.txt`。
- **数据依赖（只读）**：`blocks`、`merged_blocks`、`block_listings`、`purchase_requests`、`users`。不写库、不加表、不加索引（千级行数据，现有 `blocks.owner_id` 索引足够；`purchase_requests.user_id` 无索引但可接受）。
- **不受影响**：认领/合并/交易等写入路径；城市榜现有维度与口径。
- **风险前置**：`blocks.price` 脏数据（历史 0 值）与 `merged_blocks` 归属不一致（拍卖单块转移路径疑似不同步）会影响"区块数/总价值"两个 Tab 的正确性，必须在实施前先跑只读校验并视结果决定修复方案。
