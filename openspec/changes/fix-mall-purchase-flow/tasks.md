# 商城购买主流程修复 — 实施任务

## 任务列表

### 任务 1：修复"立即购买"按钮 🔴

**文件**: `mall/product/detail.php`

**内容**:
- [x] 将第536行 `<a href="../cart/checkout.php?product_id=...">立即购买</a>` 改为 `<button onclick="buyNow()">`
- [x] 在 `<script>` 块末尾新增 `buyNow(productId, btn)` 函数
  - fetch POST 到 `../cart/add_to_cart.php`
  - 成功后 `window.location = '../cart/checkout.php'`
  - 失败时恢复按钮状态并 alert

**依赖**: 无

**验收**:
- 在商品详情页点击"立即购买"，商品加入购物车并跳转到结算页
- 刷新购物车，确认只有当前商品（不会被之前购物车内容干扰）
- 未登录时，add_to_cart.php 返回"请先登录"错误提示

---

### 任务 2：取消订单时恢复库存 🔴

**文件**: `mall/user/cancel_order.php`

**内容**:
- [x] 在 `Order::cancelOrder()` 调用前，获取订单商品明细 `$order->getOrderDetails($orderId)`
- [x] 逐条执行 `UPDATE products SET stock = stock + ?, sold_count = GREATEST(sold_count - ?, 0) WHERE id = ?`
- [x] 事务保护：库存恢复 + 取消订单同在一个 try/catch

**依赖**: 无

**验收**:
- 创建订单（库存扣减 N 件），取消订单后确认库存恢复 N 件
- sold_count 也正确恢复
- 非 pending 状态订单取消失败不恢复库存（被 `cancelOrder()` 内部拦下）

---

### 任务 3：BCT 支付创建交易记录 🟡

**文件**: `mall/user/pay.php`

**内容**:
- [x] 第14行附近：`require_once '../../classes/BCTTransaction.php'`
- [x] 在第57-62行 `UPDATE orders` 成功后，新增 `BCTTransaction::create()` 调用
  - 通过 `shops.user_id` 获取卖家 from_user
  - 映射 city/amount/tx_type 等字段
  - 失败时 `error_log()` 记录但不阻断主流程

**依赖**: `classes/BCTTransaction.php` (已存在)

**验收**:
- 完成一笔支付确认后，`bct_transactions` 表有对应的交易记录
- `tx_no` 格式为 `TX + YmdHis + 4位随机数`
- `from_user` = 买家，`to_user` = 卖家
- `city` 正确对应订单的城市
- `amount` 和 `net_amount` 正确

---

### 任务 4：验证与收尾 🧪

**内容**:
- [x] 端到端测试："立即购买"流程完整走通
- [x] 测试取消订单后库存恢复
- [x] 测试支付后 bct_transactions 记录
- [x] 检查无 PHP 错误/警告

**依赖**: 任务 1-3 全部完成

**验收**:
- 三个场景全部通过
- 无 regression（购物车、结算、订单列表等功能不受影响）
