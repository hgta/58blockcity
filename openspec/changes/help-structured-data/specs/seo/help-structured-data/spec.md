## Purpose

让 help 子站的每类页面都输出与其可见内容一致的结构化数据，并复用全站统一的品牌实体 `@id`，使生成式引擎能够正确抽取事实并把内容归因到「58区块城市」这一实体。

## ADDED Requirements

### Requirement: 全局品牌实体锚点

help 子站的全部页面 MUST 输出 `Organization` JSON-LD，其 `@id` MUST 与其余子域一致（取自 `shared/organization.php` 这一单一来源），MUST NOT 在 help 子站内另行拼装实体描述。

#### Scenario: 实体 @id 一致
- **WHEN** 请求 help 子站任一页面
- **THEN** 页面含 `Organization` JSON-LD，`@id` 与主域页面输出的 `@id` 相同

### Requirement: 文章页输出 Article 结构化数据

已发布文章页 MUST 输出 `Article` JSON-LD，至少含 `headline`、`description`、`url`、`datePublished`、`dateModified`，且 `publisher` MUST 为组织 `@id` 引用。

#### Scenario: 普通文章
- **WHEN** 请求一篇 `content_type='richtext'` 的已发布文章
- **THEN** 页面含 `Article` JSON-LD，`headline` 为文章标题，`dateModified` 反映文章 `updated_at`

#### Scenario: 日期字段取自真实数据
- **WHEN** 文章的 `updated_at` 发生变化
- **THEN** 该页 `Article` 的 `dateModified` 同步变化

### Requirement: 分步教程输出 HowTo

`content_type='steps'` 且步骤非空的文章页 MUST 额外输出 `HowTo` JSON-LD，其步骤 MUST 与页面渲染的步骤一致；非 steps 型文章 MUST NOT 输出 `HowTo`。

#### Scenario: steps 型文章
- **WHEN** 请求一篇含 3 个步骤的 steps 型文章
- **THEN** 页面含 `HowTo` JSON-LD，且 `step` 数量为 3，步骤文本与页面可见文本一致

#### Scenario: richtext 型文章
- **WHEN** 请求一篇 richtext 型文章
- **THEN** 页面不含 `HowTo` JSON-LD

### Requirement: FAQ 结构化数据与可见问答一致

FAQ 页 MUST 输出 `FAQPage` JSON-LD，其内容 MUST 仅为当前展示分类下实际渲染的问答；文章页在存在「相关常见问题」区块时 MAY 额外输出 `FAQPage`，其内容 MUST 与该区块一致。

#### Scenario: FAQ 页按分类输出
- **WHEN** 请求 `https://help.58.tl/faq?cat={某分类}`
- **THEN** `FAQPage` 中的问答条目与页面该分类下渲染的问答一一对应，且不含其他分类的问答

#### Scenario: 文章页相关 FAQ
- **WHEN** 请求一篇存在关联 FAQ 的文章
- **THEN** 页面 `FAQPage` 的问答与页面「相关常见问题」区块一致

### Requirement: 术语表输出 DefinedTermSet

术语表页 MUST 输出 `DefinedTermSet` JSON-LD，其中 MUST 包含页面可见术语及其定义。

#### Scenario: 术语条目被结构化
- **WHEN** 请求 `https://help.58.tl/glossary`
- **THEN** 页面含 `DefinedTermSet` JSON-LD，条目数与页面可见术语数一致

### Requirement: 面包屑结构化

具备面包屑导航的页面 MUST 同时输出 `BreadcrumbList` JSON-LD，层级与可见面包屑一致。

#### Scenario: 文章页面包屑
- **WHEN** 请求一篇文章
- **THEN** 页面含 `BreadcrumbList`，层级为 帮助中心 → 分类 → 本文

### Requirement: 首页提供站点与搜索能力声明

首页 MUST 输出 `WebSite` JSON-LD，且 MUST 含 `SearchAction` 指向 help 子站的搜索地址。

#### Scenario: 搜索富摘要可用
- **WHEN** 请求 `https://help.58.tl/`
- **THEN** 页面含 `WebSite` JSON-LD，其 `potentialAction` 为 `SearchAction`，target 指向 help 子站搜索地址

### Requirement: Open Graph 元信息完整

页面 MUST 输出 `og:url` 与 `og:image`；文章页的 `og:type` MUST 为 `article`。

#### Scenario: 文章页 OG
- **WHEN** 请求一篇文章
- **THEN** 页面含 `og:type=article`、`og:url`、`og:image`，且 `og:image` 为绝对 URL

### Requirement: 结构化数据可解析

全部输出的 JSON-LD MUST 为可被 JSON 解析的合法结构，MUST NOT 出现未转义字符或截断。

#### Scenario: JSON 合法
- **WHEN** 抓取任一 help 页面并提取全部 `application/ld+json` 块
- **THEN** 每个块均可被 JSON 解析且含 `@context` 与 `@type`
