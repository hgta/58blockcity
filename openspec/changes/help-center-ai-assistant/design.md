## Context

- 站点为纯 PHP + MySQL（PDO），无框架、无前端构建链；8 个子站通过各目录 `includes/header.php` 薄壳复用 `shared/header.php`（`$site_config['nav_links']` 驱动导航）；主站 `index.php`、`all-cities.php`、block 子站为内联页头；统一页脚 `shared/footer.php`。
- 统一后台在 `admin/`，菜单由 `shared/admin/admin-menu-config.php` 定义；已有 `system_settings` KV 表（含 module/category/sort）。
- 前端：jQuery 3.7.1 + 内联 CSS/JS，`city/js/` 提供共享资源范例。
- 生产服务器已部署 Hermes Agent（OpenAI 兼容 API Server，`hermes api-server`）。
- `help/` 现有 10 个静态 HTML（glossary/block_city_intro/block_buy_guide 等）。

## Goals / Non-Goals

**Goals:**
- 帮助中心动态化并全站可触达；AI 问答一条 PHP 代理链路贯穿（Provider 抽象 → RAG → SSE）。
- 与现有共享模板、后台、`system_settings` 机制平滑集成，不引入构建链、不引入框架。
- Provider 抽象同时覆盖直连大模型与本机 Hermes Agent，支持故障切换。

**Non-Goals:**
- 不做 embedding 向量库（一期用 MySQL FULLTEXT，预留接口二期升级）。
- 不做用户长记忆、跨会话个性化（仅会话内上下文 + 页面来源上下文）。
- 不改造 Hermes Agent 本体；仅消费其 OpenAI 兼容端点。
- 不做帮助内容的多语言版本。

## Decisions

### D1: 帮助子站实现形态 —— 动态 PHP 页面 + URL 重写
- `help/index.php` 为统一路由入口，配合 `.htaccess` 将 `/help/{category}/{slug}` 风格地址映射到 `?route=...`（服务器现状为 Apache + PHP，已有 `404.php` 等先例）。
- 文章正文分两种存储模式：`richtext`（富文本 HTML）与 `steps`（JSON 数组：`[{title, text, image}]`），渲染器统一处理，天然支持"图文步骤条"。
- 旧静态页：`.htaccess` 对 `help/*.html` 逐条 301 到新文章地址。
- 替代方案（否决）：继续纯静态生成——无法承接搜索/反馈/AI 检索与后台管理闭环。

### D2: 数据模型
```sql
help_categories(id, parent_id, name, slug, icon, sort_order, is_visible)
help_articles(id, category_id, title, slug, summary, content_type, content_richtext,
              content_steps JSON, cover_image, view_count, helpful_count,
              is_pinned, status[draft|published|archived], created_by, created_at, updated_at)
  UNIQUE(slug), FULLTEXT(title, summary, content_richtext)  -- 一期检索
help_faq(id, category_id, question, answer, related_article_id, sort_order, status)
help_glossary(id, term, pinyin, definition, related_article_id, sort_order)
help_article_feedback(id, article_id, helpful TINYINT, visitor_hash, created_at)  -- 防刷
ai_providers(id, name, endpoint, api_key_cipher, model, sort_order, is_enabled,
             is_default, daily_limit, failed_at)   -- Key AES-256-GCM 加密
ai_chat_logs(id, user_id, visitor_ip_hash, source_page, question_digest,
             matched TINYINT, provider_id, tokens_used, created_at)
ai_feedback_tickets(id, user_id, contact, question, status[open|done],
                    admin_reply, created_at, handled_at)
```
- 不复用 `system_settings` 存 Provider 列表（需要多行结构化数据，KV 不合适）；`system_settings` 仅存全局开关类配置（频控阈值、每日总量、是否启用助手）。
- 迁移脚本放 `init/`，遵循现有编号 SQL 惯例。

### D3: AI 链路 —— 单端点 PHP 代理 + OpenAI 兼容抽象
```
前端(悬浮窗/问答页) → POST /api/ai/chat.php (SSE)
  → 频控检查（内存/表计数）
  → RAG: FULLTEXT 检索 help_articles/help_faq（阈值 + topN）
  → 组装 messages: system(站点角色+知识片段) + 会话历史 + 来源页上下文
  → AiProviderFactory: 按 sort_order 取启用渠道，cURL 兼容端点
  → 逐 chunk 透传 SSE；结束帧附 sources[]（文章链接）
```
- 统一走 OpenAI `/chat/completions` 协议（`stream: true`）——DeepSeek/通义/Kimi/智谱/OpenAI 与 Hermes Agent 端点全部兼容，一套 cURL 客户端覆盖。
- Hermes Agent 作为普通渠道配置（endpoint=`http://127.0.0.1:PORT/v1`），可设为默认；失败自动降级到直连大模型渠道。
- 替代方案（否决）：PHP 侧接各厂商原生 SDK——引入 Composer 依赖且各 SDK 流式实现不一，维护成本高。

### D4: 悬浮窗分发 —— 单文件共享 JS + `defer`
- 新建 `js/ai-widget.js`（无依赖、原生 JS、Shadow DOM 隔离样式），一行 `<script defer>` 挂载。
- 注入点：`shared/footer.php` 尾部统一输出（覆盖 8 个共享模板子站 + 已 include footer 的页面）；主站 `index.php`、`all-cities.php` 与 block 子站内联页单独补一行。
- 前端绝不持有 Key；气泡组件 localStorage 记忆"已关闭"状态与会话窗口状态。

### D5: 场景化引导 —— 显式锚点约定
- 约定数据属性：`data-help-hint="slug"`（元素旁帮助提示）、`data-help-empty="slug"`（空状态引导）、`data-help-complex="slug"`（复杂页横幅，localStorage 记忆关闭）。
- `ai-widget.js` 之外的 `js/help-guide.js` 扫描锚点渲染引导 UI，各子站只需在模板关键位置埋锚点，不动布局逻辑。

### D6: 后台 —— 遵循现有总控后台模式
- 新增 `admin/help-articles.php`、`admin/help-categories.php`、`admin/help-faq.php`、`admin/help-glossary.php`、`admin/ai-providers.php`、`admin/ai-chat-logs.php`、`admin/ai-tickets.php`，遵循 `shared/admin/admin-page-shell.php` 框架。
- 菜单在 `admin-menu-config.php` 追加两个分组（帮助中心管理 / AI 助手），icon 用现有 Font Awesome。
- 富文本：引入轻量所见即所得（如 TinyMCE CDN 单文件），截图上传走独立 `admin/help-upload.php`（校验类型/大小，按日期目录落盘 `uploads/help/`）。
- "AI 生成草稿"：后端调默认 Provider，prompt 注入目标子站说明与步骤结构要求；草稿落库为 `draft` + 来源标记 `ai_generated`，发布强制走人工编辑确认。

### D7: 频控与安全
- 未登录：IP 哈希 + 时间窗计数（`ai_chat_logs` 聚合查询，窗口 60s/次）；登录：用户维度每日 N 次（`system_settings` 可配）。
- Key 加密：AES-256-GCM，密钥来自 `config/` 下独立密钥文件（不入库不入 Git）。
- SSE 输出过滤：Provider 返回中若出现 Key/端点字符串则脱敏；system prompt 注入防护——用户输入只作为 user role 内容，不拼接进 system。

## Risks / Trade-offs

- [MySQL FULLTEXT 中文分词弱，RAG 命中率有限] → 检索时附加 ngram 分词（MySQL 8 内建 ngram parser）+ FAQ 精确匹配优先；预留 embedding 接口二期升级。
- [SSE 长连接与共享主机/反向代理超时] → 每 chunk 输出即 flush；加心跳注释行；Nginx 配置参考 `docs/` 现有 conf。
- [Provider Key 泄露风险] → 全链路仅服务端持有；日志脱敏；上传/配置页仅限超级管理员角色。
- [AI 回答涉及价格/规则误导用户] → system prompt 限定"以帮助文章为准，无依据时明确说不知道"；涉及交易规则的内容强制引用来源；AI 草稿须人工审核。
- [悬浮窗拖慢子站页面] → `defer` + 非阻塞加载，组件初始仅一个气泡节点。
- [旧静态页 SEO 权重] → 301 跳转保权重，sitemap 同步提交。

## Migration Plan

1. 部署顺序：DB 迁移 SQL → help 动态页与 API → 后台页面与菜单 → 子站锚点与 footer 注入 → `.htaccess` 301 → 观察日志。
2. 灰度：`system_settings.ai_assistant_enabled` 总开关，可先只开帮助中心、后开悬浮窗。
3. 回滚：整个变更新增为主，回滚只需还原被修改的共享模板文件 + 关闭开关；新表留存无害。

## Open Questions

- Hermes Agent API Server 的生产端口与鉴权方式（实施时由站长提供，填入渠道配置即可，不影响架构）。
- 站点是否启用 HTTPS（影响 SSE 与 fetch 流式的兼容写法，实现时确认，已按兼容方案设计）。
- 二期 embedding 检索的模型选型（待一期 FULLTEXT 效果数据出来再定）。
