# 设计文档

## DB 变更
```sql
ALTER TABLE reviews MODIFY order_id int(11) DEFAULT NULL;
ALTER TABLE reviews MODIFY order_item_id int(11) DEFAULT NULL;
```

## Review 类新增方法

```php
// 检查用户是否购买过该商品
hasPurchased($userId, $productId): bool

// 统计用户对某商品的非购买评价数
countUserProductReviews($userId, $productId): int

// 创建非购买评价（product-level review）
createProductReview($data): int
  - 校验: 未购买 → 最多3条，已购买 → 无限制
  - order_id=NULL, order_item_id=NULL
```

## detail.php 改动

在评价列表底部加入：
- 已登录 → 评价表单（星级+内容+图片+匿名选项）
- 未登录 → 「登录后评价」→ 跳转登录页
- POST 处理在页面顶部（header 之前）
