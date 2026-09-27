# 模特库发现页 — 设计文档

## 1. 数据模型

### 1.1 新增 `model_follows` 表

```sql
CREATE TABLE IF NOT EXISTS `model_follows` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `model_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `model_user` (`model_id`, `user_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_model_id` (`model_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 1.2 `models.follower_count` 改造

现有字段为 `varchar(20)` 且为手填（可能含 "1.2w" 等非数值），不可靠排序。

```sql
-- 改为 INT 并由关注行为维护；旧手填值不可信，统一重置为 0（实际以 follow 表为准）
ALTER TABLE `models` MODIFY COLUMN `follower_count` int(11) NOT NULL DEFAULT 0;
UPDATE `models` SET `follower_count` = 0;
```

> 关注数以 `model_follows` 实时计数维护，与现有 `like_count` 机制一致。

### 1.3 关系

```
users(1) ──(N) model_follows (N)──(1) models
models(1) ──(N) products              （已有，图集来源）
models(1) ──(1) daily_photos(JSON)    （已有，日常照片）
```

## 2. Model 类新增方法

| 方法 | 说明 |
|------|------|
| `follow($modelId, $userId)` | 关注/取消（事务：写 `model_follows` + 维护 `follower_count`），返回 `followed`/`unfollowed` |
| `isFollowed($modelId, $userId)` | 是否已关注 |
| `getFollowedModels($userId, $page, $perPage)` | 用户中心「我的关注」列表 |
| `getFilteredList($filters, $page, $perPage)` | 发现页叠加筛选 |
| `getFacets()` | 城市 / 星座 去重 + 计数，喂筛选 chips |
| `getRelated($modelId, $limit)` | 详情页「相关模特」 |

### 2.1 follow 事务（参照 like）

```php
public function follow($modelId, $userId) {
    $modelId = intval($modelId); $userId = intval($userId);
    $this->pdo->beginTransaction();
    try {
        $stmt = $this->pdo->prepare("SELECT id FROM model_follows WHERE model_id=? AND user_id=?");
        $stmt->execute([$modelId, $userId]);
        if ($stmt->fetch()) {
            $this->pdo->prepare("DELETE FROM model_follows WHERE model_id=? AND user_id=?")->execute([$modelId,$userId]);
            $this->pdo->prepare("UPDATE models SET follower_count=GREATEST(follower_count-1,0) WHERE id=?")->execute([$modelId]);
            $action='unfollowed';
        } else {
            $this->pdo->prepare("INSERT INTO model_follows (model_id,user_id) VALUES (?,?)")->execute([$modelId,$userId]);
            $this->pdo->prepare("UPDATE models SET follower_count=follower_count+1 WHERE id=?")->execute([$modelId]);
            $action='followed';
        }
        $this->pdo->commit(); return $action;
    } catch (Exception $e) { $this->pdo->rollBack(); throw $e; }
}
```

### 2.2 getFilteredList（叠加筛选核心）

```php
public function getFilteredList($filters = [], $page = 1, $perPage = 24) {
    $where = ["m.status='active'"];
    $params = [];
    if (!empty($filters['gender']) && in_array($filters['gender'], ['男','女','保密'])) {
        $where[] = "m.gender = ?"; $params[] = $filters['gender'];
    }
    if (!empty($filters['city'])) { $where[] = "m.city = ?"; $params[] = $filters['city']; }
    if (!empty($filters['zodiac'])) { $where[] = "m.zodiac = ?"; $params[] = $filters['zodiac']; }
    if (!empty($filters['q'])) { $where[] = "m.nickname LIKE ?"; $params[] = "%{$filters['q']}%"; }

    $sortMap = [
        'follower' => 'm.follower_count DESC',
        'like'     => 'm.like_count DESC',
        'product'  => 'm.product_count DESC',
        'new'      => 'm.created_at DESC',
    ];
    $orderBy = $sortMap[$filters['sort'] ?? 'follower'] ?? 'm.follower_count DESC';

    $offset = (max(1,intval($page))-1)*$perPage;
    $sql = "SELECT m.*, u.username, u.avatar as user_avatar
            FROM models m LEFT JOIN users u ON m.user_id=u.id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY {$orderBy}
            LIMIT {$perPage} OFFSET {$offset}";
    $stmt = $this->pdo->prepare($sql); $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
```

> 排序键白名单化，避免注入。`city`/`zodiac` 来自受控 chips，仍用参数绑定。

### 2.3 getFacets

```php
public function getFacets() {
    $cities = $this->pdo->query(
        "SELECT city, COUNT(*) c FROM models WHERE status='active' AND city IS NOT NULL AND city<>'' GROUP BY city ORDER BY c DESC"
    )->fetchAll(PDO::FETCH_ASSOC);
    $zodiacs = $this->pdo->query(
        "SELECT zodiac, COUNT(*) c FROM models WHERE status='active' AND zodiac IS NOT NULL AND zodiac<>'' GROUP BY zodiac ORDER BY c DESC"
    )->fetchAll(PDO::FETCH_ASSOC);
    return ['cities' => $cities, 'zodiacs' => $zodiacs];
}
```

### 2.4 getRelated

```php
public function getRelated($modelId, $limit = 6) {
    $m = $this->getById($modelId);
    if (!$m) return [];
    $sql = "SELECT m.id, m.nickname, m.gender, m.city, m.zodiac, m.follower_count, m.like_count, m.product_count, u.avatar as user_avatar
            FROM models m LEFT JOIN users u ON m.user_id=u.id
            WHERE m.status='active' AND m.id<>?
              AND (m.gender=? OR m.city=? OR m.zodiac=?)
            ORDER BY ((m.city=?) + (m.zodiac=?) + (m.gender=?)) DESC, m.follower_count DESC
            LIMIT " . intval($limit);
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute([$modelId, $m['gender'], $m['city'], $m['zodiac'], $m['city'], $m['zodiac'], $m['gender']]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
```

## 3. 发现页 `/model/list.php`

### 3.1 布局

```
┌──────────────────────────────────────────────────────────┐
│ 面包屑 首页 > 模特库                                        │
├──────────────────────────────────────────────────────────┤
│ 搜索[昵称] │ 性别●全部●男●女                                │
│ 城市: 北京 上海 广州 … (chips, 带计数)                     │
│ 星座: 12 宫 chips                                          │
│ 排序: 🔥粉丝 | 人气 | 作品数 | 最新                          │
├──────────────────────────────────────────────────────────┤
│ 卡片网格 (responsive, 每卡展示: 头像 + 图集缩略条(≤4) +    │
│         昵称·♀女·📍城市·★星座 + ❤赞 👥粉丝 📦作品 + [关注]) │
│                        ↓ AJAX 加载更多 (history.replaceState 同步 ?page=) │
└──────────────────────────────────────────────────────────┘
```

### 3.2 筛选与 URL

- 所有筛选状态写入 query string：`?gender=女&city=北京&zodiac=天蝎&sort=follower&q=`
- 切换筛选 → `history.replaceState` 更新 URL（可分享/刷新保持）
- 加载更多：AJAX 请求 `list.php?...&page=N&ajax=1` 返回 HTML 片段追加；同时 `replaceState` 同步 `?page=`
- 卡片图区：头像为主，下方一条 1–4 张缩略图（取 `getModelProductImages($id, 4)` + `daily_photos` 前几张）；无图则仅头像
- 无作品模特：作品数显示「—」，仍正常展示（遵循决策 A）

### 3.3 卡片内关注（AJAX）

- 已登录：点击 → `POST follow` → 切换「+关注 / 已关注」+ 粉丝数动画
- 未登录：点击 → 跳 `auth/login.php?redirect=...`

## 4. 详情页「相关模特」+ 关注按钮

- `mall/model/view.php` 底部新增「相关模特」网格，调用 `getRelated()`
- 详情页头部关注按钮（复用 `follow()`，AJAX 或表单 POST 兜底）

## 5. 用户中心「我的关注」`mall/user/following.php`

- 登录后列出 `getFollowedModels($userId)`：模特卡 + 取消关注
- 导航/个人中心入口

## 6. 导流与 SEO

- `mall/includes/header.php` 导航加「模特库」链接到 `/model/list.php`
- `mall/index.php` 首页加「人气模特」横滑模块（取 `getRanking('follower_count'|'like_count', N)`）
- `SeoHelper`：发现页 `<link rel="canonical" href="https://mall.58.tl/model/list.php">`（过滤页均指回基础 URL，防重复收录）
- `sitemap.php`：增加 `https://mall.58.tl/model/list.php`（仅基础 URL）

## 7. 计数同步策略

- `follower_count` 仅由 `follow()`/`unfollow()` 事务维护（同 `like_count`）
- 不在商品/评价事件中改动，避免与点赞数混淆
