# 实施任务：help 子站内容层 SEO

## 0. 前置（硬约束）

- [x] 0.1 确认 `help-structured-data`（批次 B）已完成或 `help/pages/article.php` 无并发改动 —— **本变更与 B 同改该文件，建议 B 后实施或合并**
      （验证：`git status` 中 `pages/article.php` 无并发改动 —— B 已完成并提交，本变更在同一工作区继续）

## 1. 数据层

- [x] 1.1 新增 `init/migration-help-content-seo.sql`：`help_articles` 增 `seo_title VARCHAR(120) NULL`、`meta_description VARCHAR(200) NULL`
      （验证：SQL 可在测试库执行，`SHOW COLUMNS` 可见新列 — 需测试库执行）
- [x] 1.2 `admin/help-articles.php` 表单增加两个字段（可留空），摘要字段旁加「建议 40–80 字、能独立回答标题问题」提示
      （验证：后台可保存并回显；留空提交不报错 —— 新增 `hseo_cols_ready()` 探测列存在性，迁移未执行时跳过这两个字段，不阻断保存）

## 2. 前台取值与回退

- [x] 2.1 统一 title / description 取值：`seo_title ?: title`，`meta_description ?: summary ?: 正文首句`
      （验证：三种组合分别符合预期 —— 另为 steps 型文章加了从步骤文本兜底的分支，避免 description 为空）
- [x] 2.2 用 `?? ''` 判空取用，确保迁移未执行时页面不报错
      （验证：临时注释掉新列查询后页面仍正常渲染）
- [x] 2.3 `help_plain_summary()` 改为句末收尾（`help/_init.php:81-84`），上限内不足则不截
      （验证：短句不被截断，长句在句末标点处结束 —— 本地脚本覆盖 短句/长句/无标点/空内容 四组，无标点时留 1 字补省略号保证不超上限）

## 3. 答案前置

- [x] 3.1 `help/pages/article.php` 把 `summary` 从浅色提示框改为 `<h1>` 之后、正文之前的直答段落（新增 `.hc-lead` 样式，放 `_layout.php`）
      （验证：DOM 中该段落在标题/元信息之后、`#article-body` 之前）
- [x] 3.2 summary 为空时不输出空段落
      （验证：无摘要文章无空段落、无多余间距）
- [x] 3.3 后台「AI 摘要」按钮（`admin/help-summary-ai.php`）生成的文案可直接用作直答段落，验证长度 40–120 字
      （验证：生成结果填入 summary 后前台呈现正常 —— prompt 已限定 80–120 字，落在 40–120 区间）

## 4. 参数页收敛

- [x] 4.1 `help/pages/search.php` 输出 `<meta name="robots" content="noindex,follow">`
      （验证：任意 `q` 下页面均含该 meta —— `help_header()` 新增 `robots` 参数）
- [x] 4.2 确认 `help/robots.txt` 未 `Disallow` `/search`（否则 noindex 不生效）
      （验证：robots.txt 全文无 `/search` 的 Disallow 规则 —— 仅 `Disallow: /api/`）
- [x] 4.3 `help/pages/faq.php` 带 `?cat=` 时 canonical 输出为 `https://help.58.tl/faq`
      （验证：任意 `?cat=` 下 canonical 均为 `/faq` —— 已在批次 A 固定为 `help_canonical_url('faq')`）
- [x] 4.4 `help/pages/category.php` 分页输出 self-canonical（含 `page` 参数）
      （验证：`?page=2` 的 canonical 为自身而非第一页）

## 5. 跨子站内链

- [x] 5.1 `help/_init.php` 增加「分类 slug → 业务子站入口」映射数组（名称 + URL），集中一处便于维护
      （验证：数组结构清晰，新增分类映射不需改模板 —— `help_subsite_links($catSlug)`，覆盖 block/bct/nft/mall/club/bid/task/getting-started）
- [x] 5.2 `help/pages/article.php` 在「继续阅读」之后按分类输出入口区块；无映射时不输出且不报错
      （验证：有映射分类出现入口链接；无映射分类无区块、无错误 —— 新增 `.hc-links` 样式）

## 6. 收尾验证

- [ ] 6.1 逐页抽查 title / description 取值正确（文章、分类、FAQ、术语表、搜索、首页）
      （验证：无空 description、无半句截断 — 需部署服务器）
- [ ] 6.2 部署后复查搜索页已从索引中移除、FAQ 变体已收口 — 需部署服务器与站长平台
      （验证：站长平台 URL 检查工具显示搜索页为 noindex）
- [ ] 6.3 部署后抽查跨站入口链接可访问且为 HTTPS 绝对地址
      （验证：链接点击可达，无协议相对或相对路径）
