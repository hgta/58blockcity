# help 子站接入结构化数据（GEO 主力）

## Why

help 子站动态页面的结构化数据覆盖为**零**：`help/` 下 PHP 文件检索 `ld+json` 零命中，全部页面只有 `og:title / og:description / og:type` 三条最基础的元信息（`help/_layout.php:26-28`）。

由此产生三个直接后果：

1. **实体锚点断链**：`shared/organization.php` 的全局 `Organization`（`@id: https://www.58.tl/#organization`）已注入其余 9 个子域与首页，唯独 help 子站没有。AI 引擎无法把 help 内容与「58区块城市」这个实体关联，引用时容易归因到别的站点。
2. **最有被引价值的内容反而最不可抽取**：帮助文章（Article）、常见问题（FAQPage）、分步教程（HowTo）、术语表（DefinedTermSet）正是生成式引擎最爱引用的四类内容，目前全部没有 schema。
3. **讽刺的现状**：9 个**已被 301 的静态 HTML**（`help/help.html` 等）反而带了完整的 Organization + Article + FAQPage + HowTo + BreadcrumbList；真正被收录的动态页面一套都没有。即「**有 schema 的被跳过了，被收录的没有 schema**」。

好消息是**不需要造轮子**：`classes/SeoHelper.php` 已有 `articleSchema()` / `faqPageSchema()` / `howToSchema()` / `definedTermSetSchema()` / `breadcrumbList()` / `itemListSchema()` / `webSiteSchema()` / `organizationSchema()`，`shared/organization.php` 已是单一来源。本变更本质是**接线 + 保证 schema 与可见内容一致**。

**依赖**：本变更改 `help/_layout.php` 的 `<head>`，必须在 `help-seo-foundation`（批次 A，权威域与 canonical）落地之后实施——canonical 与 `@id`/`url` 取值依赖 A 确定的权威域。

## What Changes

- **全局实体锚点**：`help/_layout.php` 引入 `shared/organization.php`，全站输出与其他子域**同一 `@id`** 的 `Organization`。
- **文章页**：`Article`（headline / description / image / datePublished / dateModified / articleSection / publisher=@id）；`content_type='steps'` 且步骤非空时附加 `HowTo`；存在关联 FAQ 时附加 `FAQPage`。
- **FAQ 页**：当前展示分类的问答输出 `FAQPage`。
- **术语表页**：术语输出 `DefinedTermSet` + `DefinedTerm`。
- **分类页**：`CollectionPage`+`ItemList`（分类下文章）+ `BreadcrumbList`。
- **首页**：`WebSite`（含 `SearchAction` → 搜索框富摘要）+ `Organization` + `BreadcrumbList`。
- **全站面包屑**：`BreadcrumbList`（视觉面包屑已存在，补结构化版本）。
- **Open Graph 补全**：`og:url`（A 已覆盖）、`og:image`（用 `cover_image`，缺省回退品牌图）、文章页 `og:type=article`。

## Capabilities

### New Capabilities

- `seo/help-structured-data`：help 子站各页面输出与可见内容一致的结构化数据，并复用全局品牌实体 `@id`。

### Modified Capabilities

<!-- 无：site-geo-optimization / geo-content-coverage 均未归档，seo/* 未进入主规格。 -->

## Impact

**修改文件**

- `help/_layout.php` — 引入实体锚点、统一 JSON-LD 输出位、og:image / og:type
- `help/pages/home.php` — `WebSite` + `SearchAction`
- `help/pages/article.php` — `Article` + `HowTo` + `FAQPage` + `BreadcrumbList`
- `help/pages/faq.php` — `FAQPage`
- `help/pages/glossary.php` — `DefinedTermSet`
- `help/pages/category.php` — `CollectionPage` + `ItemList` + `BreadcrumbList`
- `help/pages/search.php` — `BreadcrumbList`（无结果/结果页不做 schema）

**新增文件**

- 无（全部复用 `classes/SeoHelper.php` 与 `shared/organization.php`）

**依赖与风险**

- **排期硬约束**：与 `help-site-layout`（进行中）及 `help-seo-foundation`（批次 A）同改 `help/_layout.php`。必须在两者之后实施，**不得并行编辑**。
- **schema 与可见内容必须一致**：`FAQPage` 只输出页面当前可见的问答（FAQ 页只输出当前 tab，不输出全部 tab）否则属隐藏内容，可能被判 spam 并失去富摘要资格。
- **PHP 渲染页无法本地校验**：需部署后用结构化数据校验工具复检（静态 JSON 可本地 `json_decode` 验证）。
- **对百度无效**：百度不解析 JSON-LD。本变更价值在 ChatGPT / Perplexity / 豆包 / Kimi 等生成式引擎的引用与归因，勿与百度收录类变更混为一谈。
- **无 BREAKING 变更**，纯 `<head>` 内新增，不影响页面功能与样式。
