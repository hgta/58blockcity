## Why

首页的「涨跌城市 ▲0 / ▼0」以及行情页、城市详情页的涨跌幅长期恒为 0，城市详情页的 24h 高低价与价格走势图也全为空。根因是这些指标的数据源指向 `bct_transactions`（成交流水），而该表**从未产生过记录**：

- `bct_transactions` 仅由 `BCTOrder::executeTrade()` 写入，而 `executeTrade()` 只被**平台交易（`trade_type='platform'`）**的自动/手动撮合调用。
- 平台交易限制单笔 **500 BCT 以下**，而用户实际发布的是 19000~65000 量级的大额挂单，只能走 `direct`（直接交易）或 `mediator`（中介交易）。
- 结果是：**大额挂单永不进入撮合 → `bct_transactions` 永远为空 → 所有依赖它的行情指标恒为 0**。

与此同时，项目已存在一套**真实可用**的价格数据：`cities.bct_current_price`，由 `CityBCT::updatePrice()` 维护（后台手工改价、批量改价、撮合后的 `autoAdjustPrice()` 自动调价都会写入）。也就是说，行情显示的两套价格来源长期割裂：

| 数据源 | 状态 | 被谁消费 |
|---|---|---|
| `cities.bct_current_price` | 有数据、持续更新 | 总市值、城市列表价格 |
| `bct_transactions.price` | **始终为空** | 涨跌城市、涨跌榜、24h 高低价、走势图 |

值得注意的是，本项目**已经有过一次针对双数据源的重构**（`archive/2026-09-08-city-prices-pagination` 将单价并入 `cities`），当时确立的原则是**单一事实源**。本次变更正是回到该原则：让行情指标改读已经存在的权威价格字段。

## What Changes

- 新增 `bct_price_history` 价格历史表，记录城市价格的时间序列（城市、当前价、基础价、记录时间）。
- 在 `CityBCT::updatePrice()` 内埋点写入历史，**所有改价路径自动覆盖**（后台单行保存、后台批量设置、自动调价），无需在各调用方分别埋点。
- 改造以下方法**改用 `bct_price_history`** 作为数据源：
  - `get24hChanges()` —— 首页/行情页/城市详情/交易页的涨跌幅
  - `getTopGainersLosers()` —— 涨跌榜（复用 `get24hChanges()`，自动受益）
  - `getCity24hHighLow()` —— 城市详情 24h 最高/最低价
  - `getPriceHistory()` —— 城市详情的价格走势数据
- 涨跌幅采用**时间点对齐**口径：当前价取该城市最新一条历史，前价取「24 小时前及之前的最后一条」；若近期无调价，当前价回退到最新一条历史，使**所有有历史记录的城市都能被统计**，不再因「窗口内无调价」而从统计中消失。
- 迁移脚本为**所有城市补一条初始快照**（以当前 `bct_current_price` 为起点），使历史表上线即有数据，避免冷启动期指标长期为 0。
- 明确本次**不处理成交量类指标**：`getCity24hVolume()`（24h 成交量）、`getRecentTrades()`（最近成交列表）与走势图的成交量柱状图仍依赖 `bct_transactions`，需要真实的成交事件才能填充，留待后续变更。`getPriceHistory()` 的 `volume` 字段同步**返回 0 并注明原因**，不以假数据填充。

## Capabilities

### New Capabilities

- `bct/price-history`: 城市 BCT 价格的时间序列记录与消费，涵盖历史表的写入时机、涨跌幅的时间点对齐口径、以及涨跌城市/涨跌榜/24h 高低价/价格走势四个指标的取数规则。

### Modified Capabilities

- 无。既有 `bct/batch-order-publish`、`bct/trade-publish-limits`、`bct/order-expiry` 的需求不变。

## Impact

- **数据库**：新增 `bct_price_history` 表；迁移脚本为存量城市补初始快照。无字段变更。
- **后端类**：`classes/CityBCT.php` 为主要改动点——`updatePrice()` 增加埋点，`get24hChanges()` / `getTopGainersLosers()` / `getCity24hHighLow()` / `getPriceHistory()` 更换数据源。
- **消费页面**（无需改动页面代码，取数逻辑集中在类内）：
  - `bct/index.php` —— 涨跌城市 ▲▼、各城市 `change_pct`
  - `bct/market.php` —— 行情列表 `change_pct`
  - `bct/city.php` —— 城市详情涨跌幅、24h 高低价、走势图
  - `bct/trade.php` —— 交易页预览行情 `change_pct`
- **性能约束**：`bct/index.php` 与 `bct/market.php` 在 421 个城市的循环中逐个取数，因此 `get24hChanges()` 必须**一次批量返回全部城市结果**（现有实现即为批量聚合，改造时保持该特性），不得引入按城市逐个查询的 N+1 模式。
- **不涉及**：`getCity24hVolume()`、`getRecentTrades()`、成交额统计——这些需要真实成交事件，属后续变更范围。
- **数据保留**：历史表不设自动清理（长期保留）。
