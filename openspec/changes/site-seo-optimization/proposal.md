# 全站 SEO 优化：适配国内搜索引擎、提升收录概率与访问

## 问题

当前站点面向百度、搜狗、360、必应中国等国内搜索引擎时，存在多个阻碍收录和排名的结构性问题：

1. **动态页面 TDK 重复/缺失**：商品详情、店铺详情、NFT 详情、圈子详情、城市页等核心页面大多使用固定 title/description，或 canonical 指向子站首页，导致搜索引擎判断为大量重复内容。
2. **Sitemap 静态且残缺**：`sitemap.xml` 只包含 11 个首页/列表页，缺少商品、店铺、城市、圈子、NFT 等动态页面，搜索引擎难以发现全量内容。
3. **百度主动推送未配置**：`site.php` 中百度推送 token 为 `YOUR_BAIDU_TOKEN`，且 URL 列表手写，无法自动将新发布内容提交给百度。
4. **URL 不友好**：所有动态页面使用 `xxx.php?id=123` 形式，不利于排名权重集中和点击率。
5. **重定向状态码不正确**：大量关键跳转使用 PHP 默认 `Location`（302 临时重定向），没有返回 301 永久重定向或正确的 404 状态码。
6. **缺少结构化数据**：只有首页的 `WebSite` schema，缺少商品（Product）、店铺（LocalBusiness/Store）、组织（Organization）等富媒体摘要。

## 目标

- 让全站每个页面拥有独特、准确、符合国内搜索引擎习惯的标题与描述。
- 让搜索引擎自动发现所有有价值的公开页面（商品、店铺、城市、圈子、NFT、列表页）。
- 在内容发布/更新时自动向百度等搜索引擎提交，加速收录。
- 统一 URL 规范与重定向状态码，集中权重，避免重复收录。
- 提升搜索结果中的展现样式（富媒体摘要）和点击率。

## 范围

本次变更覆盖全站公开页面，聚焦**收录**与**排名**两个核心指标，分 P0/P1 实施：

### P0（立即提升收录概率）

| # | 目标 | 涉及文件 |
|---|------|----------|
| 1 | 动态页面 TDK 个性化（title/description/keywords/canonical/og） | `mall/product/detail.php`, `mall/shop/view.php`, `mall/product/list.php`, `block/city.php`, `hufang/circles/view.php`, `nft/nft/view.php` 等 |
| 2 | 自动生成全站 `sitemap.xml`（商品/店铺/城市/圈子/NFT/列表页） | `sitemap.xml` 改为 `sitemap.php` 或新增生成脚本 |
| 3 | 百度主动推送配置 + 发布/更新时自动推送 | `config/seo.php`, `classes/SeoHelper.php`, `site.php` 重构 |
| 4 | 修复关键 302 为 301，规范 404 状态码 | 各子站入口/详情页的跳转逻辑 |

### P1（提升排名与点击）

| # | 目标 | 涉及文件 |
|---|------|----------|
| 5 | URL 伪静态（商品/店铺/城市/圈子） | 各子站 `.htaccess` + 页面链接生成函数 |
| 6 | 结构化数据（Product/Shop/Organization） | `shared/header.php`, `mall/product/detail.php`, `mall/shop/view.php` |
| 7 | 内链与面包屑补全 | `mall/shop/view.php`, `nft/nft/view.php` 等 |

## 验收标准

- 每个商品/店铺/城市/圈子/NFT 详情页的 `<title>` 都包含该对象名称，且 `<meta name="description">` 不为空。
- `sitemap.xml` 包含至少商品、店铺、城市、圈子、NFT 的 URL，且可通过 Web 访问/自动更新。
- 百度主动推送 token 可配置，新增商品/店铺后自动调用百度接口提交 URL。
- 关键旧 URL 返回 301 到规范 URL，不存在页面返回 404 状态码。
- `.htaccess` 启用伪静态后，旧 `?id=123` 链接仍可用且 301 到新地址。
- 商品/店铺详情页出现 `Product` / `Store` 结构化数据。

## 非目标

- 不做站外外链建设。
- 不做关键词竞价/广告投放。
- 不修改现有业务逻辑，仅在页面渲染和 URL 层优化。
