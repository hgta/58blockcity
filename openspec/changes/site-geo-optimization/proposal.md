# 全站生成式引擎优化（GEO）

## Why

AI 搜索正在成为新的流量入口：ChatGPT / Perplexity / 豆包 / Kimi / 元宝 / Google AI Overview 在回答用户问题时，会引用并推荐它们「读得懂、信得过」的网站。本站目前对这一渠道几乎零优化——不是内容不够，而是**内容对 AI 不可见、不可理解、不可归因**。

可达性实测已确认基础设施没问题：`GPTBot`、`Bytespider`、`PerplexityBot` 请求 `https://www.58.tl/` 均返回 `HTTP/2 200` + 真实 HTML，Cloudflare 未拦截。缺的是可达性之后的三个环节：

1. **不可发现**：`help/`（9 篇）、`news/`、`rankings/` 等解释型静态内容页不在 `sitemap.php` 中（命中数 = 0），AI 抓不到最有引用价值的内容。
2. **不可理解**：全站仅 12 种 `@type`，缺 `Product/Store/Article/FAQPage/HowTo/Person/Place/DefinedTerm` 等近 9 类；帮助中心 9 篇文章只有 `BreadcrumbList`，首页 `index.php` 完全没有结构化数据。
3. **不可归因**：全站 `sameAs` 数量为 0，`Organization` 仅 2 处且无 `@id`。8 个子域（www/block/bct/mall/nft/v/bid/club）各自为政，AI 无法判断它们是同一个实体，品牌信号被切成 8 份。

## What Changes

- **新增 `llms.txt`**：主域一份说明「我们是谁、有什么、去哪儿看」，各子域一份指向主实体。
- **`robots.txt` 显式放行 AI 爬虫**：现状 `User-agent: *` 已通配放行，补充显式声明（`GPTBot`/`OAI-SearchBot`/`PerplexityBot`/`ClaudeBot`/`Google-Extended`/`Bytespider` 等），明确放行意图并防止未来 CDN 托管规则覆盖。策略为**搜索型与训练型全部放行**。
- **`sitemap.php` 补全静态内容**：将 `help/`（9 篇）、`news.php`、`news/`、`rankings/`（2 个）、`top100city.html`、`all-cities.php` 等纳入，`lastmod` 由文件 `filemtime()` 生成。
- **`SeoHelper` 新增结构化数据方法**：`organizationSchema()` / `webSiteSchema()` / `articleSchema()` / `faqPageSchema()` / `howToSchema()` / `productSchema()` / `storeSchema()` / `personSchema()` / `placeSchema()` / `definedTermSet()` / `nftSchema()`，风格对齐现有 `breadcrumbList()`。
- **建立全局品牌实体**：新增 `shared/organization.php` 输出带 `@id` 的 `Organization` JSON-LD，**所有子站复用同一 `@id`**，各页 `publisher` 改为 `@id` 引用，从而把 8 个子域聚合成一个实体。
- **实体关系声明**：`sameAs` 仅放自有跨平台主页（GitHub 等）；`BlockCity.vip` 作为**独立第三方工具站**，用 `isRelatedTo` + 正文说明表达弱关系，**不放入 `sameAs`**（避免实体错误合并）。
- **首页补全结构化数据**：`index.php` 增加 `WebSite` + `Organization`。
- **帮助中心内容引擎化**：9 篇文章改为「答案优先」结构（首段 40–80 字直答 + 分点证据 + 边界说明），补充 FAQ，并新增术语表页（人气值 / BCT / 区块 / 互访圈 / 区块城市）。
- **建立度量基线**：从服务器 access log 统计 AI Bot UA 访问量，作为后续所有改动的效果对照。

## Capabilities

### New Capabilities

- `seo/ai-crawlability`: AI 爬虫可达性保障、robots.txt 显式放行策略与 `llms.txt` 引荐清单。
- `seo/sitemap-coverage`: sitemap 对静态与动态公开页面的覆盖完整性。
- `seo/structured-data`: 各实体页面的 JSON-LD 结构化数据覆盖与正确性。
- `seo/entity-identity`: 品牌实体锚点（Organization `@id` 聚合、`sameAs`、与第三方站的关系声明）。
- `seo/answer-first-content`: 面向 AI 引用的内容结构（答案优先段落、FAQ、术语定义）。

### Modified Capabilities

<!-- 无：本项目现有主规格为 bct/ 与 popularity/ 域，本次不改变其需求。 -->

## Impact

**新增文件**
- `llms.txt`（主域），各子域 `llms.txt`
- `shared/organization.php`：全局 `Organization` JSON-LD 组件
- 术语表页（如 `help/glossary.html`）

**修改文件**
- `robots.txt`（AI 爬虫显式声明）
- `sitemap.php`（补充静态节点 + `filemtime` lastmod）
- `classes/SeoHelper.php`（新增 11 个 schema 方法）
- `shared/header.php`（引入全局实体 + `@id` 引用）
- `index.php`（补 `WebSite` + `Organization`）
- `help/*.html`（9 篇：结构改造 + `Article`/`FAQPage`/`HowTo`）
- `city.php`、`block/city.php`（`Place`）
- `mall/product/detail.php`、`mall/shop/view.php`、`mall/model/view.php`、`mall/author/view.php`（`Product`/`Store`/`Person`）
- `nft/nft/view.php`（`VisualArtwork`）
- `club/post.php`（`publisher` 改用 `@id` 引用）
- `news.php` / `news/*.html`（`Article`）

**依赖与风险**
- 无新增外部依赖；纯服务端渲染与静态文件改动。
- 无 **BREAKING** 变更，不修改现有业务逻辑与 URL 结构。
- 需用户提供 `sameAs` 的自有平台主页 URL 清单（GitHub 已提供，微信账号名待补充为可引用形式）。
- Cloudflare 侧无需改动（实测已放行）。
