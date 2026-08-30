# Tasks: club-redesign-v2ex-seo

## Phase 1: 数据与模型层（前置）

- [x] 1.1 `init/migrate-club.sql` 追加幂等 DDL：posts 加 `is_sticky`、`view_count`；新建 `club_follows` 表
- [x] 1.2 `classes/SeoHelper.php` 新增 `postUrl($id, $title)`（纯增量方法）
- [x] 1.3 `classes/Post.php` 新增：`search()` / `getHotPosts()` / `getActiveUsers()` / `getRelatedPosts()` / `incrementView()` / `toggleSticky()` / `isFollowing()` / `toggleFollow()` / `getFollowerCount()`
- [x] 1.4 `classes/Post.php` `getFeed()` 扩展 `$sort` 参数（hot 加权 + 置顶优先），评论 `getComments()` 支持分页
- [x] 1.5 `classes/Post.php` 通知链接（new_comment / new_like）改为 `SeoHelper::postUrl()`，`create()` 成功后调用 `pushContentUrl()`

## Phase 2: 视觉基座（V2EX 极简风）

- [x] 2.1 新增 `club/assets/css/club.css`：布局 token、双栏 `.club-layout`、卡片/列表/按钮/徽章/表单组件、移动端折叠
- [x] 2.2 新增 `club/includes/sidebar.php`：搜索框 + 发帖 CTA + 热门话题 + 本周热帖 + 活跃用户（数据走 Post.php 新方法）
- [x] 2.3 `club/includes/header.php`：引入 club.css、设置 `schema_search`（SearchAction）
- [x] 2.4 `club/includes/footer.php`：保持 shared 结构，视觉随 club.css 收敛

## Phase 3: 页面改造

- [x] 3.1 `club/index.php`：h1 + 城市/话题 pills + 最新|热帖 switch + 搜索框 + 双栏列表（伪静态链接、置顶标记、浏览数）+ 分页 rel=prev/next + ItemList schema + 分页 canonical
- [x] 3.2 `club/post.php`：面包屑 + h1 + 楼主(OP)标记 + Article/Breadcrumb JSON-LD + og:image + 301 canonical + 浏览+1 + 作者卡(关注按钮/粉丝数) + 相关推荐 + 评论分页 + admin 置顶按钮(CSRF)
- [x] 3.3 `club/create.php`：发布成功跳 `postUrl()`，表单样式切换 pill
- [x] 3.4 `club/my.php`：伪静态链接 + club.css 样式
- [x] 3.5 新增 `club/search.php`：标题/正文 LIKE 搜索，结果列表复用首页卡片，`robots noindex`，空查询重定向首页

## Phase 4: 全站接入

- [x] 4.1 `sitemap.php` 追加 club 帖子段（active 帖子，`postUrl()`，try/catch 保护）
- [x] 4.2 `club/user/dashboard.php`：伪静态链接 + 新样式
- [x] 4.3 全站搜索检查 club 内残留 `post.php?id=` 链接并替换（index/post/my/create/dashboard/sidebar/Post.php）

## Phase 5: 部署后验证（线上）

- [ ] 5.1 访问 `/post/1-示例标题.html` 正常渲染，`post.php?id=1` 301 到新 URL
- [ ] 5.2 首页双栏、移动端折叠正常；无 console 报错
- [ ] 5.3 搜索关键词有结果；搜索结果页被 noindex
- [ ] 5.4 热帖 tab 排序正确；置顶帖置顶且标记可见
- [ ] 5.5 详情页评论分页、作者卡关注按钮（登录态）可用
- [ ] 5.6 `sitemap.xml` 包含 club 帖子条目；发帖后百度推送日志无报错
- [ ] 5.7 详情页 JSON-LD（Article/Breadcrumb）通过 Google Rich Results 校验
