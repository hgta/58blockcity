# nft-batch-claim 规格

## ADDED Requirements

### Requirement: 批量编号解析
系统 SHALL 从用户提交的自由文本中提取 NFT 头像编号（两字母+两数字，如 `ML24`），大小写不敏感、大写归一化、去重且保持首次出现顺序，单批上限 50 个（超出部分忽略并在结果中提示）。

#### Scenario: 从混乱文本中提取编号
- **WHEN** 用户粘贴文本 `ML24 使用 出售 FX24 使用 出售 KQ63 在用`
- **THEN** 系统解析出编号列表 `ML24, FX24, KQ63`，噪音词"使用/出售/在用"被忽略

#### Scenario: 大小写与重复
- **WHEN** 用户粘贴 `ml24 fx24 ML24`
- **THEN** 系统解析出 `ML24, FX24`，重复的 `ML24` 只保留首次出现并标记为"重复输入已合并"

#### Scenario: 超出单批上限
- **WHEN** 解析出的去重编号超过 50 个
- **THEN** 系统 SHALL 只处理前 50 个，并在预览结果中明确提示被忽略的数量

### Requirement: 预校验 dry-run
系统 SHALL 提供不落库的预校验接口：对每个解析出的编号，在选定城市下返回状态之一——可认领 / 已被他人认领（含占用者与区块信息）/ 编号不存在 / 编号歧义（多行匹配）。dry-run MUST NOT 产生任何写操作。

#### Scenario: 全部可认领
- **WHEN** 提交的编号均存在且在目标城市无 `is_current=1` 认领记录
- **THEN** 预览结果对每个编号返回"可认领"

#### Scenario: 已被他人认领
- **WHEN** 某编号在该城市已存在 `is_current=1` 且 `user_id` 非当前用户
- **THEN** 该编号返回"已被认领"，附带认领者用户名与区块 ID；不阻止其余编号继续预览

#### Scenario: 编号不存在或歧义
- **WHEN** 某编号在 `nft_avatars` 中无匹配或多行匹配
- **THEN** 分别返回"编号不存在"或"编号歧义"，且执行阶段 MUST 跳过这些编号

### Requirement: 批量执行与结果明细
系统 SHALL 在用户确认后逐条执行认领：每条独立事务，事务内行锁复查占用后再写入；未被预览判定为可认领的编号 MUST NOT 被执行。执行结果 SHALL 返回逐条明细（成功/跳过/失败及原因）与汇总（N 成功 / M 跳过 / K 失败）。

#### Scenario: 部分成功
- **WHEN** 提交 5 个编号，其中 1 个在预览后被他人抢先认领
- **THEN** 其余 4 个成功认领，被抢的 1 个标记为"跳过（已被认领）"，绝不覆盖他人认领记录

#### Scenario: 事务原子性
- **WHEN** 某条认领写入失败（如数据库异常）
- **THEN** 仅该条失败回滚，其余编号不受影响

#### Scenario: 抢先占用兜底
- **WHEN** 执行时行锁内复查发现该 NFT+城市已被 `is_current=1` 占用
- **THEN** 该条标记"跳过"，事务回滚且不产生写入

### Requirement: 认领竞态防护
认领写入（单个与批量）SHALL 在事务内通过 `SELECT ... FOR UPDATE` 行锁 + 服务端占用复查保证同一 NFT+城市不会出现两行 `is_current=1`。单认领入口 `claim_detail.php` MUST 使用同样的防护。

#### Scenario: 并发认领同一编号
- **WHEN** 两个用户同时提交认领同一 NFT+城市
- **THEN** 仅一方成功，另一方得到"已被认领"结果，数据库中只有一行 `is_current=1`

### Requirement: 防刷限制
批量认领接口 MUST 要求登录态，且 MUST 强制：单批编号数 ≤ 50；同一用户当日（按服务器日期）认领写入总数 ≤ 2000。达到限制时 SHALL 返回明确错误提示（含次日可重试说明），且不产生部分写入。

#### Scenario: 未登录访问
- **WHEN** 未登录用户调用预览或执行接口
- **THEN** 返回登录态错误，不执行任何校验或写入

#### Scenario: 超出每日总量
- **WHEN** 用户当日已认领写入数达到 2000
- **THEN** 新的批量执行请求被拒绝并提示"今日认领已达上限，请明日再试"；预览（dry-run）不受影响仍可用

### Requirement: 批量认领入口
`nft/claim_list.php` SHALL 提供"批量认领"入口链接到 `nft/claim_batch.php`；批量页 SHALL 复用现有城市下拉与用户区块 datalist 组件，区块不存在时沿用自动创建链路（批量执行前先确保区块存在一次）。

#### Scenario: 进入批量页
- **WHEN** 用户从 claim_list 点击"批量认领"
- **THEN** 批量页展示城市选择、区块选择（含已有区块提示）、编号粘贴文本框

#### Scenario: 区块自动创建
- **WHEN** 用户输入的区块 ID 在该城市不存在
- **THEN** 执行前按现有 `determineZoneByBlockId → calculateBasePrice → createBlock` 链路创建一次，随后全部编号挂到该区块
