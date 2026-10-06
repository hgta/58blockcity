# help 子站 SEO/GEO 地基：权威域名收口与爬虫/AI 可发现性

## Why

帮助中心已完成从「9 篇静态 HTML」到「help.58.tl 独立子站 + 动态 PHP 路由」的改造，但 SEO/GEO 地基没有跟上。全站 GEO 基建（`shared/organization.php` 全局实体、`SeoHelper` 11 个结构化数据方法、`robots.txt` AI 放行、`llms.txt`）**在 help 子站的接入率为零**，而且动态化改造还带来两个新问题：

1. **同一份内容存在两套可索引 URL，且都没有 canonical**
   - `https://help.58.tl/*`（独立 server block，`docs/nginx-rewrite.conf:508`，root 指向 `help/`）
   - `https://www.58.tl/help/*`（www server 内 rewrite，`docs/nginx-rewrite.conf:60-74`）
   - 两边都返回 200 输出相同内容；`help/` 目录下 `canonical` 关键词**零命中**。

2. **软 404 造成无限 URL 空间**
   help server 块 `location / { try_files $uri $uri/ /index.php?$query_string; }`（nginx-rewrite.conf:539-541），而 `help/index.php:19` 的 route 默认回落 `home`。结果是 `/任意路径` 都返回 **200 + 首页 HTML**——包括 `/robots.txt` 与 `/llms.txt`，AI 与搜索引擎在这两个地址上拿到的是首页 HTML 而非规则/清单文件。

3. **AI 发现性链路全线指向死链**
   根 `llms.txt:49-53` 的「帮助与知识」5 条全部指向 `www.58.tl/help/*.html`——这些静态页已被 301（`help/.htaccess:9-18`、nginx-rewrite.conf:531-535）。全仓 `llms.txt` 中**没有任何 `help.58.tl`**，而 bct/block/mall/nft/bid/club/task/model/hufang 都已各有 `llms.txt`。

4. **根 sitemap 收录 10 条 301 死链，却不含任何 help.58.tl URL**（`sitemap.php:58-67`）。

本变更只做**地基**：把「哪个 URL 是权威的」和「爬虫/AI 怎么找到它」这两件事定下来并落地。结构化数据接入（`SeoHelper` 接线）、内容层（答案前置、SEO 字段、参数页收敛）**不在本变更范围**，拆为后续变更，避免与进行中的 `help-site-layout`（同样在改 `_layout.php`）叠加大改。

## What Changes

- **权威域名收口**：确定 `https://help.58.tl/` 为唯一权威域；`www.58.tl/help/*` 全路径 301 到对应 `help.58.tl/*`；help 全部页面输出自引用 canonical（含 `og:url`）。
- **消除软 404**：`help/index.php` 对非已知路由返回真 404（复用既有 404 呈现），不再回落首页。
- **help 域可发现性三件套**：新增静态 `help/robots.txt`（AI 爬虫显式放行 + 声明本域 sitemap）与 `help/llms.txt`（站点定位 + 入口清单 + 指向主实体）。
- **sitemap 修正**：help 自身 sitemap 补齐 `lastmod` 与固定权威域；根 sitemap 移除 10 条 301 死链。
- **全站引荐改指**：根 `llms.txt`、`bct/llms.txt`、`task/llms.txt`、`hufang/llms.txt` 中指向 `help/*.html` 的条目全部改为 `https://help.58.tl/…`。

## Capabilities

### New Capabilities

- `seo/help-canonical-identity`：help 子站的权威 URL 归属与重复内容收口（两套 host 只保留一个可索引集合）。
- `seo/help-crawl-discovery`：help 子站对搜索引擎与生成式引擎的可发现性（robots.txt / sitemap.xml / llms.txt 三件套齐全且指向正确）。

### Modified Capabilities

<!-- 无：site-geo-optimization 与 geo-content-coverage 均未归档，其 seo/* 能力未进入主规格，
     故本变更以独立能力增量表达，不做 MODIFIED。 -->

## Impact

**修改文件**

- `help/index.php` — 未知路由 404 兜底
- `help/_layout.php` — `<head>` 注入 `canonical` / `og:url`（**注意：与进行中的 `help-site-layout` 同文件冲突，见依赖与风险**）
- `help/pages/sitemap.php` — 固定权威域、补齐 `lastmod`
- `sitemap.php`（根）— 移除 10 条 `help/*.html` 301 死链
- `llms.txt`（根）— 「帮助与知识」条目改指 `https://help.58.tl/`
- `bct/llms.txt`、`task/llms.txt`、`hufang/llms.txt` — 术语表链接改指 `https://help.58.tl/glossary`
- `docs/nginx-rewrite.conf` — www server 增 `/help/*` → `https://help.58.tl/*` 的 301

**新增文件**

- `help/robots.txt`
- `help/llms.txt`

**依赖与风险**

- **canonical 主域切换有收录波动风险**：`www.58.tl/help/*` 若已被收录，301 后短期排名/收录量可能波动。需上线后观测百度站长平台与 Google Search Console 的 help.58.tl 收录量，作为验收的一部分。
- **与 `help-site-layout` 的文件冲突**：该变更正在改 `help/_layout.php`（15/20，含 hero/pagehead 重构，剩余部署复验）。本变更同样要改 `_layout.php` 的 `<head>` 区。**必须先归档 `help-site-layout` 或把 head 区改动并入其中，禁止两个变更并行改同一文件。**
- **需部署权限**：`docs/nginx-rewrite.conf` 的改动要上服务器生效；`help/robots.txt` 与 `help/llms.txt` 只需放文件（nginx `try_files $uri` 会优先命中物理文件，无需改配置）。
- **无 BREAKING 变更**，纯服务端渲染与静态文件改动，不影响既有功能。
- **本变更对百度收录的影响需与 GEO 区分**：canonical 收口与 sitemap 对百度有效；`llms.txt` 仅对生成式引擎有效，百度不解析。
