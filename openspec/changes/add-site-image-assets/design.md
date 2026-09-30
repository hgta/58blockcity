# 设计：站点图片资产与搜索缩略图

## 0. 探索结论回放（2026-09-30 实测）

```
百度缩略图链路（实测推断）
┌──────────────┐   抓 <img>    ┌───────────────┐   语义过滤    ┌────────────┐
│  页面 HTML    │ ───────────▶ │  百度图片库    │ ──────────▶  │ 结果页配图  │
└──────────────┘               └───────────────┘              └────────────┘
                                       ▲
                    过滤掉：SVG / 头像 / 二维码 / 小于阈值的图 / 与内容无关的图
                                       ▲
                唯一通过全部过滤的：mall 首页的 27 张商品实拍 JPG（225×400）

og:image 的角色：百度官方不把它当缩略图来源；它服务微信/QQ/X 分享卡片
                —— 而我们全站的 og:image 当前都是 404 死链
```

各子站首页图片构成（去重后）：

| 子域 | `<img>` 总数 | 去重后 | 性质 | 百度判定 |
|------|-------------|--------|------|----------|
| mall | 46 | 27 商品实拍 + 头像/店铺logo | 模特实拍 JPG 225×400 | ✅ 合格配图 |
| nft | 102 | 99 个 SVG | image/svg+xml | ❌ 不索引 |
| club | 25 | 1 张头像（重复 20 次） | 200×200 | ❌ 装饰/头像 |
| v | 24 | 7 张头像 | 用户头像 | ❌ 头像 |
| bid | 7 | 1 张 mall 域商品图 + 1 SVG | 跨域归属 mall | ⏳ 快照过期，重抓待观察 |
| 全部 | ×4 | 4 张二维码 PNG | 360×360+，尺寸达标 | ❌ 二维码被排除 |

## 1. 决策记录

### D1：OG 图规格 = 1200×630 JPG，存 `assets/images/`（A）

- `shared/header.php:108-109` 已固定输出 `og:image:width=1200 / height=630`，新资产按此规格制作，无需改头部代码。
- 文件名沿用现有引用（`og-main` / `og-mall` / `og-block` / `og-bct` / `og-nft` / `og-hufang` / `og-bid` / `og-club`），**只补文件、不改引用**，把改动面压到最小。
- 商品详情页已有 `og:image:width/height 800×800` 覆盖逻辑（`mall/product/detail.php:197-198`），不受影响。
- model / task 从未设置 og_image → 补 `og-model.jpg` / `og-task.jpg` 并在各自 `includes/header.php` 加默认值，实现十站全覆盖。

### D2：LOGO 图规格 = 121×75 与 200×133 两套 PNG（E 的素材）

百度「站点属性-LOGO」要求两种规格各一张，按子域命名存放于 `assets/images/logo/`。
LOGO 展示于**首页**搜索结果旁，与内容缩略图是两条独立通道，互不冲突。

### D3：mall 首页 head 统一收口到 `mall/includes/header.php`（F）

现状：

```
mall/index.php
  L61   <!DOCTYPE html>          ┐
  L66   <title>…BCT商城平台</title> │ 第一套 head（自带）
  L67-512  <style>…</style>       │
  L513  </head>                  ┘
  L514  <body>
  L515  <?php include 'includes/header.php'; ?>  ← 又输出第二套 DOCTYPE/head/og/JSON-LD
```

修复方向：删除 L61–514 的自带文档结构，把 `<title>` 值写入 `$site_config['title']`、内联 `<style>` 迁入 `$site_config['extra_head']`，include 保持在页面内容之前。这样 mall 首页与其它 mall 页面（product/list、shop/view 等）结构完全一致。

**验证锚点**：修复后线上 `mall.58.tl/` 的 HTML 只应出现**一个** `<title>` 和**一个** `<!DOCTYPE`；og 标签位于 `</head>` 之前。

### D4：club 信息流缩略图 = 每行右侧一张帖子首图（B-club）

素材链路已全部就绪，只差渲染：

```
club/create.php L39   上传 → club/assets/uploads/posts/xxx.jpg（jpg/png/gif/webp）
club/posts 表          images 字段（JSON 数组）
club/index.php L147   $imgs = json_decode($p['images']) ← 已解析，未使用
```

设计：

- `club-post-row` 行内右侧加一个固定尺寸缩略图位（约 96×72，`object-fit: cover`），仅当 `$imgs` 非空时渲染，无图时行布局回退为现状（纯增量，不重排版式）。
- v1 直接引用原图 + `loading="lazy"`；如带宽成为问题，v2 可在上传时用 `includes/functions.php` 既有的 `compressImage()` 生成缩略图（本变更不含）。
- 帖子详情页 `club/post.php:217` 已渲染全部图片，内页缩略图条件天然满足，本项只为首页补图。

### D5：club 图片 URL 域名修正

`club.58.tl` 的 nginx root = 仓库 `club/` 目录，帖子图实际 URL 是 `https://club.58.tl/assets/uploads/posts/…`。

```php
// club/post.php:140 与 :155 现状（错误）
'https://www.58.tl/' . ltrim($firstImage, '/')          // → 404
// 修正后
'https://club.58.tl/' . ltrim($firstImage, '/')
```

`club/index.php` 新增的缩略图同样使用 `/` 根相对路径（与 post.php 正文 L217 一致）。

### D6：图片 sitemap 定位修正——面向 Google/Bing，百度忽略无害（D）

> 探索阶段的表述修正：此前称图片 sitemap 是「让百度图片库收录的正规通道」，**不准确**。`<image:image>` 是 Google 的 sitemap 扩展，百度不读取也不报错。

因此 D 的真实价值：Google/Bing 图片索引（进而 AI 问答引用图片）受益；对百度缩略图无直接作用（那靠 A 修复死链 + B-club 提供内容图 + 页面自然抓取）。

实现：

- 仅给**确有栅格内容图**的页面加节点：mall（商品/店铺/作者详情）、club（帖子详情）、bid（拍品详情）。
- nft 不加（素材为 SVG）；block/bct/v/task 首页无内容图，不加。
- `<image:image>` 结构：`<image:loc>`（绝对 URL）+ `<image:title>`；`urlset` 需补 `xmlns:image="http://www.google.com/schemas/sitemap-image/1.1"`。
- bid 拍品图引用的是 mall 域 URL：`<image:loc>` 原样输出，接受 Google 可能因跨域不收录（不做域名代理，避免制造重复图片 URL）。

### D7：明确不做的项（附证据，供未来翻案时复查）

- **C（NFT 栅格化）**：用户拍板 NFT 即 SVG，形态不可变。
- **B-v**：`circles` 表无封面字段（见 `init/db-init.sql:153-164`），出内容图需加字段+上传功能+布局，收益/成本比最差。
- **B-nft**：无栅格素材，首页配图指望 E 的 LOGO。
- **B-bid**：商品实拍图已于 `e66dc53`（2026-09-29 20:05）上首页；百度快照标题缺「商品」二字证明快照早于该提交。待重抓自愈，可手动推送加速（配额 10 条/天，见 `per-subsite-baidu-indexing` tasks 5 节备注）。

### D8：`block/includes/header.php` og_image 兜底

`block/includes/header.php:17` 直接赋值 `$site_config['og_image'] = '…og-block.jpg'`（无 `??` 判断，且覆盖页面已设置的值）。改为与 mall/nft/club/bid/hufang 一致的 `$site_config['og_image'] ?? '…'` 模式。

### D9：主站首页代表图 = hero 右侧 emoji 换成真实地块图（B-main）

现状（`index.php:236-246`）：

```php
<div class="hero">
    <div class="hero-left">
        <h1>探索元宇宙城市生态</h1>
        <p>58区块城市 — 基于区块链的虚拟城市平台…</p>
        <div class="hero-btns">…</div>
    </div>
    <div class="hero-right">🏙️</div>     <!-- font-size:44px，纯 emoji 占位 -->
</div>
```

`.hero-right` 本就是给视觉元素预留的位置，把它换成真实栅格图是**改动面最小的落点**：DOM 结构不变，仅替换一个 `div` 的内容 + 约束尺寸的 CSS。

素材决策：用 `assets/images/default-block.png`（1024×1024、794KB；当前是 bid 地块拍品的兜底图，`bid/includes/lot_helpers.php:134`）压缩出 `assets/images/hero-block.png`（256×256，目标 < 60KB）。理由：语义即「数字地块」，与主站「探索元宇宙城市生态」主题直接对应，零设计成本，可立即上线；后续若要品牌插画再替换同一文件名即可。

CSS：

```css
.hero-right img { width:96px; height:96px; object-fit:contain; border-radius:12px; }
```

移动端已有 `@media(max-width:768px){ .hero{flex-direction:column;text-align:center} }`，保持居中即可。

**验证锚点**：线上 `https://www.58.tl/` 的 `<img>` 数量由 4 变 5，且新增者位于 `.hero-right` 内、`alt` 非空、HTTP 200 且为栅格格式。

**同时确认无需处理的两件事**（避免以后重复讨论）：

- **裸域重复收录**：`58.tl` 已 `301 → https://www.58.tl/`，且 www 首页已有 `<link rel="canonical" href="https://www.58.tl/">`。截图里的两条同标题结果属百度索引合并时滞，非站点缺陷，本变更不动。
- **城市网格不加图**：`cities`（`init/db-init.sql:188-206`）与 `city_profiles`（`:214-237`）**均无图片字段**，与 v 的 `circles` 情况相同；给城市加封面属新功能，不在本变更内。

### D10：实施顺序依赖

```
F（mall 双 head） ──▶ A（补 og 图）──▶ 验证 og 落在 head 内且 200
                                    ├──▶ D（图片 sitemap，可并行）
                                    └──▶ B-club + B-main + D5（可并行，互不触碰同一文件）
E（LOGO 平台操作）随时可启动 —— 验证周期最长（数周），越早越好
```

F 必须先于 A 验证：不修双 head，A 补的图在 mall 首页仍落在第二个 head（body 内）不生效。

## 2. 风险与开放问题

| 风险 | 缓解 |
|------|------|
| og 图 / LOGO 图需人工设计，风格不统一 | proposal 中规格已定死（1200×630 / 121×75+200×133）；可先出图后走变更 |
| 百度 LOGO 审核不通过 | LOGO 通道失败不影响其余项；内容缩略图通道独立 |
| 百度对子域缩略图的触发条件不公开，A+B-club 后 club 是否出图无保证 | 记录基线（当前 site: 结果截图），上线 2–4 周后对比；不达预期再评估 B-v |
| club 帖子图若多数无图，缩略图覆盖率低 | 首页出图取决于「任意一行有图」；随发帖自然增长 |
| 双 head 修复改变 mall 首页现有渲染顺序 | 迁移时保持 DOM 输出等价（样式进 head、内容顺序不变），上线后人工比对首屏 |
