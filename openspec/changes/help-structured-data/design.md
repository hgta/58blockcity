# 设计：help 子站结构化数据接入

## 前提事实

| 事实 | 证据 |
|---|---|
| help 动态页无任何 JSON-LD | `help/**/*.php` 检索 `ld+json` 零命中 |
| `SeoHelper` 已具备全部所需方法 | `articleSchema`(:776) / `faqPageSchema`(:824) / `howToSchema`(:855) / `definedTermSetSchema`(:1048) / `breadcrumbList`(:612) / `itemListSchema`(:642) / `webSiteSchema`(:741) |
| `publisher` 默认已回退到组织 `@id` | `articleSchema` 内 `'publisher' => $a['publisher'] ?? ['@id' => self::ORG_ID]` |
| 其余 9 个子域已注入实体锚点 | `shared/header.php:80` require + 各子域 header 复用 |
| 文章表已有可用字段 | `help_articles`: `title` `summary` `cover_image` `created_at` `updated_at` `content_type` `content_steps` |
| FAQ 答案 CSS 折叠但 HTML 中存在 | `_layout.php` `.hc-faq-a{display:none}`；`pages/faq.php:62-72` 服务端已输出 |

---

## D1：实体锚点复用 `shared/organization.php`，不自行拼装

**决策**：`help/_layout.php` 顶部 `require_once __DIR__ . '/../shared/organization.php'`，在 `help_header()` 输出的 `<head>` 内调用既有输出函数。

**理由**：该文件已在文件头注释中声明为「全局实体单一来源」。help 是**漏接入**而非需要不同实现。自行拼装会重蹈静态页「两份实体描述漂移」的覆辙（`geo-content-coverage` 已修过一次「八个子域 vs 九个子域」的不一致）。

**路径注意**：`help/_layout.php` 到 `shared/` 的相对路径为 `__DIR__ . '/../shared/organization.php'`，与 `block/includes/header.php:20` 的两级上跳不同，需实测确认。

---

## D2：`publisher` 一律用 `@id` 引用

**决策**：所有 schema 的 `publisher` / `author` 中的组织引用统一为 `{"@id":"https://www.58.tl/#organization"}`，不内联完整 Organization。

**理由**：与 `club/post.php` 既有做法一致（`site-geo-optimization` 任务 4.3 确立）。`SeoHelper::articleSchema()` 的默认值已经是这个行为，本变更只需**不传 `publisher` 参数**即可，无需额外代码。

---

## D3：FAQPage 只输出「当前可见」的问答

**决策**

- `pages/faq.php`：只把**当前激活 tab**（`$active` 分类）下的问答送进 `faqPageSchema()`，不输出其他 tab。
- `pages/article.php`：只输出页面底部「相关常见问题」区块实际渲染的问答（`article.php:60-64` 已按 `related_article_id` 取了最多 3 条）。

**理由**：schema 描述不可见内容属误导性结构化数据，Google 明确会降权或移除富摘要资格。FAQ 答案虽因 CSS 折叠而视觉不可见，但**在 HTML 源与 DOM 中都存在**（点击即展开），与 schema 一致，安全。

**不做的**：不给首页的「大家都在问」区块单独输出 FAQPage（首页主类型是 WebSite，混用会让页面主题信号模糊）。

---

## D4：HowTo 仅对 steps 型文章输出

**决策**：仅当 `content_type === 'steps'` 且 `json_decode(content_steps)` 为非空数组时输出 `HowTo`，步骤取自同一份 `$steps`（与页面渲染同一数据源，避免两处不一致）。

**理由**：`site-geo-optimization` 任务 7.2 在静态页 `buy-blocks-guide.html` 上做过同样的事，动态页沿用同一判据。richtext 型文章没有结构化步骤，强行输出 HowTo 会与正文不符。

**同时**：`Article` 所有文章都输出（steps 与 richtext 都是 Article；不改用 `TechArticle`，除非后续确认语义更贴切）。

---

## D5：JSON-LD 输出位统一到 `help_header()`，不散落各页

**决策**：`help_header()` 的 `$opts` 增加 `jsonld`（字符串或数组）与 `og_image` / `og_type` 参数，在 `</head>` 前统一输出；各页面只负责**组装数据**，不负责 echo `<script>`。

**理由**

- 与 A 批次 canonical 的注入方式保持一致（同一个入口改一次）。
- 避免各页各自拼 `<script>` 导致重复/遗漏，也便于统一做 `json_encode` 的转义选项。
- `SeoHelper` 的方法已直接返回完整 `<script>…</script>` 字符串，页面侧只需拼接。

**顺序**（`<head>` 内）：canonical / og:* → Organization → 本页主 schema → BreadcrumbList。

---

## D6：`og:image` 取值与回退

**决策**：`og:image` 优先取文章 `cover_image`；为空时回退到统一品牌 OG 图（与主域一致）；首页/FAQ/术语表等无专属图的页面直接用品牌图。文章页 `og:type` 设为 `article`，其余保持 `website`。

**理由**：`cover_image` 字段已在表中存在但从未用于任何元信息，属现成资源。无图会让社交与 AI 摘要失去缩略图。

**注意**：`og:image` 必须是**绝对 URL**；`cover_image` 当前存的是相对路径还是绝对需在实现时确认，若是相对路径需补 `https://help.58.tl/` 或主域前缀。

---

## 各页 schema 对照表

| 页面 | 主 schema | 附加 |
|---|---|---|
| home | `WebSite`（+`SearchAction`） | `Organization`、`BreadcrumbList`（首页无面包屑，可省） |
| category/{slug} | `CollectionPage` + `ItemList` | `BreadcrumbList` |
| article/{slug} | `Article` | `HowTo`（steps 型）、`FAQPage`（有相关 FAQ）、`BreadcrumbList` |
| faq | `FAQPage`（当前 tab） | `BreadcrumbList` |
| glossary | `DefinedTermSet` + `DefinedTerm` | `BreadcrumbList` |
| search | — | `BreadcrumbList`（结果本身不做 schema） |
| ask | — | 不输出（无 SSR 内容，见 `help-content-seo`） |

---

## 风险与权衡

| 项 | 选择 | 放弃的 |
|---|---|---|
| 实体来源 | `require shared/organization.php` | 各页内联（会漂移） |
| FAQPage 范围 | 仅当前可见问答 | 全量 tab（可能被判隐藏内容） |
| 输出位置 | 统一在 `help_header()` | 各页自 echo（易重复/遗漏） |
| BlogPosting vs Article | `Article`（通用） | 更具体的子类型（收益不明确） |

**遗留**

- `search` 页与 `ask` 页的处理（noindex / 无 schema）→ 由 `help-content-seo` 处理。
- schema 上线后的校验工具复检 → 本变更 tasks 内，需部署后执行。
