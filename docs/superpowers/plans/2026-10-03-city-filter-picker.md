---
change: city-filter-picker
design-doc: docs/superpowers/specs/2026-10-03-city-filter-picker-design.md
base-ref: 7af30276f2bebccdbf398db0a32e9706ff95335f
---

# 实施计划：城市筛选选择器（city-filter-picker 批 1）

> 需求/验收/任务边界以 OpenSpec 产物为准；本计划按 tasks.md 的 5 个任务组组织为有序执行步骤，
> 所有接口、SQL、CSS 变体决策引用技术设计文档（下称「设计文档」）第 3-5 节。

## 0. 目标摘要

把 4 个页面的城市筛选换成可搜索组件 `includes/city_picker.php`：
- block 三页默认候选 =「该页有数据的城市」（替换 `getHotCitiesList(20)`），输入可搜全部 421 城
- `bct/admin/orders.php` 保留 DISTINCT 口径，仅换控件（admin 深色变体）
- 服务端筛选加 `buildCityFilterWhere()` 归一化，消除拼音/别名静默 0 结果
- 注册页/微信补全页零回归（参数缺省 = 现状）

**不做**（设计文档第 9 节）：其余约 33 处城市入口、硬编码数组清理、AJAX 搜索、cities 非地名条目过滤。

## 1. 前置确认（开工前）

- [ ] 当前分支基于 `7af30276f2bebccdbf398db0a32e9706ff95335f`，工作区干净
- [ ] 本地环境可访问数据库（cities ~421 行、block_listings/purchase_requests/blocks/bct_orders 有测试数据）
- [ ] 阅读设计文档第 3-5 节（组件接口 D2、候选 SQL D3、归一化 D4）

---

## 阶段一：组件升级（任务组 1，对应 tasks 1.1-1.5）

### 步骤 1.1 参数扩展（任务 1.1）

**文件**： `includes/city_picker.php:17-20`（现有参数缺省块之后）

**改动**：
- 在现有 `$cityPickerName/$cityPickerValue/$cityPickerId/$cityOptions` 缺省赋值（17-20 行）后新增 4 个可选参数缺省赋值，默认值严格按设计文档 3.1 节：
  - `$cityPickerCandidates = null`（null = 展开即全量，注册页现状）
  - `$cityPickerVariant = 'default'`（仅接受 `'default'|'filter'|'admin'`，越界回退 default）
  - `$cityPickerAllowClear = false`
  - `$cityPickerPlaceholder = '选择或输入城市'`
- 23-36 行的 `foreach ($cityOptions ...)` 组装逻辑旁，同构组装 `$__candJson`（candidates 子集，结构同 `['n','p','h']`）与 `$__candTotal`、candidates 的 `$__candHotNames`（is_hot 置顶 ≤12）
- 59-64 行根节点追加 variant class（`cp-filter` / `cp-admin`）、64 行 placeholder 改用 `$cityPickerPlaceholder` 变量

**完成判据**：不传新参数时，组件输出 HTML 与升级前逐字节等价（可用 `php -r` 渲染 diff 验证）；`php -l includes/city_picker.php` 通过。

### 步骤 1.2 双数据集渲染（任务 1.2）

**文件**： `includes/city_picker.php` JS 部分（`render()` 函数，107-134 行区域）

**改动**（设计文档 3.2 节，唯一行为分叉）：
- `render(keyword)` 中：`keyword == ''` 且 candidates 非空时，从 `candData`（新增内联 JSON，同 79-80 行 hidden input 模式）渲染候选列表，热门 chips 取 candidates 内 is_hot 前 12；脚注文案 = 「默认 N 个有数据城市 · 输入可搜全部 M 个」
- 其它情况走现有全集 `cities` 过滤逻辑（101-105 行 `match()` 与 110-124 行不动），脚注维持现状文案「匹配 X 个城市」
- 键盘（147-163 行）、点击外部关闭（181-183 行）、聚焦/点击展开（142-144 行）零改动

**完成判据**：本地构造带 candidates 的测试页，空关键字渲染候选子集 + 新脚注；输入任一字符回退全集过滤；MAX_RENDER=300 上限对两个数据集均生效。

### 步骤 1.3 CSS 变体（任务 1.3）

**文件**： `includes/city_picker.php` `<style>` 块（39-57 行之后追加，不新增外部文件，D1）

**改动**（设计文档 3.3 节）：
- `.city-picker.cp-filter`：input padding/高度对齐 `.filter-bar` 内 `<select>`（约 38px 高、font-size 14px）
- `.city-picker.cp-admin`：`background: var(--admin-bg-light)`、`border: var(--admin-border)`、`color: var(--admin-text)`，panel 用 `var(--admin-card)`，hover/active 用 `var(--admin-accent)`——变量定义见 `assets/css/admin.css:8-18`，由 `shared/admin/admin-header.php` 引入的页面自动获得
- 现有 default 亮色样式（39-56 行）原样保留

**完成判据**：`admin` 变体在 bct 后台深色背景下无白色残留（input/panel/chips/foot 全覆盖）；`filter` 变体与 sale_list 现有 `.filter-bar` 中 `<select>` 视觉高度一致。

### 步骤 1.4 allowClear 清除按钮（任务 1.4）

**文件**： `includes/city_picker.php` 标记区（65 行 `<i class="cp-arrow">` 附近）+ JS

**改动**：
- allowClear 为 true 时在输入框右侧渲染 `.cp-clear`（✕，absolute 定位，需把 arrow 右移避让）
- 点击 ✕：清空 input 值（GET 提交时 `city` 参数为空 = 「全部城市」语义），面板重渲染
- 样式随 variant 联动（admin 变体下用 `var(--admin-text-muted)`）

**完成判据**：点 ✕ 后提交表单，URL 中 `city` 为空；再点开面板显示默认候选；default 变体（allowClear=false）无 ✕ 按钮。

### 步骤 1.5 注册页/微信补全页回归（任务 1.5）

**文件**： 只读验证 `auth/register.php:132-140`、`auth/wechat_complete.php:205` 附近（两处均只传 `$cityPickerName/$cityPickerValue/$cityPickerId` + `$cityOptions`，预期零改动）

**验证**：展开即全量 421 城、中文「州」/拼音 `hangzhou` 过滤正常；提交后城市名仍经现有 `normalizeCityName()` 落库（服务端逻辑不动）。

**完成判据**：两页 DOM 结构（除 placeholder 属性外）与升级前一致，搜索/键盘/提交归一化行为一致。

**提交点 A**：`feat(city-picker): 组件新增 candidates/variant/allowClear/placeholder 参数（向后兼容）` —— 仅 `includes/city_picker.php`。

---

## 阶段二：block 三页接入（任务组 2，对应 tasks 2.1-2.5）

### 步骤 2.0 服务端归一化 buildCityFilterWhere（任务 2.4 的公共件，先行）

**文件**： `includes/functions.php`，插入在 `normalizeCityName()`（119-142 行）之后（约 143 行）

**改动**（设计文档第 4 节签名）：
- 新增 `buildCityFilterWhere(string $input, array $allCities): array`，返回 `['where' => SQL 片段, 'params' => 绑定参数, 'city' => 归一化后的标准名（供回显）]`
- 内部流程：
  1. `normalizeCityName($input, $allCities)` 命中 → 标准名做 `c.name LIKE '%名%'`
  2. 未命中 → `(c.name LIKE ? OR c.pinyin LIKE ?)` 同参双列（双列是现状 `c.name LIKE ?` 的超集，老行为保留）
  3. 仍未命中 → 按原输入包含匹配（保留「输入子串也能搜」宽松语义，D4）
- **注意**：现有 `normalizeCityName()`（120-141 行）只处理精确名/全拼音等值，不处理「武汉市」后缀别名；本步骤需在其内部（或 buildCityFilterWhere 中）补充去行政后缀匹配，复用 `classes/City.php:300` `getCityByName()` 的「精确 → 去后缀（武汉市/武汉地区）」顺序逻辑，保持该函数向后兼容（注册页同样受益）
- `$where` 列名参数化：block 三页传 `c.name`/`c.pinyin` 前缀，bct 后台传 `o.city` 单列（见步骤 3.3）

**完成判据**：`php -l` 通过；走查三个输入：`hangzhou`→杭州、`武汉市`→武汉、乱码文本→原样 LIKE（返回空态不报错）。

**提交点 B**：`feat(functions): 新增 buildCityFilterWhere 城市筛选归一化（normalizeCityName 支持行政后缀）` —— 仅 `includes/functions.php`。

### 步骤 2.1 出售市场接入（任务 2.1）

**文件**： `block/sale_list.php`

**改动点**：
1. **候选 SQL**（76 行 `$hotCities = $city->getHotCitiesList(20)` 替换）：按设计文档第 5 节
   `SELECT c.name, c.pinyin, c.is_hot FROM cities c WHERE c.id IN (SELECT DISTINCT l.city_id FROM block_listings l WHERE l.status='listed') ORDER BY c.rank` → `$cityPickerCandidates`
2. **搜索全集**：`$allCities = $city->getAllCities()`（`classes/City.php:10`，一次查询同时供组件 `$cityOptions` 与归一化，避免重复查询——任务 2.5）
3. **筛选归一化**（13 行 `$filterCity` 与 25-28 行）：改调 `buildCityFilterWhere($filterCity, $allCities)`，主查询（47-57 行）与 COUNT 查询（70 行）共用其 where/params；`$filterCity` 回显值改用返回的标准名
4. **控件替换**（119-128 行 `.filter-bar` 内城市 `<select>`）：换成 `include 'includes/city_picker.php'`，传 `filter` 变体 + `allowClear=true` + candidates + 全集
5. **空态**：城市筛选有值且结果为空时，空态文案含「该城市暂无在售挂牌」

**完成判据**：展开默认候选 = 有 listed 挂牌的城市（按 rank 排序，热门置顶）；输入任意 cities 表城市提交返回空态不报错；`?city=北京&page=2` 行为不变；分页链接继续携带 city 参数。

### 步骤 2.2 求购市场接入（任务 2.2）

**文件**： `block/purchase_list.php`，与 2.1 同构：
- 49 行 `$hotCities` 替换为候选 SQL：子查询 `purchase_requests pr WHERE pr.status='active'`
- 9 行 `$filterCity`、19-22 行 where 构造改走 `buildCityFilterWhere`
- 60-69 行城市 `<select>` 换组件（filter 变体 + allowClear）
- 空态文案含「该城市暂无求购」

### 步骤 2.3 认领市场接入（任务 2.3）

**文件**： `block/claim_list.php`，与 2.1 同构：
- 49 行候选 SQL：子查询 `blocks b WHERE b.status='sold'`
- 归一化接入；57-66 行城市 `<select>` 换组件
- 空态文案含「该城市暂无认领记录」

**提交点 C**：`feat(block): 三列表页城市筛选换 city_picker（有数据城市候选 + 归一化）` —— 可逐页拆 commit（同构改动，逐页验证逐页提交更稳）。

---

## 阶段三：bct 后台接入（任务组 3，对应 tasks 3.1-3.3）

### 步骤 3.1 候选 SQL 升级（任务 3.1）

**文件**： `bct/admin/orders.php:161`

**改动**：保留 DISTINCT 口径，LEFT JOIN cities 取 pinyin/rank（设计文档第 5 节）：
`SELECT o.city AS name, c.pinyin, COALESCE(c.is_hot, 0) AS is_hot FROM (SELECT DISTINCT city FROM bct_orders) o LEFT JOIN cities c ON o.city COLLATE utf8mb4_unicode_ci = c.name COLLATE utf8mb4_unicode_ci ORDER BY COALESCE(c.rank, 999999), o.city`
- 显式 COLLATE 规避 #1267（先例：`init/migration-bct-price-history.sql:17-19`）
- 不在 cities 的脏数据城市照常展示（pinyin 留空，组件已兼容 `p` 为空）

### 步骤 3.2 控件替换（任务 3.2）

**文件**： `bct/admin/orders.php:238-246`（筛选表单内城市 `<select class="admin-form-select">`）

**改动**：换 city_picker（**admin 变体** + allowClear）；搜索全集传 cities 全量（`City::getAllCities()`，需 require `classes/City.php`；include 路径 `../../includes/city_picker.php`，该文件已有 `../../config/database.php` 先例）。

### 步骤 3.3 服务端归一化（任务 3.3）

**文件**： `bct/admin/orders.php:97-100`（现为 `o.city = ?` 精确匹配）

**改动**：`bct_orders.city` 为 varchar 城市名、无 pinyin 列，用 `buildCityFilterWhere()` 归一化结果做单列 `o.city LIKE ?`；`$filterCity` 回显（164-171 行 `$returnQuery`）用归一化标准名。

**完成判据（阶段三整体）**：候选 = 有订单城市（DISTINCT 不变）且深色主题下组件配色正常；输入 `hangzhou` 直接提交命中「杭州」订单；点 ✕ 清除回全部；城市筛选与 type/trade_type/status/user 筛选、分页、操作后 `return_query` 跳回联动正常。

**提交点 D**：`feat(bct-admin): 交易管理城市筛选换 city_picker（admin 深色变体 + 归一化）`。

---

## 阶段四：性能与索引核验（任务组 4）

### 步骤 4.1 索引核验（任务 4.1）

**已知线索**（`init/db-init.sql`，以线上 `SHOW INDEX` 实测为准）：
- `block_listings`：`idx_city_status (city_id, status)`（约 367 行）
- `purchase_requests`：`city_zone_block (city_id, zone, block_number)`（约 1143 行）
- `blocks`：`city_zone_block` UNIQUE + `status`（约 950-954 行）
- `bct_orders`：`KEY city (city)`（约 934 行）

**动作**：线上执行 `SHOW INDEX` 核对；若有缺失，出幂等迁移 SQL（如 `init/migration-city-filter-indexes.sql`，参照 `migration-bct-price-history.sql` 幂等写法），**单独 commit**。

### 步骤 4.2 EXPLAIN 核验（任务 4.2）

**动作**：本地与线上 `EXPLAIN`：
- 三页候选 DISTINCT 子查询
- bct 后台 DISTINCT + LEFT JOIN 候选查询
- 各页列表主查询与 COUNT 查询（带 city 筛选）

**完成判据**：无全表扫描慢查询（type 不低于 ref/range 或 rows 千级可接受）；结果记录备查。

---

## 阶段五：验证与发布（任务组 5）

### 步骤 5.1 语法与环境验证（任务 5.1）

- `php -l` 全部 6 个改动文件
- 微信内置浏览器（X5/WKWebView）：四页聚焦展开、↑↓/Enter/Esc 键盘选择正常

### 步骤 5.2 spec 12 场景走查清单（任务 5.2）

| # | 场景（specs/city-filter/spec.md） | 验证入口 | 通过标准 |
|---|---|---|---|
| 1 | 聚焦展开候选 | 三列表页 | 点击/聚焦即展开默认候选，热门置顶 |
| 2 | 拼音搜索 hangzhou | 三列表页 | 候选过滤出「杭州」 |
| 3 | 中文包含「州」 | 三列表页 | 杭州/广州/苏州等全部含「州」城市 |
| 4 | 候选完整性（出售） | sale_list | 列表 = 有 listed 挂牌城市，逐个点选非空 |
| 5 | 候选外城市仍可搜到 | 三列表页 | 搜无数据城市提交 → 空态文案，不报错 |
| 6 | 后台候选不受影响 | bct orders | DISTINCT 口径不变，深色主题配色正常 |
| 7 | 拼音直接提交 | 三列表页 + bct orders | GET city=hangzhou → 「杭州」结果非 0 |
| 8 | 带行政后缀别名 | 同上 | 「武汉市」→「武汉」结果 |
| 9 | 未收录输入 | 同上 | 空态返回，无 PHP 错误 |
| 10 | 清除回到全部 | 同上 | 点 ✕ 提交后 city 为空、显示全部 |
| 11 | URL 兼容 | 三列表页 | `?city=北京&page=2` 行为与改造前一致 |
| 12 | 注册页零回归 | register + wechat_complete | 全量展开、搜索、归一化落库不变 |

### 步骤 5.3 发布（任务 5.3）

- 按提交点 A→B→C→D（→E 索引迁移如有）推送
- 服务器拉取后线上走查四个入口：block.58.tl 三个列表页 + bct 后台交易管理，确认无慢查询告警
- 回滚方案：单 commit revert，无数据迁移依赖（设计文档 Migration Plan）

## 提交粒度汇总

| 提交 | 内容 | 文件 |
|---|---|---|
| A | 组件升级（参数/双数据集/变体/清除） | includes/city_picker.php |
| B | buildCityFilterWhere + 归一化扩展 | includes/functions.php |
| C | block 三页接入（可逐页拆分） | block/{sale,purchase,claim}_list.php |
| D | bct 后台接入 | bct/admin/orders.php |
| E（条件） | 索引迁移 SQL（仅当 4.1 核验缺失） | init/migration-*.sql，单独提交 |
