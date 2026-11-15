# session-persistence

## ADDED Requirements

### Requirement: 会话 Cookie 生命周期不得被降低

任何端点启动会话时，MUST 使用与登录入口一致的 Cookie 参数（域名 `.58.tl`、路径 `/`、有效期 30 天、`HttpOnly`、`SameSite=Lax`）。系统 MUST NOT 存在以 `lifetime=0`、`SameSite=None` 或与统一入口不同的参数启动会话的代码路径。

#### Scenario: 定位接口调用后会话 Cookie 仍为 30 天
- **WHEN** 已登录用户首次访问某子域，页面触发 `/api/city_geo.php` 定位请求
- **THEN** 浏览器中的 `PHPSESSID` 仍带有具体过期时间（30 天后），不出现 Expires 为「Session」的会话级 Cookie
- **AND** 关闭浏览器后重新打开，用户仍处于登录状态

#### Scenario: 全部会话入口参数一致
- **WHEN** 静态检查所有 `session_start()` 调用点
- **THEN** 每个调用前都使用了 `includes/session.php` 提供的统一参数函数
- **AND** 仓库内不再存在重复定义的 `session_set_cookie_params` 数组字面量

### Requirement: 多设备令牌并存

`remember_tokens` MUST 支持同一用户存在多条令牌（一设备一条）。任一设备登录 MUST NOT 使其他设备的令牌失效。

#### Scenario: 手机与电脑同时保持登录
- **WHEN** 用户先在电脑勾选「30 天内自动登录」登录，再在手机登录
- **THEN** 两台设备各自持有一条有效令牌
- **AND** 两台设备访问受保护页面时均无需重新输入密码

#### Scenario: 单设备退出不影响其他设备
- **WHEN** 用户在电脑点击退出
- **THEN** 仅删除电脑对应的那条令牌
- **AND** 手机仍保持登录

#### Scenario: 令牌数量受控
- **WHEN** 同一用户累计登录超过 10 台设备
- **THEN** 系统保留最近 10 条令牌并删除最旧的
- **AND** 已过期令牌在登录时被清理

### Requirement: 自动登录失败的分级处置

系统 MUST 区分「令牌确实无效」与「系统暂时错误」。仅在令牌不存在或已过期时清除 `remember_me` Cookie；数据库异常 MUST 保留 Cookie 以便下次重试。

#### Scenario: 令牌不存在时清除 Cookie
- **WHEN** 浏览器携带的 `remember_me` 在数据库中查不到对应记录
- **THEN** 清除该 Cookie，并视为未登录

#### Scenario: 数据库异常时保留 Cookie
- **WHEN** 自动登录过程中数据库连接失败或查询抛错
- **THEN** 保留 `remember_me` Cookie 不清除
- **AND** 记录一条可识别的错误日志
- **AND** 下次请求若数据库恢复，仍可自动登录成功

### Requirement: 空闲超时前后台分离

前台会话空闲超时 MUST 与「记住我」有效期一致（30 天）；后台管理端 MUST 使用不超过 2 小时的独立空闲超时。

#### Scenario: 前台长期未访问仍可自动登录
- **WHEN** 用户勾选记住我后 29 天未访问，第 30 天打开站点
- **THEN** 系统用 Cookie 自动恢复登录，不跳转登录页

#### Scenario: 后台长时间未操作需重新验证
- **WHEN** 管理员登录后台后连续 2 小时无操作再访问后台页面
- **THEN** 系统要求重新登录
- **AND** 前台登录态不受影响

### Requirement: 滑动续期

活跃用户访问时，系统 MUST 在令牌剩余有效期不足 20 天时续期，且同一自然日最多续期一次。

#### Scenario: 活跃用户在第 25 天访问
- **WHEN** 用户在令牌签发后第 25 天访问任意页面
- **THEN** 令牌过期时间与 `remember_me` Cookie 均延长至 30 天后

#### Scenario: 同日重复访问不重复续期
- **WHEN** 用户在已续期的同一天再次访问
- **THEN** 不重复写入数据库，也不重复重发 Cookie

### Requirement: 令牌过期时间时区一致

令牌过期时间的写入与比较 MUST 使用同一时区基准（统一由 PHP 生成时间参数），不得出现「PHP 写入、MySQL `NOW()` 比较」的混用。

#### Scenario: PHP 与 MySQL 时区不同时令牌仍有效
- **WHEN** PHP 时区为 `Asia/Shanghai`、MySQL 会话时区为 UTC，用户在登录后第 1 天访问
- **THEN** 令牌被判定为有效并成功自动登录
- **AND** 令牌不会因时区偏移被提前判为过期

### Requirement: 登录态事件可观测

系统 MUST 记录可区分的自动登录结果事件，至少包含：自动登录成功、失败原因（无 Cookie / 令牌不存在 / 已过期 / 数据库错误）、令牌创建、令牌续期、会话过期。

#### Scenario: 自动登录失败可定位原因
- **WHEN** 一次自动登录失败发生
- **THEN** 日志中存在一条包含事件类型与失败原因的单行记录
- **AND** 运维可通过关键字统计各失败原因的分布
