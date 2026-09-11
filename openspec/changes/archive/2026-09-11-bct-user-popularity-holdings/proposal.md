## Why

`bct/user/dashboard.php` 的「我的持仓」目前是只读的，数据来自 BCT 交易账户 `user_bct_account`；而用户真正想管理的资产是「城市人气值」。真实交易发生在站外 blockcity.vip，58 子站内的人气值只是用户自管的一份记录，站内的「消费」不构成真实流通。

因此需要让用户在自己面板里直接设置每个城市拥有多少人气值，按城市单价折算资产金额并汇总总资产；同时停止站内各子站对人气值的真实扣减。

## What Changes

- 「我的持仓」由只读改为可编辑：列出用户**持有区块的城市**，每行可填写人气值数量，整表一次提交保存
- 每行展示：资产数量（人气值）/ 城市单价（`cities.bct_current_price`）/ 资产金额（数量 × 单价）/ 占比
- 顶部概览与右侧分布图统一到人气值口径：总资产额 = Σ(数量 × 单价)，持有城市数 = 持区块城市数；**移除**「可用余额 / 冻结中」两张 BCT 交易卡片
- 保存写入 `user_city_popularity`（不新建表），仅允许「用户持有区块的城市」，数量为非负整数
- **BREAKING**：人气值计价 NFT 成交、人气值任务结算不再真实扣减 / 划转人气值，仅做余额校验与记录
- 城市来源：`blocks` 中 `owner_id = 当前用户 AND status = 'sold'` 的 DISTINCT 城市（合并块所有权已镜像进 `blocks`，无需再查 `merged_blocks`）

## Capabilities

### New Capabilities

- `bct/user-holdings`: 用户在 bct 个人面板自主登记并维护各城市人气值持仓，按城市单价折算资产金额并汇总总资产，全页以人气值持仓为口径
- `popularity/self-managed-consumption`: 明确人气值在 58 子站内是用户自管记录，站内消费/结算只做余额校验与记录，不产生真实扣减或划转

### Modified Capabilities

（无。现有唯一 spec 为 `bct/admin/city-prices`，本变更不修改其行为。）

## Impact

- 页面：`bct/user/dashboard.php`（持仓区、概览卡片、分布图重写）
- 新增：`classes/UserHoldings.php`（持区块城市集合 + 批量 upsert 人气值），或扩展 `classes/UserPopularity.php`
- 停止扣减的调用点：`nft/nft/buy.php`、`classes/TaskClaim.php::settleOnce`、`classes/Transaction.php::completeTransaction`、`classes/Transaction.php::purchaseNft`（后者为未被调用的遗留方法）
- 数据：`user_city_popularity`（已有表，读写）、`blocks`（只读）、`cities`（只读 `bct_current_price`）
- 无数据库结构变更；`classes/UserPopularity.php` 保留（`transferPopularity` 不再被消费流程调用）
