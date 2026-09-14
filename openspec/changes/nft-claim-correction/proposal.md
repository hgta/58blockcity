## Why

注册流程的历史缺陷（`User::register()` 返回布尔值 `true`，被当作 `user_id=1` 使用）导致新注册用户在被修复前的全部操作都记在了 1 号用户名下。已发现实例：用户 390 注册后认领海口虎头 NFT（133.html），实际认领人被记录成 user 1。

代码已修复（提交 `4554850`），但**已产生的脏数据仍留在数据库中**。目前缺乏管理手段：现有申诉模块（`nft_claim_appeals`）只处理用户之间的抢认领，既不能改归属到指定用户，也不能按「注册时间 ≈ 认领时间」这个 bug 指纹批量筛查脏数据。因此需要一个后台稽核台，让运营能定位并订正错误认领。

## What Changes

- 新增 NFT 后台「认领管理」页面，支持按 NFT 编号 / 城市 / 用户名 / 用户ID / 区块ID / 时间区间组合检索认领记录
- 新增「可疑认领」一键筛查：命中「认领时间与该认领用户注册时间接近」的指纹，暴露注册 bug 造成的脏数据
- 新增单条订正：将某条认领的归属用户 A 改为用户 B，填写订正原因
- 新增批量订正：按当前筛选结果或按「来源用户」一次性转移多条认领记录
- 新增订正审计表 `nft_claim_corrections`，记录操作人、时间、原归属、新归属、原因
- 订正采用「新增认领记录 + 原记录 `is_current=0`」的语义，保留历史痕迹，与正常认领流转一致

## Capabilities

### New Capabilities

- `nft-claim-correction`: NFT 后台认领稽核与订正能力，覆盖可疑认领筛查、认领记录检索、单条与批量归属订正、订正审计留痕

### Modified Capabilities

（无。本次不改变现有认领业务的行为契约，仅新增后台管理能力。）

## Impact

- 新增页面：`nft/admin/claims.php`
- 新增数据表：`nft_claim_corrections`（需执行一次 DDL）
- 修改：`nft/admin/includes/admin-menu-config.php`（新增菜单项）、`classes/NFT.php`（新增稽核/订正相关方法）
- 依赖现有：`nft_city_user`、`users`、`nfts`、`cities` 表及 NFT 后台的权限校验与布局
- 不影响前台认领、挂售、求购等既有流程
