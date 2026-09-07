## Why

创建互访圈时，"拥有区块总数"目前依赖用户手动填写，容易填错或与 `block` 子站实际认领数据不一致。应让该字段根据用户选择的所在城市，自动从 `block` 子站已认领区块数计算得出，减少人为错误并保证数据一致。

## What Changes

- `classes/Block.php` 新增 `countUserBlocksByCity($userId, $cityId)`：统计某用户在某城市已认领（`status='sold'`）的区块总数。
- `hufang/circles/create.php` 表单中，"拥有区块总数"输入框改为只读并支持自动填充。
- 选择城市后，前端通过 AJAX 请求新端点获取当前用户在该城市的已认领区块数，回填到输入框。
- 服务端在表单提交时做兜底：若前端未成功回填，则按所选城市名查询 `city_id` 并重新计算，以区块数创建互访圈。
- 更新 `classes/Circle.php` 的创建逻辑文档注释，说明 `blockCount` 已改为自动计算。

## Capabilities

### New Capabilities

- `hufang/circles/block-count-auto-calc`: 根据用户在 block 子站某城市的已认领区块数，自动计算并填充互访圈"拥有区块总数"。

### Modified Capabilities

- `hufang/circles/circle-creation`: "拥有区块总数"字段的输入方式由手动填写改为根据所选城市自动计算；其余字段与创建流程不变。

## Impact

- 前端页面：`hufang/circles/create.php`
- 后端类：`classes/Block.php`、`classes/Circle.php`
- 新增 AJAX 端点：`hufang/circles/ajax-block-count.php`（或类似）
- 数据库表：`blocks`（读取 `owner_id`、`city_id`、`status`）
- 用户体验：创建互访圈时无需手动数数，降低错误率。
