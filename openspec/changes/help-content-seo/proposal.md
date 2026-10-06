# help 子站内容层 SEO：SEO 字段、答案前置、参数页收敛、跨站内链

## Why

地基（批次 A：权威域与发现性）与结构化数据（批次 B：schema 接线）解决的是「能不能被找到、能不能被解析」。但**被引用**与否最终取决于内容本身的可抽取性与页面集合的干净程度。当前 help 子站内容层有四个缺口：

1. **没有 SEO 专属字段**：`help_articles` 只有 `title` / `summary`，没有 `seo_title` / `meta_description`。页面 description 由 `summary ?: 正文截断` 生成（`pages/article.php:76`），容易过短、重复、或截断在半句。
2. **「答案前置」只在静态页做过**：`site-geo-optimization` 任务 7.1 为 9 篇静态 HTML 插入了 40–80 字直答段落，动态页没有承接。当前 `summary` 只是文章顶部一个浅色提示框（`:118-122`），**不在正文首段**，生成式引擎抓取正文时第一段仍是任意内容。
3. **参数页无限组合**：`/search?q=任意词`、`/faq?cat=`、`/category/{slug}?page=N` 都没有 canonical 也没有 noindex。搜索页尤其危险——`q` 由用户输入，`title` 直接写成「搜索：{q}」（`pages/search.php:64`），可被无限索引为薄内容。
4. **内链只出不进**：help 页面指向主站的链接只有导航条的「返回主站」和页脚，正文与推荐位**没有任何指向 block / mall / bct / nft 等功能页的深链**。权重传不出去，AI 也无法沿链路理解业务关系。

## What Changes

- **SEO 字段**：`help_articles` 新增 `seo_title`、`meta_description`；后台表单可编辑；前台优先取用，缺失时按既定优先级回退。
- **答案前置**：文章 `summary` 渲染为**正文首段的直答段落**（不再是浅色提示框），并在后台给出「40–80 字、可独立成立」的撰写提示。
- **description 生成质量**：回退逻辑改为在句末收尾，避免半句截断。
- **参数页收敛**：搜索页 `noindex,follow`；FAQ 的 `?cat=` 变体 canonical 收口到 `/faq`；分类分页保持 self-canonical。
- **跨子站内链**：文章页在「继续阅读」之外，按分类映射输出指向对应功能子站的入口。

## Capabilities

### New Capabilities

- `seo/help-content-optimization`：help 子站内容的元信息可控性、直答结构、参数页收敛与跨站内链。

### Modified Capabilities

<!-- 无：主规格中尚无 seo/* 能力（site-geo-optimization 与 geo-content-coverage 均未归档）。 -->

## Impact

**修改文件**

- `init/migration-help-content-seo.sql`（新）— `help_articles` 增列
- `admin/help-articles.php` — 表单增 SEO 字段 + 摘要撰写提示
- `help/pages/article.php` — 答案前置渲染、description 优先级、canonical（与 FAQ tab 无关）、内链入口
- `help/pages/search.php` — `noindex,follow`
- `help/pages/faq.php` — `?cat=` 变体 canonical 收口
- `help/pages/category.php` — 分页 self-canonical
- `help/_init.php` — `help_plain_summary()` 改为句末收尾

**新增文件**

- `init/migration-help-content-seo.sql`

**依赖与风险**

- **与批次 B 同改 `pages/article.php`**：B 加 schema、C 改正文渲染。建议 B 完成后再实施本变更，或两者合并，**避免并行编辑同一文件**。
- **迁移需部署执行**：新增列要上服务器跑 SQL；未执行前前台必须能正常回退（字段缺失不应报错）。
- **搜索页 noindex 有取舍**：会损失少量长尾收录，换取避免大量薄内容页被索引。这是明确权衡，不是疏漏。
- **答案前置是内容规范**：技术实现只负责渲染位置与提示，实际文案需内容侧配合（后台提示 + 既有「AI 摘要」按钮可复用）。
- **无 BREAKING 变更**。
