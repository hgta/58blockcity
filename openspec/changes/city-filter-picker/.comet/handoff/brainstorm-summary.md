# Brainstorm Summary

- Change: city-filter-picker
- Date: 2026-10-03

## 确认的技术方案

- 组件 `includes/city_picker.php` 保持单文件内联（不抽外部 JS/CSS），新增 4 个可选参数：`$cityPickerCandidates`（默认展示子集）、`$cityPickerVariant`（default/filter/admin）、`$cityPickerAllowClear`、`$cityPickerPlaceholder`；缺省 = 现状，注册页/微信补全页零改动
- 双数据集渲染：空关键字展示 candidates（is_hot chips 置顶 ≤12）；输入后从全量 `$cityOptions`（421 城）过滤；脚注「默认 N 个有数据城市，输入可搜全部 M 个」
- CSS 变体内联于组件：`.cp-filter`（对齐 filter-bar select 尺寸）、`.cp-admin`（吃 `assets/css/admin.css` 的 `--admin-*` 变量适配深色后台）、`.cp-clear` 清除按钮
- block 三页候选 SQL：`cities WHERE id IN (SELECT DISTINCT city_id FROM <业务表> WHERE <状态>) ORDER BY rank`（sale=block_listings/listed；purchase=purchase_requests/active；claim=blocks/sold），各页内联不抽 City 方法
- `bct/admin/orders.php` 保留 `DISTINCT city FROM bct_orders`，LEFT JOIN cities 取 pinyin/rank，COLLATE utf8mb4_unicode_ci 对齐规避 #1267；不在 cities 的城市照常展示
- 服务端归一化：`includes/functions.php` 新增 `buildCityFilterWhere($input, $allCities)`——`normalizeCityName()` 优先（拼音/「武汉市」→标准名）→ `(c.name LIKE ? OR c.pinyin LIKE ?)` 双列兜底；bct 后台无 pinyin 列，归一化后单列 `o.city LIKE ?`
- GET 参数名 `city` 不变，URL 兼容；当前选中值用标准名回显

## 关键取舍与风险

- DISTINCT 子查询性能 → 任务内核验 `block_listings.city_id` / `purchase_requests.city_id` / `blocks.city_id` / `bct_orders.city` 索引，缺则出幂等迁移 SQL 单独提交
- bct_orders.city 与 cities.name 排序规则不一致 → JOIN 显式 COLLATE（migration-bct-price-history.sql 先例）
- 搜到无数据城市 → 各页空态文案（沿用现有空态区块）
- 内联 JSON ~13KB/页 → 仅 4 页生效，与注册页等量，可接受
- 组件改动回归注册页 → 参数默认值即旧行为 + 回归验证任务

## 测试策略

- 全部改动文件 `php -l`
- delta spec 12 个验收场景逐条走查（本地 + 线上 block.58.tl 四入口）
- 注册页/微信补全页零回归验证
- EXPLAIN 候选查询无慢查询
- 微信内置浏览器（X5/WKWebView）聚焦展开与键盘操作（线上人工验）

## Spec Patch

无（delta spec 5 条 Requirement / 12 个场景已覆盖全部确认行为）
