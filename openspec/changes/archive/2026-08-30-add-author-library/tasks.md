# 作者库功能 — 任务清单

实现方式**平行模特（Model）功能**，可对照 `classes/Model.php`、`mall/model/`、`mall/admin/models.php` 的现有实现。

## Phase 1: 数据库

### 1.1 迁移脚本
- [ ] 新增 `init/migrate-add-author.sql`：
  - `authors` 表（含 `style`、`bio`、`author_works`、`follower_count int` 等，见 design 1.1）
  - `author_follows` 表（关注，UNIQUE(author_id, user_id)）
  - `author_likes` 表（点赞，UNIQUE(author_id, user_id)）
  - `ALTER TABLE products ADD author_id int DEFAULT NULL AFTER model_id` + `ADD KEY idx_author_id`

### 1.2 同步建表语句
- [ ] `init/db-init.sql` 同步：新增 `authors`/`author_follows`/`author_likes` 三张表 + `products` 追加 `author_id`（保持与迁移脚本一致）

---

## Phase 2: 业务层

### 2.1 Author 类
- [ ] 新增 `classes/Author.php`，方法平行 `classes/Model.php`：
  - 基础：`formatFollower` / `getById` / `getByUserId` / `create` / `update` / `delete`(软删) / `getList`(后台分页) / `getAll`(商品下拉)
  - 互动：`like` / `isLiked` / `follow` / `isFollowed` / `getFollowedAuthors`
  - 作品：`getAuthorProducts` / `getProductCount` / `getAuthorProductImages` / `getAuthorWorks` / `getAuthorImageStrips`(author_works 优先 + 商品图补齐)
  - 计数：`refreshCounts` / `recordView`
  - 发现页：`getFilteredList`(性别/城市/星座/风格/搜索 + 4 排序) / `getFacets`(城市/星座/风格) / `getRelated` / `getRanking`

### 2.2 Product 类集成
- [ ] `classes/Product.php`：
  - `createProduct()` INSERT 追加 `author_id` 字段与参数
  - `updateProduct()` `$allowedFields` 追加 `author_id`
  - `getProductById()` SQL JOIN `authors` 取 `nickname` 为 `author_nickname`
  - 保存后若 `author_id` 有值，调用 `Author::refreshCounts()`（保留现有 Model 逻辑）

---

## Phase 3: 后台管理

### 3.1 Admin 作者管理页
- [ ] 新增 `mall/admin/authors.php`（平行 `mall/admin/models.php`）：
  - POST action 分发：save（create/update）/ delete（软删），成功后 `pushContentUrl(authorUrl())`
  - 头像上传：GD 400×400 → `assets/uploads/authors/YYYYMM/`（校验 MIME + ≤5MB，编辑时未上传不覆盖）
  - 表单分区：头像 / 基本信息(昵称*、站内用户、城市、性别、星座、风格下拉、简介 bio) / 社交(QQ/微信/微博/小红书) / 数据(粉丝数 normalizeFollowerCount→int、状态) / 原创作品图集(author_works[] 多图上传 + existing/delete JSON 管理)
  - 列表：卡片网格（头像/昵称/状态/username/性别/城市/风格/商品数/赞数）+ 编辑/停用按钮
  - 搜索（昵称/用户名）+ 分页 + 编辑回填

---

## Phase 4: 前端展示

### 4.1 作者库发现页
- [ ] 新增 `mall/author/list.php`（平行 `mall/model/list.php`）：
  - 筛选：性别 / 城市 / 星座 / 风格(chips，`getFacets`) / 搜索(q)
  - 排序：follower / like / product / new
  - 首屏渲染 + AJAX 加载更多（JSON 返回 html/page/pages/hasMore，`X-Requested-With`）
  - 关注状态批量查询（author_follows）+ SEO

### 4.2 作者卡片
- [ ] 新增 `mall/author/card.php`：`renderAuthorCard($a, $imgStrip, $isFollowed, $userId)`
  - 头像（author.avatar → user_avatar → 占位）
  - 图集缩略 ≤4（`getAuthorImageStrips`）
  - 元信息（性别·城市·星座·风格）+ 简介一行 + 统计（❤/👥/📦）
  - 关注按钮（`data-author-id` / `data-logged-in` / `data-login-url`）

### 4.3 作者详情页
- [ ] 新增 `mall/author/view.php`（平行 `mall/model/view.php`）：
  - 头部：头像 / 昵称 / 元信息(性别·城市·星座·风格·粉丝·访问) / 简介 / 社交 badge(QQ/微信/微博/小红书)
  - 统计 + 点赞（仅登录且非本人）+ 关注 + 分享
  - 原创作品图集（author_works，灯箱 + 加载更多）
  - 图案商品（`getAuthorProducts` 商品卡片）
  - 留言（站内信，仅关联站内用户时显示）
  - 相关作者（`getRelated`）
  - `recordView()` + SEO（title "昵称 - 58作者库"、canonical authorUrl、JSON-LD Person）

### 4.4 关注 AJAX
- [ ] 新增 `mall/author/follow.js` + `mall/author/follow.php`（复制 model 版，`data-model-id`→`data-author-id`，操作 author_follows/authors 或调用 `Author::follow()`）

### 4.5 样式
- [ ] 新增 `mall/author/style.css`（复制 model style.css，调整作者卡片配色/图标）

---

## Phase 5: 商品关联

### 5.1 商品编辑页
- [ ] `mall/shop/products.php`：
  - "关联模特"后新增"关联图案作者"下拉（`Author::getAll()`）
  - add / edit 提交收集 `author_id`（`!empty ? intval : null`）
  - 回填三场景：编辑 `$editProduct['author_id']` / 提交失败 `$_POST` / 卖同款 `$copyProduct['author_id']`

### 5.2 商品详情页
- [ ] `mall/product/detail.php`：模特信息旁新增作者卡片（头像 + 昵称 → `SeoHelper::authorUrl()`，`author_nickname` 来自 Product JOIN）

---

## Phase 6: 我的关注 + SEO + 入口

### 6.1 我的关注并入
- [ ] `mall/user/following.php`：页签切换 `?type=model`（默认，现状不动）/ `?type=author`（`getFollowedAuthors` + `renderAuthorCard`）；计数分别统计；空态分别引导；按 type 引入对应 follow.js

### 6.2 SEO / 入口
- [ ] `classes/SeoHelper.php`：新增 `authorUrl($id, $nickname)`
- [ ] `mall/includes/header.php`：导航加"作者库"
- [ ] `mall/index.php`：首页加"查看作者库"入口
- [ ] `sitemap.php`：加 authors 节点（authorUrl，0.7 weekly）

---

## Phase 7: 验证（需部署后手动验证）

### 7.1 后台录入验证
- [ ] 新增作者（填昵称/简介/风格/社交/粉丝数/上传头像+作品图），保存正常、列表展示正确
- [ ] 编辑作者回填正确；停用后前台不再展示
- [ ] 粉丝数输入 "1.2万" 保存后前台显示 "1.2万"（int 存储）

### 7.2 商品关联验证
- [ ] 商品编辑选择作者保存；编辑回填正确；卖同款自动带上作者
- [ ] 商品详情页显示作者卡片，点击进入作者主页

### 7.3 作者页验证
- [ ] 作者库发现页筛选（性别/城市/星座/风格/搜索）与排序正常，加载更多正常
- [ ] 作者详情页展示原创作品图集 + 图案商品；无作品的作者不显示空区块
- [ ] 关注/取消关注计数正确；点赞计数正确
- [ ] 我的关注页 tab 切换正常，模特/作者互不干扰

### 7.4 迁移验证
- [ ] 对已有数据执行迁移脚本后，无作者商品详情页不显示作者卡片（旧数据兼容）
- [ ] `follower_count` int 类型无 1265 Data truncated 报错

---

## 备注

- 部署时先执行 `init/migrate-add-author.sql`（仅需一次）
- 上线前需在有 PHP 环境执行 `php -l` 语法检查（本机无 PHP 运行时，IDE lint 兜底）
