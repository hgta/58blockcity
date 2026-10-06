# 设计：help 子站 SEO/GEO 地基

## 背景事实（核查结论，作为设计前提）

| 事实 | 证据 |
|---|---|
| 全站主导航与页脚「帮助」链接已全部指向 `https://help.58.tl/` | `index.php:384-386`、`shared/footer.php:81-83`、各子域 header |
| AI 侧已把 `help.58.tl` 当权威答案来源/落地页 | `api/ai/chat.php:91`、`js/help-guide.js:17`、`js/ai-widget.js:24` |
| `www.58.tl/help/*` 与 `help.58.tl/*` 双份 200，均无 canonical | nginx-rewrite.conf:60-74 / 508-541；`help/**/*.php` 搜 `canonical` 零命中 |
| 任意未知路径返回 200 + 首页 HTML | try_files 兜底 + `help/index.php:19` route 默认 `home` |
| `help/` 目录无 robots.txt / llms.txt | 目录清单；请求落到上述软 404 |

---

## D1：权威域定为 `help.58.tl`，`www.58.tl/help/*` 全路径 301

**决策**：`https://help.58.tl/` 为唯一可索引域；`www.58.tl/help/*` 通过 nginx 301 到 `https://help.58.tl/*`；help 各页输出自引用 canonical。

**理由**

1. **零迁移成本**：全站内链已经是 `help.58.tl`（页脚、首页、各子域 header），改主域为 `www.58.tl/help/` 反而要改几十处内链。
2. **与既有架构一致**：block / bct / mall / nft / model / task 等均为独立子域且各自作为收录目标，help 不应是唯一例外。
3. **AI 侧引用已固化**：`api/ai/chat.php` 与前端组件把 `help.58.tl` 当落地页，换主域会让 AI 答案里的引用链接全部失效一段时间。

**为何不用「两边都留 + canonical」**：两套 host 都要注入 canonical、都要进 sitemap 与 robots，长期治理成本高于一次性 301，且百度对跨 host canonical 的支持弱于 301。

**风险与对冲**：百度对子域的收录权重通常弱于主域子目录。对冲手段是上线后观测（见 tasks 观测项），若 help.58.tl 收录明显不达预期，再评估反向改为主域子目录（届时内链与 AI 引用需同步改，成本高，故先走低成本路径）。

**301 映射**（www server 块新增，放在 `/help/` rewrite 之前）：

```
rewrite ^/help/(.*)$ https://help.58.tl/$1 permanent;
```

保留 `www.58.tl/help/` 目录下的 `.htaccess` 不动（其对 Nginx 无效，仅 Apache 环境兜底）。

---

## D2：软 404 —— 未知路由返回真 404

**决策**：在 `help/index.php` 路由分发前增加「请求路径白名单」判定，不在白名单且未显式带 `route` 参数时返回 404。

**理由**：根因是 `index.php` 把「没有 route 参数」等同于「首页」，而 nginx 把一切未命中路径都转给了 `index.php`。在 PHP 层判定的好处是不依赖 nginx 配置，Apache/Nginx 两种部署都生效。

**白名单**（与 `.htaccess` / nginx 的 rewrite 保持一致，单一事实来源需两处同步）：

```
/  /index.php
/category/{slug}  /article/{slug}
/search  /faq  /glossary  /ask  /sitemap.xml
```

**呈现**：复用 `help/pages/article.php:17-24` 的既有 404 呈现方式（`http_response_code(404)` + `help_header` + `.hc-empty`），抽出为 `_layout.php` 的 `help_404()` 以免复制。

**顺带解决**：`robots.txt` / `llms.txt` 只要作为**物理文件**放进 `help/` 目录，`try_files $uri` 就会优先命中并返回静态内容，无需改 nginx，也无需进白名单以外的特殊处理。

---

## D3：`help/robots.txt` —— 显式放行 AI，声明本域 sitemap

**决策**：新增静态 `help/robots.txt`，内容为「`User-agent: *` 全放行 + 主流 AI 爬虫显式 `Allow: /` + `Sitemap: https://help.58.tl/sitemap.xml`」。

**理由**

- 主域 `robots.txt` 的 `Sitemap:` 与 `Disallow:` 对 `help.58.tl` **完全无效**（不同 host），help 域目前等同于「无 robots.txt」。
- AI 放行段与主域保持同一套 UA 清单，避免 help 域出现策略空白。

**不放行 / 不声明的点**

- `Disallow: /api/`：help 域下 `api/feedback.php` 为 POST 接口，保留 Disallow 以防误抓（与主域策略一致）。
- **不在主域 robots.txt 里声明 help 的 sitemap**：跨 host 的 `Sitemap:` 指令需域名所有权验证，收益低；由 help 域自己的 robots.txt 声明即可。

---

## D4：sitemap —— 固定权威域 + 补齐 lastmod

**决策**

1. `help/pages/sitemap.php` 的 base **写死 `https://help.58.tl/`**，不再取 `$_SERVER['HTTP_HOST']`。
   - 现状（`:11`）用 `HTTP_HOST`，导致通过 `www.58.tl/help/sitemap.xml` 访问时会输出一整套 `www.58.tl/help/…` 的 URL——与 D1 的权威域策略自相矛盾。
2. 补齐 `lastmod`：首页 / faq / glossary 用各内容表 `MAX(updated_at)`，分类用自身 `updated_at`（`:18-20` 已经查出来了却没用，属漏写）。
3. 根 `sitemap.php` **移除** 10 条 `help/*.html` 静态节点（`:58-67`）——它们已 301，收录它们是浪费抓取预算。help 的 URL 由 help 自己的 sitemap 负责。

**不做的**：不新增 `changefreq`（现代引擎基本忽略）；不把 `/search`、`/ask` 纳入 sitemap（薄内容/无 SSR 内容，后续变更处理）。

---

## D5：llms.txt —— 新建 help 域清单 + 全站改指

**决策**

- 新增 `help/llms.txt`：站点定位一句话 → 主实体声明（显式写 `https://www.58.tl/`，与其它子域 llms.txt 一致）→ 分类入口 → 热门文章/FAQ/术语表入口 → Optional（sitemap / robots）。
- 根 `llms.txt` 的「帮助与知识」5 条改指 `https://help.58.tl/`（首页 / article/buy-blocks-guide / article/create-city-guide / article/why-create-city / glossary）。
- `bct/llms.txt:14`、`task/llms.txt:24`、`hufang/llms.txt:24` 的 `glossary.html` 链接改指 `https://help.58.tl/glossary`。

**理由**：AI 引擎按域抓取 `/llms.txt`，help 作为独立域必须有自己的清单；而各子域 llms.txt 里指向 301 死链的条目会让 AI 抓到空结果。

**不做**：不生成 `llms-full.txt`（内容量未到需要，且需额外维护）。

---

## D6：与 `help-site-layout` 的排期（硬约束）

`help-site-layout`（15/20）正在重构 `_layout.php` 的 hero / pagehead / CSS，本变更要改同一文件的 `<head>` 区。

```
_layout.php ├─ help-site-layout（进行中：结构 + CSS）
            └─ help-seo-foundation（本变更：<head> 的 canonical / og:url）
```

**处置**：先完成并归档 `help-site-layout`（剩余 3 项为部署后复验），再实施本变更；或把 `<head>` 改动并入该变更一并提交。**不允许两个变更同时编辑 `_layout.php`**。

---

## 权衡与遗留

| 项 | 选择 | 放弃的 |
|---|---|---|
| 权威域 | `help.58.tl` 独立子域 | `www.58.tl/help/` 子目录（权重可能更高，但内链/AI 引用改造成本大） |
| 去重手段 | 301（服务端） | 双 host + canonical（省事但长期治理成本高） |
| 软 404 | PHP 层白名单 404 | 改 nginx try_files（只覆盖 Nginx，Apache 部署失效） |
| 本变更范围 | 只做地基（收口 + 发现性） | 结构化数据、答案前置、参数页收敛 → 拆后续变更 |

**遗留到后续变更**

- B：`SeoHelper` 接线 —— Article / FAQPage / HowTo / DefinedTermSet / BreadcrumbList / WebSite+SearchAction，以及 `shared/organization.php` 实体锚点注入。
- C：内容层 —— `help_articles` 加 `seo_title` / `meta_description`、答案前置规范、`/search` 与 `/faq?cat=` `/category?page=` 参数页收敛。
