# 各子站独立接入百度收录

## Why

站点采用多子域架构（www / block / bct / mall / model / nft / v / bid / club），各子域由独立 nginx vhost 提供服务。**用户明确要求子域内容能被百度直接收录**，而非仅作为主站的一部分。

当前子域在百度侧处于完全未接入状态：

1. **仅 `www.58.tl` 完成验证**：其余 8 个子域均未在百度搜索资源平台验证归属，无法推送、无法查看收录数据。

2. **子站 sitemap 大面积缺失**：nginx 配置在 `www` / `mall` / `model` 三个 vhost 中都有 `rewrite ^/sitemap\.xml$ /sitemap.php last;`，但只有 `www`（根目录 `sitemap.php`）与 `model`（`model/sitemap.php`）存在实际文件：
   - `mall.58.tl/sitemap.xml` → 被 rewrite 到 `mall/sitemap.php` → **文件不存在，404**

3. **子站 robots.txt 几乎全缺**：仅根目录有 `robots.txt`，`block` / `bct` / `mall` / `nft` / `bid` / `club` / `model` / `task` / `v` 均无独立 `robots.txt`，爬虫在子域内得不到 sitemap 指引。

4. **推送与子域不匹配**：`fix-baidu-push-pipeline` 完成后，主站推送链路可用，但所有子域 URL 仍会推给 `www.58.tl` 的 token。百度对未验证子域的 URL 会返回 `not_same_site` 或直接拒绝。

前置变更 `fix-baidu-push-pipeline` 必须先完成——本变更假设「推送机制本身已被验证可用」。

## What Changes

- **多子域推送配置**：`config/seo.php` 结构扩展为支持「每个子域一组 token/site」，而非单一 token。
- **推送时按 URL 归属选择 token**：`pushContentUrl()` 根据传入 URL 的 host 自动匹配对应子域的 token，匹配不到则跳过（不再误推给 www）。
- **各子站独立 sitemap**：为 `mall` / `block` / `bct` / `nft` / `v` / `bid` / `club` 补齐子站 `sitemap.php`（复用 `model/sitemap.php` 的既有模式），各 sitemap 只输出本域 URL。
- **各子站 robots.txt**：为每个子域补 `robots.txt`，声明 `Sitemap:` 指向本域 sitemap，并继承主站的爬虫策略（AI 爬虫放行 + 后台路径屏蔽）。
- **子域验证与接入指引**：新增一份操作文档，说明如何在百度搜索资源平台逐个验证子域、获取 token、填入配置。
- **分阶段启用**：配置支持按子域独立开关，允许逐个验证、逐个启用，避免一次性铺开。

## Capabilities

### New Capabilities

- `seo/multi-subsite-indexing`: 多子域的百度归属验证、独立 sitemap、独立 robots 与按域推送。

### Modified Capabilities

<!-- 无：site-geo-optimization 的 GEO 需求不受影响。 -->

## Impact

**新增文件**

- `mall/sitemap.php`、`block/sitemap.php`、`bct/sitemap.php`、`nft/sitemap.php`、`v/sitemap.php`、`bid/sitemap.php`、`club/sitemap.php`
- `mall/robots.txt`、`block/robots.txt`、`bct/robots.txt`、`nft/robots.txt`、`v/robots.txt`、`bid/robots.txt`、`club/robots.txt`、`model/robots.txt`
- 子域百度接入操作指引文档

**修改文件**

- `config/seo.php` / `config/seo-sample.php` — 多子域 token 配置结构
- `classes/SeoHelper.php` — 推送按 URL host 选择 token
- `docs/nginx-rewrite.conf` — 为缺 sitemap 的子站补 rewrite（如需）

**依赖与风险**

- **前置**：`fix-baidu-push-pipeline` 已完成并验证。
- 无新增外部依赖。
- **站点群风险**：9 个同主体、同模板子域在百度侧有「站群」特征，可能被整体降权。缓解：分阶段启用（每个子域间隔数天）、确保各子域内容真正差异化、避免子域间雷同锚文本互链。
- 需用户逐个在百度平台完成子域验证（DNS / HTML 文件 / CNAME），这一步无法由代码代劳。
