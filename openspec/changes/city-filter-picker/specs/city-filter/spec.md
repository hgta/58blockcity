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
