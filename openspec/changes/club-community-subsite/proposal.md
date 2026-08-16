# 提案：新增 58区块社区子站（club.58.tl）

## 背景

58 生态已有 block（区块）、nft（头像）、mall（商城）、bct（人气值）、v（互访圈）、bid（拍卖）六个子站，共用同一数据库、认证、类库和共享组件。

现需新增「58区块社区」子站，用户可发表**帖子**（长文）和**一句话心情**（短动态），按城市区分，内容与其他子站联动（聊区块/头像/人气值/城市）。

## 域名

**`club.58.tl`** —— 短、亲切、国际化，契合元宇宙/DAO 社区氛围，与现有 block/nft/bid/mall 短词风格一致。

## 目标

1. 新增 `club/` 子站目录，复用共用基础设施。
2. 发帖：长文帖子，可多图、带标题、可评论、可点赞。
3. 发一句话心情：短动态，可配 1 张图。
4. 按城市区分内容（城市名字符串，跟随 `users.city` / `circles.city` 惯例）。
5. 话题分类联动：block / nft / bct / city，首页按话题过滤。
6. 评论、回复、点赞，及相应站内通知。

## 关键决策（已与用户确认）

| # | 决策项 | 结论 |
|---|--------|------|
| 1 | 域名 | `club.58.tl` |
| 2 | 城市维度 | 城市名字符串（非 cities.id） |
| 3 | 点赞 | 做，帖子/心情都可点赞 |
| 4 | 心情配图 | 心情最多 1 张图；帖子多图 |
| 5 | 匿名 | 不允许，必须登录且实名 |

## 设计要点（详见 design.md）

- **单表 `posts` + `type` 字段**（post/moment）统一承载帖子和心情，评论/点赞/城市/通知共用一套逻辑。
- 评论用 `post_comments` 表支持二级回复（`parent_id`）。
- 点赞用 `post_likes` 表（复用 model_likes 的幂等点赞思路）。
- 通知复用 `Notification`，扩展 `notifications.type` enum 增加 `new_comment`/`new_reply`/`new_like`。
- 图片复用 `handleFileUpload`/`compressImage` + review.php 多图上传模式。

## 非目标（第一版不做）

- 帖子内嵌物品引用（关联具体区块/NFT 的卡片跳转）—— 第二版做，第一版只做话题分类。
- 富文本编辑器（用纯文本 + 换行）。
- 私信/关注用户等社交功能（已有 user_messages 可后续接）。
- 内容审核工作流（仅 status 字段预留，管理后台可下架）。

## 受影响文件

### 新建
- `club/` 子站目录（入口 + includes + auth + user + 页面）
- `classes/Post.php` — 帖子/心情业务类
- `init/migrate-club.sql` — 3 张新表 + notifications.type 扩展

### 修改
- `config/seo.php` — subdomains 增加 club
- `sitemap.php` — 社区首页 URL
- `shared/footer.php` — 生态卡片区加「社区」入口
- `index.php` — 主站导航加「社区」入口（视导航拥挤度决定）
- `docs/nginx-rewrite.conf` — 增加 club server 块
