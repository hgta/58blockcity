# 模特库功能 — 设计文档

## 1. 数据模型

### 1.1 `models` 表

```sql
CREATE TABLE IF NOT EXISTS `models` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL COMMENT '关联站内用户',
  `nickname` varchar(100) NOT NULL COMMENT '昵称（必填）',
  `gender` enum('男','女','保密') DEFAULT '保密',
  `age` tinyint(3) UNSIGNED DEFAULT NULL,
  `qq` varchar(20) DEFAULT NULL,
  `weixin` varchar(100) DEFAULT NULL,
  `weibo` varchar(200) DEFAULT NULL,
  `xiaohongshu` varchar(200) DEFAULT NULL,
  `height` decimal(5,1) DEFAULT NULL COMMENT '身高 cm',
  `weight` decimal(4,1) DEFAULT NULL COMMENT '体重 kg',
  `measurements` varchar(50) DEFAULT NULL COMMENT '三围',
  `hobbies` text DEFAULT NULL COMMENT '爱好',
  `like_count` int(11) DEFAULT 0 COMMENT '点赞数（冗余）',
  `product_count` int(11) DEFAULT 0 COMMENT '关联商品数（冗余）',
  `review_count` int(11) DEFAULT 0 COMMENT '关联评论数（冗余）',
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

### 1.2 `model_likes` 表

```sql
CREATE TABLE IF NOT EXISTS `model_likes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `model_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `model_user` (`model_id`, `user_id`),
  KEY `idx_model_id` (`model_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 1.3 `products` 表扩展

```sql
ALTER TABLE `products` ADD `model_id` int(11) DEFAULT NULL AFTER `shop_id`;
ALTER TABLE `products` ADD KEY `idx_model_id` (`model_id`);
```

### 1.4 数据关系

```
users(1) ────(1) models(1) ────(N) products
                   └────(N) model_likes(N)────┘ user_id
```

- `models.user_id` UNIQUE → 一个用户对应一个模特档案
- `products.model_id` 可空 → 非模特商品不受影响
- `model_likes` UNIQUE(model_id, user_id) → 一人一赞

## 2. Model 类 (`classes/Model.php`)

### 方法列表

| 方法 | 说明 |
|------|------|
| `getById($id)` | 获取单个模特（JOIN users 取 username/avatar） |
| `getByUserId($userId)` | 根据 user_id 查找 |
| `create($data)` | 创建模特 |
| `update($id, $data)` | 更新模特信息 |
| `delete($id)` | 软删除（status = inactive） |
| `getList($page, $perPage, $search)` | 后台分页列表 |
| `getAll($status)` | 获取全部活跃模特（商品编辑下拉使用） |
| `like($modelId, $userId)` | 点赞/取消点赞（事务更新 model_likes + models.like_count） |
| `isLiked($modelId, $userId)` | 检查是否已点赞 |
| `getModelProducts($modelId, $page, $perPage)` | 模特关联商品列表 |
| `getModelProductImages($modelId, $limit)` | 模特关联商品图片聚合（取 main_image + images JSON） |
| `getProductCount($modelId)` | 关联商品数 |
| `refreshCounts($modelId)` | 重新计算 product_count / review_count |
| `getRanking($type, $limit)` | 排行查询（product_count / like_count / review_count） |

### 点赞事务逻辑

```php
public function like($modelId, $userId) {
    $this->pdo->beginTransaction();
    try {
        $stmt = $this->pdo->prepare(
            "SELECT id FROM model_likes WHERE model_id=? AND user_id=?"
        );
        $stmt->execute([$modelId, $userId]);
        if ($stmt->fetch()) {
            // 取消点赞
            $this->pdo->prepare("DELETE FROM model_likes WHERE model_id=? AND user_id=?")
                 ->execute([$modelId, $userId]);
            $this->pdo->prepare("UPDATE models SET like_count=GREATEST(like_count-1,0) WHERE id=?")
                 ->execute([$modelId]);
            $action = 'unliked';
        } else {
            // 点赞
            $this->pdo->prepare("INSERT INTO model_likes (model_id,user_id) VALUES (?,?)")
                 ->execute([$modelId, $userId]);
            $this->pdo->prepare("UPDATE models SET like_count=like_count+1 WHERE id=?")
                 ->execute([$modelId]);
            $action = 'liked';
        }
        $this->pdo->commit();
        return $action;
    } catch (\Exception $e) {
        $this->pdo->rollBack();
        throw $e;
    }
}
```

## 3. URL 设计

| 页面 | URL |
|------|-----|
| 模特详情 | `https://mall.58.tl/model/{id}-{slug}.html` |
| 模特列表 | `https://mall.58.tl/model/list.php` |

### Nginx rewrite

```nginx
rewrite ^/model/([0-9]+)-.*\.html$ /model/view.php?id=$1 last;
```

### SeoHelper 扩展

```php
public static function modelUrl($id, $nickname) {
    return 'https://mall.58.tl/model/' . intval($id) . '-' . self::slug($nickname) . '.html';
}
```

## 4. 页面设计

### 4.1 Admin 模特管理 (`admin/models.php`)

```
┌──────────────────────────────────────────────────────────┐
│  模特管理                                    [+ 添加模特] │
├──────────────────────────────────────────────────────────┤
│  搜索: [________]  [搜索]                                │
├──────┬────────┬──────┬────┬──────┬────────┬─────────────┤
│  ID  │ 昵称   │ 用户  │性别│ 身高  │ 关联商品│ 操作       │
├──────┼────────┼──────┼────┼──────┼────────┼─────────────┤
│  1   │ 张三   │ zhangs│ 女 │ 168  │   12   │ 编辑 删除  │
│  2   │ 李四   │ lisi  │ 女 │ 172  │    8   │ 编辑 删除  │
└──────┴────────┴──────┴────┴──────┴────────┴─────────────┘
```

添加/编辑表单字段：
- 昵称（必填）
- 站内用户（搜索选择器，必填关联到一个用户）
- 性别（下拉：男/女/保密）
- 年龄
- QQ
- 微信
- 微博
- 小红书
- 身高 cm
- 体重 kg
- 三围
- 爱好（textarea）

### 4.2 商品编辑（`mall/shop/products.php` 改动）

在表单中增加：

```
┌─────────────────────────────────────────┐
│  关联模特:  [▼ 选择模特]  (可选)        │
│            搜索: [________]              │
│            ○ 张三 (zhangsan)            │
│            ○ 李四 (lisi)               │
└─────────────────────────────────────────┘
```

- 加载 `Model::getAll('active')` 获取活跃模特列表
- 保存时 `$_POST['model_id']` → `products.model_id`

### 4.3 商品详情页（`mall/product/detail.php` 改动）

在商品标题/元数据区域增加：

```html
<?php if ($productDetail['model_id']): ?>
<div class="product-model-info">
    <span class="model-label">模特：</span>
    <a href="<?= SeoHelper::modelUrl($productDetail['model_id'], $productDetail['model_nickname']) ?>">
        <?= htmlspecialchars($productDetail['model_nickname']) ?>
    </a>
</div>
<?php endif; ?>
```

需要修改 `Product::getProductById()` SQL，JOIN models 表取 `nickname`。

### 4.4 模特详情页 (`mall/model/view.php`)

```
┌───────────────────────────────────────────────────────────┐
│  面包屑: 首页 > 模特库 > 张三                              │
├──────────────────────┬────────────────────────────────────┤
│                      │                                    │
│   ┌──────────────┐   │  🏷 昵称: 张三                      │
│   │              │   │  👤 用户: zhangsan                  │
│   │   模特头像   │   │  ♀ 性别: 女                        │
│   │              │   │  📏 身高: 168 cm                    │
│   │              │   │  ⚖  体重: 48 kg                     │
│   └──────────────┘   │  📐 三围: 86-60-88                  │
│                      │  🎯 爱好: 拍照、旅行、瑜伽           │
│                      │                                    │
│                      │  📱 社交: [QQ] [微信] [微博] [小红书] │
│                      │                                    │
│                      │  ❤️ [点赞] (125)                     │
│                      │  📦 关联商品: 12 件                  │
├──────────────────────┴────────────────────────────────────┤
│                                                           │
│  📸 作品图集                                               │
│  ┌──────┐ ┌──────┐ ┌──────┐ ┌──────┐                     │
│  │ img1 │ │ img2 │ │ img3 │ │ img4 │  ...                │
│  └──────┘ └──────┘ └──────┘ └──────┘                     │
│                                                           │
│  🛍 关联商品                                               │
│  ┌────────────┐ ┌────────────┐ ┌────────────┐            │
│  │ 商品卡片1  │ │ 商品卡片2  │ │ 商品卡片3  │            │
│  │ [图片]    │ │ [图片]    │ │ [图片]    │            │
│  │ 名称+价格  │ │ 名称+价格  │ │ 名称+价格  │            │
│  └────────────┘ └────────────┘ └────────────┘            │
└───────────────────────────────────────────────────────────┘
```

**SEO 配置**：
```php
$site_config['title']       = SeoHelper::title($model['nickname'] . ' - 58模特库');
$site_config['description'] = SeoHelper::description('模特' . $model['nickname'] . '的专属展示页，查看TA的模特作品与关联商品。');
$site_config['keywords']    = '58,模特,' . $model['nickname'] . ',商城,区块城市';
$site_config['canonical_url'] = SeoHelper::modelUrl($modelId, $model['nickname']);
$site_config['og_image']    = $model['avatar'] ?? 'https://58.tl/assets/images/og-mall.jpg';
$site_config['og_type']     = 'profile';
```

**Person JSON-LD**：
```json
{
  "@context": "https://schema.org",
  "@type": "Person",
  "name": "张三",
  "url": "https://mall.58.tl/model/1-zhangsan.html",
  "gender": "Female",
  "height": {"@type": "QuantitativeValue", "value": 168, "unitCode": "CMT"},
  "weight": {"@type": "QuantitativeValue", "value": 48, "unitCode": "KGM"}
}
```

### 4.5 排行榜（`mall/rankings/index.php` 改动）

新增 "模特排行" tab：

```
[商品排行] [店铺排行] [模特排行]
                         ┌──────────┬──────────┬──────────┐
                         │ 关联商品  │ 点赞数   │ 评论数   │
                         ├──────────┼──────────┼──────────┤
                         │ 张三  12│ 李四  189│ 王五  56│
                         │ 李四   8│ 张三  125│ 张三  42│
                         │ 王五   6│ 赵六   98│ 李四  38│
                         └──────────┴──────────┴──────────┘
```

SQL 查询（无缓存，与现有排名机制一致）：
```sql
-- 关联商品数排行
SELECT m.*, u.username, u.avatar 
FROM models m JOIN users u ON m.user_id = u.id 
WHERE m.status='active' 
ORDER BY m.product_count DESC LIMIT 20

-- 点赞数排行
SELECT m.*, u.username, u.avatar 
FROM models m JOIN users u ON m.user_id = u.id 
WHERE m.status='active' 
ORDER BY m.like_count DESC LIMIT 20

-- 评论数排行
SELECT m.*, u.username, u.avatar 
FROM models m JOIN users u ON m.user_id = u.id 
WHERE m.status='active' 
ORDER BY m.review_count DESC LIMIT 20
```

## 5. 图片聚合逻辑

```php
public function getModelProductImages($modelId, $limit = 50) {
    $stmt = $this->pdo->prepare(
        "SELECT main_image, images FROM products 
         WHERE model_id = ? AND status = 'active' 
         ORDER BY created_at DESC LIMIT ?"
    );
    $stmt->execute([$modelId, $limit]);
    $allImages = [];
    while ($row = $stmt->fetch()) {
        $allImages[] = $row['main_image'];
        if ($row['images']) {
            $decoded = json_decode($row['images'], true);
            if (is_array($decoded)) {
                $allImages = array_merge($allImages, $decoded);
            }
        }
    }
    return array_unique(array_filter($allImages));
}
```

## 6. 计数同步策略

`models.product_count` 和 `models.review_count` 是冗余缓存字段。

**更新时机**：
- 商品创建/删除/状态变更时 → `Model::refreshCounts($modelId)`
- 评价创建/删除时 → `Model::refreshCounts($modelId)`
- `models.like_count` 由点赞事务直接维护

**在 Product 类中集成**：在 `createProduct()` 和 `updateProduct()` 中，如果 `model_id` 有值，调用 `Model::refreshCounts()`。
