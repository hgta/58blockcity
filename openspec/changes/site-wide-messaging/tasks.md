# Tasks: 全站站内信系统

## Task 1: DB + Notification 类扩展
- [ ] ALTER TABLE notifications 扩展 type + 加字段
- [ ] Notification::sendSystemNotify()
- [ ] Notification::getUserNotificationsAll()

## Task 2: Mall 事件自动通知
- [ ] pay.php — 买家支付后通知卖家
- [ ] ship_order.php — 卖家发货后通知买家
- [ ] confirm_receipt.php — 确认收货后通知卖家
- [ ] Review::createProductReview — 新评论通知店主

## Task 3: 全局通知铃铛 + 消息中心
- [ ] shared/header.php — 铃铛全站可见
- [ ] shared/user/messages.php — 消息中心
