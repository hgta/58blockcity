# 实施任务：help 子站 SEO/GEO 地基

## 0. 前置（硬约束）

- [x] 0.1 确认 `help-site-layout` 已归档，或本变更的 `help/_layout.php` 改动并入该变更 —— **两个变更不得并行编辑 `_layout.php`**
      （验证：`git status` 中 `_layout.php` 仅被一个变更持有 —— 已确认：`help-site-layout` 的代码任务 1.1~3.5.2 全部完成，剩余 4 项均为部署后复验，工作区 `_layout.php` 无未提交改动，不存在并行编辑）

## 1. 权威域收口

- [x] 1.1 `docs/nginx-rewrite.conf` 的 www server 块新增 `rewrite ^/help/(.*)$ https://help.58.tl/$1 permanent;`，置于 `/help/` 动态路由 rewrite 之前
      （验证：配置语法检查通过；顺序上早于 `/help/category/` 等规则）
- [x] 1.2 `help/_layout.php` 的 `help_header()` 支持接收 `canonical` 参数并在 `<head>` 输出 `<link rel="canonical">` 与 `<meta property="og:url">`
      （验证：未传参时按当前请求路径自动推导，缺省不报错 —— 已用临时脚本覆盖 9 类白名单路径 + 5 组 `?route=` 组合，全部通过）
- [x] 1.3 各页面传入自身权威 URL：home / category / article / faq / glossary
      （验证：逐页查看源码，canonical 与浏览器地址栏 URL 一致且均为 `https://help.58.tl/…`）
- [ ] 1.4 部署后复验：`https://www.58.tl/help/faq` 与 `https://www.58.tl/help/` 均 301 到对应 help.58.tl 地址 — 需部署服务器

## 2. 软 404 修复

- [x] 2.1 `help/index.php` 路由分发前增加请求路径白名单判定（`/`、`/index.php`、`/category/{slug}`、`/article/{slug}`、`/search`、`/faq`、`/glossary`、`/ask`、`/sitemap.xml`）
      （验证：请求 `/this-does-not-exist` 返回 404 而非首页）
- [x] 2.2 抽出 `help_404($msg)` 到 `help/_layout.php`，统一 404 呈现（当前 `pages/article.php:17-24` 为内联实现）
      （验证：404 页样式与文章不存在页一致，含返回首页入口 —— `article.php` 的「不存在 / 已下架(410)」与 `category.php` 的「分类不存在」已统一改调 `help_404()`）
- [ ] 2.3 回归：白名单内 9 类路径行为不变，`?route=xxx` 直连方式仍可用
      （验证：逐条请求均返回 200 且内容正确 — 需部署服务器）

## 3. help 域 robots.txt

- [x] 3.1 新增 `help/robots.txt`：`User-agent: *` 全放行 + AI 爬虫显式 `Allow: /` 段（UA 清单与主域 `robots.txt` 一致）+ `Disallow: /api/` + `Sitemap: https://help.58.tl/sitemap.xml`
      （验证：文件内容含 GPTBot / OAI-SearchBot / PerplexityBot / ClaudeBot / Claude-SearchBot / Google-Extended / Bytespider / CCBot）
- [ ] 3.2 部署后复验 `https://help.58.tl/robots.txt` 返回文本内容（非 HTML）— 需部署服务器
      （验证：`curl -s https://help.58.tl/robots.txt` 首行为 `User-agent:` 而非 `<!DOCTYPE`）

## 4. help 域 llms.txt

- [x] 4.1 新增 `help/llms.txt`：站点定位 → 主实体声明（显式含 `https://www.58.tl/`）→ 入口清单（首页 / 常见问题 / 术语表 / 主要分类）→ Optional（sitemap / robots）
      （验证：文件含 `https://www.58.tl/` 与各入口链接）
- [ ] 4.2 部署后复验 `https://help.58.tl/llms.txt` 返回文本内容 — 需部署服务器
      （验证：`curl -s` 首行为 `# ` 标题行而非 HTML）

## 5. sitemap 修正

- [x] 5.1 `help/pages/sitemap.php` 的 base 改为写死 `https://help.58.tl/`，不再取 `$_SERVER['HTTP_HOST']`
      （验证：任意 host 请求，输出 `<loc>` 均为 `https://help.58.tl/…` —— 改用 `help_canonical_base()`，与 canonical 同源）
- [x] 5.2 补齐 `lastmod`：分类用自身 `updated_at`，首页 / faq / glossary 用各内容表 `MAX(updated_at)`
      （验证：首页与 faq 节点出现 `<lastmod>`，且更新一篇文章后首页 lastmod 同步变化）
- [x] 5.3 根 `sitemap.php` 移除 10 条 `help/*.html` 静态节点（第 58–67 行）
      （验证：主域 sitemap 中不含 `help/help.html` 等 URL）
- [ ] 5.4 部署后复验两个 sitemap 均为合法 XML 且可访问 — 需部署服务器
      （验证：`Content-Type: application/xml`，XML 可解析无结构错误）

## 6. 全站引荐改指

- [x] 6.1 根 `llms.txt`「帮助与知识」5 条改指 `https://help.58.tl/`（首页 / article/buy-blocks-guide / article/create-city-guide / article/why-create-city / glossary）
      （验证：全文检索无 `www.58.tl/help/*.html` 残留）
- [x] 6.2 `bct/llms.txt`、`task/llms.txt`、`hufang/llms.txt` 中 `glossary.html` 链接改指 `https://help.58.tl/glossary`
      （验证：三个文件检索无 `glossary.html` 残留 —— 另 `task` / `hufang` 的「帮助中心」条目原指向 `help/help.html`，一并改指）
- [x] 6.3 全仓复查：`site.php`、`rankings/*.html` 等仍引用 `/help/*.html` 的位置一并改指
      （验证：`grep -rn "help/.*\.html"` 在 llms/sitemap 类文件中无残留 —— 已修 `site.php:27`、`rankings/rankings.html:655`、`rankings/gdp-total.html:895`；`.htaccess` 与 nginx 的 301 规则保留不动）

## 7. 观测与收尾

- [ ] 7.1 上线后 2~4 周记录 `help.58.tl` 的收录量与 AI 爬虫访问量，作为 canonical 主域决策的对照依据 — 需服务器 access log 与站长平台权限
      （验证：产出「切换前后」对照数据；若 help.58.tl 收录明显不达预期，记录反向改为主域子目录的评估结论）
- [ ] 7.2 用 `curl -s -A "GPTBot"` 抽查 `https://help.58.tl/article/{slug}`，确认返回 200 与真实 HTML 且 canonical 正确 — 需部署服务器
