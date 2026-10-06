# help 子站接入百度主动推送

## Why

前面三个批次（A 权威域与发现性、B 结构化数据、C 内容层）解决的都是「爬虫能抓到、AI 能解析」，但**百度对新链接的发现主要靠主动推送**——只挂 sitemap 等自然抓取，帮助文章这类低频低权重的新页面可能长期不收录。当前 help 子站在推送链路上是**双重空缺**：

1. **代码层：后台发布帮助内容不触发推送**
   `admin/help-articles.php` 中 `SeoHelper` / `pushContentUrl` / `push` 全部零命中。作为对照，互访圈（`hufang/circles/create.php:62`）、商城商品、NFT、模特/短剧/作者、社区帖子（`classes/Post.php`）都已在保存成功后调用 `SeoHelper::pushContentUrl()`。帮助中心是**唯一没有接入的内容类型**。

2. **配置层：即使接了代码也会静默跳过**
   `SeoHelper::pushContentUrl()`（`:528-553`）会按 URL 的 host 去 `config/seo.php` 的 `sites` 映射取 token，取不到就记一条日志后直接 return。而 `config/seo-sample.php:17-33` 的 `sites` 列了 10 个子域 + 2 个一级域名，**没有 `help.58.tl`**。

   根源是 `per-subsite-baidu-indexing` 变更（"各子站独立接入百度"，30 任务）覆盖的是建站时的 9 个子域，help 子站是后来才有的，未被纳入。而百度推送的前置条件是**站点在百度搜索资源平台完成归属验证并拿到 token**——这一步无法由代码代劳。

3. **顺带的两处旧账**
   - `site.php:27` 的默认推送入口列表仍含 `https://www.58.tl/help/help.html`（**已 301 的死链**），且列表里没有任何 help.58.tl 的 URL。
   - 文章下架或 slug 变更后，旧 URL 无任何机制告知百度，只能等自然失效。

**与批次 A 的顺序耦合**：A 确定权威域为 `help.58.tl`，`www.58.tl/help/*` 全部 301。若在 A 落地前推送 `www.58.tl/help/...`，推完立刻被 301 跳走——推的是非规范地址，白烧配额。因此本变更**必须在 A 之后实施**。

## What Changes

- **站点接入**：`config/seo-sample.php` 的 `sites` 增 `help.58.tl` 项；部署环境的 `config/seo.php` 填入百度验证后的 token 并启用（人工步骤）。
- **推送触发**：后台帮助文章「首次发布」与「从非发布转为发布」时调用推送；FAQ / 术语新增后重推对应聚合页。
- **推送节流**：新增推送记录表，同一 URL 在冷却期内不重复推送，避免烧配额。
- **失败隔离**：推送为同步 HTTP 请求（`baiduPush` 的 curl 超时 20s），必须包在异常隔离里，MUST NOT 阻塞或拖慢后台保存。
- **工具修正**：`site.php` 默认入口移除 301 死链并补 help.58.tl 首页。
- **死链处理（可选）**：为已下架 / 已改 slug 的文章生成死链清单，供百度平台提交。

## Capabilities

### New Capabilities

- `seo/help-baidu-indexing`：help 子站内容在发布时主动推送至百度，含站点凭据配置、触发时机、节流与失败隔离。

### Modified Capabilities

<!-- 无：主规格中尚无 seo/* 能力（相关变更均未归档）。 -->

## Impact

**修改文件**

- `config/seo-sample.php` — `sites` 增 `help.58.tl` 项
- `admin/help-articles.php` — 发布/转发布时触发推送
- `admin/help-faq.php`、`admin/help-glossary.php` — 新增后重推聚合页
- `site.php` — 默认推送入口移除死链、补 help.58.tl
- `help/_init.php` — 新增推送封装（节流 + 异常隔离）

**新增文件**

- `init/migration-help-baidu-push.sql` — 推送记录表
- 百度搜索资源平台 help.58.tl 验证与取 token 的操作说明（文档）

**依赖与风险**

- **强依赖批次 A**：推送目标必须是 `https://help.58.tl/…`。A 未上线前推送帮助内容属浪费配额。
- **人工步骤无法代劳**：`help.58.tl` 需在百度搜索资源平台完成归属验证（DNS / HTML 文件 / CNAME）并获取 token。未完成时代码保持静默跳过，不报错。
- **推送配额有限**：百度按站点分配每日配额，必须节流，避免每次编辑都推。
- **同步 HTTP 的阻塞风险**：`baiduPush()` 的 curl 超时为 20s，后台保存必须做异常隔离；必要时后续改为异步。
- **站群风险**：help 是第 11 个子域，`per-subsite-baidu-indexing` 已记录站群特征风险。缓解沿用其策略——分阶段启用、内容保持差异化。
- **对 GEO 无直接影响**：百度不解析 JSON-LD 也不读 llms.txt；本变更仅作用于百度收录，勿与 B/C 的 GEO 目标混淆。
