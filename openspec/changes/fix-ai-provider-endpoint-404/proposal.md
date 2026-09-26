## Why

在后台新增 AI 渠道（Hermes 本机智能体）后测试连接返回 404。排查发现现有 `AiProvider` 的端点拼接和错误反馈存在多处缺陷：预置模板端口/模型名与实际部署不符、用户填完整 `chat/completions` 路径会被二次拼接成错误 URL、测试失败时不显示实际请求的 URL 和服务端错误正文，导致 404 无法定位。

## What Changes

- 修正预置模板 `hermes`：端点 `http://127.0.0.1:8642/v1`、模型 `hermes-agent`（与服务器实际部署的 api-server 对齐），并在提示中说明可按实际端口修改
- `AiProvider::baseUrl()` 端点归一化增强：若用户填写了完整的 `/chat/completions` 结尾路径，先剥离再统一拼装，避免拼出 `.../chat/completions/v1/chat/completions` 这类 404 URL
- 请求失败时错误信息带上**实际请求的完整 URL**，HTTP >= 400 时附带响应正文片段（当前正文被丢弃，只剩 `HTTP 404` 一行）
- 后台「测试连接」结果中展示解析后的最终请求 URL，方便对照排障
- 无破坏性变更，OpenAI 兼容协议调用方式不变

## Capabilities

### New Capabilities

（无）

### Modified Capabilities

- `ai-assistant`：「多 Provider 大模型接入」需求的端点归一化规则与测试连接错误反馈要求变更（端点容错、错误信息含实际请求 URL 与响应正文）

## Impact

- `classes/AiProvider.php`：`baseUrl()` 归一化逻辑、`request()` 错误返回结构（`error` 文案增强，不改变返回字段）
- `admin/ai-providers.php`：`$presets['hermes']` 模板修正、测试结果显示 URL
- 运维提示：已在服务器上按旧模板（8787/hermes）建好的渠道需要编辑改为 8642/hermes-agent
