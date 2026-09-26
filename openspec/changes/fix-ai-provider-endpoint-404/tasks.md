## 1. AiProvider 端点归一化与错误反馈

- [x] 1.1 `classes/AiProvider.php`：`baseUrl()` 增强为幂等归一化——`rtrim('/')` 后先剥离结尾的 `/chat/completions` 后缀，再按需补全 `/v1`；保持存量 `/v1` 填法行为不变
- [x] 1.2 `classes/AiProvider.php`：新增 `public function endpointUrl()` 返回归一化后的完整请求 URL（`baseUrl() . '/chat/completions'`）
- [x] 1.3 `classes/AiProvider.php`：`request()` 失败分支的错误信息附带实际请求 URL；非流式 HTTP >= 400 时从响应正文截取片段（沿用 200 字符截断）拼入错误信息

## 2. 后台渠道配置页

- [x] 2.1 `admin/ai-providers.php`：修正 `$presets['hermes']` 为 `http://127.0.0.1:8642/v1` / `hermes-agent`，hint 注明端口以 api-server 实际部署为准
- [x] 2.2 `admin/ai-providers.php`：「测试连接」结果中展示归一化后的最终请求 URL（成功与失败均显示），失败时完整透出 error 信息

## 3. 验证

- [x] 3.1 本地验证端点归一化三种填法（裸域名 / `/v1` 结尾 / 完整 `chat/completions` 结尾）均得到正确 URL
- [ ] 3.2 部署到服务器后编辑现有 Hermes 渠道（8642 / hermes-agent），后台点「测试」确认连接成功
