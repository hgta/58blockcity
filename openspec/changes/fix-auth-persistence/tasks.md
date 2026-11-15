# Tasks — fix-auth-persistence

> 部署硬约束：第 3.1 的 migration **必须先于代码**执行；索引变更对旧代码向后兼容，可安全回滚。

## 1. 止血（P0，先做，风险最低）

- [ ] 1.1 `api/city_geo.php` 移除会话依赖：删除 `session_start()` 与 `$_SESSION['city_geo']` 读写，仅保留文件缓存路径；验证定位功能与 `?nocache=1` 诊断仍正常
- [ ] 1.2 `includes/session.php` 新增 `auth_is_https()`（兼容 `X-Forwarded-Proto`）与 `auth_session_options()`（域名/路径/30 天/HttpOnly/Lax 的唯一口径）
- [ ] 1.3 将 `includes/auth.php`（checkLogin、checkAdminApi）、`auth/logout.php`、`includes/session.php` 共 4 处 `session_set_cookie_params` 改为调用 `auth_session_options()`；仓库内不再残留参数数组字面量
- [ ] 1.4 `attemptAutoLogin()` 失败分级：令牌不存在/已过期才清 Cookie；`PDOException` 保留 Cookie 并记录事件
- [ ] 1.5 离线断言：扫描全部 `session_start()` 调用点，确认均走统一参数函数；浏览器验证定位请求后 `PHPSESSID` 仍有具体过期时间

## 2. 会话寿命（P0）

- [ ] 2.1 `includes/session.php` 增加 `ini_set('session.gc_maxlifetime', AUTH_SESSION_TTL)`；支持 `AUTH_SESSION_DIR` 独立 `save_path`（自动 `mkdir 0700`，失败回退系统路径并记日志）
- [ ] 2.2 前台 `AUTH_IDLE_TIMEOUT` 改为 30 天；新增 `AUTH_ADMIN_IDLE_TIMEOUT = 7200`，`checkAdmin()` 与 `checkAdminApi()` 改用后台超时
- [ ] 2.3 `auth/login.php` 补 `date_default_timezone_set('Asia/Shanghai')`，与 `api/ai/*`、`help/_init.php` 口径一致
- [ ] 2.4 `docs/` 增加服务器侧核对清单：`session.gc_maxlifetime`、`session.save_path`、多站共用目录风险、`php -i` 复核命令

## 3. 多设备令牌（P1，含数据迁移）

- [ ] 3.1 新增 `init/migration-remember-tokens-multidevice.sql`：`DROP INDEX uk_user_id`（保留 `UNIQUE(token)`、`idx_token`），幂等可重复执行
- [ ] 3.2 `createRememberMeToken()` 改为插入新行（每设备一条）；同用户保留最近 10 条，登录时惰性清理过期行
- [ ] 3.3 `logout()` 校验仅删除当前 Cookie 对应的那一行，不影响其他设备
- [ ] 3.4 SQLite 兼容单测：多设备插入、按 token 查找、超量裁剪、过期清理

## 4. 时区与滑动续期（P1）

- [ ] 4.1 令牌过期判定改为 PHP 时间参数（`expires_at > ?`），续期改用 PHP 计算，移除 `DATE_ADD(NOW(), ...)`
- [ ] 4.2 `isLoggedIn()` 命中会话时实现滑动续期：剩余 < 20 天且当日未续期则更新令牌 + 重发 Cookie（`$_SESSION` 内记节流时间）
- [ ] 4.3 单测：跨时区（PHP +08 / MySQL UTC）边界、续期节流、续期后有效期正确

## 5. 观测与验证（P2）

- [ ] 5.1 新增 `auth_event($event, $reason, $uid)` 结构化日志（`auto_login_ok` / `auto_login_fail` / `remember_created` / `remember_refreshed` / `session_expired`）
- [ ] 5.2 浏览器验收：F12 确认 `PHPSESSID` 非 Session Cookie、关浏览器后仍登录、手机与电脑并存、跨子站（www/mall/block/hufang）不掉登录
- [ ] 5.3 线上观察 72 小时：`grep auth_event` 统计失败原因分布，输出结论并回写 design.md 的 Open Questions
