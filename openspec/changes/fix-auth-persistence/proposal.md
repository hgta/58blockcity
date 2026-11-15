# fix-auth-persistence

## Why

用户勾选「30 天内自动登录」后仍频繁被要求重新登录：登录后不久掉线、多设备之间互相顶掉、关浏览器后需重输密码。代码审查定位到 4 处可指认缺陷（会话 cookie 被定位接口降级、单设备令牌互踢、自动登录失败即删 cookie、空闲 1 小时销毁会话）与 2 处环境级风险（PHP `session.gc_maxlifetime` 默认 24 分钟、PHP 与 MySQL 时区错位），「记住我」链路在多处断裂。这会直接造成用户流失，优先级高于功能性迭代。

## What Changes

- **止血 · 会话 cookie 不再被降级**：`api/city_geo.php` 目前以 `lifetime=0 / SameSite=None / secure=true` 启动会话，任一定位请求都会把 30 天的 `PHPSESSID` 覆盖为「关浏览器即失效」的会话级 cookie。改为定位接口不触碰会话（仅用文件缓存）。
- **止血 · 失败不再删 cookie**：自动登录失败时仅在「令牌确实不存在或已过期」时清除 cookie；数据库异常保留 cookie 待下次重试，避免一次抖动变成永久登出。
- **会话参数单一来源**：现有 4 个文件、5 处重复的 `session_set_cookie_params` 收敛为 `includes/session.php` 中的一个函数，杜绝再次漂移；`secure` 判断兼容 `X-Forwarded-Proto`（Cloudflare 回源场景）。
- **会话寿命与 cookie 声明对齐**：显式设置 `session.gc_maxlifetime`，并支持站点独立 `session.save_path`，避免同机其他站点按默认 24 分钟 GC 误清本站会话文件。
- **空闲超时策略分离**：前台由 1 小时改为跟随「记住我」有效期（30 天）；后台保留独立短超时（2 小时）。
- **BREAKING（数据模型）**：`remember_tokens` 去掉 `uk_user_id` 唯一约束，改为「一设备一令牌」，多设备不再互踢；增加过期令牌与超量设备的清理。
- **时区一致性**：令牌过期时间改为「PHP 写入 + PHP 比较」，消除与 `classes/Auction.php` 已记录过的同类错位。
- **滑动续期**：活跃用户命中会话时，按每日节流刷新 `remember_me`，避免活跃满 30 天反而掉线。
- **可观测性**：自动登录结果按可区分原因结构化记录（无 cookie / 令牌不存在 / 已过期 / 数据库错误）。

## Capabilities

### New Capabilities

- `session-persistence`: 登录态在 30 天内的持久化契约——会话 cookie 生命周期、多设备令牌、空闲超时、滑动续期、时区一致性、失败处置与可观测性。

### Modified Capabilities

（无。现有 `city-data-sync` / `city-prices` / `self-managed-consumption` / `user-holdings` 的规格行为不变。）

## Impact

- **代码**：`api/city_geo.php`、`includes/session.php`、`includes/auth.php`、`auth/logout.php`、`auth/login.php`
- **数据**：`remember_tokens` 索引变更，新增 `init/migration-remember-tokens-multidevice.sql`（幂等，不删业务数据）
- **部署**：migration 必须先于代码上线；服务器需确认独立 session 目录可写
- **安全**：会话有效期延长，用「后台独立短超时 + HttpOnly + SameSite=Lax + 单设备令牌可吊销」控制风险；不引入第三方依赖
- **运维**：新增结构化登录态日志，可用 `grep auth_event` 统计掉线原因分布
