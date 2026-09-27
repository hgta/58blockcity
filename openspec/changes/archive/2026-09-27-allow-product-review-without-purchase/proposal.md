# 商品评价无需购买

## 问题
当前评价必须完成订单后才能评价，用户希望登录即可评价。

## 规则
1. 同一用户+同一商品：未购买最多3条，已购买无限制
2. 默认审核通过（`status='approved'`）
3. 保留匿名选项
4. 未登录引导到登录/注册页

## 改动

| 文件 | 改动 |
|------|------|
| DB `reviews` 表 | `order_id`, `order_item_id` → 允许 NULL |
| `classes/Review.php` | 新增 `createProductReview()`, `countUserProductReviews()`, `hasPurchased()` |
| `mall/product/detail.php` | 加入评价表单 + POST 处理 + 登录引导 |
