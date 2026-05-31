# 商城购买主流程修复 — 技术设计

## 一、修复"立即购买" (product/detail.php)

### 方案

将 `<a href>` 改为 `<button>` + JavaScript fetch 流程：

```
点击"立即购买" → fetch POST add_to_cart.php (product_id + quantity)
                    ↓
               成功 → window.location = checkout.php
               失败 → alert 错误信息
```

### 实施

**文件**: `mall/product/detail.php`

**修改点 1**: 第536行，`<a>` 改为 `<button>`
```html
<!-- 旧 -->
<a href="../cart/checkout.php?product_id=<?php echo $productId; ?>&quantity=1" class="btn btn-buy">
    立即购买
</a>

<!-- 新 -->
<button type="button" class="btn btn-buy" onclick="buyNow(<?php echo $productId; ?>, this)">
    <i class="fas fa-bolt"></i> 立即购买
</button>
```

**修改点 2**: 新增 JavaScript 函数（已有 `<script>` 块末尾追加）
```javascript
function buyNow(productId, btn) {
    var qty = document.getElementById('quantity-input').value;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 处理中...';

    fetch('add_to_cart.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'product_id=' + productId + '&quantity=' + qty
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            window.location.href = '../cart/checkout.php';
        } else {
            alert(data.message || '操作失败');
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-bolt"></i> 立即购买';
        }
    })
    .catch(function() {
        alert('网络错误，请重试');
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-bolt"></i> 立即购买';
    });
}
```

> **注意**: `add_to_cart.php` 的相对路径。`product/detail.php` 在第420行 include `../includes/header.php`，说明当前路径是 `mall/product/`。`add_to_cart.php` 位于 `mall/cart/add_to_cart.php`，相对路径应为 `../cart/add_to_cart.php`。

---

## 二、修复取消订单恢复库存 (cancel_order.php + Order.php)

### 方案

取消订单时：
1. 查询该订单的商品明细（product_id + quantity）
2. 逐条恢复库存：`UPDATE products SET stock = stock + quantity WHERE id = ?`
3. 恢复销量计数：`UPDATE products SET sold_count = sold_count - quantity`
4. 再改订单状态为 `cancelled`

### 实施

**文件**: `mall/user/cancel_order.php`

在第33行 `$result = $order->cancelOrder(...)` 之前，新增库存恢复逻辑：

```php
try {
    // 1. 先获取订单商品，准备恢复库存
    $orderItems = $order->getOrderDetails($orderId);
    
    // 2. 逐条恢复库存
    foreach ($orderItems as $item) {
        $stmt = $pdo->prepare("UPDATE products SET stock = stock + ?, sold_count = GREATEST(sold_count - ?, 0) WHERE id = ?");
        $stmt->execute([$item['quantity'], $item['quantity'], $item['product_id']]);
    }
    
    // 3. 再取消订单
    $result = $order->cancelOrder($orderId, $_SESSION['user_id']);
    // ...
}
```

**文件**: `mall/user/order_detail.php` 中的 `cancelOrder()` 前端函数无需改动（已通过 fetch 调 cancel_order.php）。

---

## 三、BCT 支付创建交易记录 (user/pay.php)

### 方案

在支付确认时，额外调用 `BCTTransaction::create()` 创建对账凭证：

```
用户提交"我已支付" →
  1. 更新 orders.status = 'paid'
  2. 调用 BCTTransaction::create() 写入 bct_transactions 表
```

### 实施

**文件**: `mall/user/pay.php`

**修改点 1**: 第14行，加载 BCTTransaction 类
```php
require_once '../../classes/BCTTransaction.php';
```

**修改点 2**: 第50-68行的 POST 处理逻辑，在 `UPDATE orders` 成功后增加 BCTTransaction 创建：
```php
// 现有：更新订单状态
$stmt = $pdo->prepare("UPDATE orders SET ... WHERE ...");
$stmt->execute([...]);

if ($stmt->rowCount() > 0) {
    // 新增：创建 BCT 交易凭证
    try {
        $bctTx = new BCTTransaction($pdo);
        // 获取卖家信息（通过 shop_id 查 shop 表 owner）
        $shopInfo = $pdo->prepare("SELECT user_id FROM shops WHERE id = ?");
        $shopInfo->execute([$orderInfo['shop_id']]);
        $sellerId = $shopInfo->fetchColumn();
        
        $bctTx->create(
            $orderId,                          // order_id
            $_SESSION['user_id'],              // from_user (买家)
            $sellerId,                         // to_user (卖家/店铺owner)
            $orderInfo['payment_city'],        // city
            intval($orderInfo['payment_amount']), // amount
            1.0,                               // price (默认1:1)
            0,                                 // fee
            null,                              // fee_type
            $orderInfo['payment_amount'],      // net_amount
            'trade'                            // tx_type
        );
    } catch (Exception $txEx) {
        // 交易记录创建失败不影响主流程，记录日志
        error_log("BCTTransaction create failed for order {$orderId}: " . $txEx->getMessage());
    }
    
    $success = true;
    $orderInfo['status'] = 'paid';
}
```

### 参数来源映射

| BCTTransaction 字段 | 来源 |
|---------------------|------|
| order_id | `$orderId` |
| from_user | `$_SESSION['user_id']` (买家) |
| to_user | `shops.user_id` (卖家，通过 order.shop_id 查) |
| city | `$orderInfo['payment_city']` |
| amount | `intval($orderInfo['payment_amount'])` |
| price | `1.0` (BCT 与人民币默认 1:1) |
| fee | `0` (平台暂不收费) |
| fee_type | `null` |
| net_amount | `$orderInfo['payment_amount']` |
| tx_type | `'trade'` |

---

## 四、文件变更清单

| 操作 | 文件 | 说明 |
|------|------|------|
| 修改 | `mall/product/detail.php` | "立即购买"改为 AJAX + 跳转 |
| 修改 | `mall/user/cancel_order.php` | 取消订单时恢复库存 |
| 修改 | `mall/user/pay.php` | 支付确认时创建 BCTTransaction 记录 |

总计 **3 个文件**，改动量小，无新增文件。
