# NFT 售卖页重新设计 — 技术设计

## 一、后端 SQL 修改 (classes/NFT.php)

### 1.1 getSaleList() 修复

**当前 SQL**:
```sql
SELECT n.id AS nft_id, n.code, n.base_image, t.price, t.currency, t.created_at, u.username AS seller_name
FROM nft_avatars n
JOIN nft_transactions t ON n.id = t.nft_id
JOIN users u ON t.seller_id = u.id
WHERE t.status = 'listed'
```

**修改后**:
```sql
SELECT
    n.id AS nft_id, n.code, n.base_image,
    t.price, t.currency, t.created_at,
    u.username AS seller_name,
    c.id AS city_id, c.name AS city_name,
    (SELECT COUNT(*) FROM nft_purchase_requests WHERE nft_id = n.id AND status = 'active') AS purchase_count
FROM nft_avatars n
JOIN nft_transactions t ON n.id = t.nft_id
JOIN users u ON t.seller_id = u.id
JOIN cities c ON t.city_id = c.id
WHERE t.status = 'listed'
```

新增 JOIN `cities`，新增子查询统计求购数，新增 city 筛选条件逻辑：
```php
if (!empty($city)) {
    $conditions[] = "c.name = ?";
    $params[] = $city;
}
```

移除 `rare` 排序（字段不存在），改为新增 `purchase_count` 排序：
```php
case 'hot':
    $sql .= " ORDER BY purchase_count DESC, t.created_at DESC";
    break;
```

### 1.2 getTotalSaleCount() 修复

同步 JOIN cities，添加 city 筛选支持：
```php
if (!empty($city)) {
    $conditions[] = "c.name = ?";
    $params[] = $city;
}
```

### 1.3 删除 getAllRarities()

此方法返回 `['common','rare','epic','legendary']`，但在售卖场景中无意义。保留方法本身不删除（其他页面可能引用），仅从前端移除调用。

---

## 二、前端页面重写 (nft/nft/sale_list.php)

### 2.1 页面结构

```
┌──────────────────────────────────────────┐
│ 顶部统计栏（橙色渐变底）                   │
│  N个在售  ·  M个城市有挂售               │
├──────────────────────────────────────────┤
│ 水平筛选栏                                │
│  [搜索编号] [城市▼] [货币▼] [排序▼]      │
│  [筛选] [重置]                           │
├──────────────────────────────────────────┤
│ NFT 卡片网格                              │
│  ┌──────┐ ┌──────┐ ┌──────┐ ┌──────┐    │
│  │  ○   │ │  ○   │ │  ○   │ │  ○   │    │
│  │ SVG  │ │ SVG  │ │ SVG  │ │ SVG  │    │
│  │ AB01 │ │ XY23 │ │ CD45 │ │ EF67 │    │
│  │📍北京│ │📍上海│ │📍深圳│ │📍杭州│    │
│  │Ⓟ3500│ │ ¥99  │ │Ⓟ8200│ │ ¥45  │    │
│  │@user │ │@user │ │@user │ │@user │    │
│  │🔥12  │ │🔥5   │ │🔥30  │ │      │    │
│  └──────┘ └──────┘ └──────┘ └──────┘    │
├──────────────────────────────────────────┤
│ 分页导航（带省略号）                      │
└──────────────────────────────────────────┘
```

### 2.2 卡片 CSS 设计

```css
/* 自适应网格: 最小 160px 列 */
.sale-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
    gap: 16px;
}

/* 卡片样式 */
.sale-card {
    background: #fff;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    transition: transform 0.2s, box-shadow 0.2s;
    cursor: pointer;
    text-decoration: none;
    color: inherit;
    display: block;
}
.sale-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.12);
}

/* 圆形头像区 */
.sale-avatar-wrap {
    display: flex;
    justify-content: center;
    padding: 16px 0 8px;
}
.sale-avatar-circle {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    border: 3px solid #ff6b00;
    padding: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f8f9fa;
}
.sale-avatar-circle img {
    max-width: 85%;
    max-height: 85%;
    object-fit: contain;
    border-radius: 50%;
}

/* 城市标签 */
.sale-city {
    display: inline-block;
    font-size: 12px;
    color: #6c757d;
    background: #f0f0f0;
    padding: 2px 8px;
    border-radius: 10px;
    margin: 4px 0;
}

/* 价格 */
.sale-price {
    font-size: 18px;
    font-weight: 800;
    color: #ff6b00;
    background: linear-gradient(135deg, #ff6b00, #ff8c00);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}

/* 求购热度 */
.sale-hot {
    font-size: 11px;
    color: #e74c3c;
    font-weight: 600;
}
```

### 2.3 筛选栏设计

水平单行布局，使用 flexbox，移动端自动换行：

```html
<div class="sale-filters">
    <input type="text" name="code" placeholder="🔍 搜索编号..." />
    <select name="city">...</select>
    <select name="currency">...</select>
    <select name="sort">...</select>
    <button type="submit">筛选</button>
    <a href="sale_list.php">重置</a>
</div>
```

排序选项变更：
- `newest` → 最新上架
- `price_asc` → 价格从低到高
- `price_desc` → 价格从高到低
- `hot` → 求购热度（新增）

### 2.4 分页设计

仿照 `admin-pagination` 风格，带省略号窗口：

```
◀ 1  ...  4  5  [6]  7  8  ...  15 ▶
```

页码范围 = 当前页 ± 2，首尾页始终显示，中间用 `…` 省略。

---

## 三、文件变更清单

| 操作 | 文件 | 说明 |
|------|------|------|
| 修改 | `classes/NFT.php` | getSaleList() / getTotalSaleCount() SQL 重写：JOIN cities，加 purchase_count，启用 city=筛选 |
| 重写 | `nft/nft/sale_list.php` | 全页面新布局、新样式、新筛选栏、新分页 |

总计 **2 个文件**，无新增文件。
