## Purpose

让搜索引擎与生成式引擎能够在 help 子域上找到抓取规则、完整 URL 清单与站点说明，且这些引荐文件不指向已失效的旧静态页地址。

## ADDED Requirements

### Requirement: help 域提供 robots.txt

`https://help.58.tl/robots.txt` MUST 返回纯文本抓取规则，MUST NOT 返回 HTML 页面。内容 MUST 包含主流生成式引擎爬虫（至少 `GPTBot`、`OAI-SearchBot`、`PerplexityBot`、`ClaudeBot`、`Claude-SearchBot`、`Google-Extended`、`Bytespider`、`CCBot`）的显式 `Allow: /`，并 MUST 声明 `Sitemap: https://help.58.tl/sitemap.xml`。

#### Scenario: 机器人规则可获取
- **WHEN** 请求 `https://help.58.tl/robots.txt`
- **THEN** 返回 200 且内容为文本规则（非 HTML），含上述 AI 爬虫放行段与 Sitemap 声明

### Requirement: help 域提供 llms.txt

`https://help.58.tl/llms.txt` MUST 返回站点说明，内容 MUST 显式声明主实体 `https://www.58.tl/`，并 MUST 列出帮助中心的入口（首页、常见问题、术语表）与主要分类链接。

#### Scenario: AI 引擎读取站点说明
- **WHEN** 请求 `https://help.58.tl/llms.txt`
- **THEN** 返回 200 且内容为文本，含 `https://www.58.tl/` 主实体声明与 help 入口链接

### Requirement: help 站点地图覆盖公开内容且标注更新时间

`https://help.58.tl/sitemap.xml` MUST 包含首页、常见问题、术语表、全部可见分类与全部 published 文章；全部 URL MUST 以 `https://help.58.tl/` 为前缀，MUST NOT 随请求的 host 变化。每个 URL SHOULD 携带反映内容真实更新时间的 `<lastmod>`。

#### Scenario: 权威域固定
- **WHEN** 以任意 host 请求 help 的 sitemap
- **THEN** 输出的 `<loc>` 全部为 `https://help.58.tl/…` 形式

#### Scenario: lastmod 反映内容更新
- **WHEN** 某篇文章被更新后重新请求 sitemap
- **THEN** 该文章 URL 的 `<lastmod>` 反映其 `updated_at`

#### Scenario: 内容被下架
- **WHEN** 某文章状态从 published 变更为非 published
- **THEN** 该文章 URL 从 sitemap 中移除

### Requirement: 全站引荐不指向失效地址

站点内的 llms.txt 与 sitemap MUST NOT 包含已 301 的 `help/*.html` 静态页地址；指向帮助中心的条目 MUST 使用 `https://help.58.tl/` 下的地址。

#### Scenario: 根 llms.txt 帮助条目
- **WHEN** 请求 `https://www.58.tl/llms.txt`
- **THEN** 「帮助与知识」条目全部为 `https://help.58.tl/…`，无 `help/*.html` 链接

#### Scenario: 根 sitemap 不含死链
- **WHEN** 请求 `https://www.58.tl/sitemap.xml`
- **THEN** 结果中不含 `help/help.html`、`help/glossary.html` 等已 301 的静态页 URL

#### Scenario: 子域 llms.txt 术语表链接
- **WHEN** 请求 `bct` / `task` / `v`(hufang) 子域的 `llms.txt`
- **THEN** 其中术语表链接为 `https://help.58.tl/glossary`
