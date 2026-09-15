## Why

发布交易时用户可以指定单价与数量，但**无法指定挂单的有效期**，且订单一旦发布就永久停留在待撮合状态，不会被系统清理。结果是行情列表里长期堆积大量早已无人问津的历史挂单，稀释了真实行情的可读性，也让用户无法表达"我只愿意在近期以这个价格成交"的意图。

代码层面，`bct_orders.expires_at` 字段、索引与 `BCTOrder::createOrder()` 的 `$durationDays` 参数**均已存在**，但全项目没有任何地方传入该参数、也没有任何地方读取该字段——所有订单的 `expires_at` 恒为 `NULL`（永不过期）。本次变更的核心是**把这条已铺好但两端悬空的管道接上**：前端提供有效期选择，后端在订单过期后自动取消。

## What Changes

- 发布交易页（单条模式）新增**有效期选择**，可选：`1天 / 2天 / 7天 / 30天 / 3个月 / 长期`，默认 **30天**。
- 批量模式新增有效期选择，**整批次共用同一有效期**（与方向、交易方式保持一致的整体设置模型）。
- `createOrder()` 接收有效期并写入 `expires_at`：
  - `长期` → `expires_at = NULL`（与 `Task.php` 现有把空值视为"不过期"的约定一致）
  - `3个月` → 按**自然月**计算（`+3 months`），而非固定 90 天
  - 其余 → 按天数计算
- 新增**过期自动取消**，采用**惰性推进 + 定时任务兜底**的双保险：
  - 惰性：用户访问订单列表 / 行情相关页面时，顺带将该用户（或该城市）已过期的订单置为过期状态
  - 定时：`bct/cron/` 下新增过期清理任务，按固定周期批量处理全站过期订单
- 过期范围覆盖 `pending`（未成交）与 `processing`（部分成交）两种状态：过期后剩余未成交数量一并作废，避免半成交订单永久挂起。
- 新增订单状态 **`expired`**，与 `canceled` 区分：
  - `canceled` = 用户主动取消
  - `expired` = 系统因超期自动取消
- 用户订单页与后台交易管理页新增 `expired` 状态的展示与筛选。
- 统一过期时间的**时区口径**：`expires_at` 由 PHP 写入，过期判断也统一以 PHP 时间为准，避免与 MySQL `NOW()` 混用导致错位（`classes/Auction.php` 已有同类时区问题的先例）。

## Capabilities

### New Capabilities

- `bct/order-expiry`: BCT 挂单有效期的选择、写入与过期自动取消，涵盖时长选项语义（天数 / 自然月 / 长期）、过期判定口径、动惰性推进与定时兜底、以及 `expired` 状态的流转。

### Modified Capabilities

- 无。`bct/batch-order-publish` 与 `bct/trade-publish-limits` 的既有需求不变，本次仅在其上叠加"有效期"这一新增维度，因此以新能力承载。

## Impact

- **前端页面**：
  - `bct/trade.php`：单条模式与批量模式各新增有效期选择控件
  - `bct/user/orders.php`：新增 `expired` 状态的展示与筛选
  - `bct/admin/orders.php`：新增 `expired` 状态的筛选与统计
- **后端类**：
  - `classes/BCTOrder.php`：`createOrder()` 有效期解析与写入；新增过期批量处理；
  - 过期推进逻辑需可被页面与定时任务复用
- **新增定时任务**：`bct/cron/expire_orders.php`，并需具备防重入保护（与 `bct/cron/auto_match.php` 的 `flock` 做法一致）。
- **数据库**：`bct_orders.expires_at` 字段与索引**已存在**，无表结构迁移；但 `status` 需扩展 `expired` 枚举值（`ALTER TABLE ... MODIFY status ENUM(...)`）。
- **依赖其它能力**：过期判定依赖 `bct_orders.expires_at` 与 `status`；批量模式的有效期复用 `bct/batch-order-publish` 的整体设置模型。
- **会话与登录状态**：无影响。
- **风险提示**：现有历史订单 `expires_at` 全部为 `NULL`（永久有效）。上线后这些订单**不会**被自动过期，需明确是否要对存量数据做一次性处理。
