## Purpose

让每个实体页面输出符合 schema.org 的 JSON-LD 结构化数据，使生成式引擎能够精确理解页面中的商品、店铺、人物、地点、文章与问答等实体及其关键属性。

## ADDED Requirements

### Requirement: 实体页面结构化数据覆盖
站点 SHALL 为主要实体类型输出对应的结构化数据，至少覆盖：商品 `Product`、店铺 `Store`、人物 `Person`、城市 `Place`、文章 `Article`、问答 `FAQPage`、教程 `HowTo`、NFT `VisualArtwork`。

#### Scenario: 商品详情页输出 Product
- **WHEN** 爬虫抓取商品详情页
- **THEN** 页面包含 `Product` JSON-LD，且含名称与 `Offer`（价格、货币、可购状态）

#### Scenario: 城市页输出 Place
- **WHEN** 爬虫抓取城市详情页
- **THEN** 页面包含 `Place` 或 `AdministrativeArea` JSON-LD，且含城市名称

#### Scenario: 人物页输出 Person
- **WHEN** 爬虫抓取模特或作者详情页
- **THEN** 页面包含 `Person` JSON-LD，且含名称与头像

### Requirement: 结构化数据语法有效
所有 JSON-LD SHALL 为合法 JSON，且 `@context` MUST 指向 `https://schema.org`，MUST NOT 产生解析错误。

#### Scenario: JSON-LD 可被解析
- **WHEN** 提取页面中的全部 `application/ld+json` 块并解析
- **THEN** 每个块均为合法 JSON，且包含 `@context` 与 `@type`

### Requirement: 结构化数据与页面内容一致
结构化数据中的关键字段 SHALL 与页面可见内容一致，MUST NOT 声明页面中不存在或与页面相矛盾的信息。

#### Scenario: 价格一致
- **WHEN** 商品详情页输出 `Offer.price`
- **THEN** 该价格与页面上展示的商品价格一致

### Requirement: 帮助与资讯内容输出 Article
帮助中心文章与新闻文章 SHALL 输出 `Article`（或 `NewsArticle`）结构化数据，含标题、描述、发布时间与发布者。

#### Scenario: 帮助文章含 Article
- **WHEN** 爬虫抓取任一帮助中心文章
- **THEN** 页面包含 `Article` JSON-LD，且含 `headline`、`description` 与 `publisher`

### Requirement: 问答与教程结构化
含问答内容的帮助文章 SHALL 输出 `FAQPage`；分步操作指南 SHALL 输出 `HowTo`。

#### Scenario: 帮助文章含 FAQ
- **WHEN** 帮助文章包含问答内容
- **THEN** 页面输出 `FAQPage` JSON-LD，其 `mainEntity` 包含 `Question`/`Answer`

#### Scenario: 购买指南含 HowTo
- **WHEN** 抓取区块购买指南类文章
- **THEN** 页面输出 `HowTo` JSON-LD，其 `step` 为有序步骤
