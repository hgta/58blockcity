## Context

`bct/user/orders.php` 使用 `BCTOrder::getUserOrders()` 获取分页列表，并调用 `BCTOrder::getUserOrderCount()` 获取总数用于分页。后者在 `classes/BCTOrder.php` 中未定义，导致访问订单页时直接抛出 Fatal error。

## Goals / Non-Goals

**Goals:**
- 在 `classes/BCTOrder.php` 中新增 `getUserOrderCount()` 方法，与 `getUserOrders()` 的过滤逻辑保持一致。
- 修复 `bct/user/orders.php` 的 Fatal error，使订单列表页可正常访问和分页。

**Non-Goals:**
- 不修改 `bct/user/orders.php` 的调用方式。
- 不新增订单表字段或改变订单业务状态。
- 不复用 `classes/Order.php`，因为 BCT 订单使用独立的 `bct_orders` 表与 `BCTOrder` 类。

## Decisions

1. **在 `BCTOrder` 类中新增 `getUserOrderCount()`**
   - 与 `getUserOrders()` 使用相同的 `WHERE` 条件（user_id、type、status），确保同一筛选条件下列表与总数一致。
   - 方法签名保持与调用方一致：`getUserOrderCount($userId, $type, $status = 'all')`。
   - 替代方案：修改 `bct/user/orders.php` 不再统计总数。选择新增方法，保留分页功能。

2. **status 参数默认值为 `'all'`**
   - 当前调用方只传入 `$type`，新增方法默认统计全部状态，与 `getUserOrders($userId, $type)` 默认行为一致。
   - 未来若需按状态统计，可直接扩展。

## Risks / Trade-offs

- [Risk] 新增方法逻辑与 `getUserOrders()` 不一致，导致分页总数与列表条数不匹配 → Mitigation: 复制同一套 `WHERE` 条件，必要时提取公共辅助方法。
- [Risk] `getUserOrders()` 本身存在参数绑定冗余（`type` 条件直接拼接字符串但仍向 `$params` 追加）→ Mitigation: 本次仅修复缺失方法，不改变 `getUserOrders()` 现有逻辑，避免引入额外回归。
