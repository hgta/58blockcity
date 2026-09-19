## Why

主站及 10 个子站（区块交易、BCT 市场、NFT、商城、互访圈、拍卖、任务广场等）概念门槛高、帮助内容匮乏：`help/` 仅 10 个静态 HTML 且几乎无站内引用，新用户"来了不会用就走"。需要一套图文结合的帮助中心子站 + 全站场景化引导 + AI 自助问答来降低流失；同时生产服务器已部署 Hermes Agent（OpenAI 兼容 API Server），具备引入大模型能力的现成条件。

## What Changes

- **新建帮助中心子站**（`help.58.tl`，动态 PHP）：分类/文章/FAQ/术语表/搜索，文章支持步骤化图文（截图上传）；现有 10 个静态帮助页迁移并入。
- **全站帮助入口注入**：
  - 8 个共享模板子站（`shared/header.php` 薄壳）导航统一追加"帮助"按钮；
  - 主站首页、block 子站内联页头部/页脚追加帮助中心链接；
  - 全站右下角悬浮 AI 助手气泡（一份共享 JS，处处生效）；
  - 关键场景引导：复杂页面首次访问横幅、空状态引导卡、下单/出价等关键操作旁的"先看指南"提示。
- **AI 自助问答**：
  - 新增 `/api/ai/chat.php` SSE 流式聊天接口，基于帮助文章的 RAG 检索（MySQL FULLTEXT 起步）组装上下文，回答尾部附引用文章链接；
  - 帮助中心新增 `/ask` 全屏问答页；
  - 未命中知识库时兜底引导留言工单。
- **后台管理扩展**（总控后台）：
  - 帮助内容管理：文章/分类/FAQ/术语表 CRUD + 截图上传 + AI 生成教程草稿；
  - AI Provider 配置：多渠道（DeepSeek/通义/Kimi/智谱/OpenAI/自定义 OpenAI 兼容端点/本机 Hermes Agent），含启用、排序、失败切换、每日调用限额；API Key 加密存储（存 `system_settings`）；
  - AI 对话记录查看（脱敏）、未解决问题标注、留言工单处理。
- **数据层**：新增 `help_categories`、`help_articles`、`help_faq`、`help_glossary`、`ai_chat_logs`、`ai_feedback_tickets` 表及迁移 SQL。

不涉及对现有交易/支付/账户行为的修改，无 BREAKING 变更。

## Capabilities

### New Capabilities
- `help-center`: 帮助中心子站的公开浏览能力——分类、图文文章、FAQ、术语表、站内搜索，以及全站各子站的帮助入口与场景化引导。
- `ai-assistant`: AI 自助问答能力——OpenAI 兼容多 Provider 抽象（含本机 Hermes Agent）、RAG 知识检索、SSE 流式对话、悬浮窗与全屏问答入口、频控与限额、对话日志。
- `admin-help-ai`: 总控后台对帮助内容（文章/分类/FAQ/术语表）、AI Provider 配置、对话记录与留言工单的管理能力。

### Modified Capabilities
<!-- 无：现有 specs（bct/、popularity/）的规格级行为不受影响 -->

## Impact

- **新增目录/文件**：`help/`（静态页改造为动态子站）、`api/ai/`（聊天接口）、`js/ai-widget.js`（全站悬浮窗）、`classes/HelpArticle.php` 等、`init/` 迁移 SQL、admin 新页面。
- **修改文件**：各子站 `includes/header.php` 薄壳（追加 nav_link）、`shared/admin/admin-menu-config.php`（追加菜单）、主站 `index.php` 与 block 子站关键内联页（页脚/引导位）、`shared/footer.php`。
- **外部依赖**：生产服务器 Hermes Agent API Server（OpenAI 兼容端点，作为默认或备用 Provider）；至少一家直连大模型 API Key（用户提供）。
- **安全与成本**：API Key 仅存服务端并加密；接口需 IP/用户双维频控与每日 token 限额；AI 生成的价格/规则类内容需人工审核后发布。
