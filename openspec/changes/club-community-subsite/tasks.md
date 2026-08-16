# 实施任务：58区块社区子站（club.58.tl）

## Task 1：数据表 + 迁移
- [ ] 创建 `init/migrate-club.sql`：`posts` + `post_comments` + `post_likes` 三张表。
- [ ] 扩展 `notifications.type` enum，幂等加入 `new_comment`/`new_reply`/`new_like`。

## Task 2：业务类 `classes/Post.php`
- [ ] `create()`：发布帖子/心情，校验登录、类型、内容非空、心情限 1 图。
- [ ] `getFeed()`：信息流（城市/话题/类型筛选 + 分页）。
- [ ] `getPostById()` / `getComments()` / `getUserPosts()` 查询。
- [ ] `addComment()`：评论/回复（二级），更新 comment_count，通知作者。
- [ ] `toggleLike()`：点赞/取消（事务 + 幂等），更新 like_count，通知作者。
- [ ] `isLiked()` 判断。

## Task 3：子站骨架 club/
- [ ] 创建 `club/` 目录 + `includes/auth.php`（代理）+ `includes/header.php`（logo_sub='社区'）+ `includes/footer.php`。
- [ ] 创建 `club/.htaccess`。
- [ ] 创建 `club/auth/` 薄封装（login/register/logout/forgot_password）。

## Task 4：首页信息流 club/index.php
- [ ] 顶部城市/话题筛选 tab（全部/聊区块/聊头像/聊人气值/聊城市）。
- [ ] 帖子卡片列表（作者、城市、标题、内容摘要、图片、话题标签、点赞数、评论数）。
- [ ] 发帖/发心情入口按钮。
- [ ] 分页。

## Task 5：发帖/发心情 club/create.php
- [ ] 类型切换（帖子/心情）。
- [ ] 帖子：标题 + 正文 + 多图上传；心情：内容 + 单图。
- [ ] 城市选择（默认用户城市）、话题选择。
- [ ] 复用 `handleFileUpload`/`compressImage`，多图参照 review.php。
- [ ] 后端校验：心情限 1 图、内容非空、登录。

## Task 6：帖子详情 club/post.php
- [ ] 帖子全文 + 图片展示 + 话题标签。
- [ ] 点赞按钮（登录态切换）。
- [ ] 评论列表（二级回复）+ 发表评论表单。
- [ ] 通知作者。

## Task 7：我的内容 club/my.php
- [ ] 我发布的帖子/心情列表。
- [ ] 我的评论列表（可选）。

## Task 8：共享配置 + SEO + 生态入口
- [ ] `config/seo.php` subdomains 增加 club。
- [ ] `sitemap.php` 增加社区首页。
- [ ] `shared/footer.php` 生态卡片区加「社区」卡片。
- [ ] `index.php` 主站导航/快速链接加「社区」入口（视拥挤度精简）。
- [ ] `docs/nginx-rewrite.conf` 增加 club server 块。

## Task 9：联调与验证
- [ ] 发帖（多图）→ 详情展示 → 评论 → 回复 → 点赞，全部正常。
- [ ] 发心情（单图限制生效，前后端双重校验）。
- [ ] 城市筛选、话题筛选正确。
- [ ] 未登录发帖/评论/点赞被拦截。
- [ ] 通知正确触达（评论/回复/点赞）。
- [ ] 图片上传路径正确、跨子站可访问。
