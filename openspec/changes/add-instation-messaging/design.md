# 统一站内信 — 设计文档

## 1. 数据模型

### `user_messages` 表

```sql
CREATE TABLE IF NOT EXISTS `user_messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `from_user_id` int(11) NOT NULL,
  `to_user_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_to_user` (`to_user_id`, `is_read`),
  KEY `idx_conversation` (`from_user_id`, `to_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 迁移

将 `model_messages` 数据迁移到 `user_messages`，然后删除 `model_messages` 表：

```sql
INSERT INTO user_messages (from_user_id, to_user_id, message, created_at)
SELECT user_id, (SELECT user_id FROM models WHERE id = model_id), message, created_at
FROM model_messages;
DROP TABLE model_messages;
```

## 2. Message 类 (`classes/Message.php`)

| 方法 | 说明 |
|------|------|
| `send($fromId, $toId, $msg)` | 发送站内信 |
| `getConversations($userId)` | 用户的所有会话列表（按最新消息排序） |
| `getMessages($userA, $userB, $page, $perPage)` | 两人之间的消息历史 |
| `markRead($userId, $fromId)` | 标记某人发来的所有消息为已读 |
| `getUnreadCount($userId)` | 获取未读消息总数 |
| `getConversationPartner($userId)` | 获取每个会话的对方用户信息 |

## 3. UI 设计

### 3.1 全局发送入口

用户名/Hover 卡片 → 点击 → 弹出小窗快速发送：

```
┌─────────────────────────────────────┐
│  给 @张三 发送站内信                  │
├─────────────────────────────────────┤
│  [在此输入...]                  [发送] │
│                                     │
│  ── 最近对话 ──                      │
│  我: 你好啊                           │
│  张三: 你也好                         │
│  [查看完整对话 →]                     │
└─────────────────────────────────────┘
```

实现方式：
- 共享 JS 函数 `openMessageModal(toUserId, toUsername)`
- 模态框通过 AJAX 加载最近的几条消息
- 无需跳转，快速发送

### 3.2 收件箱 `/messages/`

```
┌─────────────┬──────────────────────────────────────────┐
│ 会话列表     │ 聊天面板                                   │
│             │                                          │
│ ┌─────────┐ │  ┌──────────────────────────────────────┐ │
│ │ 张三     │ │  │ 张三                          12:30  │ │
│ │ 你好啊   │ │  │ 你好啊，这件衣服最小码是多少？         │ │
│ │ 2未读    │ │  │                                      │ │
│ └─────────┘ │  │                      我  13:05       │ │
│ ┌─────────┐ │  │  最小码是S，目前有现货哦               │ │
│ │ 李四     │ │  │                                      │ │
│ │ 谢谢你   │ │  └──────────────────────────────────────┘ │
│ └─────────┘ │                                          │
│             │  ┌──────────────────────────────────────┐ │
│             │  │ [输入消息...]              [发送]      │ │
│             │  └──────────────────────────────────────┘ │
└─────────────┴──────────────────────────────────────────┘
```

- 左侧：会话列表（头像 + 用户名 + 最后消息 + 时间 + 未读数）
- 右侧：聊天记录 + 发送框
- 新消息轮询或手动刷新

### 3.3 头部未读徽章

```
┌────────────────────────────────────────────────────┐
│  [58] 首页  通知(3)  站内信(5)    [用户]             │
└────────────────────────────────────────────────────┘
```

在 `shared/header.php` 已有通知徽章基础上，增加站内信徽章。

### 3.4 用户名可点击增强

```
现有：@张三 (不可点击)
改为：@张三 (可点击 → 弹出站内信)

触发点：
- 商品详情页：店铺名旁边的店主用户名
- 店铺详情页：店主信息
- 互访圈：圈主、访问记录中的用户名
- NFT 页：持有者/卖家用户名
- 模特页：关联用户名
- 评论/评价中的用户名
- 排行榜中的用户名
```

## 4. 集成策略

分步集成，每步回归：

| 步骤 | 站点/页面 | 用户名位置 |
|------|----------|-----------|
| 1 | `shared/header.php` | 全局用户信息接口 |
| 2 | `mall/product/detail.php` | 商品卖家店主 |
| 3 | `mall/model/view.php` | 模特关联用户 |
| 4 | `mall/shop/view.php` | 店铺店主 |
| 5 | `block/city.php` | 区块拥有者 |
| 6 | `hufang/circles/view.php` | 圈主、访客 |
| 7 | `nft/nft/view.php` | NFT 持有者、卖家 |
| 8 | 各列表/排行页 | 用户名链接 |

## 5. SEO 考量

- `/messages/` 页面需登录，`robots.txt` 已 Disallow 用户中心路径
- 不需要 sitemap 收录
- 不需要结构化数据

## 6. 数据迁移

```sql
-- 1. 迁移现有模特留言
INSERT INTO user_messages (from_user_id, to_user_id, message, is_read, created_at)
SELECT 
    mm.user_id AS from_user_id,
    m.user_id AS to_user_id,
    mm.message,
    0 AS is_read,
    mm.created_at
FROM model_messages mm
JOIN models m ON mm.model_id = m.id
WHERE m.user_id IS NOT NULL;

-- 2. 删除旧表
DROP TABLE IF EXISTS model_messages;
```
