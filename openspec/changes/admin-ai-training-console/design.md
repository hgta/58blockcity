## Context

前台 AI 助手「小帮」走 `AiProvider` 抽象（OpenAI 兼容 `/chat/completions`，多渠道故障切换），system prompt 硬编码于 `api/ai/chat.php`，RAG 注入 `help_articles`/`help_faq`，日志落 `ai_chat_logs`（含 `matched`/`unmatched` 状态）。服务器本机部署 Hermes Agent（127.0.0.1:8642，Python aiohttp 网关，`API_SERVER_KEY` 鉴权），其能力清单（GET /v1/capabilities 实测）显示：

- 原生会话 API：`/api/sessions`（创建/列表/详情/fork/消息历史/会话内对话含流式）
- 记忆机制：跨会话持久（`~/.hermes/memories/`，约 2200 字符容量），**无外部写入 HTTP 路由**（官方设计：Agent 在对话中自行调用记忆工具写入）；支持 `X-Hermes-Session-Key` 头按 key 隔离记忆（为多用户前端设计）
- 多用户隔离：`X-Hermes-Session-Key`（记忆空间）+ `X-Hermes-Session-Id`（会话连续性）
- 长任务 `/v1/runs`、审批、浏览器控制等本期不使用

既有约束：渠道 key 经 `SecureCrypto` AES-256-GCM 加密存储；`chatWithFailover` 在部分输出后不切换渠道；后台统一走 `checkAdmin()` 鉴权与 `shared/admin/admin-header.php` 模板。

## Goals / Non-Goals

**Goals:**
- 管理员在后台与 Hermes 流式对话，用原生会话（含 fork 实验）管理调教过程
- 训练成果三类沉淀：知识（FAQ/文章草稿）、记忆（admin 区试验 → assistant 区发布）、人设（prompt 编辑）
- 小帮 prompt 可配置化 + Hermes 记忆分区（assistant/admin），前台防记忆污染
- 未命中问题进入「回答 → 沉淀」闭环，盘活 `ai_chat_logs` 数据

**Non-Goals:**
- 不做 `/v1/runs` 长任务/审批/steer（PHP-FPM 长连接是坑，二期 AJAX 轮询模式再做）
- 不做训练对话全量落库（靠 Hermes 会话历史回看，避免双写；仅沉淀动作与未命中看板用库）
- 不做 PHP 直写 Hermes 记忆文件（方案三：容量上限 2200/1375 字符、格式风险、权限扩大攻击面，已否决）
- 不改 `AiProvider` 抽象与前台频控逻辑
- 不做 prompt 多版本 A/B 对比（仅"可编辑+默认回退"，版本历史留二期）

## Decisions

### D1: 训练台直连 Hermes 原生 API（新 `HermesClient`），不复用 `AiProvider`

两个角色的定位不同：前台小帮面向用户、渠道可切换（Hermes 挂了切 DeepSeek），必须保持 OpenAI 兼容协议的渠道无关性；训练台的调教对象**就是这台 Hermes**，原生会话/fork/历史是核心价值。
- 备选：训练台也走 `AiProvider` → 只能用 chat/completions，多轮自己传 messages，fork/历史全废，且"训练 DeepSeek 上的小帮"目前不是诉求。
- `HermesClient` 与 `AiProvider` 共享渠道行（从 `ai_providers` 取 `preset='hermes'` 的端点与 key，复用 `SecureCrypto` 解密），只复用数据不复用调用层。
- 训练台在 Hermes 不可用时降级为明确提示（「训练台依赖本机 Hermes，当前不可达」），不影响前台。

### D2: 记忆分区策略——assistant/admin 双 key + 前台禁写令

```
web:58tl:assistant  ← 前台小帮所有用户共享（"小帮人格"记忆，受管资产）
web:58tl:admin      ← 训练台试验区（管理员随便折腾）
```
- 训练流程：admin 区试验 → 满意后「发布到小帮」（PHP 将内容包装为祈使句指令以 assistant key 发给 Hermes，Hermes 自行调用记忆工具写入，遵守其整理逻辑与容量淘汰）。
- 前台防污染：小帮 system prompt 追加禁写令（"不要写入或修改长期记忆"）；用户个人信息不进共享记忆。
- 备选：前台不带 key（全局共享）→ 用户对话可污染小帮人格，否决；前台 per-user key → 训练成果对前台不可见且 2200 字符被 N 个用户瓜分，否决。
- 降级特性：故障切换到非 Hermes 渠道时记忆（小脑）缺席，但知识库（大脑）与人设（prompt）都在渠道无关层——降级不降智。

### D3: 知识/记忆双载体分工（"大脑/小脑"模型）

Hermes 记忆容量仅约 2200 字符，只适合人设微调级内容；事实性知识必须走平台侧 RAG 知识库（容量无限、渠道无关、可版本化）。沉淀按钮按内容类型分流：FAQ/文章草稿 → 知识库；人设校准/关键偏好 → 记忆区。训练成果的主体（约八成）预期是知识沉淀。

### D4: 小帮 system prompt 配置化

- `system_settings` 新增 `ai_assistant_system_prompt`；`api/ai/chat.php` 启动时读取，空值回退到现有硬编码文案（保证平滑升级与可回滚）。
- 编辑入口放训练台（「小帮人设」编辑卡片），保存即生效，并在编辑页展示当前生效 prompt 的来源（自定义/默认）。
- RAG 知识注入、页面上下文注入、用户信息注入的**拼接逻辑保持在代码中**，settings 只覆盖人设主文案——避免管理员误删注入结构导致前台故障。

### D5: 训练台 UI 结构与代理层

```
admin/ai-console.php（页面）
 ├─ 左栏：Hermes 会话列表（新建/切换/fork/删除）
 ├─ 主区：对话区（SSE 流式）
 │    模式：[admin 实验]（默认，X-Hermes-Session-Key: admin 区）
 │    每条 AI 回答旁沉淀按钮组
 ├─ 卡片：小帮人设编辑（settings）
 └─ 卡片：未命中问题看板（unmatched 聚合，点击带入对话区）

api/ai/console.php（服务端代理，checkAdmin）
 ├─ POST sessions / messages / fork / delete → 转发 Hermes /api/sessions*
 ├─ POST chat/stream → SSE 透传（key 由服务端注入）
 ├─ POST distill → 沉淀动作（写 help_faq / help_articles 草稿 / 记忆写入 / 发布）
 └─ GET  unmatched → 未命中聚合（GROUP BY 问题摘要）
```
浏览器永不接触 `API_SERVER_KEY`；代理对 Hermes 侧错误做统一 JSON 错误包装。

### D6: 沉淀动作的落点

| 动作 | 落点 | 复用 |
|------|------|------|
| 存为 FAQ | `help_faq`（source='ai_draft'，status='draft'） | 已有字段 |
| 存为文章草稿 | `help_articles`（is_ai_generated=1，status='draft'） | 已有字段 |
| 写入记忆（试验） | Hermes admin 区（祈使句包装） | D2 |
| 发布到小帮 | Hermes assistant 区（祈使句包装）+ 可选同步存 FAQ | D2 |

沉淀后均跳转/提示到对应后台编辑页（`help-faq.php` / `help-articles.php` 已有），不在训练台内重复建设编辑器。

### D7: 训练台不受用户频控与渠道限额约束

训练是运营行为：代理层仅做 `checkAdmin()` + 基本输入校验，不复用 `api/ai/chat.php` 的三层频控，也不计入 `ai_providers.daily_limit`（Hermes 原生会话调用本身不经过 `AiProvider::touch` 计数）。代价可接受：管理员人数极少且可信。

## Risks / Trade-offs

- [Hermes 原生 API 参数结构未实测（POST /api/sessions 的 system prompt 注入方式等）] → 实现首个任务时先做一次 curl 探测脚本摸清参数，探测结论直接指导 `HermesClient` 接口设计；如会话创建不支持初始 system prompt，则回退为「首条消息注入人设」方案
- [祈使句记忆写入依赖 Hermes 自觉执行，无成功回执] → 写入后自动发一条验证问题（如"刚才要求记住的内容是什么"）并展示验证结果，失败可见
- [2200 字符容量满后 Hermes 自行淘汰旧记忆，可能淘汰掉已发布内容] → 发布动作保留对话记录可追溯；接受"记忆是易失的小脑"这一模型，重要内容一律引导走知识沉淀
- [prompt 配置化后管理员误写坏 prompt 影响前台] → 保存时做基本校验（非空、长度上限）；空值自动回退默认文案；编辑页常驻"恢复默认"按钮
- [训练台与前台共用 Hermes 实例，长对话可能挤占资源] → 本机部署、单管理员场景，一期接受；二期如需可在代理层加简单并发限制
- [unmatched 看板聚合格式] → `question` 是截断文本（200 字符），按精确 GROUP BY 会碎片化 → 聚合按"近30天 + 精确问题 + 计数倒序"，一期不做语义归并

## Migration Plan

1. 部署顺序：SQL（settings 初始项 + `ai_chat_logs` 无需变更）→ `HermesClient` → 代理 → 前台 `chat.php` prompt 配置化 → 后台页面 + 导航入口
2. `ai_assistant_system_prompt` 初始为空 → 前台行为与现状完全一致（回退默认文案），零风险上线
3. 记忆分区无需迁移（新机制，首次使用即生效）
4. 回滚：删除后台入口即可停用训练台；prompt 配置置空即回滚前台变更

## Open Questions

（无阻塞项。任务 1.1 探测已由服务器实测完成，结论如下。）

## 探测结论（2026-09-26 服务器实测，任务 1.1 产出）

- 会话列表：`GET /api/sessions` → `{"object":"list","data":[{id,title,preview,message_count,last_active,…}]}`
- 创建会话：`POST /api/sessions` 接受 `{title, system_prompt}`（`has_system_prompt` 置位确认生效），返回 `201` + `{"object":"hermes.session","session":{id,…}}` —— **id 在 `.session.id` 路径**
- `X-Hermes-Session-Key` 头创建/对话均正常，分区隔离机制可用
- **记忆写入标准（重要）**：Hermes 拒绝无用途说明的裸内容写入（"不符合记忆存储标准"，且用户消息不能以"系统指令"提升权限）；给出用途与"跨会话稳定生效"声明后可写入，或建议写入 `blockcity-58tl-assistant` 技能。已据此调整祈使句包装（带用途声明），并在自动验证中检测"已写入/已保存/DONE"
- 跨会话验证诚实（未记住会如实回答），验证机制可靠
- 前端解析：`HermesClient`/console 页面 JS 已做多形态字段兼容，与实测结构吻合
