# 设计：58区块社区子站（club.58.tl）

## 1. 数据模型（3 张新表）

```sql
-- 帖子/心情（统一表，type 区分）
CREATE TABLE `posts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL COMMENT '作者',
  `city` varchar(50) DEFAULT NULL COMMENT '城市名(字符串, 跟随 users.city 惯例)',
  `type` enum('post','moment') NOT NULL DEFAULT 'post' COMMENT '帖子/一句话心情',
  `title` varchar(100) DEFAULT NULL COMMENT '标题(moment 可为空)',
  `content` text NOT NULL COMMENT '正文/心情内容',
  `images` text COMMENT '配图 JSON 数组(post 多图, moment 单图)',
  `topic` varchar(30) DEFAULT NULL COMMENT '话题: block/nft/bct/city',
  `like_count` int(11) NOT NULL DEFAULT '0',
  `comment_count` int(11) NOT NULL DEFAULT '0',
  `status` enum('active','hidden') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_city_time` (`city`,`created_at`),
  KEY `idx_topic_time` (`topic`,`created_at`),
  KEY `idx_user` (`user_id`),
  KEY `idx_type_time` (`type`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='社区帖子/心情';

-- 评论/回复（parent_id 支持二级回复）
CREATE TABLE `post_comments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `post_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `parent_id` int(11) NOT NULL DEFAULT '0' COMMENT '0=直接评论帖子, 否则回复某条评论',
  `content` text NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_post` (`post_id`),
  KEY `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='帖子评论';

-- 点赞
CREATE TABLE `post_likes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `post_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `post_user` (`post_id`,`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='帖子点赞';

-- 扩展通知类型（幂等）
ALTER TABLE `notifications`
  MODIFY COLUMN `type` enum('visit_request','visit_confirm','return_confirm','system','order_paid','order_shipped','order_done','new_review','dm','new_comment','new_reply','new_like') NOT NULL;
```

### 关键设计点

- **单表多态**：帖子和心情用同一张 `posts` 表 + `type` 字段区分，评论/点赞/城市/通知全部共用一套逻辑，避免两套重复代码。
  - `post`（帖子）：有 `title`，`images` 多图。
  - `moment`（心情）：`title` 为空，`images` 单图（前端限制最多 1 张）。
- **城市维度**：`city` 存城市名字符串，与 `users.city`、`circles.city` 保持一致。发帖时默认取用户自己的城市，也可切换。
- **话题联动**：`topic` 字段 `block`/`nft`/`bct`/`city`，首页按话题 tab 过滤，实现"聊区块/聊头像/聊人气值/聊城市"。
- **通知类型扩展**：`notifications.type` 增加 `new_comment`/`new_reply`/`new_like`。

## 2. 业务类 `classes/Post.php`

```php
class Post {
    // 发布帖子/心情
    create($userId, $type, $city, $title, $content, $images, $topic): int|string

    // 查询
    getFeed($page, $perPage, $city, $topic, $type): array  // 信息流(按时间倒序)
    getPostById($id): ?array                                // 含作者信息
    getComments($postId, $page, $perPage): array
    getUserPosts($userId, $page, $perPage): array           // 用户主页

    // 评论/回复
    addComment($postId, $userId, $content, $parentId): bool
    deleteComment($commentId, $userId): bool

    // 点赞/取消点赞（幂等，事务）
    toggleLike($postId, $userId): string  // 'liked'|'unliked'
    isLiked($postId, $userId): bool

    // 通知辅助（评论/回复/点赞时通知作者）
    notifyComment($post, $commenter, $content)
}
```

## 3. 子站目录结构（仿照 hufang）

```
club/
├── .htaccess
├── index.php            # 首页：信息流(城市/话题筛选 + 发帖/发心情入口)
├── post.php             # 帖子详情 + 评论 + 点赞
├── create.php           # 发帖/发心情(复用同一表单, type 切换)
├── my.php               # 我的帖子/我的评论
├── includes/
│   ├── auth.php         # 透明代理到 ../../includes/auth.php
│   ├── header.php       # logo_sub='社区'
│   └── footer.php
├── auth/                # login/register/logout/forgot_password(薄封装)
└── user/                # 预留(用户主页/我的内容)
```

## 4. 联动与复用

| 项 | 复用方式 |
|----|---------|
| 用户/登录 | 复用 `users` + `isLoggedIn()`/`checkLogin()`，跨子站 cookie 通用 |
| 城市 | 复用 `City.php`，存城市名字符串 |
| 通知 | 复用 `Notification::sendSystemNotify`，type 用新增的 new_comment/new_reply/new_like |
| 图片上传 | 复用 `handleFileUpload()`/`compressImage()`，多图参照 review.php |
| 头部尾部 | 复用 `shared/header.php`/`shared/footer.php` |
| SEO | 复用 `SeoHelper` |

## 5. 话题联动（第一版）

- 首页信息流顶部话题 tab：`全部` / `聊区块` / `聊头像` / `聊人气值` / `聊城市`。
- 发帖时选择话题（可选，默认无）。
- 帖子卡片显示话题标签（如「聊区块」）。

## 6. 图片处理

- 帖子（post）：支持多图，复用 review.php 的多图上传，`images` JSON 数组存相对路径。
- 心情（moment）：单图，前端 `accept` + JS 限制 1 张。
- 存储目录：`club/assets/uploads/posts/`。

## 7. 风险与注意

- **枚举扩展风险**：`ALTER TABLE notifications MODIFY COLUMN type` 在生产需谨慎，迁移脚本应幂等（先判断枚举值是否存在再扩展，或用 `information_schema` 判断）。
- **城市字符串 vs id**：本项目混用，社区明确用字符串（跟互访圈一致），避免与 cities.id 混淆。
- **图片数量**：心情限 1 图需前后端双重校验（后端也校验，防止绕过前端）。
- **内容安全**：第一版不做审核流程，但保留 `status='hidden'` 字段，管理后台可下架。
- **并发点赞**：`toggleLike` 用事务 + `UNIQUE(post_id,user_id)` 幂等，避免重复计数。
