## 1. 后端分页逻辑（`bct/admin/city_prices.php`）

- [x] 1.1 定义每页条数常量（`$perPage = 100`），读取并校验 `page` 参数（小于 1 取 1）；在排序/搜索完成后的全量结果集上 `array_slice` 取当前页，并计算 `$total`（过滤后总数）与 `$totalPages`；`page` 超过总页数时收敛到末页。验证：访问 `page=99`（数据不足 99 页）展示末页不报错；访问 `page=0` 展示第 1 页。
- [x] 1.2 抽取统一 URL 构造逻辑（含 `sort/order/search/page`）：POST 单行保存后 `header("Location: ...")` 保留当前页与搜索/排序；每行保存表单 `action`、批量设置表单 `action` 同样带上全部参数。验证：在第 3 页保存某城市后停留在第 3 页且排序/搜索条件不变。
- [x] 1.3 表格渲染循环改为遍历当前页切片；"排名"列的 fallback 序号使用跨页连续的全量序号（`($page-1)*$perPage + $idx + 1`）而非每页从 1 重排。验证：第 2 页首行 fallback 序号等于第 1 页末行序号 + 1。
- [x] 1.4 无数据空态：搜索无匹配或 `city_bct` 为空时展示既有"暂无城市数据/未找到匹配"提示，且不渲染分页控件。验证：搜索不存在城市时页面提示且无分页条。

## 2. 分页 UI 与统计信息

- [x] 2.1 在列表工具条/表格下方展示"共 N 个城市 · 第 X/Y 页"统计信息。验证：页面可见总数与当前页/总页数。
- [x] 2.2 在表格下方新增分页控件（首页/上一页/页码窗口/下一页/末页），仅 `$totalPages > 1` 时渲染；复用全局 `admin.css` 的 `.admin-pagination` 与 `admin-btn` 系列样式，当前页高亮、首/末页禁用。验证：多页时可通过控件逐页浏览并最终到达末页所有城市；单页时不显示分页条。
- [x] 2.3 排序按钮与搜索提交链接切换到第 1 页（page 重置），"重置"链接同样回到第 1 页。验证：在第 5 页发起搜索/切换排序后 URL 回到 `page=1` 并展示正确结果。

## 3. 批量设置全量语义文案

- [x] 3.1 在"批量设置城市人气值单价"卡片说明区新增一句加粗说明：批量按输入行在全量城市（`city_bct`/`cities`）中匹配城市名或拼音，与上方列表当前页码、搜索词无关；匹配与保护规则逻辑保持不变。验证：文案可见且批量处理代码分支未改动。
- [x] 3.2 同步更新页面底部"说明"第 7 条，明确批量作用于全量城市而非当前分页。验证：说明文字与实现一致。

## 4. 验证

- [x] 4.1 对改动文件执行 `php -l` 语法检查（本地无 php 时用编辑器诊断复核）。验证：语法检查通过无错误。
- [x] 4.2 在线上 `bct.58.tl/admin/city_prices.php` 冒烟：默认第 1 页 100 条、翻页完整、第 3 页保存回跳、搜索命中非首页城市、搜索后回第 1 页、批量填入非当前页城市行仍能匹配更新。验证：上述行为与 spec 场景一致。
- [x] 4.3 `git add` 改动文件并提交推送（commit `18230e0`）。验证：`git log` 与远程一致。

## 5. 开通全量城市行情（数据补齐）

- [x] 5.1 在 `classes/CityBCT.php` 新增 `countMissingMarketCities()`（统计 `cities` 中未登记 `city_bct` 的词条数）与 `openMarketForAllCities()`（`INSERT INTO city_bct (city) SELECT c.name FROM cities c WHERE NOT EXISTS(...)`，其余列走表默认值；返回本次插入数与开通后总数）。验证：方法统计/补齐逻辑正确且幂等。
- [x] 5.2 在 `bct/admin/city_prices.php` 的 POST 处理中新增 `action=sync_cities` 分支调用补齐方法，通过 `$_SESSION['message']` 反馈本次开通数与当前总数。验证：提交后跳转展示结果提示；重复提交提示"已全量开通，无需新增"。
- [x] 5.3 列表卡片标题区新增"开通全部城市行情（+N）"按钮（提交前 `confirm` 明示影响，含品牌/数字资产），N=0 时改为展示"城市行情已全量开通"；读取全量 `cities` 数与待开通数并展示；底部"说明"新增第 8 条解释数据口径与操作语义。验证：开通前显示 +327 类数字且按钮可用，开通后变为已全量开通状态，说明文案与实现一致。
- [x] 5.4 语法检查（`php -l` 不可用则以编辑器诊断复核）、`openspec validate --change city-prices-pagination --strict` 通过、`git add` 提交推送。验证：语法与 spec 校验通过，`git log` 与远程一致。
- [ ] 5.5 ~~线上冒烟（按钮方案）~~：已被第 6 组"单价并入 cities"取代，勿再按按钮流程执行。原验收点（列表 421、分页、批量命中新词条、market 放量）并入 6.6。

## 6. 演进：BCT 单价并入 cities（单一事实源，取代方案 5）

- [x] 6.1 新增幂等迁移 `init/migration-merge-city-bct.sql`：`cities` 加 `bct_base_price`/`bct_current_price`/`bct_price_updated`，从 `city_bct` 按 name 回填既有 94 城单价并输出核对；`init/db-init.sql` 建表同步三列、标注 `city_bct` 废弃为历史备份。验证：脚本可重复执行（加列幂等、UPDATE 覆盖同值），回填行数与 `city_bct` 一致。
- [x] 6.2 `classes/CityBCT.php` 重构为以 `cities` 为唯一数据源（公共 `bctSelect()`，返回键 `id/city/base_price/current_price/circulating_supply/total_supply/last_updated/city_popularity` 保持兼容）；`total_supply` 收敛类常量 `TOTAL_SUPPLY=21000000`；流通量统一 `popularity - popularity_consume`；删除 `countMissingMarketCities()`/`openMarketForAllCities()`；`updatePrice()`/`updateBasePrice()`/`getMarketStats()`/`get24hChanges()` 去 `city_bct`。验证：php lint 或编辑器诊断通过；market.php / index.php / city.php / 城市门户 / 账户估值 / 订单详情取键不变。
- [x] 6.3 清理人气值双写与裸 JOIN：`CitySyncer::applyPopularity()` 与 `hufang/admin/sync-popularity-api.php` 不再 `UPDATE city_bct.circulating_supply`；`UserBCTAccount`/`bct/user/order_detail.php` 改 JOIN `cities`。验证：全仓 PHP 无 `city_bct` 读写（仅历史注释）。
- [x] 6.4 `bct/admin/city_prices.php`：移除 POST `sync_cities` 分支、标题区"开通全部行情"按钮与统计、批量匹配 SQL 改 `cities.bct_current_price`；标题区改为"BCT 单价存于 cities，全量 N 词条均含行情"；说明第 3/8 条改新口径。验证：页面无"开通/待开通"残留元素，全量约 421 词条。
- [x] 6.5 同步 openspec 工件（spec Requirement、design Context/Goals/Decision 8/Risks、tasks）并 `openspec validate --strict` 通过。验证：validate 无错误。
- [x] 6.6 线上迁移与冒烟：① 在服务器执行 `init/migration-merge-city-bct.sql`；② 部署代码；③ 冒烟：`city_prices.php` 共 421 词条分页正常、原 94 城价格与迁移前一致、批量设置命中新词条、`market.php`/`city.php`/首页/门户行情正常、人气值同步单城更新不报错、`process_order.php` 校验正常；④ 全部通过后执行 `RENAME TABLE city_bct TO city_bct_deprecated_20260908` 备份。验证：行为与 spec "BCT 单价并入 cities" 场景一致。
