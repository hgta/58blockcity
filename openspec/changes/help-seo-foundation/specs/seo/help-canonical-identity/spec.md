## Purpose

确立 help 子站的唯一权威 URL 集合，使搜索引擎与生成式引擎在 `help.58.tl` 与 `www.58.tl/help/` 两套地址中只认一个，并把不存在的路径明确判为 404 而非首页。

## ADDED Requirements

### Requirement: 权威域唯一

help 子站的内容 MUST 只在 `https://help.58.tl/` 下可索引；`https://www.58.tl/help/` 下的等价路径 MUST 以 301 跳转到对应的 `https://help.58.tl/` 地址。

#### Scenario: www 子路径跳转
- **WHEN** 请求 `https://www.58.tl/help/faq`
- **THEN** 返回 301，Location 为 `https://help.58.tl/faq`

#### Scenario: 首页路径跳转
- **WHEN** 请求 `https://www.58.tl/help/`
- **THEN** 返回 301，Location 为 `https://help.58.tl/`

### Requirement: 页面自引用 canonical

help 子站全部可索引页面 MUST 在 `<head>` 输出指向自身权威 URL 的 `rel="canonical"`，并同步输出 `og:url`。

#### Scenario: 文章页 canonical
- **WHEN** 请求 `https://help.58.tl/article/{slug}`
- **THEN** 页面 `<head>` 含 `<link rel="canonical" href="https://help.58.tl/article/{slug}">`

#### Scenario: 首页与频道页 canonical
- **WHEN** 请求 `https://help.58.tl/`、`/faq`、`/glossary`、`/category/{slug}`
- **THEN** 各页 `<head>` 含指向自身 `https://help.58.tl/…` 地址的 canonical

### Requirement: 未知路径返回 404

help 子站 MUST NOT 把未匹配任何已知路由的请求渲染为首页；此类请求 MUST 返回 HTTP 404。

#### Scenario: 任意不存在的路径
- **WHEN** 请求 `https://help.58.tl/this-does-not-exist`
- **THEN** 返回 HTTP 404，且响应体不是 help 首页内容

#### Scenario: 已知路由不受影响
- **WHEN** 请求 `/`、`/faq`、`/glossary`、`/ask`、`/search`、`/sitemap.xml`、`/article/{slug}`、`/category/{slug}`
- **THEN** 均按原有行为正常返回（非 404）
