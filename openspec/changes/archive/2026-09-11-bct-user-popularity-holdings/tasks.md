## 1. 持仓数据层

- [x] 1.1 新增 `classes/UserHoldings.php`：实现「取用户持区块城市（含城市名与 `bct_current_price`）」查询，用真实数据验证返回城市与 `blocks.owner_id/status='sold'` 一致
- [x] 1.2 在同文件实现「按用户批量读人气值」方法，返回 `city => popularity` 映射，验证无记录的城市返回 0
- [x] 1.3 在同文件实现「批量 upsert 人气值」方法：入参为白名单城市 + 数量，单事务 `INSERT ... ON DUPLICATE KEY UPDATE`，用多城市提交验证要么全写入要么全回滚

## 2. 个人面板改造

- [x] 2.1 重写 `bct/user/dashboard.php` 顶部概览：总资产额、持有城市数、总人气值；移除「可用余额 / 冻结中」，验证页面不再出现这两张卡片
- [x] 2.2 将「我的持仓」改为可编辑表单：每行城市 / 数量输入 / 城市单价 / 资产金额 / 占比，验证金额 = 数量 × 单价
- [x] 2.3 实现 POST 保存：白名单校验城市、空值转 0、负数与非整数拒绝并提示，验证提交非持有城市时数据库无写入
- [x] 2.4 将右侧持仓分布饼图切换到（数量 × 单价）口径，验证图例金额与持仓表一致
- [x] 2.5 增加「人气值为自管记录，真实交易以 blockcity.vip 为准」提示，并处理无持区块城市时的空态

## 3. 站内消费停止扣减

- [x] 3.1 修改 `nft/nft/buy.php`：删除买卖双方人气值写入，保留余额校验与订单状态/NFT 所有权变更，验证购买后双方数值不变
- [x] 3.2 修改 `classes/TaskClaim.php::settleOnce`：人气值任务直接推进为 `completed`，不再划转、不再因余额不足卡在 `settling`，验证雇主余额不足时结算仍完成
- [x] 3.3 处理 `classes/Transaction.php`：`completeTransaction` 移除 `transferPopularity` 分支，`purchaseNft` 移除 `users.popularity` 扣减，验证无遗留调用报错
- [x] 3.4 确认 `classes/UserPopularity.php` 保留 `transferPopularity` 但已无消费流程调用，验证全仓搜索仅剩定义处引用
- [x] 3.5（实施中发现并补充）同步任务页与仲裁页中与新口径矛盾的文案：`task/create.php`（赏金说明、结算城市、余额提示）、`task/view.php`（结算说明、认领完成语）、`task/my.php`（结算中提示）、`task/admin/disputes.php`（裁决说明）、`classes/TaskDispute.php`（注释），移除「划转 / 补足」表述

## 4. 验证

- [ ] 4.1 后台管理员自行调整某城市人气值后，用户面板刷新能读到最新值
- [ ] 4.2 端到端联调：面板保存 → 回显 → 参与一次 NFT 购买 → 参与一次任务结算，确认人气值数值始终只由用户自己改动
- [x] 4.3 运行 `openspec validate bct-user-popularity-holdings --strict` 通过，且 `openspec status --change bct-user-popularity-holdings` 可正常输出（tasks 进度 13/15）

## 说明（实施约束）

- 4.1 / 4.2 为需要真实数据库与已登录会话的运行时验证：本机无 PHP/MySQL 运行时，且 `config/database.php` 为占位（生产注入），故只能在部署环境执行，未在本轮勾选。
- 1.x 的 SQL 与 schema 已按 `init/db-init.sql` 静态核对：`user_city_popularity` 主键为 `(user_id, city)`（`db-init.sql:1174`），`blocks(city_id, owner_id, status)`、`cities(name, bct_current_price)` 字段均存在。
