## Purpose

保证 sitemap 完整覆盖站点的全部公开价值页面（含静态解释型内容），使生成式引擎与搜索引擎能够发现并持续追踪站点内容的更新。

## ADDED Requirements

### Requirement: 静态内容页纳入 sitemap
sitemap SHALL 包含站点全部公开静态内容页，至少覆盖帮助中心文章、新闻页与新闻文章、排行榜页面及主要入口页。

#### Scenario: 帮助中心文章可被发现
- **WHEN** 请求 `https://www.58.tl/sitemap.xml`
- **THEN** 结果中包含 `help/` 目录下全部公开文章的 URL

#### Scenario: 排行榜与新闻页被包含
- **WHEN** 请求 sitemap
- **THEN** 结果中包含 `rankings/` 页面、`news.php` 与新闻文章的 URL

### Requirement: lastmod 反映真实更新时间
sitemap 中静态页面的 `<lastmod>` SHALL 取自对应文件的实际修改时间，MUST NOT 使用固定值或生成时的当前时间。

#### Scenario: 静态页 lastmod 来自文件
- **WHEN** 某静态 HTML 文件内容被更新后重新请求 sitemap
- **THEN** 该 URL 的 `<lastmod>` 反映更新后的文件修改日期

### Requirement: 动态内容覆盖不回退
sitemap SHALL 持续包含既有动态内容类型（城市、商品、店铺、互访圈、NFT、模特、作者、社区帖子），MUST NOT 因本次变更减少既有覆盖。

#### Scenario: 既有动态类型仍在
- **WHEN** 请求 sitemap
- **THEN** 结果中仍包含城市、商品、店铺、互访圈、NFT、模特、作者、社区帖子等类型的 URL

### Requirement: sitemap 输出有效
sitemap SHALL 通过 `/sitemap.xml` 可访问，其内容 MUST 为符合 sitemap 协议的有效 XML。

#### Scenario: 输出合法 XML
- **WHEN** 请求 `/sitemap.xml`
- **THEN** 返回 `Content-Type: application/xml`，且内容为结构合法的 `<urlset>` XML
