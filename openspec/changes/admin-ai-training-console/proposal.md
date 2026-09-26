## Why

Hermes 渠道已打通，但后台缺少管理员与智能体直接对话的入口，导致两个问题：其一，小帮（前台 AI 助手）的 system prompt 硬编码在 `api/ai/chat.php` 中，调整人设必须发版；其二，训练成果（好回答、平台知识纠正、人设微调）无处沉淀，知识库闭环零件（未命中转 FAQ、AI 文章草稿）没有生产入口。需要一个后台训练台，让管理员通过对话持续调教智能体并将成果沉淀为平台资产。

## What Changes

- 新增后台「AI 训练台」（`admin/ai-console.php`）：管理员与 Hermes 智能体流式对话，支持 Hermes 原生会话（列表/新建/fork/历史回看）
- 新增 `classes/HermesClient.php`：PHP 服务端直连 Hermes 原生 API（`/api/sessions/*`），浏览器不接触 Hermes 与 key
- 训练台沉淀动作：每条 AI 回答旁提供「存为 FAQ」「存为文章草稿」「写入记忆（admin 试验区）」「发布到小帮（assistant 记忆区）」
- 小帮 system prompt 从硬编码迁移到 `system_settings`，后台可编辑、可保存，未配置时回退现有默认值
- 前台小帮调用 Hermes 渠道时携带 `X-Hermes-Session-Key: web:58tl:assistant` 并在 prompt 中加入记忆禁写令；训练台使用 `web:58tl:admin` 独立记忆区（记忆分区策略）
- 新增「未命中问题看板」：聚合 `ai_chat_logs` 中 `status=unmatched` 的问题，支持一键带入训练台回答并沉淀
- 训练台对话不受用户频控与渠道每日限额约束（运营行为）
- `/v1/runs` 长任务、审批流、浏览器控制等 Hermes 高级能力**不在本期范围**（留二期）

## Capabilities

### New Capabilities

- `admin-ai-console`: 管理员 AI 训练台——与 Hermes 原生会话流式对话、会话管理（fork/历史）、沉淀动作（FAQ/文章/记忆/发布）、小帮人设编辑、未命中问题看板

### Modified Capabilities

- `ai-assistant`: 「多 Provider 大模型接入」与 RAG 问答相关行为变更——system prompt 可配置化（settings 覆盖默认）、Hermes 渠道携带会话隔离 key 与记忆禁写令

## Impact

- 新文件：`classes/HermesClient.php`、`admin/ai-console.php`、`api/ai/console.php`（训练台 SSE/JSON 代理）、`init/migration-admin-ai-console.sql`（settings 初始项 + 日志标记字段）
- 修改：`api/ai/chat.php`（prompt 从 settings 读取、Hermes 渠道 header 注入）、`shared/admin/` 后台导航（新增训练台入口）
- 依赖既有：`AiProvider`（渠道解密）、`SecureCrypto`、`help_faq`/`help_articles`（沉淀承接）、`ai_chat_logs`（未命中聚合）
- 运维：训练台需要 Hermes 常驻（本机 8642）；训练台在 Hermes 不可用时明确降级提示，不影响前台小帮（前台仍走渠道故障切换）
