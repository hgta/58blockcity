# Tasks — fix-auth-persistence

> 部署硬约束：第 3.1 的 migration **必须先于代码**执行；索引变更对旧代码向后兼容，可安全回滚。
>
> 实测前提（2026-11-15）：`session.gc_maxlifetime=1440`（24 分钟）、`session.save_path` 无值（落 `/tmp`，与全机共用）→ **GC 型掉线已确认**。
>
> **禁止改 php.ini**：宝塔的 php.ini 按 PHP 版本全局共享，改 `session.save_path` / `gc_maxlifetime` 会波及同版本下**所有**站点。已改为代码内按站点隔离。

## 0. 会话存储隔离（P0，已实现）

- [x] 0.1 `includes/session.php` 新增 `AUTH_SESSION_DIR`（默认 `sys_get_temp_dir() . '/58blockcity-sessions'`）与 `AUTH_SESSION_TTL`（30 天）；启动会话前 `ini_set('session.save_path')` + `ini_set('session.gc_maxlifetime')`，目录不存在则 `mkdir 0700`，不可写时回退系统默认并记日志
- [x] 0.2 目录选址依据：位于站点根之外（`sess_*` 不会被 Web 直接访问）；PHP 的 GC 只扫描 save_path 下以 `sess_` 开头的文件且**不递归子目录**，因此同机其他站点（`save_path=/tmp`）清理时不会误删本站会话
- [x] 0.3 离线自检 5/5 通过：save_path 已隔离、`gc_maxlifetime=2592000`、目录自动创建、会话文件实际落盘
- [ ] 0.4 部署后确认：`ls -ld /tmp/58blockcity-sessions`（应为 `drwx------`，属主为 PHP 运行用户）+ `ls -lt /tmp/58blockcity-sessions | head` 出现 `sess_*`；注意**切换目录会让在线用户掉一次登录**，需低峰期执行

## 1. 止血（P0，先做，风险最低）

- [x] 1.1 `api/city_geo.php` 移除会话依赖：删除 `session_start()` 与 `$_SESSION['city_geo']` 读写，仅保留文件缓存路径；验证定位功能与 `?nocache=1` 诊断仍正常
  （必须与 0.1 同批：否则该接口仍用 `/tmp` 作为 save_path，同一 PHPSESSID 会指向两个目录）
- [ ] 1.2 `includes/session.php` 新增 `auth_is_https()`（兼容 `X-Forwarded-Proto`）与 `auth_session_options()`（域名/路径/30 天/HttpOnly/Lax 的唯一口径）
- [ ] 1.3 将 `includes/auth.php`（checkLogin、checkAdminApi）、`auth/logout.php`、`includes/session.php` 共 4 处 `session_set_cookie_params` 改为调用 `auth_session_options()`；仓库内不再残留参数数组字面量
- [ ] 1.4 `attemptAutoLogin()` 失败分级：令牌不存在/已过期才清 Cookie；`PDOException` 保留 Cookie 并记录事件
- [ ] 1.5 离线断言：扫描全部 `session_start()` 调用点，确认均走统一参数函数；浏览器验证定位请求后 `PHPSESSID` 仍有具体过期时间

## 2. 会话寿命（P0）

- [x] 2.1 `includes/session.php` 增加 `ini_set('session.gc_maxlifetime', AUTH_SESSION_TTL)`；支持 `AUTH_SESSION_DIR` 独立 `save_path`（自动 `mkdir 0700`，失败回退系统路径并记日志）
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
