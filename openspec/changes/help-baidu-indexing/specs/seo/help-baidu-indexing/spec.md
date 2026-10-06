## Purpose

让 help 子站新发布的内容能被百度主动发现并收录，同时保证推送不消耗过量配额、不阻塞后台操作、在未完成站点验证时不影响既有流程。

## ADDED Requirements

### Requirement: 文章发布时触发主动推送

后台保存帮助文章时，MUST 在「新建且状态为已发布」或「状态从非已发布变更为已发布」两种情形下，将该文章的权威 URL 推送至百度。已处于已发布状态且仅修改内容的保存 MUST NOT 触发推送。

#### Scenario: 新建并发布
- **WHEN** 后台新建一篇文章且状态设为已发布并保存
- **THEN** `https://help.58.tl/article/{slug}` 被提交推送

#### Scenario: 从草稿转为发布
- **WHEN** 一篇草稿文章被改为已发布并保存
- **THEN** 该文章 URL 被提交推送

#### Scenario: 已发布文章的常规编辑
- **WHEN** 一篇已发布文章仅修改正文后保存
- **THEN** 不触发推送

### Requirement: 推送目标为权威域

推送的 URL MUST 使用 `https://help.58.tl/` 下的权威地址，MUST NOT 推送 `www.58.tl/help/` 下的等价地址。

#### Scenario: 目标地址正确
- **WHEN** 触发一次文章推送
- **THEN** 推送的 URL 形如 `https://help.58.tl/article/{slug}`

### Requirement: 聚合页在子内容新增后重推

FAQ 或术语新增/发布后，对应的聚合页 URL（`/faq`、`/glossary`）SHOULD 被重新推送一次，且 MUST 受同一节流规则约束。

#### Scenario: 新增 FAQ
- **WHEN** 后台新增一条已发布 FAQ
- **THEN** `https://help.58.tl/faq` 被提交推送（若在冷却期内则跳过）

#### Scenario: 新增术语
- **WHEN** 后台新增一个术语
- **THEN** `https://help.58.tl/glossary` 被提交推送（若在冷却期内则跳过）

### Requirement: 推送节流

同一 URL 在冷却期（默认 24 小时）内 MUST NOT 被重复推送；冷却期过后 MAY 再次推送。冷却期 SHOULD 可配置。

#### Scenario: 冷却期内重复触发
- **WHEN** 同一 URL 在 1 小时内被两次触发推送
- **THEN** 第二次被跳过并记录跳过原因

#### Scenario: 冷却期过后
- **WHEN** 同一 URL 距上次推送超过冷却期后再次触发
- **THEN** 正常推送

### Requirement: 推送失败不影响后台操作

推送 MUST 被异常隔离：网络超时、接口错误或数据库异常 MUST NOT 中断后台保存流程，MUST NOT 向用户抛出错误，且 SHOULD 记录日志。

#### Scenario: 接口不可达
- **WHEN** 百度接口超时或不可达时后台保存一篇已发布文章
- **THEN** 文章保存成功并返回成功提示，推送失败仅记日志

#### Scenario: 未配置 token
- **WHEN** `help.58.tl` 的 token 未配置或未启用
- **THEN** 保存流程正常完成，推送静默跳过且记录原因

### Requirement: 站点凭据可配置

推送凭据结构 MUST 支持 `help.58.tl` 作为独立站点项（token / site / enabled），其缺失时 MUST 表现为"未启用"而非回退到主站 token。

#### Scenario: 新增配置项
- **WHEN** 查看 SEO 配置示例文件
- **THEN** `sites` 中存在 `help.58.tl` 项

#### Scenario: 未启用时不误推
- **WHEN** `help.58.tl` 已配置但 `enabled=false`
- **THEN** help 的 URL 不被推送给其他站点的 token

### Requirement: 批量推送工具不含失效地址

命令行批量推送工具的默认入口列表 MUST NOT 包含已 301 或已下架的地址，SHOULD 包含 help 子站的入口地址。

#### Scenario: 默认入口清单
- **WHEN** 不带参数执行批量推送工具
- **THEN** 默认列表不含 `www.58.tl/help/*.html`，且含 `https://help.58.tl/`

### Requirement: 失效地址进入死链清单

文章被下架或其 slug 被修改时，原 URL SHOULD 被记入死链清单，并通过一个可访问的纯文本地址输出，供在百度平台提交。

#### Scenario: 文章下架
- **WHEN** 一篇已发布文章被改为非发布状态
- **THEN** 其原 URL 出现在死链清单输出中

#### Scenario: 修改 slug
- **WHEN** 一篇已发布文章的 slug 被修改
- **THEN** 旧 URL 进入死链清单，新 URL 触发一次推送
