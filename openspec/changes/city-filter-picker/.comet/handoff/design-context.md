# Comet Design Handoff

- Change: city-filter-picker
- Phase: design
- Mode: compact
- Context hash: a0890402e27f1a01e71427a4c23da51bb44585b2f3bbae92886ac42b2203ecb4

Generated-by: comet-handoff.sh

OpenSpec remains the canonical capability spec. This handoff is a deterministic, source-traceable context pack, not an agent-authored summary.

## openspec/changes/city-filter-picker/proposal.md

- Source: openspec/changes/city-filter-picker/proposal.md
- Lines: 1-40
- SHA256: 8f34da83adc1fef9a4f05c3c6c2787c06909d65e11fb3a2bf56cb577536ce9aa

```md
# 城市筛选选择器：可搜索下拉 + 数据源口径修正（批 1）

## Why

`cities` 表已有约 421 个城市，但出售/求购/认领市场的城市筛选下拉用的是 `getHotCitiesList(20)`——`WHERE is_hot = 1 ORDER BY rank LIMIT 20`，只显示人工标记的热门前 20 名。这与「该城市有没有挂牌」完全脱钩：下拉里有点进去空空如也的城市，而 400 个有数据的城市永远选不到。后台交易管理（`bct/admin/orders.php`）数据源正确（`DISTINCT city FROM bct_orders`）但控件是纯 `<select>`，城市一多无法输入搜索。

站内其实已写好解药：`includes/city_picker.php`（注册页在用的纯原生 JS 可搜索选择器，支持中文名/拼音匹配，诞生原因正是「微信内置浏览器不弹 datalist，只能看到几个城市」），只是从未推广到筛选页。本 change 把它升级为全站可复用组件并接入第一批 4 个页面。

## What Changes

- 升级 `includes/city_picker.php` 为可复用组件：
  - 新增「筛选变体」：空值 = 全部城市、支持一键清除、适配前台 `filter-bar` 尺寸
  - 新增「深色后台变体」：改用 `assets/css/admin.css` 的 `--admin-*` CSS 变量，替代写死的白底样式
- block 三个列表页（`sale_list.php` / `purchase_list.php` / `claim_list.php`）：
  - 城市筛选 `<select>` 换为可搜索组件
  - 候选数据源从 `is_hot=1 LIMIT 20` 改为「该页有数据的城市」（按 `cities.rank` 排序），输入框仍可搜全部 421 城
- `bct/admin/orders.php`：保留 `DISTINCT city` 数据源，仅把 `<select>` 换为可搜索组件（深色变体）
- 服务端筛选归一化：用户输入拼音（如 `hangzhou`）未点选直接提交时，先经 `normalizeCityName()` 映射为标准城市名，再加 `pinyin LIKE` 兜底，消除「静默 0 结果」
- 数据分发维持内联 JSON（~13KB/页），与注册页现状一致，不新增 AJAX 接口

## Capabilities

### New Capabilities

- `city-filter`：城市筛选能力——可搜索城市选择组件（组件契约：变体、清除、拼音/中文匹配）+ 各筛选页的候选数据源口径（「有数据的城市」而非「热门城市」）+ 服务端对自由输入的归一化行为。

### Modified Capabilities

（无——现有 `bct/admin`、`city-data-sync` 等 spec 的需求不受影响；`bct/admin/orders.php` 仅控件形态变化，无 spec 级行为变更。）

## Impact

- **代码**：
  - `includes/city_picker.php`（组件本体，向后兼容注册页现有调用方式）
  - `block/sale_list.php`、`block/purchase_list.php`、`block/claim_list.php`（筛选栏 + 候选 SQL）
  - `bct/admin/orders.php`（筛选栏控件替换）
  - `auth/register.php`、`auth/wechat_complete.php`（若组件参数/结构有调整则同步，预期兼容不动）
- **数据**：只读 `cities` 表与各业务表（`block_listings` 等去重统计），无写入
- **性能**：每页新增一次候选集 DISTINCT 查询（需确认索引）；内联 JSON 约 13KB；候选列表渲染上限沿用组件现有 MAX_RENDER=300
- **非影响（本批不做）**：其余约 33 处城市选择入口、硬编码数组清理、`cities` 表非地名条目（中国数藏等）过滤——留给后续批次 change
```

## openspec/changes/city-filter-picker/design.md

- Source: openspec/changes/city-filter-picker/design.md
- Lines: 1-112
- SHA256: ea1221960a8eb6b78ae1ce3d5e5196160a766d876db717ec27dc5ab3c93c8765

[TRUNCATED]

```md
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

```

Full source: openspec/changes/city-filter-picker/design.md

## openspec/changes/city-filter-picker/tasks.md

- Source: openspec/changes/city-filter-picker/tasks.md
- Lines: 1-34
- SHA256: b83a7b50359498357f817f45a442c18b1b9ba69ec1d5b4448507ade48712598f

```md
# Tasks：city-filter-picker 批 1（组件 + 4 页接入）

## 1. 组件升级（includes/city_picker.php）

- [ ] 1.1 新增可选参数 `$cityPickerCandidates` / `$cityPickerVariant` / `$cityPickerAllowClear` / `$cityPickerPlaceholder`，默认值保持现有行为（不传 = 现状），`php -l` 通过
- [ ] 1.2 双数据集渲染：空关键字展示 candidates（热门 chips 置顶），输入后从全量 `$cityOptions` 过滤；脚注显示「默认 N 个有数据城市 · 输入可搜全部 M 个」
- [ ] 1.3 `admin` 变体：样式改用 `assets/css/admin.css` 的 `--admin-*` CSS 变量（深色主题）；`filter` 变体：padding/高度对齐 `.filter-bar` 内 `<select>`
- [ ] 1.4 `allowClear`：输入框右侧 ✕ 清除按钮，清空后恢复「全部城市」语义
- [ ] 1.5 回归验证 `auth/register.php` 与 `auth/wechat_complete.php`：不传新参数时行为与升级前一致（展开全量候选、中/拼音搜索、提交归一化落库）

## 2. block 三页接入

- [ ] 2.1 `block/sale_list.php`：候选 SQL 改为 `cities WHERE id IN (SELECT DISTINCT city_id FROM block_listings WHERE status='listed') ORDER BY rank`；`<select>` 换为 city_picker（`filter` 变体 + allowClear），搜索全集传 `getAllCities()`
- [ ] 2.2 `block/purchase_list.php`：同构改造，子查询 `purchase_requests WHERE status='active'`
- [ ] 2.3 `block/claim_list.php`：同构改造，子查询 `blocks WHERE status='sold'`
- [ ] 2.4 三页服务端筛选加 D4 归一化：`normalizeCityName()` 优先 → `(c.name LIKE ? OR c.pinyin LIKE ?)` 兜底 → 原输入包含匹配；当前选中值用标准名回显；候选外城市显示空态文案（如「该城市暂无在售挂牌」）
- [ ] 2.5 `includes/functions.php` 如需对筛选页暴露 `normalizeCityName()` 的 cities 入参，复用 `getAllCities()` 结果，避免重复查询

## 3. bct 后台接入

- [ ] 3.1 `bct/admin/orders.php`：保留 `DISTINCT city FROM bct_orders` 候选，LEFT JOIN cities 取 pinyin/rank（不在 cities 的城市仍展示、pinyin 留空；排序规则按 `init/migration-bct-price-history.sql` 先例对齐避免 #1267）
- [ ] 3.2 `<select>` 换为 city_picker（`admin` 变体 + allowClear），搜索全集传 `getAllCities()`
- [ ] 3.3 服务端城市筛选加同款归一化（`bct_orders.city` 为城市名，逻辑同 D4）

## 4. 性能与索引核验

- [ ] 4.1 确认 `block_listings.city_id`、`purchase_requests.city_id`、`blocks.city_id`、`bct_orders.city` 索引；缺失则出幂等迁移 SQL（单独提交，不混入业务 commit）
- [ ] 4.2 本地/线上 EXPLAIN 核验三页候选 DISTINCT 查询与列表主查询无慢查询

## 5. 验证与发布

- [ ] 5.1 全部改动文件 `php -l` 通过；组件在微信内置浏览器（X5/WKWebView）聚焦展开、键盘选择正常
- [ ] 5.2 按 spec 验收场景逐条走查：四页候选完整性、拼音/别名直接提交、清除回全部、旧 URL `?city=北京&page=2` 兼容、注册页零回归
- [ ] 5.3 提交推送；服务器拉取后线上走查四个入口（block.58.tl 三个列表页 + bct 后台交易管理），确认无慢查询告警
```

## openspec/changes/city-filter-picker/specs/city-filter/spec.md

- Source: openspec/changes/city-filter-picker/specs/city-filter/spec.md
- Lines: 1-71
- SHA256: 3cc88423afeecbf0f7e6bac5b543355648f3c3f4aa53b62ef576596726c99613

```md
# city-filter：城市筛选能力（可搜索选择器 + 数据源口径 + 归一化）

## ADDED Requirements

### Requirement: 城市筛选使用可搜索选择组件

出售市场（`block/sale_list.php`）、求购市场（`block/purchase_list.php`）、认领市场（`block/claim_list.php`）与人气值后台交易管理（`bct/admin/orders.php`）的城市筛选 SHALL 使用基于 `includes/city_picker.php` 的可搜索选择组件替代纯 `<select>`，组件 SHALL 支持中文名与拼音的包含匹配，并在聚焦/点击时即展开候选面板（不依赖 `input` 事件，兼容微信内置浏览器）。

#### Scenario: 聚焦展开候选
- **WHEN** 用户点击或聚焦城市筛选输入框
- **THEN** 候选面板立即展开并显示该页默认候选城市列表（热门城市置顶）

#### Scenario: 拼音搜索
- **WHEN** 用户在搜索框输入 `hangzhou`
- **THEN** 候选列表过滤出「杭州」（拼音包含匹配）

#### Scenario: 中文包含搜索
- **WHEN** 用户输入「州」
- **THEN** 候选列表显示所有名称包含「州」的城市（杭州、广州、苏州等）

### Requirement: 默认候选为「该页有数据的城市」

block 三个列表页的默认候选列表 SHALL 只包含该页有数据的城市（出售：`block_listings` 中 `status='listed'` 的挂牌；求购：`purchase_requests` 中 `status='active'`；认领：`blocks` 中 `status='sold'`），并按 `cities.rank` 排序。后台交易管理页 SHALL 保留现有 `SELECT DISTINCT city FROM bct_orders` 数据源。所有页面的搜索范围 SHALL 覆盖 cities 表全量（约 421 个城市），不受默认候选限制。

#### Scenario: 候选完整性（出售市场）
- **WHEN** 出售市场页展开城市候选
- **THEN** 列表包含且仅包含存在在售挂牌的城市，逐个点选均有非空结果

#### Scenario: 候选外城市仍可搜到
- **WHEN** 用户输入一个当前无挂牌但存在于 cities 表的城市名并提交
- **THEN** 列表页正常返回空态（提示该城市暂无挂牌），而非筛选失效报错

#### Scenario: 后台候选不受影响
- **WHEN** 交易管理页展开城市候选
- **THEN** 列表仍为有订单的城市（DISTINCT 口径不变），且深色主题下组件配色正常

### Requirement: 服务端对自由输入的归一化

四个页面的服务端筛选 SHALL 对用户自由输入的城市文本依次执行：`normalizeCityName()` 归一化（拼音/别名/「武汉市」类后缀 → cities 标准名）→ 未命中时 `(name LIKE ? OR pinyin LIKE ?)` 双列包含匹配兜底 → 仍未命中按原输入包含匹配。城市不存在或无数据时 SHALL 展示空态而非空白。

#### Scenario: 拼音直接提交
- **WHEN** 用户输入 `hangzhou` 未从候选点选，直接提交筛选
- **THEN** 结果为城市「杭州」的列表，而非 0 结果

#### Scenario: 带行政后缀的别名
- **WHEN** 用户输入「武汉市」提交
- **THEN** 结果为城市「武汉」的列表

#### Scenario: 未收录输入
- **WHEN** 用户输入 cities 表中不存在的任意文本提交
- **THEN** 页面正常返回空态，无错误

### Requirement: 筛选组件的清除与全部语义

筛选场景下组件 SHALL 提供一键清除按钮；城市为空时 SHALL 表示「全部城市」。现有 GET 参数名（`city`）SHALL 保持不变，已收录的分页/筛选 URL 不失效。

#### Scenario: 清除回到全部
- **WHEN** 用户点击清除按钮并提交
- **THEN** `city` 参数为空，页面显示全部城市的列表

#### Scenario: URL 兼容
- **WHEN** 访问改造前已存在的 `?city=北京&page=2` 链接
- **THEN** 筛选与分页行为与改造前一致

### Requirement: 注册页与微信补全页零回归

`includes/city_picker.php` 的升级 SHALL 保持现有调用方式（`$cityOptions`/`$cityPickerName`/`$cityPickerValue`/`$cityPickerId`）向后兼容；`auth/register.php` 与 `auth/wechat_complete.php` 不传新参数时行为与现状一致。

#### Scenario: 注册页城市选择不变
- **WHEN** 用户在注册页展开城市选择
- **THEN** 展示全量城市候选、中文名/拼音搜索行为与升级前一致，提交后城市名经归一化落库
```

