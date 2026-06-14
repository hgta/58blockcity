# 购物车、结算与支付流程 — 任务清单

## Phase 1: 价格符号与字段统一修复

### 1.1 统一购物车价格显示
- [ ] `mall/cart/index.php`
  - 商品价格从 `¥` 改为 `Ⓟ` 人气值符号
  - 小计和总计从 `¥` 改为 `Ⓟ` 人气值符号
  - 空购物车提示保持不变

### 1.2 统一结算页价格显示
- [ ] `mall/cart/checkout.php`
  - 商品列表价格：`¥` → `Ⓟ`
  - 订单摘要总价：`¥` → `Ⓟ`
  - 支付方式描述中的金额同步修改

### 1.3 统一订单详情与支付页价格显示
- [ ] `mall/user/order_detail.php`
  - 商品单价：`¥` → `Ⓟ`
  - 应付总额：`¥` → `Ⓟ`
- [ ] `mall/user/pay.php`
  - 支付金额：统一为 `Ⓟamount 人气值`
  - 订单摘要中的金额：统一符号

### 1.4 统一 Order 类方法
- [ ] `classes/Order.php`
  - 清理/注释 `addOrderItem()` 方法（保留 `addOrderDetail()`）
  - 确保 `addOrderDetail()` 字段映射正确：`unit_price`、`total_price`
- [ ] `mall/cart/checkout.php`
  - 确认调用 `addOrderDetail()` 时传入的字段名与类方法一致

---

## Phase 2: 购物车按店铺分组与多店铺结算

### 2.1 购物车页面改造
- [ ] `mall/cart/index.php`
  - 后端：将 `$cartItems` 按 `shop_id` 分组
  - 前端：按店铺分组展示商品，每家店铺一个区块（显示店铺名）
  - 每个商品前增加复选框，默认全选
  - 增加"选中商品总计"实时计算
  - 去结算按钮改为提交选中项的 `cart_item_id` 列表

### 2.2 结算页支持多店铺
- [ ] `mall/cart/checkout.php`
  - 接收 `selected_items` 参数（逗号分隔的 cart_item_id）
  - 按 `shop_id` 分组生成多个订单
  - 每个订单独立：
    - 独立订单号
    - 独立金额计算
    - 独立扣减库存
    - 独立地址（同一地址）
  - 所有订单创建成功后，清空对应购物车项
  - 创建结果：
    - 1 个订单 → 跳转 `order_detail.php?id=xx`
    - 多个订单 → 跳转 `orders.php`（订单列表，显示成功提示）

---

## Phase 3: 支付超时与库存释放

### 3.1 数据库变更
- [ ] `init/db-init.sql` 中 `orders` 表增加：
  - `expire_at DATETIME DEFAULT NULL`
  - `shipping_company VARCHAR(100) DEFAULT NULL`
  - `tracking_no VARCHAR(100) DEFAULT NULL`
- [ ] 线上环境执行：
  ```sql
  ALTER TABLE orders ADD COLUMN expire_at DATETIME DEFAULT NULL;
  ALTER TABLE orders ADD COLUMN shipping_company VARCHAR(100) DEFAULT NULL;
  ALTER TABLE orders ADD COLUMN tracking_no VARCHAR(100) DEFAULT NULL;
  ```

### 3.2 订单创建时设置过期时间
- [ ] `classes/Order.php::createOrder()`
  - 插入时设置 `expire_at = DATE_ADD(NOW(), INTERVAL 30 MINUTE)`

### 3.3 超时检测与自动取消
- [ ] `classes/Order.php::getOrderById()`
  - 若 `status='pending' AND expire_at < NOW()`：
    - 调用新的 `autoCancelExpiredOrder($orderId)` 方法
- [ ] `classes/Order.php` 新增方法：
  - `autoCancelExpiredOrder($orderId)`：
    - 查询 order_items 回滚库存
    - 更新订单状态为 `cancelled`
    - 记录日志

### 3.4 库存回滚逻辑
- [ ] `classes/Product.php`
  - 新增 `rollbackStock($productId, $quantity)`：
    - `stock += quantity`
    - `sold_count -= quantity`

### 3.5 支付页倒计时
- [ ] `mall/user/pay.php`
  - 读取 `expire_at`，计算剩余秒数
  - 增加 JavaScript 倒计时组件
  - 倒计时结束后自动刷新页面触发超时检测

---

## Phase 4: 物流信息

### 4.1 卖家发货页改造
- [ ] `mall/shop/orders.php`
  - "发货"按钮改为触发模态框/跳转发货表单
  - 表单字段：物流公司（下拉选择 + 其他输入）、运单号
  - 提交到 `ship_order.php`

### 4.2 发货处理接口
- [ ] 新建/更新 `mall/shop/ship_order.php`
  - 接收 `order_id`、`shipping_company`、`tracking_no`
  - 验证订单归属当前店铺
  - 更新 `shipping_company`、`tracking_no`、`status='shipped'`、`shipped_at=NOW()`
  - 返回 JSON 或重定向

### 4.3 买家订单详情展示物流
- [ ] `mall/user/order_detail.php`
  - 当 `status` 为 `shipped` 或 `completed` 时：
    - 展示"物流公司"和"运单号"

---

## Phase 5: 测试与验证

### 5.1 价格符号验证
- [ ] 购物车页：商品价格、小计、总计均为 `Ⓟ`
- [ ] 结算页：商品单价、订单总价均为 `Ⓟ`
- [ ] 订单详情：商品单价、应付总额均为 `Ⓟ`
- [ ] 支付页：支付金额、订单摘要均为 `Ⓟ`

### 5.2 多店铺结算验证
- [ ] 将 A 店铺和 B 店铺商品加入购物车
- [ ] 结算后生成 2 个独立订单
- [ ] 每个订单对应正确的店铺和金额

### 5.3 超时释放验证
- [ ] 创建订单后等待（或手动修改 `expire_at` 为过去时间）
- [ ] 再次访问订单详情，状态自动变为 `cancelled`
- [ ] 对应商品库存已回滚

### 5.4 物流信息验证
- [ ] 卖家后台可填写物流信息
- [ ] 买家订单详情可查看物流信息

---

## 任务优先级

```
P0 (必须先完成):
  ├─ 1.1 ~ 1.3 价格符号统一（影响所有用户）
  ├─ 1.4 Order 字段统一（防止数据错误）
  ├─ 3.1 ~ 3.4 超时释放（防止库存丢失）
  └─ 3.5 支付页倒计时（提升体验）

P1 (重要):
  ├─ 2.1 购物车分组与选中（体验提升）
  ├─ 2.2 多店铺结算（修复缺陷）
  └─ 4.1 ~ 4.3 物流信息（完整交易闭环）

P2 (后续优化):
  └─ 5.1 ~ 5.4 全面测试
```

## 预估工作量

| 阶段 | 预估文件数 | 预估工时 |
|------|-----------|---------|
| Phase 1 | 修改 5 文件 | 1-2 小时 |
| Phase 2 | 修改 2 文件 | 1-2 小时 |
| Phase 3 | 修改 3 文件 + 数据库 | 1-2 小时 |
| Phase 4 | 修改 2 文件 + 新增 1 文件 | 1 小时 |
| Phase 5 | - | 0.5 小时 |
| **总计** | **修改 ~12 文件** | **4.5-7.5 小时** |
