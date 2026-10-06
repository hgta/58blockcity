# 实施任务：help 子站结构化数据接入

## 0. 前置（硬约束）

- [x] 0.1 确认 `help-seo-foundation`（批次 A）已落地或至少 `help/_layout.php` 的 `<head>` 改动已合并 —— **本变更与 A、与 `help-site-layout` 均不得并行编辑 `_layout.php`**
      （验证：A 的 canonical 已上线，`git status` 中 `_layout.php` 无并发改动 —— A 的代码任务已完成并提交，本变更在同一工作区继续编辑同一文件）

## 1. 全局实体锚点

- [x] 1.1 `help/_layout.php` `require_once __DIR__ . '/../shared/organization.php'`，并确认相对路径正确（help → 上一级 → shared）
      （验证：`php -l` 通过，页面能输出实体块，无 require 警告 —— 同时引入 `classes/SeoHelper.php`）
- [x] 1.2 在 `help_header()` 的 `<head>` 内输出实体 JSON-LD，抽查首页/文章页/FAQ 页三处 `@id` 与主域一致
      （验证：`@id` 为 `https://www.58.tl/#organization` —— 本地脚本校验一致）

## 2. head 统一输出位

- [x] 2.1 `help_header()` 的 `$opts` 增加 `jsonld`（字符串或数组）与 `og_image` / `og_type` 参数，在 `</head>` 前统一输出
      （验证：未传参时无副作用，页面不报错 —— 另新增 `breadcrumb` 参数复用 `SeoHelper::breadcrumbList()`）
- [x] 2.2 输出顺序定为 canonical/og → Organization → 本页主 schema → BreadcrumbList
      （验证：抓取页面源码，顺序符合预期）

## 3. 文章页

- [x] 3.1 `pages/article.php` 输出 `Article`：headline=title、description=summary（或正文摘要）、url=权威 URL、image=cover_image、datePublished=created_at、dateModified=updated_at、articleSection=分类名、publisher 用默认 `@id`
      （验证：页面含 `Article` 且各字段值正确；`dateModified` 随 updated_at 变化）
- [x] 3.2 `content_type='steps'` 且 `$steps` 非空时附加 `HowTo`，步骤取自与渲染同一份 `$steps`
      （验证：steps 型文章含 `HowTo` 且步骤数与页面一致；richtext 型文章不含）
- [x] 3.3 存在关联 FAQ 时附加 `FAQPage`，数据与「相关常见问题」区块一致
      （验证：问答条目与页面区块逐条一致 —— 同源于 `$faqs`）
- [x] 3.4 `og:type` 设为 `article`，`og:image` 用 `cover_image`（相对路径需补绝对前缀），为空回退品牌图
      （验证：`og:image` 为绝对 URL —— 新增 `help_abs_image()`，回退 `HELP_OG_IMAGE = https://www.58.tl/assets/images/og-main.jpg`）

## 4. FAQ 页

- [x] 4.1 `pages/faq.php` 仅把当前激活 tab 的问答送进 `faqPageSchema()`
      （验证：切换 `?cat=` 后，schema 条目随之变化，且不含其他分类问答 —— 传 `$items`）
- [x] 4.2 无问答时（分类为空）不输出空 `FAQPage`
      （验证：空分类下页面无 `FAQPage` 块 —— `$items` 为空时不调用，`faqPageSchema([])` 本身也返回空串）

## 5. 术语表页

- [x] 5.1 `pages/glossary.php` 输出 `DefinedTermSet` + `DefinedTerm`，条目取自与渲染同一份 `$terms`
      （验证：条目数与页面可见术语数一致）

## 6. 分类页与首页

- [x] 6.1 `pages/category.php` 输出 `CollectionPage` + `ItemList`（当前页文章）+ `BreadcrumbList`
      （验证：`ItemList` 条目与页面列表一致 —— `SeoHelper` 原无 CollectionPage，新增 `collectionPageSchema()`）
- [x] 6.2 `pages/home.php` 输出 `WebSite` + `SearchAction`（target 指向 `https://help.58.tl/search?q={search_term_string}`）
      （验证：`potentialAction.@type` 为 `SearchAction` —— 本地校验通过；顺带修了 `webSiteSchema()` 未传 `publisher_id` 时的 undefined 警告）

## 7. 全站面包屑与 OG

- [x] 7.1 各页补 `BreadcrumbList`，层级与可见面包屑一致（首页除外）
      （验证：文章页层级为 帮助中心 → 分类 → 本文 —— article / category / faq / glossary / search 均已补，ask 页无 SSR 内容不输出）
- [x] 7.2 非文章页 `og:image` 使用统一品牌 OG 图
      （验证：首页/FAQ/术语表含 `og:image` 且为绝对 URL —— 由 `help_header()` 统一回退）

## 8. 校验与收尾

- [x] 8.1 本地抽取各页 JSON-LD 做 `json_decode` 合法性检查（可用 `php -r` 或临时脚本，勿入库）
      （验证：全部块可解析，含 `@context` 与 `@type` —— 9 类 schema + 组织锚点全部通过，临时脚本已删除）
- [ ] 8.2 部署后用结构化数据校验工具抽查 首页 / 文章 / FAQ / 术语表 / 分类 五类页面，无 error 级问题 — 需部署服务器
      （验证：校验工具输出无 error）
- [ ] 8.3 用 `curl -s -A "GPTBot"` 抽查文章页，确认响应体含新增 `application/ld+json` — 需部署服务器
      （验证：响应体可检索到 `Article`）
