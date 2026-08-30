# 作者库功能 — 设计文档

整体实现方式**完全平行模特（Model）功能**：同样的表结构风格、业务类方法、后台录入方式、前端页面结构、商品关联方式、SEO 处理。差异点仅在于作者无三围/体重/身高，新增 `bio`（简介）与 `style`（创作风格）字段，并多一个独立上传的"原创作品图集"（`author_works` JSON，方案同模特 `daily_photos`）。

## 1. 数据模型

### 1.1 `authors` 表

```sql
CREATE TABLE IF NOT EXISTS `authors` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL COMMENT '关联站内用户（可选）',
  `nickname` varchar(100) NOT NULL COMMENT '作者名/艺名（必填）',
  `gender` enum('男','女','保密') DEFAULT '保密',
  `city` varchar(100) DEFAULT NULL COMMENT '所在城市',
  `zodiac` varchar(20) DEFAULT NULL COMMENT '星座',
  `style` varchar(50) DEFAULT NULL COMMENT '创作领域/风格标签',
  `bio` text DEFAULT NULL COMMENT '作者简介',
  `qq` varchar(20) DEFAULT NULL,
  `weixin` varchar(100) DEFAULT NULL,
  `weibo` varchar(200) DEFAULT NULL,
  `xiaohongshu` varchar(200) DEFAULT NULL COMMENT '小红书',
  `avatar` varchar(255) DEFAULT NULL COMMENT '头像',
  `author_works` text DEFAULT NULL COMMENT '原创作品图集 JSON',
  `follower_count` int(11) DEFAULT 0 COMMENT '粉丝数（整数，展示走 formatFollower）',
  `like_count` int(11) DEFAULT 0 COMMENT '点赞数（冗余）',
  `product_count` int(11) DEFAULT 0 COMMENT '关联商品数（冗余）',
  `review_count` int(11) DEFAULT 0 COMMENT '关联评论数（冗余）',
  `view_count` int(11) DEFAULT 0 COMMENT '访问量（冗余，详情页 +1）',
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  KEY `idx_like_count` (`like_count`),
  KEY `idx_product_count` (`product_count`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

> **注意**：`follower_count` 直接使用 `int`。模特历史遗留问题（db-init.sql 中仍为 `varchar(20)`，生产库已迁移为 int），作者表新建即用 int 规避。

### 1.2 `author_follows` 表（关注，平行 model_follows）

```sql
CREATE TABLE IF NOT EXISTS `author_follows` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `author_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `author_user` (`author_id`, `user_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_author_id` (`author_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 1.3 `author_likes` 表（点赞，平行 model_likes）

```sql
CREATE TABLE IF NOT EXISTS `author_likes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `author_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `author_user` (`author_id`, `user_id`),
  KEY `idx_author_id` (`author_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 1.4 `products` 表扩展

```sql
ALTER TABLE `products`
  ADD COLUMN `author_id` int(11) DEFAULT NULL AFTER `model_id`,
  ADD KEY `idx_author_id` (`author_id`);
```

### 1.5 数据关系

```
users(1) ────(1) authors(1) ────(N) products
                   ├────(N) author_follows(N)────┘ user_id
                   └────(N) author_likes(N)──────┘ user_id
```

- `authors.user_id` UNIQUE，可空 → 一个站内用户最多对应一个作者档案；未关联不影响录入
- `products.author_id` 可空 → 非图案商品不受影响；单一作者（本期不做联合作者）
- `author_follows`/`author_likes` UNIQUE(author_id, user_id) → 一人一赞/一关注

## 2. Author 类（`classes/Author.php`）

平行 `classes/Model.php`（以当前线上版本为准，含关注/筛选/facets/相关/图集优化）。

### 方法列表

| 方法 | 说明 |
|------|------|
| `formatFollower($n)` | 粉丝数展示格式化（"5.4万"，逻辑复制自 Model） |
| `getById($id)` | 获取单个作者（JOIN users 取 username/avatar） |
| `getByUserId($userId)` | 根据 user_id 查找 |
| `create($data)` | 创建作者（nickname 必填，其余可选） |
| `update($id, $data)` | 更新作者信息 |
| `delete($id)` | 软删除（status = inactive） |
| `getList($page, $perPage, $search)` | 后台分页列表（昵称/用户名搜索） |
| `getAll($status)` | 获取全部活跃作者（商品编辑下拉） |
| `like($authorId, $userId)` | 点赞/取消点赞（事务维护 author_likes + authors.like_count） |
| `isLiked($authorId, $userId)` | 是否已点赞 |
| `follow($authorId, $userId)` | 关注/取消关注（事务维护 author_follows + authors.follower_count） |
| `isFollowed($authorId, $userId)` | 是否已关注 |
| `getFollowedAuthors($userId, $page, $perPage)` | 用户关注的作者列表（我的关注） |
| `getAuthorProducts($authorId, $page, $perPage)` | 作者关联商品列表 |
| `getProductCount($authorId)` | 关联商品数 |
| `getAuthorProductImages($authorId, $limit)` | 聚合关联商品图（main_image + images JSON） |
| `getAuthorWorks($authorId)` | 解析 `author_works` JSON 为数组 |
| `getAuthorImageStrips($authorIds, $perModel)` | 批量图集缩略（author_works + 商品图混合，避免 N+1） |
| `refreshCounts($authorId)` | 重算 product_count / review_count |
| `getRanking($type, $limit)` | 排行查询（product_count / like_count / review_count） |
| `recordView($authorId)` | 详情页访问量 +1 |
| `getFilteredList($filters, $page, $perPage)` | 发现页筛选+排序（性别/城市/星座/风格/搜索；follower/like/product/new） |
| `getFacets()` | 城市/星座/风格维度去重计数（喂筛选 chips） |
| `getRelated($authorId, $limit)` | 同 城市+星座+性别 加权推荐 |

### 图集来源优先级（卡片/详情页）

1. `author_works`（后台录入的原创作品图，权威）
2. 关联商品图（`getAuthorProductImages`）

- 卡片缩略：`author_works` 优先，不足 4 张再用商品图补齐（平行模特卡片"商品图 + daily_photos"逻辑，方向相反）
- 详情页"原创作品"区：`author_works` 全量；"图案商品"区：关联商品列表

### 关注/点赞事务逻辑

与 `Model::follow()` / `Model::like()` 完全一致，仅表名与字段换为 `author_follows`/`authors.follower_count`、`author_likes`/`authors.like_count`（`GREATEST(x-1, 0)` 防负）。

## 3. URL 设计

| 页面 | URL |
|------|-----|
| 作者详情 | `https://mall.58.tl/author/{id}-{slug}.html` |
| 作者列表 | `https://mall.58.tl/author/list.php` |

### Nginx rewrite

```nginx
rewrite ^/author/([0-9]+)-.*\.html$ /author/view.php?id=$1 last;
```

### SeoHelper 扩展

```php
public static function authorUrl($id, $nickname) {
    return 'https://mall.58.tl/author/' . intval($id) . '-' . self::slug($nickname) . '.html';
}
```

## 4. 页面设计

### 4.1 Admin 作者管理（`mall/admin/authors.php`）

平行 `mall/admin/models.php` 的结构：POST action 分发（save/delete）+ 卡片网格列表 + 搜索 + 分页 + 编辑回填。

表单分区：

```
[头像] 文件上传（GD 居中裁剪 400×400 → assets/uploads/authors/YYYYMM/，校验 MIME + ≤5MB）

[基本信息] 昵称*(必填) · 站内用户ID(可选，编辑时显示 username 只读)
           · 城市(下拉，City::getAllCities) · 性别(下拉) · 星座(下拉)
           · 风格(下拉，见下) · 简介 bio(textarea)

[社交账号] QQ · 微信 · 微博 · 小红书（四列，平行模特）

[数据] 粉丝数(文本输入，normalizeFollowerCount → int，placeholder "例：1.2万")
       · 状态(仅编辑显示)

[原创作品图集] author_works[] 多文件上传（单张 ≤5MB）
              · existing_author_works 隐藏 JSON · delete_author_works 逗号分隔
              （JS deleteWorkPhoto(btn, path) 填充，平行 deleteDailyPhoto）
```

**风格取值**：预置固定下拉选项 `插画 / 国潮 / 卡通 / 写实 / 水墨 / 涂鸦 / 极简 / 复古 / 萌系 / 科技 / 其他`，存 `style` 字段；发现页 chips 由 `getFacets()` 动态统计，新风格出现自然展示。

**粉丝数规范化**（复制自 models.php 的 `normalizeFollowerCount`，作者页内实现或独立函数）：
"5.4万"→54000、"1.2w"→12000、"1k"→1000、"5,400"→5400。

### 4.2 商品编辑（`mall/shop/products.php` 改动）

在"关联模特"下拉之后增加"关联图案作者"：

```
关联模特: [▼ 选择模特] (可选)      ← 现有
关联图案作者: [▼ 选择作者] (可选)   ← 新增
```

- `$authorObj = new Author($pdo); $authors = $authorObj->getAll();`
- `<select name="author_id">`，选项 `<option value="<?= $a['id'] ?>" <?= $currentAuthorId==$a['id']?'selected':'' ?>>昵称</option>`
- add/edit 提交：`'author_id' => !empty($_POST['author_id']) ? intval($_POST['author_id']) : null`
- 回填：编辑 `$editProduct['author_id']` / 提交失败 `$_POST['author_id']` / 卖同款 `$copyProduct['author_id']`（三场景，平行 model_id）
- `createProduct` / `updateProduct` 的 INSERT/白名单追加 `author_id`；保存后若 author_id 有值则 `Author::refreshCounts()`（平行 Model）

### 4.3 商品详情页（`mall/product/detail.php` 改动）

在模特信息旁（价格区/元信息区）增加**作者卡片**（较模特链接更突出，作者是图案创作者）：

```html
<?php if ($productDetail['author_id']): ?>
<div class="product-author-card">
  <a href="<?= SeoHelper::authorUrl($productDetail['author_id'], $productDetail['author_nickname']) ?>">
    [头像 40px 圆角] 作者昵称
  </a>
  <a class="author-view-link" href="...">查看作者主页 ↗</a>
</div>
<?php endif; ?>
```

- 需修改 `Product::getProductById()` SQL，JOIN `authors` 取 `nickname` 为 `author_nickname`（平行现有 JOIN models 取 model_nickname）

### 4.4 作者库发现页（`mall/author/list.php`）

平行 `mall/model/list.php`：

```
🎨 作者库
[搜索昵称/简介] [搜索]
性别: [全部] [女] [男]
城市: [全部] 北京(3) 上海(5) ...
星座: [全部] 白羊(2) ...
风格: [全部] 插画(4) 国潮(3) ...     ← 新增维度
排序: [🔥 粉丝] [❤ 人气] [📦 作品] [🆕 最新]
共找到 N 位作者
[作者卡片网格]  [加载更多]（AJAX，X-Requested-With，JSON 返回 html/page/pages/hasMore）
```

- 筛选参数：`gender / zodiac / city / style / q / sort / page / ajax`
- 排序映射：`follower→follower_count DESC, like→like_count DESC, product→product_count DESC, new→created_at DESC`
- 卡片复用 `author/card.php` 的 `renderAuthorCard()`
- SEO：title "作者库 - 58人气值商城"，canonical `https://mall.58.tl/author/list.php`

### 4.5 作者卡片（`mall/author/card.php`）

平行 `mall/model/card.php` 的 `renderModelCard`：

```
┌─────────────────────────────┐
│ [头像]      [昵称] [+ 关注]  │
│            [简介一行]        │
│ [图1][图2][图3][图4]  ← 作品缩略│
│ ❤ 12  👥 5.4万  📦 8        │
└─────────────────────────────┘
```

- 头像：优先 `author.avatar`，否则关联用户 `user_avatar`，否则占位 icon
- 图集缩略：`getAuthorImageStrips($ids, 4)`（author_works 优先 + 商品图补齐）
- 元信息：性别·城市·星座·风格（有则拼接）
- 关注按钮：`data-author-id` + `data-logged-in` + `data-login-url`（登录跳转），未登录渲染 `<a>`

### 4.6 作者详情页（`mall/author/view.php`）

平行 `mall/model/view.php` 结构（以当前线上版为准）：

```
面包屑: 首页 > 作者库 > 昵称

┌──────────────────────────────────────────────┐
│ [头像 160 圆形]  昵称(h1)                       │
│ @username · 性别 · 城市 · 星座 · 风格 · 粉丝 · 访问│
│ 简介 bio（nl2br）                               │
│ 社交: [QQ蓝] [微信绿] [微博红] [小红书📕]         │
│ 📦 商品 N · ❤ 赞 N · 💬 留言 N                   │
│ [点赞]（仅登录且非本人） [+ 关注] [分享]           │
├──────────────────────────────────────────────┤
│ 🎨 原创作品（author_works 图集，灯箱 + 加载更多）  │
├──────────────────────────────────────────────┤
│ 🛍 图案商品（getAuthorProducts 商品卡片网格）      │
├──────────────────────────────────────────────┤
│ 💬 留言（站内信，仅关联站内用户时显示）             │
├──────────────────────────────────────────────┤
│ 相关作者（getRelated，同城/星座/性别加权）          │
└──────────────────────────────────────────────┘
```

- SEO：title `昵称 - 58作者库`；canonical `authorUrl()`；og_image 取 avatar；JSON-LD Person（name/url，不含身高体重）
- 访问量：`recordView()`（`view_count + 1` 展示，平行模特）

### 4.7 关注 AJAX（`mall/author/follow.js` + `mall/author/follow.php`）

复制 `mall/model/follow.js` / `follow.php`，请求参数与返回字段不变，仅：
- `data-model-id` → `data-author-id`
- `follow.php` 内逻辑改操作 `author_follows` / `authors`（或直接调用 `Author::follow()`）

`mall/model/follow.js` 与 `mall/author/follow.js` 可同时引入（选择器基于不同 data 属性，互不干扰）。

### 4.8 我的关注（`mall/user/following.php` 改动）

增加 tab 切换：

```
[❤ 我的关注]
[关注的模特] [关注的作者]      ← 页签：?type=model（默认）| ?type=author

type=model（现状不动）→ getFollowedModels + renderModelCard
type=author           → $author->getFollowedAuthors + renderAuthorCard(author/card.php)
顶部计数：model_follows / author_follows 分别统计
空态分别引导：去模特库 / 去作者库
```

- 需引入 `classes/Author.php` 与 `../author/card.php`；页签切换时按 type 引入对应 follow.js

## 5. 图片上传与存储

- 头像：GD 居中裁剪 400×400 JPEG(80)，`assets/uploads/authors/YYYYMM/{uniqid}.jpg`，返回相对路径（逻辑复制自 `uploadModelAvatar`）
- 原创作品图：`move_uploaded_file` → `assets/uploads/authors/YYYYMM/aw_{uniqid}.{ext}`，追加进 `existingWorks[]`，最终 `json_encode` 存 `author_works`
- 删除：`delete_author_works` 逗号分隔路径，保存时从 JSON 移除并 `@unlink`

## 6. 计数同步策略

`authors.product_count` / `review_count` 为冗余缓存：

- 商品创建/更新/删除/状态变更时 → `Author::refreshCounts($authorId)`
- 评价创建/删除时 → 平行 Model 的处理（Product 侧集成点）
- `like_count` / `follower_count` 由点赞/关注事务直接维护
- `view_count` 详情页 `recordView()` +1

**在 Product 类中集成**：`createProduct()` / `updateProduct()` 中若 `author_id` 有值，调用 `Author::refreshCounts()`（并同时保留现有 `Model::refreshCounts()` 逻辑）。

## 7. 入口与 SEO 集成

| 位置 | 改动 |
|------|------|
| `mall/includes/header.php` | 导航加"作者库"（icon e.g. `palette`，url `../author/list.php`） |
| `mall/index.php` | 首页加"查看作者库"入口（平行"查看模特库"） |
| `sitemap.php` | 加 authors 节点（平行 model 节点，`authorUrl()`，0.7 weekly） |
| `mall/admin/authors.php` 保存 | `SeoHelper::pushContentUrl(authorUrl(...))` 百度推送 |
