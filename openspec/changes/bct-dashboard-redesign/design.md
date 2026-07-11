# BCT Dashboard 重新设计 — 技术设计

## 一、数据库

```sql
ALTER TABLE bct_orders ADD expires_at DATETIME NULL COMMENT '过期时间';
ALTER TABLE bct_orders ADD INDEX idx_expires (expires_at);
```

## 二、BCTOrder 类修改

### 2.1 createOrder() — 新增 duration

```php
// 新增参数 $durationDays (0=永久)
public function createOrder($userId, $city, $type, $amount, $tradeType, 
                             $durationDays = 0, $contactInfo = null, $userPrice = null)

// expires_at 计算
$expiresAt = $durationDays > 0 
    ? date('Y-m-d H:i:s', strtotime("+{$durationDays} days"))
    : null;

// 卖单：直接扣余额（去除冻结）
if ($type == 'sell') {
    if (!$userAccount || $userAccount['balance'] < $amount) {
        throw new Exception("余额不足");
    }
    $account->updateBalance($userId, $city, -$amount, false); // 真扣
}

// INSERT 加 expires_at 字段
INSERT INTO bct_orders (..., expires_at) VALUES (..., ?)
```

### 2.2 新增 cancelOrder()

```php
public function cancelOrder($orderId, $userId) {
    // 查订单
    SELECT * FROM bct_orders WHERE id=? AND user_id=? AND status IN ('pending','processing')
    
    // 卖单：退回余额
    if ($order['type'] == 'sell') {
        $account->updateBalance($userId, $order['city'], $order['amount'], false);
    }
    
    // 改状态
    UPDATE bct_orders SET status='canceled' WHERE id=?
}
```

### 2.3 getUserOrders() — 加分页和 expires_at

```php
// 加 $status 参数筛选
public function getUserOrders($userId, $type='all', $status='all', $page=1, $perPage=15)
// 返回 list + total
```

## 三、dashboard.php 重写

### 布局

```
┌────────────────────────────────────────────────┐
│ 👤 欢迎      [买入BCT] [卖出BCT] [账户设置]    │
├────────────────────────────────────────────────┤
│ ┌──────┐ ┌──────┐ ┌──────┐ ┌──────┐          │
│ │持有   │ │买入中│ │卖出中│ │累计成交│         │
│ │3城   │ │2笔  │ │1笔  │ │15笔   │          │
│ └──────┘ └──────┘ └──────┘ └──────┘          │
├────────────────────────────────────────────────┤
│ BCT资产 表格                                   │
├────────────────────────────────────────────────┤
│ [买入订单] [卖出订单] [已完成]  tabs            │
│ 订单表格 + 分页                                │
└────────────────────────────────────────────────┘
```

### 订单表格列

| 列 | 说明 |
|----|------|
| 订单号 | 截断显示 |
| 城市 | city |
| 数量 | amount |
| 价格 | price |
| 剩余时间 | 倒计时或"已过期"/"永久有效" |
| 状态 | pending/processing/completed/canceled/expired |
| 操作 | pending→取消; completed→查看; expired→删除 |

### 有效期选项

```php
$durationOptions = [
    7   => '7天',
    15  => '15天',
    30  => '30天',
    60  => '60天',
    90  => '3个月',
    0   => '永久有效',
];
```

### 删除 POST

通过 POST action 处理取消/删除：
```php
if ($_POST['action'] === 'cancel' && $_POST['order_id']) {
    $order->cancelOrder($_POST['order_id'], $userId);
}
```

## 四、文件变更

| 操作 | 文件 | 说明 |
|------|------|------|
| SQL | `bct_orders` 表 | 加 expires_at |
| 修改 | `classes/BCTOrder.php` | createOrder+cancelOrder+分页 |
| 重写 | `bct/user/dashboard.php` | 全页 |
