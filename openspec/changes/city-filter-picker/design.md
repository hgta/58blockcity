# Design：城市筛选选择器（city-filter-picker 批 1）

## Context

现状四张「城市筛选」入口的数据源与控件形态：

| 页面 | 数据源 | 控件 | 问题 |
|---|---|---|---|
| `block/sale_list.php:76` | `getHotCitiesList(20)` → `cities WHERE is_hot=1 ORDER BY rank LIMIT 20` | `<select>` | is_hot 与有无挂牌脱钩：能选的可能是空的，有挂牌的选不到 |
| `block/purchase_list.php:49` | 同上 | `<select>` | 同构问题 |
| `block/claim_list.php:49` | 同上 | `<select>` | 同构问题 |
| `bct/admin/orders.php:161` | `SELECT DISTINCT city FROM bct_orders`（✅ 语义正确完备） | `<select>` | 只差可输入搜索 |

三页的服务端筛选均为 `c.name LIKE '%x%'`（包含匹配），用户输入拼音会静默 0 结果。

已存在的可复用资产：
- `includes/city_picker.php`：纯原生 JS 可搜索选择器（中文/拼音包含匹配、聚焦即展开兼容微信键盘、MAX_RENDER=300、热门 chips），目前仅 `auth/register.php:138`、`auth/wechat_complete.php:205` 使用
- `includes/functions.php:119` `normalizeCityName()`：把拼音/别名（如「武汉市」）映射回 cities 标准名，注册页在用
- `assets/css/admin.css`：`--admin-*` CSS 变量（深色后台主题）
- `classes/City.php`：`getAllCities()`（全量 421，含 name/pinyin/is_hot/rank）、`getCityByName()`（精确→去后缀→前缀模糊）

约束：
- 全站业务表以「城市名 varchar(50)」为键（`bct_orders.city`、`tasks.city`、`posts.city`）；`block_listings`/`purchase_requests`/`blocks` 用 `city_id`
- 静态资源版本惯例 `?v=YYYYMMDD`（仅适用于外部文件）
- 组件必须继续兼容注册页现有调用（零改动回归）

## Goals / Non-Goals

**Goals:**
- 4 个页面的城市筛选：下拉默认只列「该页有数据的城市」（每个都点得动），输入框可搜全部 421 城（中文名/拼音）
- 用户输入拼音/别名未点选直接提交时服务端能正确命中（消除静默 0 结果）
- 组件可复用：前台筛选变体 + 深色后台变体，供后续批次（约 33 处）机械接入
- 注册页/微信补全页零回归

**Non-Goals:**
- 不动其余 ~33 处城市选择入口（批 3-5 后续 change）
- 不清理硬编码城市数组（hufang 94 城、bct 11/6 城）
- 不做 AJAX/API 城市搜索接口（`api/` 现无此约定）
- 不改 cities 表数据、不过滤非地名条目（中国数藏等，有真实挂牌数据，保留展示）
- 不改 `block_listings` 等表结构（除非任务中确认索引缺失才补索引）

## Decisions

### D1. 组件保持单文件内联，不抽外部 JS/CSS

`city_picker.php` 继续 `include` 使用，CSS/JS 内联输出。
理由：搜索全集 JSON（~13KB）本来就必须内联（避免异步首屏无候选）；内联组件免掉 `?v=` 版本号与缓存管理；只有用到组件的页面才付出这笔字节。
备选（否决）：抽 `assets/js/city-picker.js` + 惰性异步拉全量——微信内必须打字才出候选，体验退化，且引入新 API 约定。

### D2. 组件参数扩展（向后兼容）

```php
// 新增（全部可选，默认行为 = 现状）
$cityPickerCandidates = null;   // 默认展示的候选子集 [['name'=>..,'pinyin'=>..,'is_hot'=>..], ...]
                                 // null 时展开即全量 $cityOptions（注册页现状）
$cityPickerVariant     = 'default'; // 'default' | 'filter' | 'admin'
$cityPickerAllowClear  = false;     // true 时输入框右侧显示 ✕ 清除按钮（筛选语义）
$cityPickerPlaceholder = '选择或输入城市';
```

- **双数据集**：`$cityOptions` = 搜索全集（全量 cities）；`$cityPickerCandidates` = 默认展开列表（该页有数据的城市）。空关键字时渲染 candidates（含热门 chips 置顶），输入后从全集过滤——脚注显示「默认 N 个有数据城市 · 输入可搜全部 M 个」
- **variant**：`filter` 收窄 padding/高度对齐 `.filter-bar select`；`admin` 的 CSS 全部改用 `var(--admin-bg)`/`var(--admin-border)`/`var(--admin-text)` 等变量（该文件被 `shared/admin/admin-header.php` 引入的页面自动获得深色主题）
- **allowClear**：清空输入并置回「全部城市」语义（GET 参数 city 为空）
- 注册页/微信补全页不传新参数，行为与现在完全一致

### D3. 候选数据源 SQL（「该页有数据的城市」）

| 页面 | 候选 SQL（示意） |
|---|---|
| `sale_list.php` | `SELECT c.name, c.pinyin, c.is_hot FROM cities c WHERE c.id IN (SELECT DISTINCT l.city_id FROM block_listings l WHERE l.status='listed') ORDER BY c.rank` |
| `purchase_list.php` | 同构，子表 `purchase_requests pr WHERE pr.status='active'` |
| `claim_list.php` | 同构，子表 `blocks b WHERE b.status='sold'` |
| `bct/admin/orders.php` | 保留 `SELECT DISTINCT city FROM bct_orders`，LEFT JOIN cities 取 pinyin/rank（不在 cities 的脏数据城市仍展示，pinyin 留空——组件已兼容 `p` 为空） |

搜索全集统一用 `City::getAllCities()`（421 条，`ORDER BY rank`）。

### D4. 服务端筛选归一化（消除拼音静默 0 结果）

三层依次尝试，输入即用户自由文本（组件值或手输）：

1. **归一化优先**：`normalizeCityName($input, $allCities)` 命中标准名（处理「武汉市→武汉」、全拼音 `hangzhou`→`杭州`、大小写）→ 用标准名做 `c.name LIKE '%名%'`
2. **双列兜底**：未命中时 `(c.name LIKE ? OR c.pinyin LIKE ?)` 同参——拼音输入落到 pinyin 列
3. 仍未命中 → 按原样包含匹配（保留现在「输入子串也能搜」的行为）

保持**包含匹配**而非前缀/精确：与三页现状一致，输入「州」看到多城是可接受的宽松搜索语义；组件候选列表本身提供了精确入口。

`bct/admin/orders.php` 的 `city` 是 varchar 名（与 `cities.name` 同为 utf8mb4，见 `init/migration-bct-price-history.sql:18` 的排序规则对齐要求），归一化逻辑同三页。

### D5. 不改动的部分

- `getHotCitiesList()` 保留（其他页面还在用，批 3+ 再清理）
- 注册页/微信补全页调用方式不动
- 各页 GET 参数名（`city`）与分页/其它筛选参数不动，URL 兼容（已收录的分页链接不失效）

## Risks / Trade-offs

- **[DISTINCT 子查询性能]** → 任务中确认 `block_listings.city_id`、`purchase_requests.city_id`、`blocks.city_id`、`bct_orders.city` 索引；缺则补。量级评估：挂牌/求购/订单均为千级，可承受
- **[bct_orders.city 与 cities.name 排序规则不一致 → #1267 Illegal mix of collations]** → JOIN 时按 migration 先例显式 `COLLATE utf8mb4_unicode_ci` 对齐
- **[搜到「无数据城市」点选后空列表]** → 候选外城市的搜索结果页显示空态文案「该城市暂无在售挂牌」而非裸空白（沿用各页现有空态区块）
- **[组件改动引入注册页回归]** → 任务含注册页/微信补全页回归验证；参数默认值即旧行为
- **[内联 JSON 体积 +13KB/页]** → 仅 4 页生效；与注册页现状等量，可接受
- **[GET 值可能是拼音/别名]** → D4 归一化同时作用于 `$filterCity` 初始化（当前选中值回显用标准名）

## Migration Plan

1. 组件升级（D2）→ 注册页回归 → 三个 block 页接入（D3+D4）→ bct 后台接入
2. 无表结构变更（索引补充如有则随迁移 SQL 单独提供，幂等）
3. 回滚：单 commit revert 即可，无数据迁移

## Open Questions

（无阻塞项——「有数据口径」「匹配语义」「数据分发」均已在 D1-D4 定案；索引存在性属实施期核验任务。）
