# 主域路由：一级域名作为主收录目标

## Why

站点存在两套并行的域名体系，且**代码行为与用户的收录目标相反**，导致内页长期无法被百度收录。

### 现状事实

**域名拓扑**（同代码同内容，多域名可访问）：

| 业务 | 子域 | 一级域名 |
|------|------|---------|
| 人气值 BCT | `bct.58.tl` | `renqizhi.com` / `renqizhi.cn` |
| 互访圈 | `v.58.tl` | `hufangquan.com` / `hufangquan.cn` |

**关键问题（已实测）**：

1. **canonical 写死子域**，与目标冲突：
   ```php
   bct/includes/header.php:5
   $site_config['canonical_url'] = $site_config['canonical_url'] ?? 'https://bct.58.tl/';
   ```
   访问 `renqizhi.com` 时，页面主动声明「正版在 `bct.58.tl`」——等于**放弃一级域名的收录**。

2. **三个域名同内容互斗**：`renqizhi.com` / `renqizhi.cn` / `bct.58.tl` 输出完全相同的页面，权重被切成三份，且存在重复内容判定风险。

3. **URL 生成器硬编码子域**：`SeoHelper::circleUrl()` 返回 `v.58.tl`，子站 sitemap / robots / llms.txt 全部指向 `58.tl` 体系。

4. **收录现状**：各域名**仅收录首页**，内页零收录。这与 canonical 混乱、推送从未生效、内链信号不足共同相关。

### 用户决策

- **主收录目标**：一级域名（`renqizhi.com` / `hufangquan.com`）
- **品牌定位**：58 生态的**子品牌**（保留生态关联，不做完全独立）
- **认证域名**：`renqizhi.com` 与 `hufangquan.com` 均需在百度平台验证
- **备案**：不备案（接受收录周期变长）

因此需要先把代码从「子域为主」切换为「一级域名为主」，否则百度认证 `renqizhi.com` 后，站内信号仍指向 `bct.58.tl`，认证收益被抵消。

## What Changes

- **canonical 改为按请求 host 决定**：一级域名访问时 canonical 指向自身；子域访问时 canonical 指向对应一级域名（收口）。
- **`.cn` 域名 301 到 `.com`**：消除重复副本，权重集中。
- **URL 生成器支持主域名**：`SeoHelper` 增加按业务返回「主域」的能力，`circleUrl()` 等改为返回一级域名。
- **子站 sitemap / robots / llms.txt 切换到一级域名**：`bct` → `renqizhi.com`，`hufang(v)` → `hufangquan.com`。
- **推送配置支持一级域名**：`config/seo.php` 的 `sites` 增加一级域名条目，`enabled` 按验证进度开启。
- **生态关联保留**：导航互链、`isRelatedTo` 维持与 `www.58.tl` 的关联声明（符合「子品牌」定位）。
- **nginx 配置**：新增 `renqizhi.com` / `hufangquan.com` vhost，`.cn` 301 规则。

**范围限定**：本变更**只处理 bct 与 v 两组域名**。其余 7 个子域（mall / block / model / nft / bid / club / task）不涉及，继续走 `per-subsite-baidu-indexing` 的子域策略。

## Capabilities

### New Capabilities

- `seo/primary-domain-routing`: 多域名同内容场景下的 canonical 决策、备用域名收敛与主域 URL 生成。

### Modified Capabilities

<!-- 无：site-geo-optimization 尚未归档，其 seo/* 能力未进入主规格。
     本变更新增独立能力，不修改既有主规格。 -->

## Impact

**修改文件**

- `bct/includes/header.php` — canonical 改为动态决策
- `hufang/includes/header.php`、`hufang/index.php`、`hufang/circles/all.php`、`hufang/circles/view.php` — canonical 与面包屑
- `bct/sitemap.php`、`bct/robots.txt`、`bct/llms.txt` — 域名切换
- `hufang/sitemap.php`、`hufang/robots.txt`、`hufang/llms.txt` — 域名切换
- `classes/SeoHelper.php` — 新增主域解析能力，`circleUrl()` 改用主域
- `config/seo.php` / `config/seo-sample.php` — 增加一级域名配置项
- `docs/nginx-rewrite.conf` — 新增 2 个一级域名 vhost + `.cn` 301
- `index.php`、`includes/city-portal-render.php` — 生态导航链接（按策略决定指向子域还是一级域名）

**依赖与风险**

- 无新增外部依赖。
- **BREAKING（SEO 层面）**：canonical 变更会改变百度已建立的收录信号，短期内可能出现排名波动。
- **需人工操作**：百度平台验证 `renqizhi.com`、获取 token、服务器 nginx 同步。
- **未备案 + 境外服务器**：用户明确不备案，收录周期预期较长（2~6 个月），本变更不解决该结构性因素。
- **不承诺短期效果**：canonical 变更后的收录改善需 2~4 周观察期。
