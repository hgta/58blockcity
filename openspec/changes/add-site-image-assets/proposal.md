# 提案：站点图片资产与搜索缩略图 — 修复 og:image 死链并让子站具备可索引图片

## Why

**起因**：百度 `site:58.tl` 结果中，只有 `mall.58.tl` 带缩略图，其余子站全部无图。

**根因（2026-09-30 线上实测）**：

1. **全站 og:image 均为死链**。十个子域头部声明的 `https://www.58.tl/assets/images/og-*.jpg` 全部返回 404（`assets/images/` 目录实际只有 `default-block.png` / `default.jpg` / `favicon.ico` 三个文件）。后果：微信/QQ/X 分享卡片无图；向搜索引擎声明的站点代表图全部无效。

2. **百度缩略图来自页面内真实图片，而非 og:image**。各子站首页图片构成实测：mall 有 27 张商品实拍 JPG（225×400）✅；nft 全是 `.svg`（百度图片不索引 SVG）❌；club 25 张 `<img>` 去重后仅 1 张用户头像（200×200，重复 20 次）❌；v 全是用户头像 ❌；bid 仅 1 张 mall 域商品图 ❌。所有站页脚的 4 张二维码 PNG（360×360+）尺寸达标却未被采用，反证百度在做「内容图 vs 头像/二维码」的语义过滤。

3. **mall 首页存在双 `<head>` 结构 bug**。`mall/index.php` 第 61–513 行自带一套 `<!DOCTYPE>/<head>/<title>`，第 515 行又 `include 'includes/header.php'` 输出第二套完整文档头 → 第二套 head 里的 `og:*` 与 Organization JSON-LD 实际落在 `<body>` 内，被搜索引擎忽略。百度 SERP 标题取的是第一个 `<title>`（无 `| 58 Mall` 后缀），与线上 HTML 完全对得上。

4. **club 帖子图 URL 拼错域名**。`club/post.php:140/155` 将图片拼为 `https://www.58.tl/assets/uploads/posts/…`，但文件实际存于 `club/assets/uploads/posts/`（`club.58.tl` vhost root = `club/`）→ 帖子详情页 og:image 与 Article JSON-LD image 均为死链。页面正文 `<img src="/assets/uploads/posts/…">`（club 域）反而是对的。

5. **全站 sitemap 无任何图片节点**。`image:image` / `image:loc` / `xmlns:image` 全仓 0 命中。

6. **club 信息流不渲染帖子图片**。发帖已支持多图上传（`club/create.php:39`，存 `images` JSON 字段），`club/index.php:147` 已解析 `$imgs` 但从未渲染 → 首页没有任何合格内容图。

7. **主站首页同样零合格内容图**（2026-09-30 用户截图补充）。`www.58.tl` 全页仅 4 个 `<img>`，全部位于页脚且全部是二维码（`/images/qr-{discount,customer-service,zhongchuang,gongzhonghao}.{png,jpg}`，CSS 尺寸 80×80）——百度排除二维码，等于主站没有任何可用于缩略图的图片。首屏 hero（`index.php:236-246`）是**纯 CSS 橙渐变 + 文字 + `<div class="hero-right">🏙️</div>` emoji 占位**，不含任何 `<img>`，也不含可作为图片索引的栅格内容图。

## What Changes

- **A · 补齐真实图片资产**：补齐 8 个已被引用的 `assets/images/og-{main,mall,block,bct,nft,hufang,bid,club}.jpg`（1200×630）；为 model / task 两个从未设置 og 的子站补默认 og 图；制作 10 个子域的 LOGO 图（121×75 与 200×133 两种规格，供百度站点属性提交）。
- **F · 修复 mall 首页双 head**：删除 `mall/index.php` 自带的 `<!DOCTYPE>/<head>`，统一走 `mall/includes/header.php`，原内联样式迁入 `$site_config['extra_head']`。
- **B-club · 信息流图文缩略图**：`club/index.php` 每行右侧渲染帖子首图缩略图（素材/字段/上传均已具备，纯增量）。
- **B-main · 主站首页代表图**：`index.php` 首屏 hero 右侧的 emoji 占位（`🏙️`）换成真实地块图 `assets/images/hero-block.png`（由既有 `default-block.png` 压缩生成），使主站首页从「零合格内容图」变为具备可被索引的栅格内容图。
- **club 图片域名修正**：`club/post.php` 的 og:image 与 Article image 改为 `https://club.58.tl/` 前缀。
- **D · 图片 sitemap**：为 mall / club / bid 三个 sitemap 增加 `<image:image>` 扩展节点（面向 Google/Bing 图片索引；百度不读取该扩展但忽略无害）。
- **E · 百度站点属性 LOGO 操作指引**：纯平台侧操作文档（逐子域验证 + 提交 LOGO），不改任何页面布局。
- **附带**：`block/includes/header.php:17` 的 `$site_config['og_image']` 补 `?? ` 兜底，消除未定义索引风险。

## 明确不做（决策记录，避免重复讨论）

| 项 | 结论 | 理由 |
|----|------|------|
| C · NFT 栅格化 | ❌ 不做 | NFT 即 SVG 文件，形态不可改变（用户拍板 2026-09-30） |
| B-v · 互访圈首屏图 | ❌ 缓议 | `circles` 表无封面字段，出真图必须动布局或新增封面上传功能，性价比最低 |
| B-nft · NFT 首屏图 | ❌ 不做 | SVG 原教旨下无栅格素材可用，首页配图由 E 的 LOGO 兜底 |
| B-bid · 拍卖首屏图 | ⏸ 零改动等待 | 首页已有商品实拍图（commit `e66dc53`，2026-09-29 20:05 上线）；百度快照标题仍是旧版（缺「商品」二字）证明快照过期，重抓后应自愈 |
| 主站裸域重复收录 | ⏸ 无需处理 | `58.tl` 已 `301 → https://www.58.tl/`，www 首页已有 `<link rel="canonical">`；截图中的两条同标题结果是百度索引合并时滞，非站点缺陷 |
| 主站城市网格配图 | ❌ 不做 | `cities`（`db-init.sql:188-206`）与 `city_profiles`（`:214-237`）**均无图片字段**，给城市加封面属新功能 |

## Capabilities

### New Capabilities

- `seo-site-image-assets`: 站点代表图与可索引图片资产（og:image 真实可访问、head 结构唯一合法、子域图片 URL 域名正确、内容图片进 sitemap、LOGO 资产齐备）。

### Modified Capabilities

<!-- 无：seo/multi-subsite-indexing 的 sitemap 仅扩展图片节点，既有要求不变。 -->

## Impact

**新增文件**

- `assets/images/og-main.jpg`、`og-mall.jpg`、`og-block.jpg`、`og-bct.jpg`、`og-nft.jpg`、`og-hufang.jpg`、`og-bid.jpg`、`og-club.jpg`、`og-model.jpg`、`og-task.jpg`（1200×630）
- `assets/images/logo/{www,mall,block,bct,nft,v,bid,club,model,task}-logo-121x75.png` 与 `-200x133.png`
- `docs/baidu-site-logo-guide.md`（E 的平台操作指引）
- `assets/images/hero-block.png` — 主站 hero 地块图（256×256，由 `default-block.png` 压缩生成）

**修改文件**

- `mall/index.php` — 删除自带 head，统一走 `mall/includes/header.php`（F）
- `index.php` — 主站首页 hero 右侧 emoji 换成真实地块图，补 `.hero-right img` 尺寸约束（B-main）
- `club/index.php` — 信息流渲染帖子首图缩略图（B-club）
- `club/post.php` — 图片 URL 改 club 域前缀
- `club/assets/css/…`（或页内样式）— 缩略图样式
- `mall/sitemap.php` / `club/sitemap.php` / `bid/sitemap.php` — 增加 `<image:image>` 节点
- `model/includes/header.php` / `task/includes/header.php` — 补 og_image 默认值
- `block/includes/header.php` — og_image 兜底

**依赖与风险**

- 图片素材需人工设计产出（og 图与 LOGO 图），代码无法代劳；LOGO 审核通过率与周期由百度决定（数周），需有心理预期。
- 百度不读取 `<image:image>` 扩展——D 的直接受益方是 Google/Bing，对百度只是无害；百度缩略图的改善主要靠 A（修复后 og 声明至少不再指向死链）与 B-club（首页出现合格内容图）。
- `per-subsite-baidu-indexing` 的子域验证进度是 E 的前置（目前已验证 www/mall/block/model/nft）。
