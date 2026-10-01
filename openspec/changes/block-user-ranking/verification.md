# 验证记录（block-user-ranking）

日期：2026-10-01（实施当日）

## G1 价格完整性（blocks.price 历史脏数据）

**本机无生产库（仓库不含 config/database.php），无法跑统计 SQL。**

决策：`BlockRanking` 的"总价值"**不依赖 `blocks.price`**，统一按 `calculateBlockPriceNew()` 权威计价
（与 `top200city.php`「我拥有的」的 `my_value` 及认领写入路径 `classes/Block.php:212,216` 同源），
对历史 0 值脏数据免疫 → 上线前无需 DB 校验即可保证正确。

部署后可选抽查（只读）：
```sql
SELECT COUNT(*) FROM blocks WHERE status='sold' AND (price IS NULL OR price=0);
```
若数量可观，可另行立项做数据修复（与本榜单正确性无关）。

## G2 合并组归属一致性（未完成，需生产库）

无法离线验证。部署前建议在服务器跑只读核查：

```sql
-- 组 owner 与组内子块 owner 不一致的合并组数量（期望为 0）
SELECT COUNT(*) FROM merged_blocks mb
WHERE EXISTS (
  SELECT 1 FROM blocks b
  WHERE b.city_id = mb.city_id AND b.zone = mb.zone
    AND FIND_IN_SET(b.block_number, mb.merged_blocks)
    AND b.owner_id <> mb.owner_id
);
```

实现已做防御：口径基于"用户自身的 sold 行 + 自身合并组"自洽计算，即使存在脏数据也只是
计数精度问题，不会报错（详见 `classes/BlockRanking.php` 头注释）。若上表非 0，需先修数据再信任
「区块数/投票数/合并组」三个 Tab 的名次。

## G3 canonical 对 ?sort= 的行为（已确认）

- 代码：`SeoHelper::canonicalTargetUrl()`（`classes/SeoHelper.php:344-357`）保留 path + query。
- 线上实测：`https://block.58.tl/top200city.php?sort=claimed` 的 canonical 为
  `https://block.58.tl/top200city.php?sort=claimed`（保留参数）。
- 结论：用户榜沿用 `block/includes/header.php` 的既有 canonical 机制即可，各维度为独立 canonical URL，
  与城市榜策略一致。

## 功能测试（对照 spec 场景）

用 SQLite 内存库构造同构表（users/blocks/merged_blocks/block_listings/purchase_requests）跑
`BlockRanking` 全部公开方法，**28 用例全部通过**，覆盖：

- 合并组双口径：3 单块 + 1 组（3 子块）→ blocks=4 / votes=6 / merged=1（spec 场景原样）
- reserved 不计入任何维度
- 零值用户不出现在持有维度榜，但出现在其非零维度（求购）
- RANK() 并列：同分同名次、后续跳号（bob 与 dave 并列第 2，carol 跳到第 4）
- admin / inactive 账号不在榜；`getUserRank` 对被排除用户返回 null
- 挂牌中=listed 计数（canceled 不计）；求购中=active 计数（fulfilled 不计）
- 我的名次：榜内（rank + gap_prev）、榜首（gap_prev=null）、榜外（gap_board）
- 非法/空 `sort` 回退默认维度 blocks

临时测试脚本已删除，不进仓库。`php -l` 全部改动文件通过。

## 实施期决策偏差（相对 design.md）

见 design.md 新增的「实施期修订」一节：聚合改为类内 PHP 完成（千级数据）、名次为 PHP RANK 计算、
页面 title 沿用 header 默认值（与城市榜一致；`block/includes/header.php:8` 无条件赋值会覆盖预置 title，
城市详情页也存在同样现象，建议另行立项修复）。
