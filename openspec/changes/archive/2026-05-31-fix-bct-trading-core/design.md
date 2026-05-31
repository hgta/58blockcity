# BCT 交易核心修复 — 技术设计

## 一、新增 `BCTTransaction` 类

### 位置
`classes/BCTTransaction.php`

### 职责
封装 BCT 交易流水的创建和查询，操作 `bct_transactions` 表。

### 接口

```php
class BCTTransaction {
    // 创建交易流水
    public function create(array $data): int
    // 参数: order_id, from_user, to_user, city, amount, price, fee, fee_type, net_amount, tx_type
    // 返回: 交易ID

    // 按订单ID查询
    public function getByOrderId(int $orderId): array

    // 按用户ID查询
    public function getByUserId(int $userId): array

    // 生成流水号
    private function generateTxNo(): string
    // 格式: TX + YmdHis + 4位随机数
}
```

### 关键实现
- 使用 PDO 预处理语句
- `create()` 的 `tx_type` 枚举值：`'trade'`（撮合交易）、`'transfer'`（用户转账）
- `net_amount = total_amount - fee`

---

## 二、修复市场成交逻辑

### 现状
`bct/index.php` 第678-704行：前端 `executeTrade()` 是假的。

### 方案
在 `bct/index.php` 中添加后端处理逻辑 + 新增 API 端点：

```
POST bct/api/execute_trade.php

请求参数:
  - order_id: int      要成交的订单ID
  - counterparty_id: int?  对手用户ID（可选，用于锁定对方订单）
  - amount: int        成交数量

处理流程:
  1. 验证登录 + CSRF
  2. 查询订单状态（必须 pending）
  3. 验证 amount 不大于订单剩余数量
  4. 如果提供 counterparty_id：
     - 查找该用户同城市、相反方向、挂单数量的订单
     - 验证价格交叉（已修复价格匹配）
  5. 调用 BCTOrder::executeTrade()
  6. 返回 JSON: {success, message, tx_id}

GET bct/api/execute_trade.php?action=preview&order_id=X&counterparty_id=Y
  返回: {match: true/false, match_order_id, match_price, ...}
```

### 前端修改
`index.php` 的 `executeTrade()` 改为真正的 AJAX 调用：

```javascript
function executeTrade(orderId) {
    const data = {
        order_id: orderId,
        amount: $('#tradeAmount').val()
    };
    if ($('#tradeCounterpartyId').val()) {
        data.counterparty_id = $('#tradeCounterpartyId').val();
    }
    $.post('api/execute_trade.php', data, function(res) {
        if (res.success) {
            alert('交易成功！交易流水号：' + res.tx_id);
            location.reload();
        } else {
            alert('交易失败：' + res.message);
        }
    });
}
```

---

## 三、统一订单创建路径

### 方案
废弃 `bct/trade.php` 简化版的直接操作数据库方式，统一使用 `bct/process_order.php` 的完整流程。

### 实施
- `trade.php` 改为：获取 POST 参数 → 302 重定向到 `process_order.php` 并附带参数
- 或将 `trade.php` 的内容替换为 `require 'process_order.php'`
- 确保表单数据兼容（检查 `trade.php` 的字段名与 `process_order.php` 一致）

### 需要兼容的字段
`trade.php` 表单字段 → `process_order.php` 期望字段：
- `city` → `city`
- `type` → `type`
- `amount` → `amount`
- `price` → 不需要（process_order 使用当前市价）
- `trade_type` → `trade_type`
- `contact_info` → `contact_info`
- `mediator_id` → `mediator_id`

> **注意**：两边的 price 来源不同。`trade.php` 允许用户自定价，`process_order.php` 使用城市当前市价。统一后应使用城市市价（由 autoAdjustPrice 维护）。

---

## 四、增加价格匹配条件

### 现状
`BCTOrder::autoMatchPlatformOrder()` 只匹配城市相同 + 方向相反，不考虑价格。

### 方案
增加价格交叉条件：

```php
// 匹配条件:
// 买单价格 >= 卖单价格  → 可以成交
// 成交价格 = 卖单价格（按挂单价格成交）

if ($buyOrder['price'] >= $sellOrder['price']) {
    // 可以匹配
    $matchPrice = $sellOrder['price'];
} else {
    // 价格不交叉，跳过
    continue;
}
```

### 匹配优先级
当有多个可匹配订单时：
1. 先按价格最优排序（买单从高到低，卖单从低到高）
2. 同价格按时间优先（FIFO）

---

## 五、修复部分成交冻结余额

### 现状
`BCTOrder::updateOrderAfterTrade()` 只更新订单数量，不调整冻结余额。

### 方案
在 `updateOrderAfterTrade()` 或 `executeTrade()` 中增加解冻多余余额的逻辑：

```php
// 计算实际成交和冻结差额
$originalAmount = $orderBeforeTrade['amount'];
$tradedAmount = $tradeQty;
$remainingAmount = $originalAmount - $tradedAmount;

if ($remainingAmount > 0) {
    // 解冻未成交部分
    UserBCTAccount::updateBalance($pdo, $sellerId, $city, -$remainingAmount, true);
}
```

---

## 六、新增定时任务

### 脚本
`bct/cron/auto_match.php`（新文件）

### 功能
- 遍历所有 status='pending' 的平台交易订单
- 对每个订单调用 `BCTOrder::autoMatchPlatformOrder()`
- 每条处理完调用 `CityBCT::autoAdjustPrice()`
- 记录处理日志

### 触发方式
Linux Cron：
```
*/5 * * * * php /www/wwwroot/58.tl/bct/cron/auto_match.php >> /var/log/bct_cron.log
```

### 手动触发
管理员可访问 `bct/admin/trigger_match.php` 手动执行（需 admin 权限）。

---

## 七、文件变更清单

| 操作 | 文件 | 说明 |
|------|------|------|
| 新增 | `classes/BCTTransaction.php` | BCT交易流水类 |
| 新增 | `bct/api/execute_trade.php` | 交易确认 API |
| 新增 | `bct/cron/auto_match.php` | 定时撮合脚本 |
| 新增 | `bct/admin/trigger_match.php` | 手动触发入口 |
| 修改 | `classes/BCTOrder.php` | 匹配加价格条件 + 修复冻结余额 |
| 修改 | `classes/CityBCT.php` | 确保 autoAdjustPrice 逻辑正确 |
| 修改 | `bct/index.php` | 前端成交逻辑连接真实 API |
| 修改 | `bct/trade.php` | 重定向到 process_order.php |

---

## 八、测试场景

| 场景 | 预期结果 |
|------|---------|
| 用户 A 卖北京 BCT 100@¥0.10，用户 B 买北京 BCT 100@¥0.10 | 匹配成功，交易完成 |
| 用户 A 卖北京 BCT 100@¥0.15，用户 B 买北京 BCT 100@¥0.10 | 价格不交叉，不匹配 |
| 用户 A 卖北京 BCT 100@¥0.10，用户 B 买广州 BCT 100@¥0.10 | 城市不同，不匹配 |
| 用户 A 卖北京 BCT 100，用户 B 买北京 BCT 50 | 部分成交 50，剩余 50 待成交 |
| 定时脚本运行 | 处理所有 pending 订单，自动撮合 |
