# 设计：help 子站内容层 SEO

## 前提事实

| 事实 | 证据 |
|---|---|
| `help_articles` 无 SEO 字段 | `init/migration-help-center-ai.sql` 字段清单 |
| description = `summary ?: 正文截断` | `help/pages/article.php:76` |
| 截断不看句末 | `help/_init.php:81-84` `help_plain_summary()` 直接 `mb_substr($plain,0,$len)` |
| summary 渲染为浅色提示框，不在正文首段 | `help/pages/article.php:118-122` |
| 搜索页 title 直接含用户输入的 q | `help/pages/search.php:64` |
| FAQ 用 `?cat=` 切 tab，无 canonical | `help/pages/faq.php:53` |
| 分类页有 `?page=` 分页 | `help/pages/category.php` 分页链接 |
| 无指向功能子站的正文深链 | 文章页仅「继续阅读」（同分类）+ 导航/页脚回主站 |
| 后台已有「AI 摘要」能力可复用 | `admin/help-summary-ai.php`（`help-content-admin` 任务 8.2） |

---

## D1：只给 `help_articles` 加 SEO 字段

**决策**：新增 `seo_title VARCHAR(120)` 与 `meta_description VARCHAR(200)`，只加在 `help_articles` 上。

**理由**

- FAQ 与术语表是**聚合页**，其 title/description 由页面性质决定（当前硬编码在 `pages/faq.php:34-36`、`pages/glossary.php` 已足够），逐条维护 SEO 字段收益远低于成本。
- 分类页同理，已有 `description` 字段可用。

**回退优先级**（前台统一在一个函数里决定，避免各页各写一套）：

```
title        = seo_title ?: title
description  = meta_description ?: summary ?: 正文首句(句末收尾, 60~120字)
```

**兼容性**：迁移未执行时字段不存在，SQL 里 `SELECT *` 会少这两列 → 前台必须用 `?? ''` 判空取用，**不能假设列一定存在**（`help_articles` 当前用 `SELECT a.*`，见 `pages/article.php:10`）。

---

## D2：答案前置 —— 把 summary 放进正文首段

**决策**：`pages/article.php` 把 `summary` 从「浅色提示框」改为渲染在 `<h1>` 之后、正文之前的**首段**（`.hc-lead`，样式上比正文略大/加粗，但语义上是正文的一部分）。

**理由**：生成式引擎抽取正文时通常取开头若干句。当前 summary 在一个带图标的提示框里，视觉上属于「元信息装饰」而非正文，且位于 `.meta` 之后、`#article-body` 之外——严格说不在正文中。静态页那轮改造（任务 7.1）的做法就是「标题区之后插入直答段落」，动态页应承接同一规范。

**不做**：不强制校验长度（避免阻塞发布）。改为在后台摘要字段旁给出「建议 40–80 字，能独立回答标题问题」的提示文案，靠撰写规范而非硬校验。

**复用**：后台已有「AI 摘要」按钮（`admin/help-summary-ai.php`），可直接用于生成这条直答段落。

---

## D3：description 回退要在句末收尾

**决策**：`help_plain_summary()` 改为在字数上限附近寻找最近的句末标点（`。！？.!?；;`）截断，找不到再硬截并补省略号；上限内文本不足则不截。

**理由**：当前实现（`:82-83`）是 `mb_substr($plain, 0, $len)`，会把句子从中间切断，SERP 与 AI 摘要里显示为半句话，可读性差。搜索页的 `hl_summary()`（`pages/search.php:59`）是另一套逻辑，也应在同一原则下调整（它还要保留命中高亮，改动需谨慎——本变更只改截断行为，不动高亮）。

---

## D4：参数页收敛 —— 分类处理，不做一刀切

| 页面 | 处置 | 理由 |
|---|---|---|
| `/search?q=…` | `noindex,follow` | `q` 由用户输入，可无限组合；结果页本质是站内工具，Google 明确建议搜索结果页 noindex。长尾价值小于薄内容风险 |
| `/faq?cat=…` | canonical → `https://help.58.tl/faq` | 各 tab 内容合起来才是完整 FAQ，单 tab 不是独立页面；收口到 FAQ 主页 |
| `/category/{slug}?page=N` | self-canonical（含 `page` 参数） | 分页内容确实不同，保留收录；`rel=prev/next` 已被主流引擎弃用，不添加 |
| `/ask` | 不加 noindex（本变更不动） | 已在 `help-seo-foundation` 中排除出 sitemap；是否 noindex 留待单独评估 |

**注意**：`noindex` 必须同时保证页面**可被抓取**（否则指令读不到）——故 `Disallow` 里不能屏蔽 `/search`，`robots.txt` 保持放行（`help-seo-foundation` D3 已确认全放行）。

---

## D5：跨子站内链 —— 用分类映射表，不硬编码

**决策**：文章页在「继续阅读」之后，按文章所属分类输出一组指向对应功能子站的入口（如「区块」分类 → `block.58.tl/claim_list.php` 认领列表）。映射关系放在 `help/_init.php` 的一个配置数组里，不在模板里硬编码 URL。

**理由**

- 权重与 AI 理解都需要 help 与业务子站之间的双向链路，当前只有「help ← 主站」单向。
- 映射表集中一处，后续增删子站入口不用翻模板。
- 映射缺失时不输出区块，不报错（分类新增时不至于崩页面）。

**不做**：不改动正文富文本内容（那是内容运营范畴，本变更只加结构化入口）。

---

## 风险与权衡

| 项 | 选择 | 放弃的 |
|---|---|---|
| SEO 字段范围 | 只加 `help_articles` | FAQ/术语表/分类也加（维护成本大于收益） |
| 答案前置 | 渲染位置 + 后台提示 | 强制长度校验（会阻塞发布） |
| 搜索页 | `noindex,follow` | 保留可索引（长尾收录 vs 薄内容惩罚，选后者） |
| 分页 | self-canonical | 统一 noindex（会丢掉分类下的长尾文章入口） |
| 内链 | 分类→子站映射表 | 正文中插链（内容运营范畴，不在本变更） |

**遗留**

- `/ask` 页的 noindex 与 SSR 内容缺失问题 → 单独评估（它不在 sitemap，短期风险低）。
- 静态遗留 HTML（`help/*.html`）的最终清理（删除还是保留）→ 依赖 `help-seo-foundation` 完成全站改指后再处理。
