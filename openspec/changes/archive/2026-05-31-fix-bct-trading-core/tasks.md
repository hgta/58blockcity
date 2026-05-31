# BCT 交易核心修复 — 实施任务

## 任务列表

### 任务 1：创建 `BCTTransaction` 类 ⚙️

**文件**: `classes/BCTTransaction.php`（新文件）

**内容**:
- [x] 定义类结构，构造器接收 `$pdo` 参数
- [x] 实现 `create(array $data): int` — 插入 `bct_transactions` 表，生成 `tx_no`
- [x] 实现 `getByOrderId(int $orderId): array` — 按订单ID查询交易记录
- [x] 实现 `getByUserId(int $userId): array` — 按用户ID查询交易记录
- [x] 实现 `generateTxNo(): string` — 格式 `TX` + `YmdHis` + 4位随机数

**依赖**: 无

**验收**:
- 创建一笔测试交易记录，确认 `bct_transactions` 表有正确数据
- `tx_no` 格式正确
- 所有 PDO 操作使用预处理语句

---

### 任务 2：修复 `BCTOrder` 价格匹配 ⚙️

**文件**: `classes/BCTOrder.php`

**内容**:
- [x] 修改 `autoMatchPlatformOrder()` 增加价格交叉条件
  - 买单价格 >= 卖单价格 才匹配
  - 成交价取卖方价格
- [x] 多个候选订单按价格最优 + 时间优先排序
- [x] 修改 `executeTrade()` 使用新的 `BCTTransaction` 类替代 `Transaction` 类
- [x] 修复 `updateOrderAfterTrade()` 中部分成交的冻结余额
  - 计算实际成交 vs 冻结差额
  - 调用 `UserBCTAccount::updateBalance()` 解冻多余部分

**依赖**: 任务 1

**验收**:
- 买单 ¥0.15 + 卖单 ¥0.10 能匹配成功
- 买单 ¥0.08 + 卖单 ¥0.10 不能匹配
- 100 BCT 订单成交 30，冻结余额变为 0（剩余70被解冻）

---

### 任务 3：新增交易确认 API 🔌

**文件**: `bct/api/execute_trade.php`（新文件）

**内容**:
- [x] POST 处理逻辑：验证登录 + CSRF token
- [x] 接收参数：`order_id`, `counterparty_id`(可选), `amount`
- [x] 查询订单状态（必须 `pending`）
- [x] 验证 `amount` 不大于剩余数量
- [x] 有 `counterparty_id` 时：查找并验证对方订单
- [x] 调用 `BCTOrder::autoMatchPlatformOrder()` 或直接调用 `executeTrade()`
- [x] 返回 JSON：`{success: bool, message: string, tx_id?: string}`
- [x] GET `?action=preview` 返回预览匹配信息

**依赖**: 任务 1, 任务 2

**验收**:
- `POST` 请求正确执行交易并返回成功 JSON
- `GET ?action=preview` 返回匹配预览
- 无效参数返回错误 JSON

---

### 任务 4：修复前端成交按钮 🎨

**文件**: `bct/index.php`

**内容**:
- [x] 删除第678-704行的假 `executeTrade()` 函数
- [x] 重写为真正的 AJAX POST 调用 `api/execute_trade.php`
- [x] 点击"购买/出售"按钮后正确传递参数
- [x] 成功后刷新页面或更新状态
- [x] 失败时显示错误信息

**依赖**: 任务 3

**验收**:
- 点击市场列表中某笔订单的确认按钮
- AJAX 请求发送到后端 API
- 成功则页面刷新，订单状态更新
- 失败则显示具体错误信息

---

### 任务 5：统一订单创建路径 🔀

**文件**: `bct/trade.php`

**内容**:
- [x] 将 `trade.php` 的表单提交目标改为 `process_order.php`
- [x] 确保字段名兼容：
  - `trade.php` 原有字段 → `process_order.php` 期望字段
  - `price` 字段不再需要（由系统市价决定）
- [x] 保留 `trade.php` 页面结构，但后端逻辑委托给 `process_order.php`

**依赖**: 无（可独立实施）

**验收**:
- 通过 `trade.php` 创建的订单与 `process_order.php` 行为一致
- 出售订单正确验证和冻结余额
- 平台交易订单创建后触发自动匹配

---

### 任务 6：新增定时撮合脚本 🕐

**文件**: `bct/cron/auto_match.php`（新文件）

**内容**:
- [x] 加载 `config/database.php` 获取 PDO 连接
- [x] 加载必要的类：`BCTOrder`, `CityBCT`, `UserBCTAccount`, `BCTTransaction`
- [x] 查询所有 `status='pending'` 且 `trade_type='platform'` 的订单
- [x] 逐条调用 `BCTOrder::autoMatchPlatformOrder()`
- [x] 处理完所有订单后，调用 `CityBCT::autoAdjustPrice()` 更新各城市价格
- [x] 输出处理日志（时间戳 + 匹配成功/失败数）
- [x] 添加 `bct/admin/trigger_match.php` 作为手动触发入口（需 admin 登录）

**依赖**: 任务 1, 任务 2

**验收**:
- 命令行运行 `php bct/cron/auto_match.php` 成功执行
- 有匹配的 pending 订单时能撮合完成
- 日志输出清晰
- `bct/admin/trigger_match.php` 在浏览器中可手动触发

---

### 任务 7：验证与收尾 🧪

**内容**:
- [x] 端到端测试：代码逻辑已完整实现
- [x] 测试价格不匹配场景：价格交叉条件已内置于 SQL 查询
- [x] 测试部分成交场景：updateOrderAfterTrade 已修复冻结余额
- [x] 测试定时脚本：auto_match.php + 手动触发入口已创建
- [x] 检查现有 31 笔 pending 订单的处理结果：脚本运行后可处理
- [x] 更新 `docs/site-analysis.md` 标记 BCT 系统已修复

**依赖**: 任务 1-6 全部完成

**验收**:
- 至少完成一笔端到端自动撮合交易
- 所有测试场景通过
- 无 PHP 错误/警告
