# 统一站内信 — 任务清单

## 阶段 1：数据层

- [ ] 1.1 `init/db-init.sql`：新增 `user_messages` 表
- [ ] 1.2 创建 `classes/Message.php`：send、getConversations、getMessages、markRead、getUnreadCount
- [ ] 1.3 数据迁移 SQL（model_messages → user_messages）
- [ ] 1.4 `init/db-init.sql`：移除 `model_messages` 表 DDL
- [ ] 1.5 `classes/Model.php`：移除 addMessage/getMessages/getMessageCount 方法
- [ ] 1.6 `mall/model/view.php`：改用 Message 类显示/发送留言

## 阶段 2：发信入口 + 徽章

- [ ] 2.1 `classes/Message.php`：增加 `sendModalHtml(fromId, toId, toName)` 生成模态框 HTML
- [ ] 2.2 创建 `assets/js/message-modal.js`：统一的站内信弹窗逻辑（AJAX 加载 + 发送 + 已读标记）
- [ ] 2.3 `shared/header.php`：引 message-modal.js，头部增加站内信未读徽章
- [ ] 2.4 全局 JS 函数：`openMessage(userId, username)` 在任何页面可用

## 阶段 3：收件箱

- [ ] 3.1 创建 `messages/index.php`：会话列表 + 聊天面板
- [ ] 3.2 创建 `messages/load.php`：AJAX 端点（加载消息 / 发送消息 / 标记已读）
- [ ] 3.3 收件箱 SEO 基本设置（noindex）

## 阶段 4：全站集成

- [ ] 4.1 `mall/product/detail.php`：商品详情页店主用户名可点击发信
- [ ] 4.2 `mall/model/view.php`：模特页用户名改为 Message 系统
- [ ] 4.3 `mall/shop/view.php`：店铺页店主用户名可点击
- [ ] 4.4 `hufang/circles/view.php`：圈主/访客用户名可点击
- [ ] 4.5 `nft/nft/view.php`：NFT 持有者/卖家用户名可点击
- [ ] 4.6 `block/city.php`：区块拥有者用户名可点击
- [ ] 4.7 各评论/评价列表中的用户名（product/detail.php 的 review 区）

## 阶段 5：收尾

- [ ] 5.1 代码 lint 检查
- [ ] 5.2 本地测试：发信 → 收信 → 未读徽章 → 已读标记
- [ ] 5.3 提交并推送
