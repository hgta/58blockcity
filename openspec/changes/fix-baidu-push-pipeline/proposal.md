# 修复百度推送链路 — 让主动推送真正生效

## Why

站点上线以来，百度仅收录主站与各子站首页，内页长期无法收录。经全代码链路排查，**根因不是内容质量问题，而是主动推送链路从未真正工作过**——所有推送调用都在同一个断点上静默失败。

排查已确认的关键事实：

1. **Token 从未配置**：`config/seo.php` 第 10 行仍是占位符 `'YOUR_BAIDU_TOKEN'`，仅 `config/seo-sample.php` 存在示例。`baiduPush()` 在 `empty($token)` 时直接 `return false`。

2. **推送入口双轨且守卫不一致**：
   - `baiduPush()` — 无 `auto_push_enabled` 守卫，被互访圈、NFT、城市页直接调用；
   - `pushContentUrl()` — 有 `auto_push_enabled` + token 占位符双重守卫，被商品、模特、短剧、作者、社区帖调用。
   - 两套入口都因 token 失效而全灭，但分裂逻辑本身即是隐患。

3. **推送结果从不落日志**：`baiduPush()` 返回 `$result` 后被调用方丢弃，百度返回的 `{"success":0,"message":"token is not valid"}` 等关键诊断信息全部丢失，导致故障长期不可见。

4. **`pingSitemap()` 实现错误**：将 sitemap 的完整 URL 传给 `site=` 参数（`site=https%3A%2F%2Fwww.58.tl%2Fsitemap.xml&type=sitemap`），且**未带 token**。百度主动推送接口的 `site=` 要求站点域名，且没有 `type=sitemap` 用法。`sitemap.php` 每次被访问都在发送无效请求。

5. **sitemap 归属违规**：`www.58.tl/sitemap.xml` 中混入了 `block/bct/mall/model/nft/v/bid/club` 共 8 个子域的 URL。按 sitemap 协议，`<loc>` 必须与 sitemap 所在站点同域，跨域节点可能导致整份 sitemap 被判无效——这与「只收录首页」的现象高度吻合。

## What Changes

本次变更**只修复主站（www.58.tl）推送链路**，目标是让「推送 → 百度返回 success」这条链路第一次真正跑通并可见。子域独立收录由后续变更 `per-subsite-baidu-indexing` 承担。

- **写入真实 token**：`config/seo.php` 的 `baidu_token` 填入百度搜索资源平台获取的真实值（该文件已被 `.gitignore` 忽略，不进入版本库）。
- **统一推送入口**：废弃 `baiduPush()` / `pushContentUrl()` 双轨逻辑，收敛为单一入口（保留 `baiduPush()` 作为底层，`pushContentUrl()` 作为带守卫的业务入口，但统一守卫语义）。
- **补推送结果日志**：推送成功/失败均记录百度返回体，含 `success` 计数与 `message`，写入 `error_log`，便于排查。
- **修复 `pingSitemap()`**：移除无效的百度 sitemap ping（百度无此 API），保留 Google ping 并修正为可选开关。
- **sitemap 合规化**：`www.58.tl/sitemap.xml` 只保留 `www.58.tl` 域下的 URL，剔除跨域节点；被剔除的子域 URL 交由后续 `per-subsite-baidu-indexing` 以独立 sitemap 承载。
- **提供一次性验证手段**：`site.php` 支持命令行推送并打印百度原始返回，用于确认链路是否真正打通。

## Capabilities

### New Capabilities

- `seo/baidu-push`: 百度主动推送的配置、触发、结果可见性与 sitemap 归属合规。

### Modified Capabilities

<!-- 无：本次不改变 site-geo-optimization 的 GEO 需求，仅修复百度收录链路。 -->

## Impact

**修改文件**

- `config/seo.php` — 写入真实 token（本地文件，不提交）
- `classes/SeoHelper.php` — 统一推送入口、补日志、修复 `pingSitemap()`
- `sitemap.php` — 剔除跨域节点，仅保留 www 域 URL
- `site.php` — 命令行推送增强，输出百度返回
- `config/seo-sample.php` — 同步说明新配置项

**依赖与风险**

- 无新增外部依赖；依赖 PHP `curl` 扩展（现有 `baiduPush()` 已使用）。
- 无 **BREAKING** 变更，不改动 URL 结构与业务逻辑。
- **前置条件**：百度搜索资源平台已验证 `www.58.tl`，token 已获取。
- **范围约束**：本变更**不新增子域 token**，子域推送明确不在范围内，避免链路未验证时放大故障面。
