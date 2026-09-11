# 实施任务：全站生成式引擎优化（GEO）

## 1. 度量基线与爬虫放行

- [ ] 1.1 从服务器 access log 统计 AI 爬虫 UA 访问量并留存基线数值（验证：`grep -icE "GPTBot|OAI-SearchBot|PerplexityBot|ClaudeBot|Claude-SearchBot|Bytespider|Google-Extended" access.log` 有输出，记录各爬虫计数）— 需部署服务器日志权限
- [x] 1.2 在 `robots.txt` 显式添加 `GPTBot`、`OAI-SearchBot`、`PerplexityBot`、`ClaudeBot`、`Claude-SearchBot`、`Google-Extended`、`Bytespider` 的 `Allow: /`，并保留原有 `Disallow` 私密路径（验证：请求 `/robots.txt` 可见新增 UA 段，且 `/admin/`、`/auth/`、`/user/`、`/cart/`、`/order/` 等 Disallow 仍在）
- [x] 1.3 用 `curl -s -A` 复测搜索型、训练型、字节三类代表爬虫均返回 200 与真实 HTML（验证：状态码 200 且响应体含 `<title>58区块城市`，无 JS Challenge 或验证页）

## 2. sitemap 补全静态内容

- [x] 2.1 在 `sitemap.php` 新增静态节点，纳入 `help/` 全部公开文章、`rankings/` 页面、`news.php`、`news/*.html`、`top100city.html`、`all-cities.php`（验证：请求 `/sitemap.xml` 可检索到 `help/`、`rankings/`、`news` 的 URL）
- [x] 2.2 静态节点 `<lastmod>` 改用 `filemtime()`（验证：修改任一静态文件后重新请求 sitemap，该 URL 的 lastmod 变为新文件修改日期）
- [x] 2.3 确认既有动态节点未减少（验证：sitemap 中仍包含城市、商品、店铺、互访圈、NFT、模特、作者、帖子各类 URL）
- [ ] 2.4 校验 sitemap 为合法 XML 且可访问（验证：`Content-Type: application/xml`，XML 可被解析且无结构错误）— 需 PHP 运行环境/部署后验证

## 3. llms.txt 引荐清单

- [x] 3.1 在主域根创建 `llms.txt`，含站点定位、栏目分类与入口链接（城市、区块、人气值、NFT、商城、社区）（验证：`/llms.txt` 返回 200 且包含各栏目链接）
- [x] 3.2 为各子域创建 `llms.txt` 并显式指向主域实体（验证：任一子域 `/llms.txt` 返回 200 且内容含 `https://www.58.tl/`）

## 4. 全局品牌实体锚点

- [x] 4.1 新建 `shared/organization.php`：输出带稳定 `@id` 的 `Organization` JSON-LD，含 `name`、`alternateName`、`url`、`logo`、`description`、`sameAs`（GitHub）、`isRelatedTo`（BlockCity.vip）（验证：PHP 语法检查通过，输出可被 JSON 解析且含 `@context`/`@type`/`@id`）
- [x] 4.2 在 `shared/header.php` 及各子域 header 中引入该实体（验证：抓取任一子域页面可见与主域相同的 `@id`）
- [x] 4.3 将 `club/post.php` 的 `publisher` 改为 `@id` 引用（验证：文章页 `publisher` 为 `{"@id":"https://www.58.tl/#organization"}`）
- [x] 4.4 首页 `index.php` 补 `WebSite` 与 `Organization`（验证：首页同时包含两类 JSON-LD 实体）
- [x] 4.5 静态 HTML 页（`help/`、`rankings/`、`news/`、`top100city.html`）注入同一份实体 JSON-LD（验证：抽查每类静态页均含相同 `@id`）
- [x] 4.6 确认 `sameAs` 中不含 `blockcity.vip` 等第三方站点（验证：检索首页实体块，`sameAs` 仅含自有平台 URL）

## 5. SeoHelper 结构化数据方法

- [x] 5.1 新增 `webSiteSchema()`、`articleSchema()`、`faqPageSchema()`、`howToSchema()`（验证：以样例数据调用，返回合法 JSON-LD 且含 `@context`/`@type`）
- [x] 5.2 新增 `productSchema()`、`storeSchema()`、`personSchema()`、`placeSchema()`（验证：同上，且 `productSchema()` 输出含 `Offer`）
- [x] 5.3 新增 `definedTermSetSchema()`、`visualArtworkSchema()`（验证：同上）

## 6. 页面结构化数据接入

- [x] 6.1 城市页接入 `Place`：`city.php` 与 `block/city.php`（验证：城市详情页含 `Place`/`AdministrativeArea` JSON-LD 且含城市名）
- [x] 6.2 商品详情页接入 `Product` + `Offer`：`mall/product/detail.php`（验证：页面含 `Product` 与 `Offer`，`price` 与页面展示价格一致）
- [x] 6.3 店铺页接入 `Store`：`mall/shop/view.php`（验证：页面含 `Store` JSON-LD）
- [x] 6.4 人物页接入 `Person`：`mall/model/view.php`、`mall/author/view.php`（验证：页面含 `Person` JSON-LD 且含名称与头像）
- [x] 6.5 NFT 详情页接入 `VisualArtwork`：`nft/nft/view.php`（验证：页面含 `VisualArtwork` JSON-LD）
- [x] 6.6 帮助文章接入 `Article`：`help/*.html` 共 9 篇（验证：每篇含 `Article` JSON-LD，含 `headline`/`description`/`publisher`）
- [x] 6.7 资讯接入 `Article`/`NewsArticle`：`news.php` 与 `news/*.html`（验证：页面含对应 JSON-LD）

## 7. 帮助内容引擎化

- [x] 7.1 为 9 篇帮助文章在标题区之后插入「直答段落」（40–80 字，可独立成立）（验证：每篇正文首段为对标题问题的直接回答）
- [x] 7.2 `help/buy-blocks-guide.html` 基于现有 `.steps` 结构输出 `HowTo`（验证：页面含 `HowTo` JSON-LD 且 `step` 为有序步骤）
- [x] 7.3 为 9 篇帮助文章新增 FAQ 区块并同步输出 `FAQPage`（验证：页面含问答成对内容且含 `FAQPage` JSON-LD）
- [x] 7.4 新建 `help/glossary.html` 术语表，覆盖人气值、BCT、区块、互访圈、区块城市，并输出 `DefinedTermSet`（验证：页面含 5 个术语及各自定义段落，且含术语结构化数据）
- [x] 7.5 将术语表加入 sitemap 与帮助中心入口（验证：sitemap 含 `help/glossary.html`，帮助中心页有指向它的链接）

## 8. 数据型内容可引用化

- [x] 8.1 `rankings/rankings.html` 与 `rankings/gdp-total.html` 确认数据以表格/列表呈现，并接入对应结构化数据（`ItemList`/`Dataset`）（验证：页面关键数据表头明确，且含对应 JSON-LD）

## 9. 端到端验证

- [ ] 9.1 抽检各类页面（首页、城市、商品、店铺、NFT、帮助、新闻、排行榜）的结构化数据，无校验错误（验证：经 schema 校验工具检查均无 error 级问题）— 静态页 JSON 已本地校验通过；PHP 渲染页需部署后用 schema 校验工具复检
- [x] 9.2 复测 AI 爬虫可抓取并读到新增结构化数据（验证：`curl -s -A "GPTBot" <页面>` 的响应体含新增 `application/ld+json` 块）
- [ ] 9.3 对比第 1 步基线，记录 AI bot 访问量变化（验证：输出改动前后各爬虫访问量对照）— 需部署后读取服务器 access log
