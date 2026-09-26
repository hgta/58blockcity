## MODIFIED Requirements

### Requirement: 多 Provider 大模型接入
系统 SHALL 支持配置多个 AI Provider 渠道，每个渠道包含：名称、OpenAI 兼容的 API 端点、API Key、默认模型、排序、启用状态；SHALL 预置常见渠道模板（DeepSeek、通义千问、Kimi、智谱 GLM、OpenAI、自定义兼容端点、本机 Hermes Agent），其中 Hermes 模板 SHALL 与服务器实际部署对齐（默认端点 `http://127.0.0.1:8642/v1`、模型 `hermes-agent`，提示语注明端口以实际部署为准）；调用失败时 SHALL 自动切换到下一个已启用渠道。API Key SHALL 仅存储于服务端且加密存储，任何前端响应 SHALL NOT 泄露 Key 或完整端点凭据。

端点归一化 SHALL 幂等容错：用户填写裸域名、`/v1` 结尾、或完整 `/chat/completions` 结尾路径，系统均 SHALL 归一化为正确的最终请求 URL，不得出现路径重复拼接。

调用失败时，错误信息 SHALL 包含实际请求的完整 URL；HTTP 状态码 >= 400 时 SHALL 附带响应正文片段（截断展示），用于后台排障。

当渠道为 Hermes 且支持会话隔离头时，系统 SHALL 携带 `X-Hermes-Session-Key`（前台助手统一使用共享助手记忆区标识），并在 system prompt 中包含长期记忆禁写指令，防止前台用户对话污染助手记忆。

#### Scenario: 按路由调用默认渠道
- **WHEN** AI 问答请求触发且默认渠道可用
- **THEN** 系统通过该渠道的 OpenAI 兼容端点完成对话补全并返回结果

#### Scenario: 渠道故障自动切换
- **WHEN** 当前路由渠道调用超时或返回错误
- **THEN** 系统自动改用排序中的下一个已启用渠道重试，用户侧无需感知失败

#### Scenario: 无可用渠道
- **WHEN** 所有已启用渠道均不可用
- **THEN** 用户收到友好降级提示（引导查看帮助文章或留言），并记录错误日志

#### Scenario: 端点填写完整路径容错
- **WHEN** 管理员将渠道端点填写为完整的 `http://127.0.0.1:8642/v1/chat/completions`
- **THEN** 系统归一化后实际请求 `http://127.0.0.1:8642/v1/chat/completions`，不出现路径重复拼接

#### Scenario: 测试失败错误信息可定位
- **WHEN** 后台「测试连接」返回 404 或其他 HTTP 错误
- **THEN** 测试结果中展示实际请求的完整 URL、HTTP 状态码及响应正文片段，管理员可直接判断是端口、路径还是模型配置错误

#### Scenario: Hermes 渠道记忆隔离
- **WHEN** 前台小帮通过 Hermes 渠道响应用户对话
- **THEN** 请求携带共享助手记忆区标识，且对话指令中包含记忆禁写令，用户对话内容不写入助手长期记忆

## ADDED Requirements

### Requirement: 小帮人设可配置
前台小帮的 system prompt 人设主文案 SHALL 存储于系统配置（`system_settings`），后台可编辑、保存后立即生效；配置为空时系统 SHALL 回退内置默认文案。RAG 知识注入、页面上下文、用户信息等结构性拼接 SHALL 保持在代码层，不受人设配置影响。

#### Scenario: 无配置时平滑升级
- **WHEN** 系统升级后管理员尚未配置自定义人设
- **THEN** 小帮行为与升级前内置文案完全一致

#### Scenario: 配置生效
- **WHEN** 管理员保存了自定义人设文案
- **THEN** 前台小帮下一次对话即采用新人设，知识库注入与频控行为不变
