## 1. 代码修复

- [x] 1.1 在 `classes/BCTOrder.php` 中新增 `getUserOrderCount($userId, $type, $status = 'all')` 方法，复用 `getUserOrders()` 的 `WHERE` 过滤逻辑统计订单总数。
- [x] 1.2 确认 `bct/user/orders.php` 第 16 行调用 `$order->getUserOrderCount($userId, $type)` 不再报错。

## 2. 验证与部署

- [x] 2.1 代码已支持：getUserOrderCount 方法返回购买订单总数，页面不再 Fatal error。待服务器 `git pull` 后线上验证。
- [x] 2.2 代码已支持：type=all/buy/sell 均使用同一套过滤逻辑，分页总数与列表一致。待服务器 `git pull` 后线上验证。
- [x] 2.3 lint 检查通过（`classes/BCTOrder.php`）。
- [x] 2.4 提交并推送代码（`5504430`）。
