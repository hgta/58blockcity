---
comet_change: city-filter-picker
role: technical-design
canonical_spec: openspec
---

# 城市筛选选择器（city-filter-picker 批 1）技术设计

> 需求与验收以 OpenSpec 产物为准：
> `openspec/changes/city-filter-picker/{proposal.md,design.md,specs/city-filter/spec.md,tasks.md}`

## 1. 背景与目标

`cities` 表约 421 城。block 三个列表页的城市筛选下拉取 `getHotCitiesList(20)`（`WHERE is_hot=1 ORDER BY rank LIMIT 20`），与「该城市有无挂牌」脱钩：能选的可能是空城，有数据的城市选不到；后台 `bct/admin/orders.php` 数据源正确（`DISTINCT city FROM bct_orders`）但纯 `<select>` 不可输入搜索。

目标：4 页城市筛选换成可搜索组件——默认只列「该页有数据的城市」（每个点得动），输入可搜全部 421 城（中文/拼音）；服务端对自由输入归一化，消除拼音静默 0 结果；组件沉淀为全站可复用（后续批次约 33 处接入）。

## 2. 改动清单

| 文件 | 改动 |
|---|---|
| `includes/city_picker.php` | 组件升级：4 个新参数、双数据集渲染、3 种 variant、清除按钮 |
| `includes/functions.php` | 新增 `buildCityFilterWhere()` 归一化筛选构造 |
| `block/sale_list.php` | 候选 SQL 换「有挂牌城市」；select → city_picker（filter 变体）；筛选走归一化 |
| `block/purchase_list.php` | 同上（purchase_requests/active） |
| `block/claim_list.php` | 同上（blocks/sold） |
| `bct/admin/orders.php` | DISTINCT 候选 + JOIN cities 取 pinyin/rank；select → city_picker（admin 变体） |
| `auth/register.php`、`auth/wechat_complete.php` | 预期零改动（参数缺省 = 现状），仅回归验证 |

## 3. 组件设计（includes/city_picker.php）

### 3.1 接口

```php
// 现有参数不动
$cityPickerName  = 'city';
$cityPickerValue = '';
$cityPickerId    = 'cityPicker';
$cityOptions     = [];      // 搜索全集：getAllCities() 行（name/pinyin/is_hot）

// 新增（全部可选）
$cityPickerCandidates = null;        // 默认展示子集，同结构；null = 展开即全量（注册页现状）
$cityPickerVariant    = 'default';   // 'default' | 'filter' | 'admin'
$cityPickerAllowClear = false;       // true 显示 ✕ 清除按钮
$cityPickerPlaceholder = '选择或输入城市';
```

### 3.2 渲染逻辑（唯一行为分叉）

```
render(keyword):
  keyword == '' 且 candidates 非空:
      渲染 candidates（is_hot 的 chips 置顶 ≤12）
      foot = 「默认 N 个有数据城市，输入可搜全部 M 个」
  其它情况:
      与现状完全一致——从全集 $cityOptions 过滤（中文包含 + 拼音包含）
      foot = 「匹配 X 个城市」（现状文案）
```

键盘（↑↓/Enter/Esc）、点击外部关闭、聚焦/点击即展开（兼容微信键盘）逻辑零改动。

### 3.3 CSS 变体（内联，不新增文件）

```
.city-picker                现有样式原样保留（default 亮色）
.city-picker.cp-filter      padding/高度对齐 .filter-bar 内 <select>（~38px、font 14px）
.city-picker.cp-admin       bg=var(--admin-bg-light)、border=var(--admin-border)、
                            text=var(--admin-text)、panel=var(--admin-card)、
                            hover/active=var(--admin-accent)
.cp-clear                   ✕ 按钮（absolute 右侧，allowClear 时显示）；
                            点击清空输入 = 「全部城市」语义（city 参数为空）
```

## 4. 服务端归一化（includes/functions.php）

```php
/**
 * 城市筛选 WHERE 构造：normalizeCityName() 归一化优先 + 双列 LIKE 兜底。
 * 返回 ['where' => SQL 片段, 'params' => 绑定参数, 'city' => 归一化后的标准名（供回显）]
 */
buildCityFilterWhere(string $input, array $allCities): array
```

内部流程：

1. `normalizeCityName($input, $allCities)`——拼音 `hangzhou`/别名「武汉市」/大小写 → cities 标准名；未命中原样返回
2. 产出 `(c.name LIKE ? OR c.pinyin LIKE ?)` 同参双列（现状仅 `c.name LIKE ?`，双列是超集，老行为保留）
3. 归一化后的标准名用于输入框回显（`$filterCity` 初始化）

页面接入：

- block 三页：`$allCities` 复用已查的全量（`getAllCities()`，组件也用它），一次查询两用
- `bct/admin/orders.php`：`bct_orders` 无 pinyin 列，用归一化结果做单列 `o.city LIKE ?`

## 5. 候选数据源 SQL

```sql
-- sale_list.php
SELECT c.name, c.pinyin, c.is_hot FROM cities c
WHERE c.id IN (SELECT DISTINCT l.city_id FROM block_listings l WHERE l.status='listed')
ORDER BY c.rank;

-- purchase_list.php：子查询 → purchase_requests pr WHERE pr.status='active'
-- claim_list.php：  子查询 → blocks b WHERE b.status='sold'

-- bct/admin/orders.php（保留 DISTINCT 口径）
SELECT o.city AS name, c.pinyin, COALESCE(c.is_hot, 0) AS is_hot
FROM (SELECT DISTINCT city FROM bct_orders) o
LEFT JOIN cities c ON o.city COLLATE utf8mb4_unicode_ci = c.name COLLATE utf8mb4_unicode_ci
ORDER BY COALESCE(c.rank, 999999), o.city;
```

要点：各页内联候选 SQL（口径不同，显式优于抽象）；COLLATE 对齐规避 `#1267 Illegal mix of collations`（`init/migration-bct-price-history.sql:18` 先例）；不在 cities 的脏数据城市照常展示（pinyin 留空，组件兼容 `p` 为空）。

## 6. 数据流

```
页面 PHP ──┬─ getAllCities() ──────────────► $cityOptions（搜索全集，内联 JSON ~13KB）
           ├─ 候选 DISTINCT SQL ──────────► $cityPickerCandidates（默认展示）
           └─ buildCityFilterWhere() ────► WHERE（归一化 + 双列 LIKE）

用户输入 hangzhou 未点选 → GET city=hangzhou
  → normalizeCityName → 「杭州」→ c.name LIKE '%杭州%' → 命中 + 回显「杭州」
```

## 7. 测试策略

| 层 | 手段 | 覆盖 |
|---|---|---|
| 语法 | `php -l` 全部改动文件 | — |
| 功能 | delta spec 12 场景逐条走查（本地 + 线上 block.58.tl 四入口） | 候选完整性、拼音/别名提交、清除回全部、URL 兼容 |
| 回归 | 注册页/微信补全页不传新参数 | 展开全量、搜索、归一化落库不变 |
| 性能 | EXPLAIN 四页候选查询；核验 4 处索引 | 无慢查询 |
| 环境 | 微信内置浏览器（X5/WKWebView） | 聚焦展开、键盘操作 |

## 8. 风险与缓解

- **DISTINCT 性能** → 索引核验入任务 4.1；缺失则幂等迁移 SQL 单独 commit
- **排序规则不一致 #1267** → JOIN 显式 COLLATE（第 5 节）
- **组件改动回归注册页** → 参数缺省即旧行为 + 回归任务
- **无数据城市点选** → 沿用各页空态区块，文案「该城市暂无在售挂牌」等

## 9. 明确不做（本批）

其余约 33 处城市入口（批 3-5）、硬编码数组清理、AJAX 城市搜索、cities 表非地名条目过滤。
