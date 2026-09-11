# 设计：全站生成式引擎优化（GEO）

## Context

现状约束（动机见 `proposal.md`）：

- **渲染方式**：全站 PHP 服务端渲染，无前端框架、无构建流程。静态内容页（`help/*.html`、`rankings/*.html`、`top100city.html`、`news/*.html`）为手写 HTML，不使用模板。
- **代码组织**：共享层为 `shared/header.php`（全子站复用）；各子域另有自己的 `includes/header.php`；业务工具类集中在 `classes/`，其中 `classes/SeoHelper.php` 已提供统一 TDK / canonical / OG / `breadcrumbList()` / `itemListSchema()` / 百度推送。
- **多子域**：`www` / `block` / `bct` / `mall` / `nft` / `v` / `bid` / `club` 共 8 个域名，指向同一代码库的不同目录，共用同一数据库。
- **可达性**：已实测 `GPTBot` / `Bytespider` / `PerplexityBot` 请求主域均返回 `200` + 真实 HTML，Cloudflare 未拦截 → 无需改 CDN 配置。
- **sitemap 规模**：现有动态类型总量约 19,300 URL（商品 5000 / 帖子 5000 / 圈子 2000 / NFT 2000 / 模特 2000 / 作者 2000 / 店铺 1000 / 城市 315），加静态节点后仍远低于单文件 50,000 上限。

## Goals / Non-Goals

**Goals**

- AI 爬虫可稳定抓到并理解全站公开内容。
- 全站内容页对生成式引擎「可发现、可理解、可归因、可度量」。
- 8 个子域在结构化数据层面聚合为**同一个组织实体**。
- 改动集中在共享层，避免在 8 个子域重复实现。

**Non-Goals**

- 不追求被 AI「训练采纳」的排名，只针对「检索引用」场景。
- 不重写静态页为 PHP，不引入模板引擎或构建流程。
- 不改动 URL 结构、不改动现有业务逻辑、不引入前端 JS 渲染。
- 不做站外外链建设、不做竞价广告。

## Decisions

### D1：全局实体以 `shared/organization.php` 作为单一来源

新增 `shared/organization.php`，输出一段 `Organization` JSON-LD。PHP 页面通过 `include` 复用，而非在各子域 header 内各自硬编码。

- **理由**：8 个子域共用一个文件，改一处全站生效；与现有 `shared/header.php` 的复用模式一致。
- **替代方案**：写死在 `shared/header.php` 内 —— 被否决，实体声明会被页面级 SEO 逻辑挤占，且不利于单独维护。
- **替代方案**：各子域各自维护 —— 被否决，必然产生漂移，正是当前「8 个实体」问题的成因。

### D2：用 `@id` 聚合 8 个子域

`Organization` 声明固定 `@id: "https://www.58.tl/#organization"`。所有子域页面（含 `club/post.php` 现有的 `publisher`）改为通过 `@id` 引用该实体，不再以 `{"@type":"Organization","name":"58区块城市"}` 字符串重复声明。

- **理由**：`@id` 是 JSON-LD 的实体归一机制；AI 借此判定 8 个域名属于同一组织，而非 8 个独立站点。
- **替代方案**：仅统一 `name` 字符串 —— 被否决，字符串不构成实体同一性声明，AI 仍可能判为不同实体。

### D3：`sameAs` 与 `isRelatedTo` 的语义边界（关键决策）

- `sameAs`：**只放本站自有的跨平台主页**，当前确定为 `https://github.com/hgta/58blockcity`。
- `isRelatedTo`：放第三方独立平台 `BlockCity.vip`，配合页面正文说明「58区块城市是基于 BlockCity.vip 生态的独立第三方工具站集群」。
- **微信号 `BitPFP` 的处理**：微信号不是 URL，`sameAs` 的语义要求 URL，因此**不写入 `sameAs`**；改由 `ContactPoint`（`contactType` 标注）或 `description` 正文承载。

- **理由**：`sameAs` 的语义是「同一实体的另一个 URL」。BlockCity.vip 是独立第三方，若写入 `sameAs` 等于对外宣称「58区块城市 = BlockCity.vip」，会导致 AI 实体错误合并、信誉风险转嫁。
- **替代方案**：把 blockcity.vip 放进 `sameAs` 以「借势」其知名度 —— 被否决，属于错误的实体声明，且违反 `entity-identity` spec 中「sameAs 仅限自有平台」的约束。

### D4：sitemap 保持单文件，静态节点用 `filemtime()` 生成 lastmod

在现有 `sitemap.php` 的静态节点区补充 `help/`（9 篇）、`rankings/`（2 个）、`news.php`、`news/*.html`、`top100city.html`、`all-cities.php` 等；静态页 `<lastmod>` 取自 `filemtime()`。

- **理由**：总量约 19,300，远低于 50,000 单文件上限，拆 sitemap index 是过度设计；`filemtime()` 让 lastmod 反映真实更新，避免用生成时间造成「每天全站都更新」的噪声。
- **替代方案**：拆分为 `sitemap.xml` + `sitemap-static.xml` 索引结构 —— 被否决，当前规模无必要，且需改 nginx 规则。

### D5：`SeoHelper` 扩展沿用既有风格

在 `classes/SeoHelper.php` 中新增方法，签名与返回形式对齐现有 `breadcrumbList()` / `itemListSchema()`（接收数组、返回 `<script type="application/ld+json">…</script>` 字符串、用 `JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE` 编码）：

```
organizationSchema(array $org)        // 全局实体（D1/D2/D3）
webSiteSchema(array $site)            // 首页 WebSite
articleSchema(array $a)               // 帮助/新闻
faqPageSchema(array $qa)              // 问答
howToSchema(array $how)               // 分步指南
productSchema(array $p)               // 商品 + Offer
storeSchema(array $s)                 // 店铺
personSchema(array $p)                // 模特 / 作者
placeSchema(array $pl)                // 城市
definedTermSetSchema(array $terms)    // 术语表
visualArtworkSchema(array $n)         // NFT
```

- **理由**：统一构造入口便于校验与维护；调用方只需传数据，不关心 JSON 细节。
- **替代方案**：各页面直接手写 JSON —— 被否决，正是当前 `@type` 零散、字段不统一的成因。

### D6：静态 HTML 页的注入策略

静态内容页不使用模板，无法 `include` PHP。采用**内容一致的手工注入**：PHP 页走 `shared/organization.php`，静态页直接把同一份 JSON-LD 块写入 `<head>`。

- **理由**：静态页仅约 12 个，引入构建流程的成本高于收益；全部改写成 PHP 属超出范围的改动。
- **代价**：实体声明变更时需同步更新这些静态文件 → 列入 Risks 并通过「实体声明集中定义 + 变更清单」缓解。

### D7：帮助内容「答案优先」改造方式

对 9 篇帮助文章：

1. 在 `.help-detail-header` 之后插入一个**直答段落**（40–80 字，直接回答标题提出的问题，可独立成立）。
2. 在文章尾部新增 FAQ 区块（问题 + 答案成对）。
3. 在 `<head>` 中按内容类型补 JSON-LD：全部补 `Article`；`buy-blocks-guide.html` 依托现有 `.steps` 结构输出 `HowTo`；含 FAQ 的页面输出 `FAQPage`。
4. 保留现有 `BreadcrumbList` 不变。

- **理由**：不改变既有页面结构与样式，只做增量注入，风险最低。
- **替代方案**：重构帮助中心为动态系统 —— 被否决，远超本次范围。

### D8：`llms.txt` 结构与放置

主域根放 `/llms.txt`，格式遵循 llms.txt 惯例：`# 站点名` → `>` 一句话定位 → `## 分类` → 每行 `- [名称](URL): 说明`。子域各放一份，首行显式指向主域实体。

- **理由**：多子域架构下，单一入口文件能一次性向 AI 说明「我们是谁、有什么、去哪儿看」。
- **替代方案**：不提供 —— 被否决，多子域场景下 AI 缺少站点地图式文本引导。

## Risks / Trade-offs

- **[静态页实体声明漂移]** 静态 HTML 中的 JSON-LD 与 `shared/organization.php` 不同步 → 建立「实体声明变更清单」记录所有持有副本的静态文件；改动时逐项同步。
- **[结构化数据与页面内容不一致]** 声明了页面上不存在或矛盾的数据（如价格）→ 所有 schema 字段必须取自页面渲染所用的**同一数据源**，禁止硬编码。
- **[`FAQPage` 滥用]** 若页面无真实问答内容却标 `FAQPage`，可能被判定为作弊 → 仅在确实新增问答区块的页面输出。
- **[Cloudflare 策略回归]** 当前放行，但 CF 侧策略可能被改动 → 在度量基线中保留 AI 爬虫可达性抽检。
- **[度量缺失导致效果不可证]** 无 AI 流量基线则无法证明改动有效 → 先建基线再实施（见 Migration Plan）。
- **[全放行训练型爬虫]** 按用户决策搜索型与训练型全放行，内容可能被用于模型训练 → 已确认接受。

## Migration Plan

分阶段部署，每阶段可独立上线与回滚：

1. **基线**：从 access log 统计 AI bot UA 访问量，留存对照数据。
2. **阶段一（低风险、立即可见）**：`sitemap.php` 补静态节点 → `robots.txt` 显式声明 → 新增 `llms.txt`。
3. **阶段二（实体锚点）**：新增 `shared/organization.php` + 改 `shared/header.php` 引用 → 首页补 `WebSite` + `Organization` → 静态页注入同一实体块。
4. **阶段三（结构化数据）**：`SeoHelper` 新增方法 → 按页面逐个接入（优先 `city.php`、`help/*.html`、`mall` 商品与店铺）。
5. **阶段四（内容引擎化）**：帮助文章直答段落 + FAQ + 术语表页。

**回滚**：各阶段均为纯增量改动（新增文件 / 追加节点 / 追加 JSON-LD），回滚即移除对应改动；不涉及数据迁移与不可逆操作。

## Open Questions

- 术语表页的落地位置（`help/glossary.html` 还是新目录 `/glossary/`）可在实施阶段决定，不影响 spec 与任务拆分。
- 除 GitHub 外是否还有其他自有平台主页可入 `sameAs`，可后续补充，实体声明结构已预留数组。
