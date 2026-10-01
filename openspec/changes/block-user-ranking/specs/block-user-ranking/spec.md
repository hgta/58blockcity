# Spec Delta: block-user-ranking

## ADDED Requirements

### Requirement: 用户排行榜页面

系统 SHALL 在 block 子站提供独立用户排行榜页面 `block/user-ranking.php`，提供七个排序维度 Tab：区块数（默认）、投票数、城市数、区块总价值、合并组数、挂牌中、求购中。Tab 仅切换排序，所有统计列 SHALL 常显，当前排序维度列 SHALL 高亮。

#### Scenario: 默认访问
- **WHEN** 访问者打开 `block/user-ranking.php`（无参数）
- **THEN** 页面按"区块数"降序展示榜单，"区块数"Tab 为选中态且该列高亮

#### Scenario: 切换排序维度
- **WHEN** 访问者点击"总价值"Tab（`?sort=value`）
- **THEN** 页面按总价值降序重新排序，其余列保持显示，"总价值"列高亮

#### Scenario: 非法排序参数
- **WHEN** 访问者传入未定义的 `?sort` 值
- **THEN** 系统按默认维度"区块数"排序，不报错

### Requirement: 持有维度口径

系统 SHALL 按以下口径计算用户持有维度：区块数采用实际口径（合并组整组计 1 块）；投票数采用拆开口径（合并组子块逐块各计 1）；城市数为去重城市计数；区块总价值为用户全部已认领区块的价格合计；合并组数为用户名下合并组条数。全部维度 SHALL 仅统计 `blocks.status='sold'` 的记录。

#### Scenario: 合并组的两种计数
- **WHEN** 某用户持有 3 个单块和 1 个由 3 个子块组成的合并组
- **THEN** 该用户"区块数"为 4、"投票数"为 6、"合并组数"为 1

#### Scenario: 排除未成交状态
- **WHEN** 某区块状态为 `reserved` 或 `available`
- **THEN** 该区块不计入任何持有维度

### Requirement: 交易维度口径

系统 SHALL 提供"挂牌中"（`block_listings` 中 `seller_id` 为该用户且 `status='listed'` 的计数）与"求购中"（`purchase_requests` 中 `user_id` 为该用户且 `status='active'` 的计数）两个交易维度。

#### Scenario: 挂牌计数
- **WHEN** 某用户有 2 条 `listed` 挂牌和 1 条已取消挂牌
- **THEN** 其"挂牌中"为 2

### Requirement: 参与者过滤与榜单规模

榜单 SHALL 仅包含当前排序维度值大于 0 的用户，并 SHALL 默认纳入全部持有账号（不限 `users.role`、不限 `users.status`），保证管理员/站长的真实持仓同样显示。系统 SHALL 提供显式的隐藏账号名单（`BlockRanking::$hiddenUserIds`）用于屏蔽测试号/系统号，名单为空时不得隐藏任何账号。单榜上限 TOP 50，不足则全显；页面 SHALL 显示实际上榜人数。所有维度 SHALL 使用 RANK() 并列语义（同分同名次、后续名次跳号），并以 `user_id` 升序作为稳定 tie-break。

#### Scenario: 零值不出现
- **WHEN** 某用户未持有任何已认领区块
- **THEN** 该用户不出现在任何持有维度榜单中

#### Scenario: 并列名次
- **WHEN** 两名用户区块数相同并列第 2
- **THEN** 下一名用户名次为 4

#### Scenario: 管理员/站长持仓照常上榜
- **WHEN** 某持有大量已认领区块的账号 `role='admin'`（或站长本人）访问榜单
- **THEN** 该账号按其真实数据出现在榜单中，并能在"我的名次条"看到自己的名次

#### Scenario: 显式隐藏测试账号
- **WHEN** 某个 `users.id` 被加入 `BlockRanking::$hiddenUserIds`
- **THEN** 该账号不出现在任何维度榜单，其登录时页面提示"当前账号未纳入排行统计"，且脚注说明有账号被隐藏

### Requirement: 我的名次

系统 SHALL 为已登录用户在榜单上方显示"我的名次条"：当前维度的名次、数值与距上一名的差值；当登录用户不在榜内时，SHALL 显示"距上榜还差 N"并支持在表格末尾追加该用户行。当登录账号未被纳入统计（被隐藏或不存在）时，SHALL 显示"当前账号未纳入排行统计"提示。未登录用户 SHALL 不显示名次条。

#### Scenario: 榜内定位
- **WHEN** 已登录用户位列当前维度第 3，与第 2 名差 5 块
- **THEN** 名次条显示"第 3 名 · 距上一名差 5"，且表格中该用户行高亮

#### Scenario: 榜外定位
- **WHEN** 已登录用户当前维度值为 0 或名次超过 50
- **THEN** 名次条显示"距上榜还差 N"，并在表格末尾追加其行

### Requirement: 城市榜互切入口

`block/top200city.php` SHALL 提供指向用户排行榜的入口，`block/user-ranking.php` SHALL 提供指向城市排行榜的入口。

#### Scenario: 双向切换
- **WHEN** 访问者在城市榜点击"用户排行"
- **THEN** 跳转到 `block/user-ranking.php`；且该页面可一键返回城市榜

### Requirement: 搜索引擎收录

`block/sitemap.php` SHALL 收录用户排行榜 URL，`block/llms.txt` SHALL 包含该页链接；页面 canonical 行为 SHALL 与 `block/top200city.php` 对 `?sort=` 参数的现有处理保持一致。

#### Scenario: sitemap 收录
- **WHEN** 生成 block 子站 sitemap
- **THEN** `user-ranking.php` 作为榜单页条目出现

### Requirement: 口径透明说明

页面 SHALL 以脚注形式说明统计口径，至少包含：仅统计已认领（sold）区块；合并组按 1 块计（投票数例外）；全体持有账号均参与排名（含管理员），若配置了隐藏名单则说明有账号未纳入。

#### Scenario: 口径脚注展示
- **WHEN** 访问者打开用户排行榜任意维度
- **THEN** 页面底部展示口径说明，明确"区块数"与"投票数"的口径差异
