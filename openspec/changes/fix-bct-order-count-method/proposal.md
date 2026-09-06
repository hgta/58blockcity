## Why

访问 `https://bct.58.tl/user/orders.php?type=buy` 时页面抛出 Fatal error：`Call to undefined method BCTOrder::getUserOrderCount()`。`bct/user/orders.php` 第 16 行调用了 `BCTOrder::getUserOrderCount()`，但 `classes/BCTOrder.php` 中未定义该方法，导致订单列表页完全无法访问，影响用户查看 BCT 交易订单。

## What Changes

- 在 `classes/BCTOrder.php` 中新增 `getUserOrderCount($userId, $type, $status)` 方法，复用 `getUserOrders()` 相同的过滤逻辑统计订单总数。
- 方法签名与调用方 `bct/user/orders.php` 保持一致：`getUserOrderCount($userId, $type)`。
- 不改变现有分页、筛选等业务行为，仅修复缺失方法导致的致命错误。

## Capabilities

### New Capabilities
- `bct/order-list`: BCT 订单列表页支持按类型（全部/购买/出售）筛选并分页展示，统计总数用于生成分页。

### Modified Capabilities
- 无

## Impact

- `classes/BCTOrder.php`：新增 `getUserOrderCount()` 方法。
- `bct/user/orders.php`：调用方式不变，修复后即可正常统计并生成分页。
