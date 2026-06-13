# Block 子站点改造设计方案

## 一、架构决策

### 1.1 全景模式渲染方案：后端生成 PNG 热力图

**决策**：不用 Canvas 前端实时绘制，改为**后端 PHP 生成 PNG 热力图**。

**理由**：
- 区块数据变化频率低（用户认领才变），完全适合服务端缓存。
- PNG 体积极小（9×9 像素放大到 300×300 只需几 KB），首屏加载快。
- 避免 9 万个 DOM 节点和前端绘制开销。
- 实现简单，不需要引入前端 canvas 绘制库。

**实现方式**：
```
PHP GD 库 → 绘制 99×101 像素位图 → 每个像素代表一个区块颜色
→ 输出 PNG → 浏览器 <img> 放大显示（image-rendering: pixelated）
```

颜色映射：
| 状态 | 颜色 |
|------|------|
| 可认领 | `#e8f5e8` (浅绿) |
| 已认领 | `#ff6b00` (橙) |
| 合并区块 | `#1976d2` (蓝) |
| 跨区边界可认领 | `#a5d6a7` (深绿) |
| 跨区边界已认领 | `#ff9800` (深橙) |

缓存策略：文件名包含 `city_id` 和 `max(updated_at)` 时间戳，如 `heatmap_3_1699876543.png`。Nginx/Apache 直接命中静态文件，PHP 只在数据变更时重新生成。

### 1.2 单区地图：保留现有 DOM 方案 + 虚拟滚动增强

**决策**：单区 9,999 个 cell 保留现有 DOM 方案（因为需要逐个 cell 的点击交互），但增加**虚拟滚动容器**和**移动端列表模式切换**。

**理由**：
- 单区模式用户需要点击具体区块，canvas 像素图的点击映射复杂度高。
- 9,999 个 DOM 在桌面端现代浏览器可接受。
- 通过 `overflow: auto` + 固定行列头（sticky）优化体验。

**移动端兜底**：当视口宽度 < 768px 时，地图下方增加"切换为列表模式"按钮，将 99×101 网格转为按行/列筛选的区块列表，避免 20px cell 的误触灾难。

### 1.3 列表页统一组件化

将 sale/claim/purchase 三列表的各自内嵌 `<style>` 提取到 `assets/css/main.css` 中，统一为 `.block-list-page` 组件系统：

```
.block-list-page
├── .list-header（标题 + 筛选器 + 发布按钮）
├── .list-grid（卡片网格）
│   └── .list-card（统一卡片样式）
├── .list-empty（空状态）
└── .list-pagination（分页导航）
```

筛选器通过 GET 参数传递，PHP 端统一处理。

### 1.4 首页数据面板

在 `block/index.php` 的"元宇宙特色"上方插入 `platform-stats-bar`，从数据库实时查询：

```php
$stats = [
    'total_cities'    => City::count(),           // 城市总数
    'activated_blocks'=> Block::countActivated(), // 已激活区块
    'total_users'     => User::count(),           // 注册用户数
    'bct_circulation' => BCT::totalSupply(),      // BCT 流通量（可选）
];
```

展示形式：4 列图标+数字卡片，带动画计数效果（`countUp.js` 或简单 JS）。

## 二、数据流与接口

### 2.1 新增/修改的数据库查询方法

在 `classes/Block.php` 中新增：

```php
/**
 * 获取指定城市的全景热力图数据
 * 返回每个区的 sold_count、merged_numbers 等聚合信息
 */
public function getCityPanoramaData(int $cityId): array;

/**
 * 获取在售区块列表（真正在售，非已售历史）
 * 假设字段：is_listed = 1, list_price > 0
 */
public function getListedBlocks(array $filters, int $page, int $perPage): array;

/**
 * 统计已激活区块总数
 */
public static function countActivated(): int;
```

在 `classes/City.php` 中新增：
```php
public static function count(): int;
```

### 2.2 热力图生成接口

新增 `block/api/heatmap.php`：
```php
GET /block/api/heatmap.php?city={pinyin}&t={timestamp}
→ 输出 PNG 图片
→ 若缓存文件存在且未过期，直接 readfile
→ 否则 PHP GD 生成并写入缓存目录
```

### 2.3 筛选参数规范

三列表统一支持：
```
?page=1              // 分页
&city=beijing       // 按城市筛选
&zone=A             // 按区域筛选
&min_price=100      // 最低价格
&max_price=10000    // 最高价格
```

## 三、UI/UX 详细设计

### 3.1 首页 (`block/index.php`)

```
┌─────────────────────────────────────────────┐
│ Header (含统一城市定位条)                    │
├─────────────────────────────────────────────┤
│ Platform Stats Bar                          │
│ [🏙️ 200+ 城市] [🧱 89万+ 激活] [👥 1.2万用户] │
├─────────────────────────────────────────────┤
│ 搜索框：🔍 输入城市名快速定位...             │
├─────────────────────────────────────────────┤
│ 字母导航：A B C D E F G H J K L M N P Q...  │
│ (居中 flex，胶囊按钮，active=橙色底白字)      │
├─────────────────────────────────────────────┤
│ 热门城市网格 (保持现有)                      │
├─────────────────────────────────────────────┤
│ 城市按字母分组列表 (保持现有)                 │
├─────────────────────────────────────────────┤
│ 元宇宙特色 (保持现有)                        │
├─────────────────────────────────────────────┤
│ Footer                                      │
└─────────────────────────────────────────────┘
```

**字母导航样式**（写入 `assets/css/main.css`）：
```css
.letter-nav { background: #fff; padding: 12px 0; border-bottom: 1px solid #eee; }
.letter-nav-container { display: flex; flex-wrap: wrap; justify-content: center; gap: 6px; max-width: 1200px; margin: 0 auto; padding: 0 15px; }
.letter-link { display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px; border-radius: 50%; background: #f5f5f5; color: #666; font-size: 13px; font-weight: bold; transition: all .2s; }
.letter-link:hover, .letter-link.active { background: #ff6b00; color: white; text-decoration: none; }
```

**城市搜索**：前端 JS 过滤，输入时实时高亮匹配城市并滚动到对应字母区。无后端请求。

### 3.2 城市页全景模式 (`city.php?view_mode=panorama`)

```
┌─────────────────────────────────────────────┐
│ 城市标题栏（渐变背景）                        │
│ 北京区块城市 | 居民: 2,100万 | 已激活: 1,203  │
├─────────────────────────────────────────────┤
│ 区域选择器：🏙️全城 A区 B区 C区 D区... Z区      │
├─────────────────────────────────────────────┤
│ 九区全景                                    │
│ ┌────────┬────────┬────────┐               │
│ │  A区   │  B区   │  C区   │  每区卡片     │
│ │[热力图]│[热力图]│[热力图]│  + 统计文字   │
│ ├────────┼────────┼────────┤               │
│ │  D区   │  E区   │  F区   │               │
│ │[热力图]│[热力图]│[热力图]│               │
│ ├────────┼────────┼────────┤               │
│ │  G区   │  H区   │  Z区   │               │
│ │[热力图]│[热力图]│[热力图]│               │
│ └────────┴────────┴────────┘               │
│ 图例 + 跨区合并提示                          │
└─────────────────────────────────────────────┘
```

热力图 `<img>` 样式：
```css
.pano-heatmap { width: 100%; height: 120px; object-fit: cover; image-rendering: pixelated; border-radius: 6px; }
```

### 3.3 单区详细模式 (`city.php?view_mode=zone`)

**桌面端**：保持现有 99×101 网格 + sticky 行列头。

**移动端 (< 768px)**：
增加"📋 列表模式"切换按钮。列表模式按行/列展示可认领区块，每行显示：区块号 | 状态 | 价格 | 操作按钮。

### 3.4 售卖列表 (`sale_list.php`)

```
┌─────────────────────────────────────────────┐
│ <i class="fas fa-tag"></i> 售卖中的区块      │
│ [发布售卖]  [城市▼] [区域▼] [价格▼]         │
├─────────────────────────────────────────────┤
│ ┌────────────┐ ┌────────────┐               │
│ │ 北京 #0101 │ │ 上海 #0502 │  卡片网格      │
│ │ ¥1,286     │ │ ¥2,500     │  2列→1列(移动)│
│ └────────────┘ └────────────┘               │
├─────────────────────────────────────────────┤
│ < 1  2  3  4  5 >                           │
└─────────────────────────────────────────────┘
```

### 3.5 求购列表 (`purchase_list.php`)

在标题右侧增加"发布求购"按钮，跳转至新增页面 `purchase_create.php`（或复用现有表单逻辑）。

## 四、安全设计

- `sale_list.php` LIMIT 参数严格使用 `intval()` + 参数绑定。
- 所有表单操作（发布求购、发布售卖）验证 CSRF token（`shared/header.php` 已提供）。
- 价格筛选参数使用 `filter_input(INPUT_GET, 'min_price', FILTER_VALIDATE_FLOAT)`。

## 五、性能设计

| 优化点 | 措施 |
|--------|------|
| 热力图 | 后端 PNG 缓存，Nginx 直接服务静态文件 |
| 列表分页 | LIMIT + OFFSET，单页 20 条 |
| 首页统计 | 独立轻量 COUNT 查询，不走复杂 JOIN |
| 城市搜索 | 纯前端过滤，0 后端请求 |
| 单区地图 | 桌面端保留 DOM，移动端降级列表模式 |

## 六、响应式断点

| 断点 | 布局调整 |
|------|---------|
| >= 1200px | 全景 3 列，列表 2 列，地图 cell 30px |
| 768px - 1199px | 全景 2 列，列表 1 列，地图 cell 25px |
| < 768px | 全景 1 列，列表 1 列，地图默认列表模式 |
| < 576px | 字母导航每行 8 个，统计卡片 2 列 |
