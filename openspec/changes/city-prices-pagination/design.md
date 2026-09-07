## Context

现状：`bct/admin/city_prices.php` 一次请求内通过 `CityBCT::getAllCitiesBCT()` 拉取 `city_bct` 全量，并在 PHP 内完成：
- 合并 `get24hChanges()` 计算涨跌幅
- 用 `cities.rank` / `cities.pinyin` 组装榜单名次与拼音映射
- 全量过滤（搜索：城市名或拼音包含）与排序（市值/排名/单价/流通量）

随后无分页地渲染整张表，每条记录是一行独立 `<form>`（含两个 number 输入与保存按钮）。城市数达数百时页面巨大，影响浏览。

批量设置走独立 POST 分支：按粘贴行逐条在 `city_bct`（城市名）与 `cities`（pinyin → 城市名）中匹配，与列表渲染完全解耦，天然作用于全量城市。

改造目标文件仅 `bct/admin/city_prices.php`。分页样式复用全局 `admin.css` 已有的 `.admin-pagination` / `.admin-page-info` / `.admin-page-buttons`（参考 `hufang/admin/circles.php` 用法）。

## Goals / Non-Goals

**Goals:**
- 城市单价列表按固定每页条数分页，全量城市逐页可达。
- 排序/搜索仍在全量上计算后分页切片，语义与现状一致。
- 保存/批量操作后回到操作前页码，URL 状态（sort/order/search/page）可复现。
- 明确"批量设置作用于全量城市、不受分页/搜索限制"的页面文案。

**Non-Goals:**
- 不改变批量设置的匹配规则、保护规则与写入逻辑。
- 不改 `classes/CityBCT.php`、不改数据库结构、不引入前端分页框架。
- 不做 SQL 化分页/查询下推（见 Decisions）。

## Decisions

### 1. 内存切片实现分页，而非 SQL LIMIT/OFFSET

保留现有"全量加载 → 内存过滤排序"流程，排序/搜索完成后用 `array_slice()` 取当前页。

- **理由**：现有排序键依赖内存内计算的 `market_cap`（`circulating_supply × current_price`）、`cities.rank` 缺失时的 fallback 排序值等；SQL 化需重写跨表表达式并处理 `rank`/`pinyin` 列可能缺失的兼容逻辑，风险与成本高。当前城市规模（数百级）在单次请求内全量处理不构成性能问题。
- **备选**：SQL 分页 + COUNT 子查询。被否：改动面大、与现有内存口径易漂移，且收益在当前数据量下不明显。

### 2. 每页固定 100 条

`$perPage = 100`。每行内嵌表单较重，100 条在保证可读性的同时将全量城市收敛为个位数页。不做每页条数切换（避免范围蔓延）。

### 3. 统一 URL 状态构造

用单个辅助逻辑（PHP 变量或闭包）构建带 `sort/order/search[/page]` 的 URL，供下列位置复用：
- 排序按钮链接 → 切到第 1 页（page=1）。
- 每行保存 `<form action>` → 保留当前 `sort/order/search/page`。
- 批量设置 `<form action>` → 保留当前 `sort/order/search/page`。
- POST 后 `header("Location: ...")` → 保留当前 `sort/order/search/page`。
- 搜索提交（GET 表单，hidden sort/order/search）→ 不带 page，自然回到第 1 页；"重置"同理。

### 4. 页码校验与收敛

```
$page = max(1, (int)($_GET['page'] ?? 1));
$totalPages = $total > 0 ? (int)ceil($total / $perPage) : 0;
$page = ($totalPages > 0) ? min($page, $totalPages) : 1;
```

越界访问展示末页而非报错；`total=0` 时渲染既有"暂无城市数据/未找到匹配"空态，不渲染分页控件。POST redirect 携带原始 `page` 即可，渲染侧统一收敛（保存后数据可能变化导致原页超出末页，由收敛兜底）。

### 5. 页面信息与分页控件

- 工具条右侧或表格下方显示"共 N 个城市 · 第 X/Y 页"。
- 表格底部仅在 `$totalPages > 1` 时渲染 `.admin-pagination` 控件（首页/上一页/页码窗口/下一页/末页），沿用页面现有 `admin-btn` 系列样式；当前页高亮、首末页禁用。

### 6. 批量设置语义文案

在批量设置卡片说明区与底部"说明"第 7 条中，补充一句加粗说明：批量按输入行在**全量城市**（`city_bct`/`cities`）中匹配，与列表当前页码、搜索词无关。逻辑代码不动。

## Risks / Trade-offs

- [每请求仍全量加载城市数据] → 当前数百级规模可接受；若未来城市量级大幅增长，可在此后单独将排序/过滤/分页 SQL 化（本 change 不做）。
- [行保存 redirect 携带的 page 在数据变化后可能越界] → 渲染侧 `min($page, $totalPages)` 收敛到末页，无报错、无空白页。
- [URL 参数增多导致链接较长] → 仅 sort/order/search/page 四个标量参数，对 GET/表单 action 无实际影响。
