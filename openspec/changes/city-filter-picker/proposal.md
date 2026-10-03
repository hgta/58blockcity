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
