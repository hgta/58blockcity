# BCT Dashboard 重新设计 — 实施任务

### 任务 1: SQL + DB
- [x] ALTER TABLE bct_orders ADD expires_at DATETIME NULL
- [x] 更新 init/db-init.sql

### 任务 2: BCTOrder 类
- [x] createOrder 加 $durationDays，卖单直接扣余额
- [x] 新增 cancelOrder($orderId, $userId)
- [x] getUserOrders 加分页支持，返回 list+total

### 任务 3: dashboard.php 重写
- [x] 移除 Circle/Visit 引用
- [x] 四卡片统计
- [x] BCT资产表格
- [x] 三 tab 订单列表（买入/卖出/已完成）+ 分页
- [x] 有效期显示（剩余时间/已过期）
- [x] 取消订单 POST 处理
- [x] 新 CSS 样式

### 任务 4: 验证
- [x] Lint 无报错
- [x] 逻辑完整性检查
