## Context

见 `proposal.md - Why`。当前关键事实：

```
bct/user/dashboard.php
  持仓表 / 顶部卡片 / 饼图  ←  user_bct_account(user_id, city, balance, frozen)
                              × cities.bct_current_price
  只读

user_city_popularity(user_id, city, popularity)   ← 人气值正文（PK: user_id+city）
  ├── nft/nft/buy.php          GREATEST(popularity - price, 0)，卖方 +
  ├── classes/TaskClaim.php    雇主 → 接单人 transferPopularity
  └── classes/Transaction.php  人气值计价 NFT 的 transferPopularity（另有 legacy 的 users.popularity 扣减）

blocks(city_id, owner_id, status)   ← 所有权；merged_blocks 的组内单块在认领时已写入 blocks 并置 sold
cities.bct_current_price            ← 城市单价（单一事实源）
```

`classes/UserPopularity.php` 已提供 `getUserPopularity` / `updateUserPopularity` / `transferPopularity`；无「按用户列出全部城市」的批量读方法。

## Goals / Non-Goals

**Goals:**

- 用户面板「我的持仓」可编辑、可持久化，并统一整页到人气值持仓口径
- 站内人气值消费/结算停止真实扣减与划转，仅保留余额校验与记录

**Non-Goals:**

- 不修改 `user_bct_account` 与 BCT 交易所（挂单/撮合/`bct_orders`）的行为
- 不修改 `cities.bct_current_price` 定价与管理员单价管理
- 不清理历史数据，不新增数据库表
- 不改动 `nft` 列表页、`task` 列表页等纯展示读逻辑

## Decisions

### 1. 数据落点：直接写 `user_city_popularity`，不新建持仓表

用户明确「人气值本来就该用户自己管」，故面板写入即该表本身，保持单一数据源，避免出现「展示用持仓」与「真实人气值」两套数字。

- 备选（已否决）：新建 `user_city_holdings(user_id, city, quantity)` 解耦表。会让同一城市出现两个数字，需要额外同步规则。
- 代价：面板的写入会影响 `rank`、NFT/任务等读取该表的展示。已与用户确认接受（站外 blockcity.vip 才是真实交易场）。

### 2. 城市集合来源：`blocks` 单一表

```sql
SELECT DISTINCT c.id, c.name, c.bct_current_price
FROM blocks b JOIN cities c ON b.city_id = c.id
WHERE b.owner_id = ? AND b.status = 'sold'
ORDER BY c.name
```

不查 `merged_blocks`：`classes/Block.php::countUserBlocksByCity` 的注释已说明合并块在认领时会把组内每个单块写入 `blocks` 并置 `sold`，故 `blocks` 即为完整所有权视图。

### 3. 保存方式：整表一次 POST + 白名单校验

- 表单一次提交全部城市（用户已确认，不做行内 AJAX）
- 服务端以「该用户持区块城市集合」为白名单，仅处理白名单内的 `city` 键
- 数量解析：空串 → `0`；非负整数 → 原值；负数/非整数 → 记为该行非法，提示并不写入该行
- 写入：单事务内对每个城市 `INSERT ... ON DUPLICATE KEY UPDATE popularity = VALUES(popularity)`
- 复用 `user_city_popularity` 的 `(user_id, city)` 唯一键保证 upsert 幂等

### 4. 页面口径：全页统一人气值（用户已确认）

- 顶部卡片：总资产额（Σ 数量×单价）、持有城市数（持区块城市数）、总人气值（Σ 数量）；去掉「可用余额 / 冻结中」
- 持仓表与右侧分布图同源同口径，饼图按各城市金额占比
- 页面标注一句「人气值为你的自管记录，真实交易以 blockcity.vip 为准」，避免误读为可消费余额

### 5. 停止扣减的改动点

| 位置 | 现状 | 改为 |
|------|------|------|
| `nft/nft/buy.php`（L77–90） | 扣买方、加卖方 | 删除两处写入，保留 L59 余额校验与订单/所有权变更 |
| `classes/TaskClaim.php::settleOnce`（L326–357） | `transferPopularity` 雇主→接单人，不足则回退为 `settling` | 直接推进状态为 `completed`（与现金分支同形），不再划转、不再因余额卡单 |
| `classes/Transaction.php::completeTransaction`（L72–86） | `transferPopularity` | 删除该分支（方法当前无调用方，属 legacy） |
| `classes/Transaction.php::purchaseNft`（L512–516 等） | `users.popularity` 扣减 | 标注/移除该扣减（方法当前无调用方，属 legacy） |

`classes/UserPopularity.php` 的 `transferPopularity` 予以保留（供未来管理员工具/迁移使用），但消费流程不再调用。

### 6. 校验与记录的边界

保留「校验」= 操作前读 `user_city_popularity` 判断是否够；不满足仍拒绝并提示。保留「记录」= 订单/认领的金额、时间、状态字段照常写入。二者都不再回写人气值数值。

## Risks / Trade-offs

- [用户可自设数值 → 排行榜 / NFT 展示口径失真] → 已定性为「用户自管记录」，并在面板显式标注「真实交易以 blockcity.vip 为准」；不引入额外的审核流程
- [历史上因余额不足卡在 `settling` 的认领] → 新逻辑不再校验余额，这些认领在重试/再验收后会直接结算完成
- [合并块 / 大块导致城市集合缺漏] → 依赖 `blocks` 已含组内单块这一既有约定；若城市集合为空但用户实际持有合并块，按 `blocks.is_large_block` 一并核对
- [并发写入同一城市] → 依赖 `(user_id, city)` 唯一键 + `ON DUPLICATE KEY UPDATE`，后写覆盖前写（自管语义下可接受）
- [面板写入污染任务/NFT 的余额判断] → 属于「自管」的预期后果，非缺陷；若后续要恢复可消费语义，需重新评估本变更

## Migration Plan

1. 代码变更不涉及 DDL，无需数据库迁移
2. 部署：上传 `bct/user/dashboard.php`、新增 `classes/UserHoldings.php`、`nft/nft/buy.php`、`classes/TaskClaim.php`、`classes/Transaction.php`
3. 灰度/回滚：均为文件级替换，回滚即还原上述文件
4. 上线后人工核验：面板保存 → 刷新回显一致；完成一次人气值 NFT 购买 → 双方数值不变；完成一次人气值任务结算 → 认领直接 `completed`
