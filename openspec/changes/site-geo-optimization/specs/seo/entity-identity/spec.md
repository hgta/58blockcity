## Purpose

为站点建立单一、稳定、可跨子域聚合的品牌实体身份，使生成式引擎将多个子域识别为同一个组织，并正确理解其与第三方独立平台之间的关系。

## ADDED Requirements

### Requirement: 全局品牌实体声明
站点 SHALL 输出带稳定 `@id` 的 `Organization` 结构化数据，包含名称、别名、官网、Logo 与描述，且所有子域 MUST 引用同一 `@id`。

#### Scenario: 主域输出品牌实体
- **WHEN** 抓取 `https://www.58.tl/` 首页
- **THEN** 页面包含 `Organization` JSON-LD，其 `@id` 为站点级稳定标识

#### Scenario: 子域引用同一实体
- **WHEN** 抓取任一子域页面
- **THEN** 页面中的组织引用使用与主域相同的 `@id`，而非仅以名称字符串表示

### Requirement: 跨页实体引用使用 @id
各页面中作为发布者或提供者的组织 SHALL 通过 `@id` 引用全局实体，MUST NOT 仅以名称字符串重复声明。

#### Scenario: 文章发布者以 @id 引用
- **WHEN** 抓取社区文章或帮助文章
- **THEN** 其 `publisher` 使用全局实体的 `@id` 引用

### Requirement: sameAs 仅限自有平台主页
`Organization.sameAs` SHALL 仅包含本站自有的跨平台主页 URL，MUST NOT 包含第三方独立站点。

#### Scenario: 自有账号写入 sameAs
- **WHEN** 抓取首页 `Organization` 结构化数据
- **THEN** `sameAs` 中包含自有平台主页（如 GitHub 仓库主页）的 URL

#### Scenario: 第三方站不进入 sameAs
- **WHEN** 检查 `Organization.sameAs`
- **THEN** 其中不包含 `blockcity.vip` 等第三方独立站点

### Requirement: 第三方平台关系弱声明
站点 SHALL 以 `isRelatedTo` 与可见正文说明其与第三方独立平台（BlockCity.vip）的生态关系，MUST NOT 使用 `sameAs` 表达该关系。

#### Scenario: 关系通过 isRelatedTo 声明
- **WHEN** 抓取首页或关于类页面
- **THEN** 存在 `isRelatedTo` 指向 BlockCity.vip，且页面正文对二者关系有文字说明

### Requirement: 首页结构性实体完整
首页 SHALL 同时输出 `WebSite` 与 `Organization` 结构化数据。

#### Scenario: 首页含两类实体
- **WHEN** 抓取首页
- **THEN** 页面同时包含 `WebSite` 与 `Organization` 两类 JSON-LD 实体
