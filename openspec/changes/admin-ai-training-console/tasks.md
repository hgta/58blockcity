## 1. 前置探测与基础设施

- [x] 1.1 服务器探测 Hermes 原生会话 API 参数结构（POST /api/sessions 请求体、X-Hermes-Session-Key/Id 头行为、fork 与 messages 返回格式），产出参数速查记录；若会话创建不支持初始 system prompt，确定「首条消息注入人设」回退方案（结论：支持 system_prompt 字段，无需回退；记忆写入需带用途声明，详见 design.md 探测结论）
- [x] 1.2 `init/migration-admin-ai-console.sql`：`system_settings` 新增 `ai_assistant_system_prompt`（初始为空，回退默认）；无需其他表结构变更
- [x] 1.3 `classes/HermesClient.php`：封装 Hermes 原生 API（会话 CRUD/fork/历史/会话内流式对话），从 `ai_providers` 取 `preset='hermes'` 渠道端点与 key（SecureCrypto 解密），服务端注入 `X-Hermes-Session-Key`（admin/assistant 分区），统一错误包装（不可达/鉴权失败/超时）

## 2. 前台小帮配置化（独立可先行上线）

- [x] 2.1 `api/ai/chat.php`：system prompt 主文案改为读 `ai_assistant_system_prompt`，空值回退现有硬编码文案；拼接逻辑（RAG 注入/页面上下文/用户信息）保持代码层不动
- [x] 2.2 `classes/AiProvider.php`：渠道为 Hermes 时请求携带 `X-Hermes-Session-Key: web:58tl:assistant`；小帮 prompt 追加记忆禁写令（仅 Hermes 渠道时追加，避免影响其他渠道）

## 3. 训练台服务端代理

- [x] 3.1 `api/ai/console.php`：管理员鉴权（checkAdmin）+ 输入校验；会话管理端点（列表/新建/删除/fork/历史消息）转发 HermesClient
- [x] 3.2 `api/ai/console.php`：会话内流式对话端点（SSE 透传，admin 记忆区）；Hermes 不可达时返回明确 JSON 错误
- [x] 3.3 `api/ai/console.php`：沉淀动作端点——存为 FAQ（help_faq 草稿 source='ai_draft'）、存为文章草稿（help_articles draft + is_ai_generated）、写入记忆（祈使句包装 + 写后自动验证问题并返回验证结果）、发布到小帮（assistant 区写入 + 验证）

## 4. 训练台后台页面

- [x] 4.1 `admin/ai-console.php` 骨架：会话列表（新建/切换/删除/fork）+ 流式对话区（复用后台模板样式），接入代理端点
- [x] 4.2 每条 AI 回答旁沉淀按钮组（存为FAQ/存为文章草稿/写入记忆/发布到小帮），动作后跳转或提示到对应编辑页
- [x] 4.3 「小帮人设」编辑卡片：读/存 `ai_assistant_system_prompt`，展示当前生效来源（自定义/默认），提供「恢复默认」；保存时校验非空与长度上限
- [x] 4.4 「未命中问题看板」卡片：聚合 `ai_chat_logs`（status='unmatched'，近30天，按问题计数倒序），支持点击带入对话区
- [x] 4.5 `shared/admin/` 后台导航新增「AI 训练台」入口

## 5. 验证与收尾

- [ ] 5.1 服务器部署后端到端验证：训练对话流式输出、fork 实验、四类沉淀动作落库/落记忆、写入记忆验证问题通过
- [ ] 5.2 前台回归：小帮人设未配置时行为与现状一致；配置后新文案生效；Hermes 渠道带 session key；切换到备用渠道时无 session key 也不报错
- [ ] 5.3 未命中闭环验证：从未命中看板带入问题 → 训练回答 → 沉淀 FAQ 发布 → 前台同类问题命中知识库
