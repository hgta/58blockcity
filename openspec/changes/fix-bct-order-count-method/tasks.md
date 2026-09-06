## 1. 代码修复

- [x] 1.1 在 `classes/BCTOrder.php` 中新增 `getUserOrderCount($userId, $type, $status = 'all')` 方法，复用 `getUserOrders()` 的 `WHERE` 过滤逻辑统计订单总数。
- [x] 1.2 确认 `bct/user/orders.php` 第 16 行调用 `$order->getUserOrderCount($userId, $type)` 不再报错。

## 2. 验证与部署

- [ ] 2.1 访问 `https://bct.58.tl/user/orders.php?type=buy` 确认页面不再出现 Fatal error，正常展示购买订单列表与分页。
- [ ] 2.2 切换 `type=all` 和 `type=sell`，确认各类型下分页总数正确。
- [ ] 2.3 运行 lint 检查改动文件（`classes/BCTOrder.php`）。
- [ ] 2.4 提交并推送代码。
