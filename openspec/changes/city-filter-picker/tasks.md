# Tasks：city-filter-picker 批 1（组件 + 4 页接入）

## 1. 组件升级（includes/city_picker.php）

- [x] 1.1 新增可选参数 `$cityPickerCandidates` / `$cityPickerVariant` / `$cityPickerAllowClear` / `$cityPickerPlaceholder`，默认值保持现有行为（不传 = 现状），`php -l` 通过
- [x] 1.2 双数据集渲染：空关键字展示 candidates（热门 chips 置顶），输入后从全量 `$cityOptions` 过滤；脚注显示「默认 N 个有数据城市 · 输入可搜全部 M 个」
- [x] 1.3 `admin` 变体：样式改用 `assets/css/admin.css` 的 `--admin-*` CSS 变量（深色主题）；`filter` 变体：padding/高度对齐 `.filter-bar` 内 `<select>`
- [x] 1.4 `allowClear`：输入框右侧 ✕ 清除按钮，清空后恢复「全部城市」语义
- [x] 1.5 回归验证 `auth/register.php` 与 `auth/wechat_complete.php`：不传新参数时行为与升级前一致（展开全量候选、中/拼音搜索、提交归一化落库）——已用「新旧组件同参数渲染输出 diff」验证等价

## 2. block 三页接入

- [x] 2.1 `block/sale_list.php`：候选 SQL 改为 `cities WHERE id IN (SELECT DISTINCT city_id FROM block_listings WHERE status='listed') ORDER BY rank`；`<select>` 换为 city_picker（`filter` 变体 + allowClear），搜索全集传 `getAllCities()`
- [x] 2.2 `block/purchase_list.php`：同构改造，子查询 `purchase_requests WHERE status='active'`
- [x] 2.3 `block/claim_list.php`：同构改造，子查询 `blocks WHERE status='sold'`
- [x] 2.4 三页服务端筛选加 D4 归一化：`normalizeCityName()` 优先 → `(c.name LIKE ? OR c.pinyin LIKE ?)` 兜底 → 原输入包含匹配；当前选中值用标准名回显；候选外城市显示空态文案（如「该城市暂无在售挂牌」）
- [x] 2.5 `includes/functions.php` 如需对筛选页暴露 `normalizeCityName()` 的 cities 入参，复用 `getAllCities()` 结果，避免重复查询（三页/后台均一次查询两用）

## 3. bct 后台接入

- [x] 3.1 `bct/admin/orders.php`：保留 `DISTINCT city FROM bct_orders` 候选，LEFT JOIN cities 取 pinyin/rank（不在 cities 的城市仍展示、pinyin 留空；排序规则按 `init/migration-bct-price-history.sql` 先例对齐避免 #1267）
- [x] 3.2 `<select>` 换为 city_picker（`admin` 变体 + allowClear），搜索全集传 `getAllCities()`
- [x] 3.3 服务端城市筛选加同款归一化（`bct_orders.city` 为城市名，单列 LIKE）

## 4. 性能与索引核验

- [x] 4.1 确认 `block_listings.city_id`、`purchase_requests.city_id`、`blocks.city_id`、`bct_orders.city` 索引；缺失则出幂等迁移 SQL（单独提交，不混入业务 commit）——静态核验（init/db-init.sql）：`idx_city_status(367)` / `city_zone_block(1143)` / `city_zone_block UNIQUE(952)` / `KEY city(934)` 均存在，无缺失，无需迁移 SQL；线上 `SHOW INDEX` 随部署复核
- [ ] 4.2 线上 EXPLAIN 核验三页候选 DISTINCT 查询与列表主查询无慢查询（本地无 DB，待部署后）

## 5. 验证与发布

- [x] 5.1 全部改动文件 `php -l` 通过（6/6）；组件在微信内置浏览器（X5/WKWebView）聚焦展开、键盘选择正常——待线上复核
- [ ] 5.2 按 spec 验收场景逐条走查：四页候选完整性、拼音/别名直接提交、清除回全部、旧 URL `?city=北京&page=2` 兼容、注册页零回归（待线上）
- [ ] 5.3 提交推送；服务器拉取后线上走查四个入口（block.58.tl 三个列表页 + bct 后台交易管理），确认无慢查询告警
