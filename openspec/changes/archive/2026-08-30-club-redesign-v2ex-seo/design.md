# Design: club-redesign-v2ex-seo

## 概述

对 club.58.tl 做三层改造，全部改动收敛在 `club/` 目录 + 3 个必要的共享点（Post.php、SeoHelper.php、sitemap.php，均为纯增量/追加，不影响其他子站行为）。

```
club/
├── assets/
│   └── css/club.css          # 新增：V2EX 极简风全站样式（页面内联 CSS 收敛于此）
├── includes/
│   ├── header.php            # 改：canonical 默认值、引入 club.css、设置 schema_search
│   ├── footer.php            # 改：极简页脚（沿用 shared 结构）
│   └── sidebar.php           # 新增：右侧栏组件（搜索/热帖/活跃用户/发帖引导）
├── index.php                 # 改：双栏首页 + 伪静态链接 + ItemList + 搜索框 + 热帖tab
├── post.php                  # 改：详情页 Article/Breadcrumb + 301 + 楼主/作者卡/相关推荐/评论分页
├── create.php                # 改：发布后跳伪静态 + 百度推送触发
├── my.php                    # 改：伪静态链接 + 新样式
├── search.php                # 新增：标题/正文搜索（noindex）
└── user/dashboard.php        # 改：伪静态链接 + 新样式
```

## 一、布局与视觉（V2EX 极简风）

### 布局结构
```
<div class="club-layout">                  ← flex，gap 16px
  <main class="club-main">                 ← flex:1，min-width:0（帖子流/详情）
  <aside class="club-side">                ← 320px，固定于右侧
    [搜索框]  [发帖 CTA 卡]  [热门话题]  [本周热帖]  [活跃用户]
  </aside>
</div>
@media(max-width:992px){ .club-layout{flex-direction:column} .club-side{width:100%} }
```

### V2EX 风格 token
| 项 | 值 |
|----|----|
| 页面底色 | `#f5f5f5` |
| 卡片 | 白底、`1px solid #e2e2e2`、圆角 6px、**无阴影** |
| 主色 | `#ff6b00`（仅 CTA / 选中态 / 高亮） |
| 正文 | `#333` / 14px；次要 `#999` / 12px |
| 列表行 | 紧凑：标题 14px、meta 12px、行内分隔 `1px solid #f0f0f0` |
| 标签徽章 | 小号浅底：`#fff7ef` 底 + `#e65a00` 字 |
| 按钮 | 圆角 6px，主按钮橙色、次按钮描边 |

### 页面级重构
- **首页**：顶部 = `h1`（当前筛选标题）+ 筛选条（城市 pills + 话题 pills + 最新/热帖 switch）+ 搜索框；列表 = 紧凑行卡片（左侧主区），右侧栏固定
- **详情页**：面包屑 → `h1` 标题 → 楼主信息（楼主标记「OP」）→ 正文 → 图片 → 互动条（赞/评论/浏览）→ 评论（分页）→ 作者卡 → 相关推荐
- **发帖页**：表单卡片化，类型切换为 pill

## 二、SEO 层

### 1. 伪静态 URL
- `SeoHelper::postUrl($id, $title)` → `https://club.58.tl/post/{id}-{slug}.html`（纯增量方法）
- 代码内所有 `post.php?id=` 链接（index/post/my/create/Post 通知）替换为 `postUrl()`
- nginx 已配 `rewrite ^/post/([0-9]+)-.*\.html$ /post.php?id=$1 last;`（docs/nginx-rewrite.conf 第 282 行，无需改动）
- `post.php` 顶部：`SeoHelper::redirectIfNotCanonical($canonical)` 将旧 URL/无 slug URL 301 到规范 URL

### 2. 各页 SEO 配置（经 `SeoHelper::setSiteConfig`）
| 页面 | title | description | canonical | JSON-LD |
|------|-------|-------------|-----------|---------|
| 首页 | `{筛选名} - 58区块社区` | 筛选描述 | 当前筛选 URL | ItemList + WebSite(SearchAction) |
| 详情 | `{标题} - 58区块社区` | excerpt 前100字 | postUrl | Article + BreadcrumbList |
| 搜索 | `搜索“{q}” - 58区块社区` | 搜索描述 | 当前 URL | 无（meta robots noindex） |
| 我的 | `我的内容 - 58区块社区` | 固定 | `/my.php` | 无 |

- **首页 WebSite 注入 SearchAction**：`$site_config['schema_search'] = 'https://club.58.tl/search.php?q={search_term_string}'`
- **详情 Article schema**：headline / author / datePublished / dateModified / image（首图或 og 默认）/ description
- **面包屑**：`首页 › 城市·话题 › 标题`
- **分页**：首页 `?page=N` canonical 各页独立，`<link rel="prev/next">`

### 3. sitemap 与推送
- `sitemap.php` 追加段：`SELECT id, title, updated_at FROM posts WHERE status='active' ORDER BY id DESC LIMIT 5000`，用 `postUrl()` 输出（try/catch 保护表不存在）
- `Post::create()` 成功后调用 `SeoHelper::pushContentUrl($url)`（仅当 config 开启 auto_push）
- 图片：`alt` 取标题/摘要；详情 og:image 取首图绝对 URL

## 三、功能层

### 数据模型（init/migrate-club.sql 幂等追加）
```sql
ALTER TABLE posts
  ADD COLUMN IF NOT EXISTS is_sticky  TINYINT NOT NULL DEFAULT 0 COMMENT '置顶',
  ADD COLUMN IF NOT EXISTS view_count INT NOT NULL DEFAULT 0  COMMENT '浏览数';

CREATE TABLE IF NOT EXISTS club_follows (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL COMMENT '关注者',
  target_id INT NOT NULL COMMENT '被关注者',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_follow (user_id, target_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='社区关注';
```
> 注意：MySQL 5.7 不支持 `ADD COLUMN IF NOT EXISTS`，migration 用「查 information_schema 再 ALTER」的幂等方式（参考 init/ 下既有 migrate 脚本风格）。

### Post.php 新增方法（纯增量，不改现有签名）
| 方法 | 说明 |
|------|------|
| `search($q, $page, $perPage)` | 标题/正文 LIKE，复用 feed 行结构 |
| `getHotPosts($limit)` | 侧栏本周热帖：按 `like_count + comment_count*2 + view_count*0.1` 加权，7 天内 |
| `getActiveUsers($limit)` | 侧栏活跃用户：按发帖+评论数聚合 |
| `getRelatedPosts($post, $limit)` | 同 topic 优先、同 city 次之，排除自身 |
| `incrementView($id)` | 详情页浏览 +1 |
| `toggleSticky($id)` | 置顶/取消（admin） |
| `isFollowing($userId, $targetId)` / `toggleFollow($userId, $targetId)` / `getFollowerCount($targetId)` | 关注关系 |

### getFeed 扩展
- 新增 `$sort` 参数：`hot`（`is_sticky DESC, 热度分 DESC`）与默认 `new`（时间 DESC），**置顶帖始终排最前**
- 查询逻辑保持原结构，仅追加 ORDER BY 分支

### 页面功能
- **搜索**：`search.php?q=`，顶部搜索框 GET 提交；空查询重定向首页；结果无 h1 污染、`robots noindex`
- **热帖 tab**：首页筛选条「最新 | 热帖」switch，URL 参数 `sort=hot`
- **置顶**：详情页在 `$_SESSION['role']==='admin'` 时显示「置顶/取消置顶」按钮（POST + CSRF）
- **评论分页**：`getComments($postId, $limit, $offset)`，每页 50，URL `?page=N`（评论页与详情页共用页码参数，注意区分）
- **关注**：作者卡显示关注/已关注按钮 + 粉丝数（POST + CSRF）

## 四、安全与兼容

- 所有新 POST 操作校验 `csrf_token`（复用 `verifyCsrfToken`）
- 搜索关键词：`trim` + `htmlspecialchars` 输出 + PDO prepare LIKE（转义 `%_`）
- 伪静态 301 使用 `redirectIfNotCanonical`（已处理 URL 编码）
- club.css 只影响 club 页面（页面内 `<link>` 引入），不动 shared 全局样式
- 移动端：双栏折叠为单栏，侧栏降为内容底部
