# 任务清单：站点图片资产与搜索缩略图

> 前置条件：og 图与 LOGO 图素材需人工设计产出。
> 实施顺序见 design.md D10：F → A →（D / B-club / B-main 并行）；E 随时启动。

## 1. 修复 mall 首页双 head（F · P0）

- [ ] 1.1 `mall/index.php`：删除 L61–514 自带的 `<!DOCTYPE>/<html>/<head>/<style>/<title>/</head>/<body>` 结构
- [ ] 1.2 `mall/index.php`：`<title>` 值写入 `$site_config['title']`，内联 `<style>` 迁入 `$site_config['extra_head']`，`<?php include 'includes/header.php'; ?>` 移到页面内容之前
- [ ] 1.3 验证：线上 `mall.58.tl/` HTML 只含 1 个 `<title>`、1 个 `<!DOCTYPE`；`og:*` 位于 `</head>` 之前；首屏渲染与修复前人工比对无回归
- [ ] 1.4 `block/includes/header.php:17`：`og_image` 改为 `$site_config['og_image'] ?? '…og-block.jpg'` 模式；验证：页面自设 og_image 时不被覆盖

## 2. 补齐图片资产（A · P0）

- [ ] 2.1 设计并落盘 `assets/images/og-{main,mall,block,bct,nft,hufang,bid,club}.jpg`（1200×630，JPG）；验证：8 个 URL 全部 `curl -o /dev/null -w "%{http_code}"` 返回 200 且 `content-type: image/jpeg`
- [ ] 2.2 `model/includes/header.php` / `task/includes/header.php`：补 `og_image` 默认值 `…/og-model.jpg`、`…/og-task.jpg`；同步落盘两张图
- [ ] 2.3 验证：十个子域首页 `og:image` 均 200 可访问；微信开发者工具/任意 OG 预览工具抽查 mall、club、nft 分享卡片出图
- [ ] 2.4 设计并落盘 `assets/images/logo/` 下 10 个子域 × 2 规格（121×75、200×133）的 LOGO PNG；验证：20 个文件尺寸与规格一致

## 3. club 图文流与 URL 修正（B-club + D5 · P1）

- [ ] 3.1 `club/index.php`：`club-post-row` 内当 `$imgs` 非空时渲染首图缩略图（`<img src="/…"` 根相对路径、`loading="lazy"`、`object-fit:cover`、固定约 96×72）
- [ ] 3.2 补缩略图 CSS；无图帖子保持现有行布局不塌陷
- [ ] 3.3 `club/post.php:140/155`：`https://www.58.tl/` 前缀改为 `https://club.58.tl/`
- [ ] 3.4 验证：含图帖子详情页 og:image 返回 200；信息流有图行显示缩略图、无图行布局不变；移动端抽查无横向溢出

## 4. 主站首页代表图（B-main · P0）

- [ ] 4.1 由 `assets/images/default-block.png`（1024×1024）压缩生成 `assets/images/hero-block.png`（256×256，目标 < 60KB，栅格 PNG/JPG）；验证：`getimagesize` 尺寸正确、体积达标
- [ ] 4.2 `index.php:245`：`<div class="hero-right">🏙️</div>` 替换为 `<img src="/assets/images/hero-block.png" alt="58区块城市 数字地块示意图" width="96" height="96">`
- [ ] 4.3 补 CSS `.hero-right img { width:96px; height:96px; object-fit:contain; border-radius:12px; }`；移动端（`@media(max-width:768px)`）确认居中不挤压
- [ ] 4.4 验证：线上 `https://www.58.tl/` 的 `<img>` 数量由 4 变 5；新增图位于 `.hero-right` 内、200 可访问、栅格格式；人工比对 hero 高度与橙色底上的对比度
- [ ] 4.5 记录确认：`58.tl` 301 → `www.58.tl` 且 www 已有 canonical，裸域重复收录无需处理（仅留档，不改动）

## 5. 图片 sitemap（D · P1）

- [ ] 5.1 `mall/sitemap.php`：`urlset` 补 `xmlns:image` 命名空间；商品/店铺/作者详情节点输出 `<image:loc>` + `<image:title>`（图 URL 取各自主图绝对地址）
- [ ] 5.2 `club/sitemap.php`：帖子详情节点在有图时输出 `<image:image>`
- [ ] 5.3 `bid/sitemap.php`：拍品详情节点输出 `<image:image>`（跨域 mall 图 URL 原样输出）
- [ ] 5.4 验证：三个 `sitemap.xml` 可被 `xmllint --noout` 解析（或浏览器打开无解析错误）；Google Search Console 提交后无「无效标签」报错

## 6. 百度站点属性 LOGO（E · P2，平台操作）

- [ ] 6.1 编写 `docs/baidu-site-logo-guide.md`：站点属性-LOGO 的入口、规格、提交流程、审核周期预期、被拒后的调整建议
- [ ] 6.2 用户侧：为已验证的 www / mall / block / model / nft 提交 LOGO（任务 2.4 的素材）
- [ ] 6.3 用户侧：bct / v / bid / club / model / task 完成子域验证后补提 LOGO（依赖 `per-subsite-baidu-indexing` tasks 5 节）
- [ ] 6.4 验证：数周后在百度搜索 `site:{sub}.58.tl` 观察首页结果是否出现 LOGO

## 7. 上线验证与基线记录（P2）

- [ ] 7.1 记录当前 `site:58.tl` 各子站缩略图基线截图存档（作为对比依据）
- [ ] 7.2 手动推送 `bid.58.tl/` 首页若干次加速百度重抓；观察快照标题更新为「…NFT · 商品在线拍卖」
- [ ] 7.3 上线 2–4 周后复查：主站 / club / bid 是否出现缩略图、og 分享卡片是否全部出图；结果记回本变更
- [ ] 7.4 若 club 仍无缩略图，评估是否重启 B-v（互访圈封面字段）方案
