## Purpose

让 BCT 交易子站的用户能够在「我的订单」页面按购买/出售类型筛选订单，并通过正确的总数统计实现分页浏览。

## ADDED Requirements

### Requirement: 订单列表页按类型筛选并分页展示
系统 SHALL 允许已登录用户查看自己的 BCT 订单列表，并支持按「全部 / 购买 / 出售」类型筛选；列表按创建时间倒序排列，并提供分页控件。

#### Scenario: 用户查看购买订单列表
- **WHEN** 已登录用户访问 `bct/user/orders.php?type=buy`
- **THEN** 页面展示该用户的购买订单列表
- **AND** 页面底部生成分页控件，总页数基于购买订单总数计算

### Requirement: 订单总数统计不引发 Fatal error
系统 SHALL 提供与 `getUserOrders()` 过滤逻辑一致的订单总数统计能力，用于分页计算；调用该方法时不得抛出 `Call to undefined method` 致命错误。

#### Scenario: 用户切换订单类型
- **WHEN** 用户在「全部 / 购买 / 出售」类型之间切换
- **THEN** 每次切换后系统都能正确统计该类型下的订单总数
- **AND** 分页控件随总数变化而更新
