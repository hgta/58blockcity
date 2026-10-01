# Tasks：block-user-ranking

## 1. 前置校验门（只读，决定实现路径）

- [x] 1.1 校验价格完整性：统计 `blocks` 中 `status='sold'` 且 `price` 为 0/NULL 的行数，抽样与 `calculateBlockPriceNew()` 比对，记录结论到本变更目录（决定"总价值"走 SQL SUM 还是 PHP 计价，见 design D3/D7-G1）
  - 结论（verification.md）：本机无生产库，无法统计；总价值直接采用 `calculateBlockPriceNew()` 权威计价路径（对脏数据免疫），DB 抽查降级为部署后可选项
- [ ] 1.2 校验合并组归属一致性：找出 `merged_blocks.owner_id` 与组内子块 `blocks.owner_id` 不一致的组并记录数量；如存在，先与站方确认修复方案（design D7-G2）
  - 遗留：需生产库执行，核查 SQL 已写入 verification.md；代码已做防御性实现（脏数据不报错，仅影响计数精度）
- [x] 1.3 确认 `SeoHelper::canonicalTargetUrl()` 对 `?sort=` 的处理，并核对 `top200city.php` 现状，保证用户榜 canonical 策略一致（design D6/D7-G3）
  - 结论（verification.md）：canonical 保留 query（代码 + 线上实测双重确认），沿用既有机制即可

## 2. 数据层 `classes/BlockRanking.php`

- [x] 2.1 创建类骨架（构造注入 `$pdo`，命名与 `NFTRanking`/`MallRanking` 对齐），定义七维度的排序白名单与列名映射
- [x] 2.2 实现 `getTopUsers($sort, $limit)`（实施期修订：类内 PHP 聚合，见 design「实施期修订」；口径与目标不变）
- [x] 2.3 实现账号过滤：排除 `users.role='admin'`、`users.status` 非 active；维度值非零才参与
- [x] 2.4 实现 RANK() 并列名次（`user_id` 升序 tie-break）与 TOP 50 截断、`getParticipantCount($sort)`
- [x] 2.5 实现 `getUserRank($userId, $sort)`（实施期修订：PHP RANK 计算，同上）
- [x] 2.6 总价值按 `calculateBlockPriceNew()` 逐块计价（任务 1.1 结论直接选定此路径）
- [x] 2.7 用临时脚本核对聚合口径（SQLite 同构库 28 用例全部通过，含合并组双口径与 `Block::normalizeBlockKey` 一致性；脚本已删除）

## 3. 页面 `block/user-ranking.php`

- [x] 3.1 搭建页面骨架：复用 `block/includes/header.php`/`footer.php`，title/description/keywords 与城市榜同风格
  - 注：header.php:8 无条件赋值 title，与城市榜一致沿用默认值（见 design「实施期修订」）
- [x] 3.2 实现七个 Tab（服务端 `?sort=`，白名单校验，非法值回退默认"区块数"），复用城市榜的 Tab 与 `.rank-num r1/r2/r3` 徽章样式
- [x] 3.3 实现统一表格：所有列常显、当前排序维度高亮、行含头像（`User::avatarUrl`）与用户名（htmlspecialchars）、前三名徽章
- [x] 3.4 实现"我的名次条"（登录后：名次/数值/距上一名差值；榜外"距上榜还差 N"并末尾追加我的行、行高亮；未登录不显示）
- [x] 3.5 实现口径脚注：仅统计 sold、合并组按 1 块计（投票数例外）、排除管理员与封禁账号
- [x] 3.6 加"城市排行"切换入口（返回 `top200city.php`）

## 4. 入口与收录

- [x] 4.1 `block/top200city.php` 页头加"用户排行"入口链接
- [x] 4.2 `block/sitemap.php` 在城市榜条目旁收录 `user-ranking.php`
- [x] 4.3 `block/llms.txt` 增加用户排行榜条目

## 5. 验证

- [x] 5.1 对照 spec 场景逐条手工验证：默认排序、切 Tab、非法 sort、合并组双口径（区块数=实际口径/投票数=拆开口径）、reserved 不计入、零值不出现、并列名次跳号（SQLite 用例覆盖，见 verification.md）
- [x] 5.2 验证"我的名次"三种态：榜内高亮/榜外差值提示/未登录隐藏（逻辑三态经 SQLite 用例验证）
- [x] 5.3 验证 admin 与封禁账号不在榜（SQLite 用例）；移动端采用横向滚动表格（`overflow-x:auto`，比城市榜隐藏列更稳妥）
- [x] 5.4 验证 canonical 与城市榜行为一致（线上实测 + 代码确认）；`sitemap.php` 已含新 URL；`php -l` 全部改动文件通过
- [ ] 5.5 部署后线上冒烟：七个维度各访问一次，确认无慢查询（聚合行数千级）且互切入口可用；建议顺带跑 verification.md 中 G2 核查 SQL
