## 1. 后端：新增区块计数能力

- [x] 1.1 在 `classes/Block.php` 中新增 `countUserBlocksByCity($userId, $cityId)`：执行 `SELECT COUNT(*) FROM blocks WHERE owner_id = ? AND city_id = ? AND status = 'sold'`，返回整数。验证：对已有测试用户与城市调用，结果与 `getUserBlocksByCity` 返回数组长度一致。
- [x] 1.2 创建 `hufang/circles/ajax-block-count.php`：接收 `city` 参数，校验登录，查找 `city_id`，调用 `Block::countUserBlocksByCity()`，返回 JSON `{success:true, count:int}`；城市不存在或未登录时返回 `{success:false, msg:...}`。验证：直接访问返回非 JSON 或未登录提示，正确传参返回当前用户在该城市的区块数。

## 2. 后端：创建流程兜底

- [x] 2.1 修改 `hufang/circles/create.php` 的 POST 处理逻辑：当 `block_count` 为空或 `< 0` 时，根据 `$_POST['city']` 查找 `city_id`，调用 `Block::countUserBlocksByCity()` 重新计算并覆盖 `block_count`。验证：提交时手动清空 `block_count`，后端仍能成功创建并写入正确数量。
- [x] 2.2 修改 `classes/Circle.php` 的 `create()` 方法注释，说明 `blockCount` 已支持自动计算；保持参数签名不变。验证：现有调用方式不变，单元/集成测试通过。

## 3. 前端：自动回填与只读

- [x] 3.1 修改 `hufang/circles/create.php` 表单：将 `block_count` 输入框添加 `readonly` 属性，并在旁边显示"根据 block 子站认领数自动计算"提示。验证：页面渲染后输入框不可编辑。
- [x] 3.2 在 `hufang/circles/create.php` 页面中添加 JavaScript：监听 `city` 输入框 `change` 事件，AJAX POST 到 `ajax-block-count.php`，成功后回填 `block_count`；切换城市时重新请求。验证：选择不同城市，`block_count` 自动变化为对应数量。

## 4. 测试与验证

- [ ] 4.1 手动测试：登录有认领区块的用户，进入 `hufang/circles/create.php`，选择已认领城市，确认"拥有区块总数"正确回填；提交后数据库 `circles.block_count` 与回填值一致。
- [ ] 4.2 手动测试：切换到一个未认领任何区块的城市，确认"拥有区块总数"变为 0，提交后 `block_count` 为 0。
- [ ] 4.3 lint 检查：运行 `php -l` 检查 `classes/Block.php`、`hufang/circles/create.php`、`hufang/circles/ajax-block-count.php`，确认无语法错误。

## 5. 提交与部署

- [x] 5.1 `git add` 所有改动，提交 `feat(hufang-circles): 创建互访圈时自动根据城市认领区块数计算 block_count`，并 `push origin main`。
- [ ] 5.2 线上 `v.58.tl/circles/create.php` 验证实际用户选择城市后 `block_count` 自动回填。
