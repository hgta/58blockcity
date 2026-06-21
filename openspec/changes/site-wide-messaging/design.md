# Design: 全站站内信系统

## 1. DB 变更

```sql
ALTER TABLE notifications 
  MODIFY COLUMN type enum('visit_request','visit_confirm','return_confirm','system','order_paid','order_shipped','order_done','new_review','dm') NOT NULL,
  ADD COLUMN from_user_id int(11) DEFAULT NULL AFTER user_id,
  ADD COLUMN related_url varchar(500) DEFAULT NULL AFTER related_id;
```

## 2. Notification 类新增方法

- `sendSystemNotify($userId, $type, $relatedId, $content, $url)` — 系统通知
- `getUserNotificationsAll($userId, $page, $perPage)` — 分页通知列表

## 3. Mall 事件钩子

| 事件 | 文件 | 通知内容 |
|------|------|---------|
| 买家支付 | `pay.php` L66 | 通知卖家 "有新的付款订单" |
| 卖家发货 | `ship_order.php` L52 | 通知买家 "订单已发货" |
| 确认收货 | `confirm_receipt.php` | 通知卖家 "订单已完成" |
| 新评论 | `Review.php` | 通知店主 "收到新评价" |

## 4. 全局 UI

- `shared/header.php`: 通知铃铛对全站可见，角标显示未读数
- `shared/user/messages.php`: 消息中心（全站通用），通知列表+已读标记
