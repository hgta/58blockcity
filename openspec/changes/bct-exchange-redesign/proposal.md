## Why

当前 BCT 子站（`bct.58.tl`）的页面结构与交互更偏向“信息发布/二手交易”，缺乏数字资产交易所应有的信息密度、实时感和资产叙事。用户已将城市人气值理解为“各城市发行的虚拟币”，需要通过交易所化的设计来强化“有买有卖、价格起伏”的市场氛围，从而提升用户交易意愿与平台专业度。

## What Changes

- 将 BCT 子站整体视觉升级为交易所风格（深色金融科技主题 + 红绿涨跌色 + 卡片式布局）。
- 新增全局行情概览：横向滚动报价带、市场情绪指数、24h 成交额、涨跌幅榜。
- 新增城市代币列表页（类似 CoinMarketCap）：支持分页、排序、搜索，展示市值、价格、24h 涨跌、成交量。
- 新增单城市代币详情交易页（类似 Binance 现货页）：K 线/走势、买卖盘深度、最新成交、快速下单。
- 新增最新成交动态组件并在首页/详情页复用。
- 改造用户后台为投资组合视图：总资产估值、各城市持仓占比、盈亏展示、资产/订单一体化。
- 后端补充价格历史聚合、深度聚合、市值/成交量计算等数据接口/方法。
- 排名前 5 城市（北京、上海、广州、深圳、杭州等）在首页和行情中默认置顶突出。

## Capabilities

### New Capabilities

- `bct/market-overview`: 首页全局行情概览，包括滚动报价带、市场情绪指数、24h 成交额、TOP5 城市卡片。
- `bct/city-token-list`: 城市代币列表页，支持分页/排序/搜索，展示市值、价格、24h 涨跌、成交量。
- `bct/city-token-detail`: 单城市代币详情交易页，包含 K 线/走势、买卖盘深度、最新成交、快速下单。
- `bct/order-book`: 买卖盘深度展示与聚合计算。
- `bct/recent-trades`: 最新成交动态展示。
- `bct/portfolio`: 用户投资组合视图（资产估值、持仓占比、盈亏、订单一体化）。
- `bct/exchange-theme`: 交易所风格视觉主题（深色金融科技风格、红绿涨跌色、响应式适配）。

### Modified Capabilities

- 无现有能力变更（本次为新增能力集）。

## Impact

- **前端页面**：`bct/index.php`、`bct/market.php`、`bct/trade.php`、`bct/user/dashboard.php` 将进行大幅改造或重构。
- **新增页面**：`bct/city.php`（单城市交易详情页）等。
- **后端类**：`classes/CityBCT.php`、`classes/BCTOrder.php`、`classes/BCTTransaction.php`、`classes/UserBCTAccount.php` 需补充聚合查询方法。
- **数据库**：依赖现有 `city_bct`、`bct_orders`、`bct_transactions`、`user_bct_account` 表，不新增核心表，仅通过聚合查询复用数据。
- **第三方依赖**：引入前端图表库（ECharts / lightweight-charts）CDN，不引入构建工具。
- **SEO/性能**：单城市页需规范 URL（`?city=北京`），图表按需初始化，移动端优先响应式。
