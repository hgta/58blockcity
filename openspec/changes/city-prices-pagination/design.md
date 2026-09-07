## Context

现状：`city_bct` 表（唯一存单价）只登记了 94 个词条，而 `cities` 表共有约 421 个词条（城市与品牌/数字资产）。全仓库没有任何代码对 `city_bct` 做 INSERT，该表靠人工/外部 SQL 初始化，故单价管理页与线上行情市场均只覆盖 94 城，其余词条无人气值单价、无法管理。

`bct/admin/city_prices.php` 一次请求内通过 `CityBCT::getAllCitiesBCT()` 拉取 `city_bct` 全量，并在 PHP 内完成：
- 合并 `get24hChanges()` 计算涨跌幅
- 用 `cities.rank` / `cities.pinyin` 组装榜单名次与拼音映射
- 全量过滤（搜索：城市名或拼音包含）与排序（市值/排名/单价/流通量）

随后无分页地渲染整张表，每条记录是一行独立 `<form>`（含两个 number 输入与保存按钮）。城市数达数百时页面巨大，影响浏览。

批量设置走独立 POST 分支：按粘贴行逐条在 `city_bct`（城市名）与 `cities`（pinyin → 城市名）中匹配，与列表渲染完全解耦，天然作用于全量城市。

改造目标文件仅 `bct/admin/city_prices.php`。分页样式复用全局 `admin.css` 已有的 `.admin-pagination` / `.admin-page-info` / `.admin-page-buttons`（参考 `hufang/admin/circles.php` 用法）。

**演进（2026-09-08）**：为根治"登记才开行情"造成的 94/421 断层，本 change 进一步把 BCT 单价**物理并入 `cities`**（`bct_base_price`/`bct_current_price`/`bct_price_updated`），全量词条天然拥有行情，取代原先的"一键开通"按钮与 `city_bct` 独立表。迁移脚本 `init/migration-merge-city-bct.sql` 可重复执行：加列 → 从 `city_bct` 回填既有 94 城单价 → 核对。旧表改名备份，代码不再引用。见决策 8。

## Goals / Non-Goals

**Goals:**
- 城市单价列表按固定每页条数分页，全量城市逐页可达。
- 排序/搜索仍在全量上计算后分页切片，语义与现状一致。
- 保存/批量操作后回到操作前页码，URL 状态（sort/order/search/page）可复现。
- 明确"批量设置作用于全量城市、不受分页/搜索限制"的页面文案。
- 将 BCT 单价并入 `cities`（单一事实源），取代"一键开通"入口与 `city_bct` 独立表，使全量词条天然拥有行情。

**Non-Goals:**
- 不改变批量设置的匹配规则、保护规则与写入逻辑。
- 不做 SQL 化分页/查询下推（见 Decisions）。
- 不改变 BCT 交易、订单、账户等下游模块对价格字段的读取语义（`CityBCT` 返回键保持兼容）。
- 不自动"静默"删除旧表：`city_bct` 改名备份，回滚只需改名恢复（脚本尾部注释给出命令，由运维人工执行）。

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

### 7. ~~"开通全部城市行情"：显式按钮 + 幂等 INSERT..SELECT~~（阶段 A，已被决策 8 取代；仅作演进存档）

在 `classes/CityBCT.php` 增加：
- `countMissingMarketCities()`：`cities` 中不存在对应 `city_bct` 行的词条数。
- `openMarketForAllCities()`：`INSERT INTO city_bct (city) SELECT c.name FROM cities c WHERE NOT EXISTS (...)`，其余列走表默认值（`total_supply=21000000`、`circulating_supply=0`、`base_price=current_price=0.10`），返回本次插入数与开通后总数。

- **入口**：`city_prices.php` 卡片标题区按钮（POST `action=sync_cities`），带 `confirm` 提示影响范围（含品牌/数字资产）；`missingCount > 0` 时可用并显示 +N，否则展示"已全量开通"。
- **幂等**：`NOT EXISTS` 过滤 + `city_bct.city` 唯一键保证重复执行零副作用、不改既有价格。
- **理由**：`city_bct` 全仓库无 INSERT 来源，仅人工初始化到 94 行；用 SQL 级补齐避免逐条 PHP 判断，语义最稳。默认 0.10 与表建表默认一致。
- **备选**：提供一次性 SQL 交由运维手工执行。被否：无 UI 反馈、易被重复执行产生不同步；按钮方案可复现、可解释。
- **注意**：补齐后 `market.php`/`index.php`/统计接口会随 `getAllCitiesBCT()` 自然覆盖新增词条（总市值按 `circulating_supply×current_price`，新增词条若 `cities.popularity>0` 会自动按实际流通量计入），属预期放量行为。

### 8. BCT 单价并入 cities：单一事实源（阶段 B，最终方案）

业务口径已定为"每个词条都应有 BCT 行情"，此时 `city_bct` 独立表退化为 `cities` 的投影：`circulating_supply` 与官方人气字段双写冗余、`total_supply` 全表固定常量、`city` 靠名字字符串 JOIN 无外键，且全仓库无 INSERT 来源——长期漂移正是本次 94/421 断层的根因。最终方案把单价物理并入 `cities`：

- **schema**（`init/migration-merge-city-bct.sql`，幂等可重复执行）：`cities` 增加 `bct_base_price`/`bct_current_price`（`decimal(10,2) NOT NULL DEFAULT 0.10`）与 `bct_price_updated`（`datetime NULL`）；回填既有 94 城：`UPDATE cities c JOIN city_bct cb ON cb.city = c.name COLLATE utf8mb4_unicode_ci SET c.bct_* = cb.*`；旧表改名备份（`RENAME TABLE city_bct TO city_bct_deprecated_*`），不自动删除。
- **代码**：`CityBCT` 所有查询改以 `cities` 为主表，返回键保持兼容（`id/city/base_price/current_price/circulating_supply/total_supply/last_updated/city_popularity`），下游页面零改动；`total_supply` 收敛为类常量 `TOTAL_SUPPLY = 21000000`；流通量统一 `GREATEST(popularity - popularity_consume, 0)` 不再落库；删除 `countMissingMarketCities()`/`openMarketForAllCities()` 与管理页"开通全部行情"入口（POST `sync_cities` 分支、标题区按钮与统计）；`updatePrice()`/`updateBasePrice()` 改为写 `cities.bct_current_price`/`cities.bct_base_price`。
- **相关清理**：人气值同步不再双写 `city_bct.circulating_supply`（`CitySyncer::applyPopularity()`、`hufang/admin/sync-popularity-api.php`）；`UserBCTAccount`/`order_detail.php` 的 JOIN 改指向 `cities`。
- **理由**：单一事实源——新增/调整 `cities` 词条天然拥有行情，登记断层机制性消失；去 JOIN、去双写；与既有"向 `cities` 叠加官方人气字段"的迁移先例一致（`migration-city-popularity-fields.sql`）。
- **备选**：保留独立表、仅把读取改为 cities 驱动 + `COALESCE(cb.current_price, 0.10)`。被否：仍留存一张近全量的冗余投影表与双写风险，并非干净终点。
- **注意**：旧版在 `popularity=0` 时用 `cb.circulating_supply` 兜底流通量；新版统一由人气字段计算，从未同步人气值的旧词条若仅有历史 fallback 值，展示流通量将归零——属去冗余的预期清理（这些词条本身无真实人气值）。

## Risks / Trade-offs

- [每请求仍全量加载城市数据] → 当前数百级规模可接受；若未来城市量级大幅增长，可在此后单独将排序/过滤/分页 SQL 化（本 change 不做）。
- [行保存 redirect 携带的 page 在数据变化后可能越界] → 渲染侧 `min($page, $totalPages)` 收敛到末页，无报错、无空白页。
- [URL 参数增多导致链接较长] → 仅 sort/order/search/page 四个标量参数，对 GET/表单 action 无实际影响。
- [一次开通 300+ 词条会显著扩大行情市场（含北大/鲸探等品牌资产，市值按实际人气值计入）] → 属用户明确需求（"补全开通 421 个全部词条"）；按钮前置 `confirm` 明示影响，默认单价 0.10 保守，开通后管理页可逐页改价/下架需另行处理。
- [DDL 迁移与部署需按序执行] → 提供幂等迁移脚本（加列 + 回填 + 核对输出）；顺序建议：先迁移 → 再部署新代码（兼容窗口内旧代码仍只读 `city_bct`）→ 冒烟通过后人工改名备份旧表。回滚只需改名恢复。
- [流通量口径对未同步人气值的旧词条展示变化（fallback 值归零）] → 去冗余的预期清理；本会话冒烟将抽查原 94 城中人气值正常的城市流通量在迁移前后一致。
