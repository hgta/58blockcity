# 设计：修复百度推送链路

## Context

站点为多子域架构（www / block / bct / mall / model / nft / v / bid / club / task），各子域由独立 nginx vhost 提供服务，对百度而言是**互相独立的站点**。当前百度搜索资源平台仅验证了 `www.58.tl`。

推送链路的排查结论：代码层面的调用点已存在（覆盖商品、店铺、互访圈、NFT、模特、短剧、作者、社区帖、城市页），但**统一断点在 token 未配置**，且失败完全不可见。

```
  内容发布 ──▶ baiduPush() ──▶ 读 config/seo.php
                                     │
                                     ▼
                        baidu_token = 'YOUR_BAIDU_TOKEN'  ⚠️
                                     │
                                     ▼
                            empty($token) → return false   ❌ 静默失败
```

本设计只解决「让主站推送真正跑通且可观测」，子域策略不在本次范围。

## Goals / Non-Goals

**Goals**

- 让 `www.58.tl` 的内容推送能真正到达百度并获得 `success` 计数。
- 推送结果可观测：成功/失败均有日志，含百度原始返回。
- 消除 `pingSitemap()` 的无效请求。
- `www.58.tl/sitemap.xml` 归属合规。

**Non-Goals**

- 不新增子域验证与子域 token（由 `per-subsite-baidu-indexing` 承接）。
- 不改变 URL 结构与页面渲染逻辑。

## Decisions

### D1：统一推送入口，保留两层结构

**决策**：`baiduPush()` 作为底层执行方法（做 HTTP 调用 + 日志），`pushContentUrl()` 作为业务侧唯一入口（带守卫）。把原先直接调用 `baiduPush()` 的 8 个业务点全部改为 `pushContentUrl()`。

**理由**：两层职责清晰——底层负责「发送 + 记账」，业务层负责「是否发送」。原先业务点混用两层，导致守卫语义不一致（部分走 `auto_push_enabled`，部分不走）。

**替代方案**：只保留一个方法 — 被否决，无法区分「业务自动推送」（应受 `auto_push_enabled` 控制）与「命令行手动批量推送」（应无视该开关）。

### D2：推送结果落 `error_log`，不引入日志框架

**决策**：`baiduPush()` 分三层记录——网络层（`curl_errno`/`curl_error`）、HTTP 层（非 200）、业务层（解析百度返回 JSON，提取 `success` / `remain` / `not_same_site` / `not_valid` / `over_quota` / `message`）。

**理由**：项目无日志框架（`log()` 仅是对 `error_log` 的封装），引入框架超出范围。

**关键**：必须区分百度返回的失败类型，尤其 `site is not valid`（配置错误）与 `not_same_site`（子域误推信号）。

### D3：`pingSitemap()` 移除百度部分

**决策**：删除百度 ping，仅保留 Google ping，并新增 `sitemap_ping_enabled` 开关控制（默认关闭）。

**理由**：百度**没有** sitemap ping 接口。原实现构造 `site=<sitemap完整URL>&type=sitemap`（且无 token），对百度完全无效，属噪声请求。百度发现 sitemap 依靠 `robots.txt` 的 `Sitemap:` 声明与平台手动提交（本项目均有）。

### D4：`www.58.tl/sitemap.xml` 剔除跨域节点

**决策**：移除 `sitemap.php` 中所有非 www 域节点（原先混入了 8 个子域共 19 条），只保留 www 的城市页与静态内容页。

**理由**：sitemap 协议要求 `<loc>` 与 sitemap 同域。跨域节点可能导致整份 sitemap 被判无效——这与「只收录首页、内页零收录」的现象吻合。

**代价与缓解**：子域 URL 暂时从任何 sitemap 消失，但子域本就未被百度验证，移除的净效果是**提升主站 sitemap 有效性**。子域覆盖由后续变更补回。

### D5：Token 直接写入 `config/seo.php`

**决策**：真实 token 直接写入 `config/seo.php`（该文件已被 `.gitignore` 忽略）。

**理由**：与项目现有配置方式（`database.php` 同模式）一致，且不进入版本库。

**约束**：`config/seo-sample.php` 必须同步更新字段说明。

## Risks / Trade-offs

| 风险 | 影响 | 缓解 |
|------|------|------|
| 百度后台绑定的 `site` 与代码拼出的不一致 | 返回 `site is not valid` | D2 日志立即暴露；`baidu_site` 可调 |
| 每日推送配额有限 | 大批量推送被截断 | 不做全量推送，只推新内容；历史内容靠 sitemap |
| 移除子域 URL 后子域收录更慢 | 子域内页短期无收录 | 已在 Non-Goals 声明，由后续变更承接 |
| 本地无 PHP 运行时 | 无法本地验证 | 验证项已标注需在服务器执行 |

## Migration Plan

1. 写入 token 与统一入口改动 → 用 `site.php` 命令行推送验证返回 `success`。
2. 确认链路通后，sitemap 合规化并提交。
3. 观察 3~7 天百度收录变化，作为子域变更的基线。

**回滚**：改动为配置与日志增强，还原 `classes/SeoHelper.php`、`sitemap.php`、`site.php` 三文件即可。

## Open Questions

- 百度后台 `www.58.tl` 的每日推送配额具体多少？（影响 `site.php` 批量策略）
- 历史内容是否需要触发一次全量推送？（倾向不需要，靠 sitemap 自然发现）
