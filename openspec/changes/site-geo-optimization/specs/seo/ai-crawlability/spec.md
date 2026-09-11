## Purpose

确保主流生成式引擎（ChatGPT、Perplexity、豆包、Kimi、元宝、Google AI Overview 等）的爬虫能够无阻碍地获取本站公开页面，并通过显式规则与 `llms.txt` 向这些引擎清晰表达站点的可抓取范围与内容入口。

## ADDED Requirements

### Requirement: AI 爬虫可达性
所有公开页面 SHALL 对主流生成式引擎爬虫返回 HTTP 200 与完整的服务端渲染 HTML，且 MUST NOT 被 CDN 的机器人拦截、JS Challenge 或验证页阻断。

#### Scenario: 搜索型爬虫抓取首页
- **WHEN** 以 `OAI-SearchBot`、`PerplexityBot`、`Claude-SearchBot` 或 `Bytespider` 的用户代理请求首页
- **THEN** 响应状态为 200，`Content-Type` 为 `text/html`，响应体为真实页面 HTML 而非拦截页或验证页

#### Scenario: 训练型爬虫抓取内容页
- **WHEN** 以 `GPTBot`、`ClaudeBot` 或 `Google-Extended` 对应用户代理请求任一公开内容页
- **THEN** 响应状态为 200 且返回真实 HTML

### Requirement: robots.txt 显式放行 AI 爬虫
`robots.txt` SHALL 显式声明主流 AI 爬虫的抓取规则，其放行策略 MUST 与站点意愿一致。后台、用户中心、购物车、订单支付等非公开路径 MUST 保持禁止抓取。

#### Scenario: 显式放行搜索型与训练型爬虫
- **WHEN** 读取 `robots.txt`
- **THEN** 其中包含针对 `GPTBot`、`OAI-SearchBot`、`PerplexityBot`、`ClaudeBot`、`Claude-SearchBot`、`Google-Extended`、`Bytespider` 的 `Allow: /` 声明

#### Scenario: 私密路径仍被禁止
- **WHEN** AI 爬虫尝试抓取 `/admin/`、`/auth/`、`/user/`、`/cart/`、`/order/` 等路径
- **THEN** `robots.txt` 对这些路径保持 `Disallow`

### Requirement: llms.txt 内容引荐
站点 SHALL 在主域根路径提供 `/llms.txt`，以结构化文本说明站点定位、内容分类与关键入口链接。各子域 SHALL 提供指向主域实体的 `llms.txt`。

#### Scenario: 主域 llms.txt 可访问
- **WHEN** 请求 `https://www.58.tl/llms.txt`
- **THEN** 返回 200，且内容包含站点定位说明与主要栏目（城市、区块、人气值、NFT、商城、社区）的入口链接

#### Scenario: 子域指向主实体
- **WHEN** 请求任一子域（如 `https://mall.58.tl/llms.txt`）
- **THEN** 返回 200，且内容显式指向主域实体 `https://www.58.tl/`

### Requirement: AI 爬虫访问度量
站点 SHALL 支持从服务器访问日志中按用户代理统计主流 AI 爬虫的访问量，以建立优化前后可对比的基线。

#### Scenario: 统计 AI 爬虫访问量
- **WHEN** 对访问日志按 AI 爬虫用户代理关键字过滤并计数
- **THEN** 可得到各 AI 爬虫的请求数量，作为效果度量基线
