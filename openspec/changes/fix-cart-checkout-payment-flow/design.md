# 购物车、结算与支付流程 — 设计方案

## 1. 价格符号统一策略

### 1.1 判断逻辑
- 商品以 `price_bct > 0` 作为 BCT 支付商品的判断标准
- BCT 价格显示格式：`Ⓟ{amount} 人气值`
- 人民币价格显示格式：`¥{amount}`

### 1.2 需修改的页面与位置

| 文件 | 当前显示 | 修改后 |
|------|---------|--------|
| `mall/cart/index.php:310` | `¥price` | `Ⓟprice 人气值` |
| `mall/cart/index.php:323,343` | `¥total` | `Ⓟtotal 人气值` |
| `mall/cart/checkout.php:571` | `¥price` | `Ⓟprice 人气值` |
| `mall/cart/checkout.php:621,633` | `¥total` | `Ⓟtotal 人气值` |
| `mall/user/order_detail.php:152,178` | `¥price` | `Ⓟprice 人气值` |
| `mall/user/pay.php:182,211` | `¥amount` / `BCT` | 统一为 `Ⓟamount 人气值` |

## 2. 购物车按店铺分组与结算

### 2.1 数据结构
```
cartItems: [
  { shop_id: 1, shop_name: "店铺A", items: [...] },
  { shop_id: 2, shop_name: "店铺B", items: [...] }
]
```

### 2.2 交互设计
- 购物车每个商品前增加复选框，用户可选择要结算的商品
- 同店铺商品可一起结算
- 页面底部显示"选中商品总价"和"去结算"按钮
- 结算时只传递选中的 `cart_item_id` 列表

### 2.3 结算页改造
- `checkout.php` 接收 `selected_items` 参数（逗号分隔的 cart_item_id）
- 按 `shop_id` 分组生成多个订单
- 每个订单独立计算金额，独立扣减库存
- 结算完成后清空对应购物车项
- 若只选中一家店铺商品，跳转该订单详情；若多家，跳转订单列表

## 3. 支付超时与库存释放

### 3.1 数据库变更
```sql
ALTER TABLE orders ADD COLUMN expire_at DATETIME DEFAULT NULL AFTER created_at;
```

### 3.2 订单创建时设置过期时间
- 创建订单时：`expire_at = NOW() + INTERVAL 30 MINUTE`
- 订单状态为 `pending` 时展示倒计时

### 3.3 超时检测策略
**方案 A（被动检测）**：每次访问订单详情/支付页时检查 `expire_at`，若已过期则自动取消
**方案 B（主动定时）**：通过自动化任务每分钟扫描过期订单

**选择方案 A**（无需额外定时任务，实现简单）：
- 在 `getOrderById()` 中增加超时检查：若 `status='pending' AND expire_at < NOW()`，自动调用取消逻辑并释放库存

### 3.4 库存释放逻辑
```
取消订单时：
  1. 查询 order_items 获取所有商品和数量
  2. 回滚商品库存：stock += quantity, sold_count -= quantity
  3. 更新订单状态为 cancelled
```

## 4. 订单字段统一

### 4.1 统一方法
保留 `Order::addOrderDetail($orderId, $productData)` 作为标准方法，删除/注释 `addOrderItem()`。

### 4.2 字段映射标准化
```php
$orderDetailData = [
    'product_id'   => $item['product_id'],
    'product_name' => $item['name'],
    'product_image'=> $item['image_url'],
    'quantity'     => $item['quantity'],
    'unit_price'   => $item['price'],   // 单价
    'total_price'  => $item['price'] * $item['quantity']
];
```

## 5. 物流信息

### 5.1 数据库变更
```sql
ALTER TABLE orders ADD COLUMN shipping_company VARCHAR(100) DEFAULT NULL AFTER shipped_at;
ALTER TABLE orders ADD COLUMN tracking_no VARCHAR(100) DEFAULT NULL AFTER shipping_company;
```

### 5.2 卖家发货流程
- `mall/shop/orders.php` 中，点击"发货"时弹出表单，要求填写物流公司和运单号
- 提交后更新 `shipping_company`、`tracking_no`、`status='shipped'`、`shipped_at=NOW()`

### 5.3 买家查看物流
- `mall/user/order_detail.php` 中，若订单状态为 `shipped` 或 `completed`，展示物流公司和运单号

## 6. 支付确认页优化

### 6.1 倒计时显示
在 `pay.php` 顶部增加倒计时组件：
- 读取 `expire_at`，计算剩余时间
- 使用 JavaScript 每秒更新显示
- 倒计时结束后自动刷新页面，触发超时检测

### 6.2 价格显示统一
- 移除所有 `¥` 前缀的 BCT 金额显示
- 统一格式：`Ⓟ{amount} 人气值`

## 7. 数据流图

```
商品详情页
   │ 加入购物车 / 立即购买
   ▼
购物车页 (按店铺分组)
   │ 选择商品 → 去结算
   ▼
结算页 (按店铺拆单)
   │ 提交订单
   ▼
订单详情页 (pending + 倒计时)
   │ 去支付
   ▼
支付确认页 (blockcity.vip 转账)
   │ 点击"我已支付"
   ▼
订单状态 → paid
   │ 卖家发货 (填写物流)
   ▼
订单状态 → shipped
   │ 买家确认收货
   ▼
订单状态 → completed

超时分支：
   pending --(30分钟)--> 自动检测 --> cancelled + 库存回滚
```
