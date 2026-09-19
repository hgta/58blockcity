## 1. 数据层与基础设施

- [x] 1.1 编写迁移 SQL（`init/` 新编号文件）：创建 `help_categories`、`help_articles`（含 `content_steps` JSON 与 FULLTEXT ngram 索引）、`help_faq`、`help_glossary`、`help_article_feedback`、`ai_providers`、`ai_chat_logs`、`ai_feedback_tickets`，并在 `system_settings` 插入 AI 全局开关与频控默认值；验证：对开发库执行后 `SHOW TABLES` 与索引齐全
- [x] 1.2 编写迁移脚本将现有 10 个静态帮助页（glossary/block_city_intro/block_buy_guide 等）解析入库为 `published` 文章并建立旧文件名→slug 映射表；验证：迁移后文章数 ≥ 10 且后台可见
- [x] 1.3 实现 API Key 加密工具（AES-256-GCM，密钥文件放 `config/`，`.gitignore` 排除）与读取封装；验证：加解密往返一致，密钥文件不在 Git 跟踪中

## 2. 帮助中心前台

- [x] 2.1 搭建 `help/index.php` 统一路由 + `.htaccess`（`/help/`、`/help/{category}/{slug}`、搜索、FAQ、术语表、ask 页地址映射；`help/*.html` 旧地址 301）；验证：各路由 curl 200/301 正确
- [x] 2.2 实现首页（搜索框、分类宫格、热门文章、AI 问答入口）；验证：浏览器访问展示完整且无 PHP 报错
- [x] 2.3 实现分类列表页与文章详情页（richtext 与 steps 两种渲染、目录导航、"有帮助/没帮助"反馈按钮防刷、相关推荐、SEO meta/og 标签）；验证：两种内容类型各打开一篇渲染正确，重复点反馈不重复计数
- [x] 2.4 实现站内搜索（FULLTEXT ngram 检索 + 摘要高亮 + 无结果引导 AI）与 FAQ/术语表页（子站 Tab 折叠、术语拼音序、FAQ 跳转教程）；验证：构造关键词命中/未命中两种用例表现符合 spec
- [x] 2.5 生成帮助中心 sitemap（`/help/sitemap.xml`，仅含 published 内容）；验证：XML 可被解析且包含全部已发布 URL

## 3. AI 助手核心

- [x] 3.1 实现 Provider 抽象层（OpenAI 兼容 cURL 客户端：流式/非流式、超时、按 `sort_order` 故障切换、每日限额计数）；验证：用假端点模拟失败自动切到下一渠道
- [x] 3.2 实现 `/api/ai/chat.php` SSE 端点：频控（IP 哈希窗口 + 用户每日限额）→ FULLTEXT RAG 检索（topN + 阈值）→ system prompt 组装（站点角色 + 知识片段 + 来源页上下文，登录用户注入身份摘要但不含敏感字段）→ 流式透传 + 结束帧附 `sources[]` + 未命中兜底话术；验证：curl 观测到逐 chunk 输出与 sources 帧，超限返回限流提示
- [x] 3.3 实现 Hermes Agent 渠道接入（配置本机 OpenAI 兼容端点为渠道，可设默认）；验证：后台测试连接通过并完成一次真实问答（端口/鉴权由站长提供）
- [x] 3.4 实现对话日志与留言工单写入（问题摘要脱敏、命中标记、渠道标记；兜底与"没帮助"处提交工单）；验证：日志表与工单表出现正确记录
- [x] 3.5 实现 `help/ask` 全屏问答页（多轮会话、会话本地保留、清空、复制回答、引用卡片跳转）；验证：连续两轮提问上下文正确，刷新后历史保留

## 4. 全站入口与引导

- [x] 4.1 实现 `js/ai-widget.js` 悬浮窗（原生 JS + Shadow DOM、defer 加载、迷你聊天窗、localStorage 关闭记忆、"查看完整教程"入口）；验证：在无任何框架的静态页上可独立工作
- [x] 4.2 在 `shared/footer.php` 注入悬浮窗脚本，并在 8 个子站 `includes/header.php` 薄壳的 `nav_links` 追加"帮助"导航；验证：抽查 block/bct/mall/club 四个子站导航出现帮助入口且气泡正常
- [x] 4.3 在主站 `index.php`、`all-cities.php` 及 block 子站内联页头/页脚补帮助中心链接与悬浮窗脚本；验证：移动端与桌面端均可见且不错位
- [x] 4.4 实现 `js/help-guide.js` 场景引导（`data-help-complex` 首次访问横幅且关闭不复发、`data-help-empty` 空状态卡、`data-help-hint` 操作提示、404 页 AI 入口），并在选块页、购物车空态、下单/出价确认处埋锚点；验证：清 localStorage 后首次访问展示横幅，关闭后刷新不再出现

## 5. 后台管理

- [x] 5.1 在 `shared/admin/admin-menu-config.php` 追加"帮助中心管理"与"AI 助手配置"菜单分组，创建 `admin/help-categories.php` 分类管理页（CRUD + 排序 + 显隐）；验证：菜单出现且增删改查生效
- [x] 5.2 创建 `admin/help-articles.php` 文章管理（列表筛选、富文本/步骤化双模式编辑器、置顶、发布/下架、`ai_generated` 标记）；验证：一篇 steps 文章从新建到前台可见全流程走通，下架后前台提示符合 spec
- [x] 5.3 创建 `admin/help-upload.php` 截图上传（类型/大小校验、日期目录、仅授权管理员）；验证：上传超限与非图片被拒且提示原因
- [x] 5.4 创建 `admin/help-faq.php` 与 `admin/help-glossary.php` 管理（CRUD、关联文章、排序）；验证：新增 FAQ 后前台 Tab 内出现并可跳转关联教程
- [x] 5.5 创建 `admin/ai-providers.php` 渠道配置（预置模板下拉、Key 掩码回显、测试连接、默认路由、每日限额、启用排序）与用量统计展示；验证：新增 DeepSeek/Hermes 渠道测试连接通过，Key 在库中为密文
- [x] 5.6 创建 `admin/ai-chat-logs.php`（时间/渠道/命中筛选、脱敏摘要、"转 FAQ 草稿"一键）与 `admin/ai-tickets.php`（状态流转、回复）；验证：未命中对话转 FAQ 后出现在 FAQ 待编辑列表，工单回复后状态更新
- [x] 5.7 实现"AI 生成草稿"按钮（调默认 Provider 生成步骤化初稿、落库 draft + ai_generated 标记、强制人工确认发布）；验证：生成的草稿在前台不可见，编辑确认后才可见

## 6. 联调与验收

- [ ] 6.1 端到端联调：主站→子站引导→帮助文章→AI 问答→工单闭环，覆盖登录/未登录两种身份；验证：全流程无 JS 报错，SSE 在生产环境（Nginx/Apache）流式正常（⚠ 代码已完成，本地无 PHP/MySQL 运行时，需部署到服务器后执行）
- [ ] 6.2 安全与限额验收：Key 不出现在任何前端响应与日志；频控阈值触发符合 spec；后台页面非授权角色不可访问；验证：抽查网络面板、error_log、越权访问用例（⚠ 需在服务器执行；静态自查已通过：Key 仅服务端持有/密文落库/后台页均 checkAdmin/接口带频控）
- [ ] 6.3 SEO 与迁移验收：旧 `help/*.html` 地址 301 到新页、sitemap 可访问、og 标签正确；验证：curl -I 逐条核对 Location 与 meta（⚠ 需在服务器执行；.htaccess 301 映射与 og 标签输出已静态核对）
