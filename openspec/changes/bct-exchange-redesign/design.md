## Context

当前 BCT 子站基于 PHP + Bootstrap 3/4 + jQuery 构建，复用 `/shared/header.php` 与 `/shared/footer.php`。数据层已存在 `city_bct`、`bct_orders`、`bct_transactions`、`user_bct_account` 等表，但前端呈现偏信息发布风格，缺少金融资产交易所需的信息密度、实时感与资产叙事。具体现状参见 `proposal.md`。

本次改版不新增核心数据表，主要通过聚合查询与前端可视化复用现有数据；同时保留共享头部导航，仅对 BCT 内容区应用新主题，降低跨子站跳转的割裂感。

## Goals / Non-Goals

**Goals:**
- 将 BCT 子站首页、行情列表、城市详情、个人中心统一改造为交易所风格。
- 实现城市币列表、单城市 K 线/走势、买卖盘深度、最新成交、投资组合视图。
- 排名前 5 城市在首页与列表中默认突出展示。
- 所有新增页面支持分页、排序、搜索与移动端响应式。

**Non-Goals:**
- 不做真实撮合引擎改造，保留现有 `bct_orders` + `bct_transactions` 机制。
- 不做 WebSocket 实时推送，首次加载展示最新数据，可选短轮询更新最新成交。
- 不新增用户账户体系或钱包体系，复用现有 `UserBCTAccount` 与 `BCTOrder`。
- 不改写其他子站（block/nft/bid/mall 等）的视觉风格。

## Decisions

### Decision: 前端图表库选用 ECharts
- **选择**：使用 Apache ECharts 5（CDN 引入）。
- **理由**：文档成熟、中文社区完善、支持折线/K线/面积图、与 jQuery 共存无冲突、可通过 CDN 引入避免构建工具改造。
- **替代方案**：lightweight-charts 更轻量但功能稍弱；Chart.js 不支持 K 线原生。考虑到未来可能需要 K 线，选 ECharts。

### Decision: 城市详情页路由
- **选择**：`bct/city.php?city={urlencode(城市名)}`。
- **理由**：与现有 `market.php?city=...`、`trade.php?city=...` 风格一致，无需配置 rewrite；SEO 通过页面标题与 canonical 补全。
- **替代方案**：`bct/city/{pinyin}` 更美观，但需要 URL rewrite 与拼音映射，增加部署复杂度。

### Decision: 深色主题仅作用于 BCT 内容区
- **选择**：在 BCT 页面额外引入 `/bct/assets/css/exchange-theme.css`，用 CSS 变量覆盖内容区背景、文字、卡片、表格样式；保留共享 header/footer 的白色主题。
- **理由**：共享 header 被所有子站复用，强制改深色会影响全局品牌一致性；BCT 内容区独立变暗足够营造交易所氛围。
- **替代方案**：全站深色。风险高，与其他子站风格冲突。

### Decision: TOP5 城市固定为北京、上海、广州、深圳、杭州
- **选择**：在配置中固定前 5 城市，用于首页突出与列表置顶；其余城市按市值/成交量动态排序。
- **理由**：用户明确希望前 5 城市突出，且这 5 个城市是当前 `market.php` 中已有的热门城市集合。
- **替代方案**：完全动态 TOP5（按 24h 成交量）。未来可扩展，但本次先固定以符合用户预期。

### Decision: 价格历史通过 `bct_transactions` 聚合生成
- **选择**：K 线/走势数据按时间区间聚合 `bct_transactions` 表中的成交价，按城市分组。
- **理由**：不新增表即可生成 OHLC/折线数据；若某城市成交稀疏，可降级为“最近成交价连线”或空状态提示。
- **替代方案**：新增 `city_price_history` 表存储分时数据。长期更优，但本次范围优先复用现有数据。

### Decision: 买卖盘深度按价格聚合 `bct_orders`
- **选择**：对 `bct_orders` 中 status 为 pending/processing 的同城市、同类型、同价格订单做 SUM(amount) 聚合。
- **理由**：直接反映当前市场挂单压力；平台/中介/直接交易统一纳入深度。
- **替代方案**：只统计平台交易。会低估市场深度，且与现有列表不一致。

### Decision: 最新成交每 30 秒可选轮询
- **选择**：城市详情页与首页的最新成交组件通过 `setInterval(30s)` 请求轻量 JSON API 刷新。
- **理由**：用户确认先展示最新数据即可，30 秒轮询成本低、实现简单。
- **替代方案**：WebSocket/SSE。实时性更强，但需后端常驻服务，超出本次范围。

## Risks / Trade-offs

- **[Risk] 现有 Bootstrap 样式与深色主题冲突** → Mitigation：使用独立 CSS 文件，提高选择器特异性，优先覆盖 Bootstrap 的 `.table`、`.btn`、`.card`、`.form-control` 等组件。
- **[Risk] 部分城市成交稀疏导致 K 线为空或只有单点** → Mitigation：当数据点少于 2 个时，图表区显示“成交数据不足，成为首位交易者”引导文案。
- **[Risk] 首页信息密度提升后移动端体验下降** → Mitigation：移动端采用垂直堆叠 + 横向滑动卡片，复杂表格支持横向滚动，统计指标 2 列或单列堆叠。
- **[Risk] 聚合查询在数据量大时变慢** → Mitigation：为 `bct_transactions` 和 `bct_orders` 的城市+时间字段确保已有索引；必要时为城市详情页缓存深度与最新成交 10 秒。
- **[Risk] 深色主题与主站橙色品牌色不协调** → Mitigation：BCT 子站使用独立主题色（如蓝紫渐变强调色），仅在 logo 与按钮保留橙色作为行动号召色，涨跌色严格使用绿/红。

## Migration Plan

1. 新增/修改后端类方法，补充行情聚合查询（不影响现有交易流程）。
2. 新增 `/bct/assets/css/exchange-theme.css` 与 `/bct/assets/js/exchange-charts.js`。
3. 改造 `/bct/index.php` 为交易所首页。
4. 改造 `/bct/market.php` 为城市币列表页。
5. 新增 `/bct/city.php` 为单城市交易详情页。
6. 改造 `/bct/user/dashboard.php` 为投资组合视图。
7. 改造 `/bct/trade.php` 样式并支持从城市详情页预填城市。
8. 本地验证深色主题、响应式、分页排序、图表渲染。
9. 部署后观察性能，必要时为热门城市加查询缓存。

## Open Questions

- 是否需要在城市详情页增加“城市公告/动态”区块（如该城市近期活动、区块销售信息）？
- 是否允许用户自定义涨跌颜色（部分用户习惯红涨绿跌）？
- 投资组合是否需要展示“累计盈亏”（需要历史成本数据，当前表结构未记录）？
