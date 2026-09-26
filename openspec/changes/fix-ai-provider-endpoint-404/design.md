## Context

后台 AI 渠道配置页（`admin/ai-providers.php`）新增 Hermes 本机智能体渠道后，点击「测试」返回 404。Hermes 官方参考代码使用的端点是 `http://127.0.0.1:8642/v1/chat/completions`、模型 `hermes-agent`，而现有代码存在三处缺陷叠加导致 404 且无法排障：

1. **预置模板错误**：`$presets['hermes']` 写死 `http://127.0.0.1:8787/v1` + 模型 `hermes`，与实际部署（8642 / hermes-agent）不符。
2. **端点归一化不足**：`AiProvider::baseUrl()`（classes/AiProvider.php:49）仅做"末尾补 `/v1`"，若用户参照参考代码把端点填成完整 `http://127.0.0.1:8642/v1/chat/completions`，会被拼成 `.../chat/completions/v1/chat/completions`，必然 404。
3. **错误反馈缺失**：非流式请求 HTTP >= 400 时，响应正文进入 `$answer` 但 `errBuf` 为空（仅流式分支填充），错误提示只剩 `HTTP 404` 一行；且不展示实际请求的 URL，无法判断是端点、端口还是路径拼错。

约束：调用协议（OpenAI 兼容 `/chat/completions`）、`request()` 返回结构（ok/answer/error 三字段）、流式回调机制均保持不变，前台问答与故障切换逻辑不受影响。

## Goals / Non-Goals

**Goals:**
- 端点容错：域名、`/v1`、完整 `chat/completions` 路径三种填法都能归一化到正确的最终 URL
- 预置模板与服务器实际部署对齐（8642 / hermes-agent），提示文案说明可改端口
- 测试失败时错误信息可自证：包含实际请求 URL + HTTP 状态 + 响应正文片段（截断）
- 后台「测试连接」结果直接展示解析后的最终 URL，无需 SSH 抓包

**Non-Goals:**
- 不引入渠道类型字段/协议适配层（仍仅支持 OpenAI 兼容协议）
- 不改动加密存储、故障切换、限额计数逻辑
- 不做 Hermes 部署侧的任何变更（端口以用户实际部署为准，模板只是默认值）

## Decisions

### D1: 端点归一化采用"先剥离、再补全"的幂等处理

`baseUrl()` 改为三步：
1. `rtrim('/')` 去尾斜杠
2. 若以 `/chat/completions` 结尾则剥离该后缀（消除完整路径填法）
3. 若不以 `/v\d+` 结尾则追加 `/v1`（保留原有域名填法兼容）

- **备选方案**：改用 URL 解析后按 path 重组——过度设计，现有字符串处理已覆盖全部场景；正则实现简单且可预测。
- **理由**：幂等（重复归一化结果不变），且对已正确填写 `/v1` 的存量渠道零影响。

### D2: 错误信息拼接 URL + 响应正文，不改返回结构

`request()` 失败分支的 `error` 文案改为包含完整请求 URL；非流式模式 HTTP >= 400 时从 `$answer`（响应正文）截取片段拼入 `errBuf` 逻辑。返回值仍是 `['ok','answer','error']` 三字段，调用方（`testConnection`、`chatWithFailover`）无需修改即可受益。

- **备选方案**：返回结构新增 `url` 字段——会波及所有调用方，收益仅是格式化自由度，不值得。
- **注意**：`chatWithFailover` 会把各渠道 error 拼接展示，URL 文案需控制在合理长度（正文片段沿用现有 200 字符截断）。

### D3: 预置模板对齐实际部署 + 提示语说明可变

`$presets['hermes']` 改为 `http://127.0.0.1:8642/v1` + `hermes-agent`，hint 注明"端口以 api-server 实际配置为准"。不做多套 Hermes 模板（不同部署端口差异靠 hint 引导手改，模板只是起点）。

### D4: 测试结果显示最终 URL

`testConnection()` 属于静态方法无渠道对象上下文，在 `ai-providers.php` 测试分支中直接复用 `baseUrl()` 的归一化结果展示。为避免在页面层重复实现归一化，将 `baseUrl()` 提升为 `public`（新增 `public function endpointUrl()` 返回最终 URL 更语义化）。

## Risks / Trade-offs

- [存量渠道已按错误模板（8787/hermes）创建] → 代码修复不会自动改正数据库记录；需运维在后台编辑该渠道改为 8642/hermes-agent，部署说明中明确提示
- [错误信息含完整 URL 可能暴露内网地址（127.0.0.1:8642）] → 仅在后台测试/管理员可见的 error 中出现，前台用户侧 `chatWithFailover` 失败时展示的是渠道名前缀 + 错误摘要，保持现状不放大暴露面
- [剥离 `/chat/completions` 后缀可能误伤非常规端点（如真实路径就叫这个）] → 概率极低，OpenAI 兼容端点最终请求路径就是 `/chat/completions`，剥离后拼装结果与原意一致

## Migration Plan

1. 部署 `classes/AiProvider.php` 与 `admin/ai-providers.php`
2. 后台编辑现有 Hermes 渠道：端点改 `http://127.0.0.1:8642/v1`，模型改 `hermes-agent`，保存后点「测试」验证
3. 回滚：直接还原两个文件即可，无数据库结构变更；已修正的渠道记录保留无害

## Open Questions

（无——Hermes 参考代码已明确 8642 端口与 hermes-agent 模型名；若用户实际部署端口不同，按 hint 提示手改即可。）
