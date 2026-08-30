# Proposal: club-redesign-v2ex-seo

## 背景与问题

club.58.tl 社区子站当前存在两类问题：

### SEO 缺失（"裸奔"状态）
全站其他子站（mall/block/bid）均已落地完整 SEO 基建（`SeoHelper` 类、伪静态 URL、JSON-LD、sitemap 收录、百度推送），但 club 完全未接入：

| SEO 项 | 全站标准 | club 现状 |
|--------|---------|-----------|
| 详情页 URL | 伪静态 `/product/123-name.html` | `post.php?id=2` 动态 URL（nginx 已配好 rewrite，代码未用） |
| canonical | 每页精确 + 旧 URL 301 | 写死 `https://club.58.tl/`，全站共用 |
| JSON-LD | Article/Product + 面包屑 | 仅 shared 头部 WebSite，详情无 Article、无面包屑 |
| 列表页 | ItemList schema + rel=prev/next | 无 |
| sitemap | 详情页逐条收录 | 仅首页 1 条 |
| 图片 SEO | alt + og:image 精确到内容 | alt 全空、og 固定一张图 |
| 语义化 | article/time/h1-h3 | 全 div，首页无 h1 |

### 界面平庸（单栏 760px、无层级）
- 单栏 760px，大屏两侧空白
- 无搜索、无侧栏、无热门/精选概念
- 城市 tab、话题 tab、帖子卡片三个信息层同样"重"，无主次
- emoji + FontAwesome + 内联 style 混用
- 详情页无楼主标记、无作者卡、无相关推荐、评论不分页

## 目标

参考市面优秀社区（V2EX 极简风），对 club 子站进行：
1. **SEO 层**：接入全站标准（伪静态 URL、canonical/301、JSON-LD、sitemap、百度推送、语义化）
2. **界面层**：双栏布局 + V2EX 极简风视觉重构
3. **功能层**：搜索、热帖排序、置顶/精华、评论分页、关注作者

## 范围

### In Scope
- `club/` 目录下全部页面的重构（index / post / create / my / user/dashboard）
- 新增：club 搜索页、club 专属侧栏组件、club 专属 CSS
- `classes/Post.php`：新增搜索/热帖/置顶/关注/相关推荐等方法，通知链接改为伪静态
- `classes/SeoHelper.php`：新增 `postUrl()`（纯增量，不修改现有方法）
- `sitemap.php`：追加 club 帖子收录段（try/catch 保护，不影响其他子站）
- `init/migrate-club.sql`：追加 posts 表字段（置顶/浏览数）与关注表（幂等 DDL）

### Out of Scope
- **不改** `shared/header.php`、`shared/footer.php`（全站 7 子站共用，双栏样式通过 club 页面内 CSS 实现）
- 不改 mall/block/bid/nft/bct/hufang 等其他子站
- 不改 `SeoHelper` 现有方法与签名
- 不做评论删除/编辑、帖子删除（用户未提）
- 不做关注通知站内信（v1 仅关注关系 + 计数）

## 验收标准

- 帖子详情可访问伪静态 URL `/post/{id}-{slug}.html`，旧 `post.php?id=` 自动 301
- 首页与详情页含正确 h1、canonical、description、JSON-LD（首页 ItemList、详情 Article+Breadcrumb）
- 首页双栏布局（主信息流 + 侧栏：搜索/热帖/活跃用户/发帖引导），移动端单栏
- 顶部搜索框可用，搜索页有结果且 noindex
- 热帖 tab 按热度排序，置顶帖在列表顶部并带标记
- 详情页：楼主标记、作者卡（含关注按钮）、相关推荐、评论分页
- sitemap 收录全部 active 帖子；发帖时推送百度
- 视觉：V2EX 极简风（白卡片、浅灰底、细边框、克制橙色），无 emoji 滥用
