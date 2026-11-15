# Design — fix-auth-persistence

## Context

现状（全部为代码事实，行号基于当前主干）：

| 事实 | 位置 | 后果 |
|---|---|---|
| 定位接口以 `lifetime=0`、`SameSite=None`、`secure=true` 启动会话 | `api/city_geo.php:227-237` | 每次定位 AJAX 都把 30 天 `PHPSESSID` 覆盖成会话级 cookie |
| 该接口被三个脚本以 `credentials:'include'` 调用，且 localStorage 缓存按源隔离 | `city/city.js:8,57`、`assets/js/main.js:346`、`hufang/assets/js/main.js:336` | 十个子域各自触发，用户逛一圈后 cookie 基本必被降级 |
| `remember_tokens` 有 `uk_user_id` 唯一键，登录用 `ON DUPLICATE KEY UPDATE token` | `init/db-init.sql:1148-1153`、`includes/auth.php:148-160` | 一个用户只有一条令牌，手机/电脑/后台互相顶掉 |
| 自动登录任何失败（含 `PDOException`）都清除 cookie | `includes/auth.php:77-85` | 一次抖动变成永久登出 |
| 空闲超时 3600 秒，注释却写「7 天」 | `includes/session.php:29-31`、`includes/auth.php:88-101` | 受保护页面 1 小时不动即销毁会话 |
| 令牌 `expires_at` 由 PHP `date()` 写入、MySQL `NOW()` 比较 | `includes/auth.php:146,47,63` | 与 `classes/Auction.php:33-42` 记录过的时区事故同型 |
| 5 处重复的 `session_set_cookie_params` | `includes/session.php:34`、`includes/auth.php:113,268`、`auth/logout.php:38`、`api/city_geo.php:228` | 参数漂移（已实际发生） |
| 无任何 `session.gc_maxlifetime` / `save_path` 覆盖 | 全站 grep 无命中 | 默认 1440 秒 GC，cookie 声明与服务器实际寿命不一致 |

约束：原生 PHP 无框架；多子站共享 `.58.tl` cookie；生产环境为宝塔 + Nginx（部分镜像前置 Cloudflare，`$_SERVER['HTTPS']` 可能缺失）；不能引入 Redis 等新组件作为前置条件。

## Goals / Non-Goals

**Goals**

- 勾选「30 天内自动登录」后，关闭浏览器、跨子站、多设备并存均保持登录
- 掉线原因可区分、可统计，后续不再靠猜
- 后台管理端的安全性不因前台延长而下降

**Non-Goals**

- 不改用 Redis / 数据库 session 处理器（作为后续可选优化记录在 Open Questions）
- 不做「退出所有设备」UI、不做设备指纹、不做二次验证
- 不改动子站 `WebSite.name` 等 SEO 相关逻辑
- 不调整登录/注册/微信登录的认证方式本身

## Decisions

### D1 定位接口彻底不碰 session

`api/city_geo.php` 的 `$_SESSION['city_geo']` 只是纯性能缓存，已有同等的文件缓存实现（`geo_cache_read/write`）。移除会话依赖后：

- 功能无损（文件缓存命中即返回，未命中查高德）
- 从根上消除「任何端点都能改写 `PHPSESSID`」的可能

**备选**：把该接口的 cookie 参数改成与全站一致 —— 否决。它仍会在每次响应重发 `Set-Cookie`，只要有一处参数写错就会复发；且定位接口本无会话需求。

### D2 会话参数单一来源

在 `includes/session.php` 新增 `auth_session_options()`（返回参数数组）与 `auth_is_https()`，所有 `session_set_cookie_params` 调用点改为调用前者。

`auth_is_https()` 判定：`$_SERVER['HTTPS'] === 'on'` 或 `HTTP_X_FORWARDED_PROTO === 'https'`（兼容 Cloudflare / Nginx 反代）。

**备选**：保持各处独立 —— 否决，现状已证明会漂移（5 处里 1 处写成 `lifetime=0`）。

### D3 会话寿命：显式 gc_maxlifetime + 独立 save_path

- `ini_set('session.gc_maxlifetime', AUTH_SESSION_TTL)`，`AUTH_SESSION_TTL = 86400 * 30`
- `session.save_path` 支持配置为站点独立目录（常量 `AUTH_SESSION_DIR`，缺省时回退系统默认）：目录不存在则 `mkdir 0700`，创建失败只记录日志并回退，不阻断请求

**为什么必须配独立目录**：`gc_maxlifetime` 只影响本站触发的 GC；同机其他站点若共用 `/tmp`，会按它们的默认 1440 秒清掉整个目录，本站设置再大也无效。

**备选**：只改 php.ini —— 否决，需要在服务器手动维护且多站共用目录问题仍在。

### D4 空闲超时分离

- 前台 `AUTH_IDLE_TIMEOUT = 86400 * 30`（跟随记住我有效期）
- 新增 `AUTH_ADMIN_IDLE_TIMEOUT = 7200`，`checkAdmin()` / `checkAdminApi()` 使用

`isSessionExpired()` 增加可传入超时的参数，缺省用前台值；后台调用显式传后台值。

### D5 多设备令牌

- `remember_tokens` 去掉 `uk_user_id`，保留 `UNIQUE(token)`
- 登录改为**插入新行**（每设备一条）；同一用户保留最近 10 条，超量删除最旧
- 惰性清理：登录时顺带删除该用户已过期行（无 cron 依赖，与项目「惰性推进」风格一致，参考 `classes/Auction.php::tick()`）
- `logout()` 只删除当前 cookie 对应的那一行（现有实现按 token 删除，语义已正确，补充测试覆盖）

**备选**：保留单令牌 + 设备指纹区分 —— 否决，设备指纹本身不可靠，且仍会导致「同一浏览器换 UA 就掉线」。

### D6 失败处置分级

`attemptAutoLogin()` 三种结果分别处理：

| 情况 | 处置 |
|---|---|
| 令牌查询无结果 / `expires_at` 已过 | 清除 cookie（确实无效） |
| `PDOException` / 表不可用 | **保留 cookie**，记录 `auth_event`，下次请求重试 |
| 令牌有效 | 建立会话 + 滑动续期 |

### D7 时区一致性

`expires_at` 一律 PHP 写入、PHP 比较：查询改为 `expires_at > ?` 绑定 `date('Y-m-d H:i:s')`，续期用 PHP 计算时间，不再出现 `DATE_ADD(NOW(), ...)`。

理由与 `bct-order-expiry` 的既有决策一致（`design.md:58-61`）：比「每个入口都记得 `SET time_zone`」更可靠。同时 `auth/login.php` 补 `date_default_timezone_set('Asia/Shanghai')`，与 `api/ai/*`、`help/_init.php` 保持一致。

### D8 滑动续期（每日节流）

`isLoggedIn()` 命中会话且存在 `remember_me` 时：若 cookie 剩余有效期 < 20 天且 `$_SESSION['remember_refreshed_at']` 不是今天，则更新令牌过期时间 + 重发 cookie + 记录节流时间。

**备选**：每次请求都续期 —— 否决，写放大且无收益。

### D9 可观测性

新增 `auth_event($event, $reason, $userId = 0)`，以 `auth_event event=... reason=... uid=...` 单行写入 error_log。事件：`auto_login_ok`、`auto_login_fail`、`remember_created`、`remember_refreshed`、`session_expired`。

用途：部署后用 `grep auth_event` 统计失败原因分布，判断是「令牌型掉线」还是「GC 型掉线」是否已消除。

## Risks / Trade-offs

- [会话有效期延长 → 共享设备被冒用风险上升] → 后台保留 2 小时短超时；令牌可吊销（logout 删行）；后续可加「退出所有设备」
- [去掉 `uk_user_id` → 令牌行增长] → 每用户上限 10 条 + 登录时清过期行
- [独立 session 目录写权限问题] → 创建失败自动回退系统路径并记日志，不影响可用性
- [多设备令牌对旧代码兼容性] → 旧代码的 `ON DUPLICATE KEY` 在无 `uk_user_id` 时退化为普通插入，仍能登录；回滚安全
- [滑动续期写放大] → 每日最多一次

## Migration Plan

1. **先跑** `init/migration-remember-tokens-multidevice.sql`：`DROP INDEX uk_user_id`（保留 `UNIQUE(token)` 与 `idx_token`），幂等；不删除任何用户数据
2. 部署代码
3. 服务器确认：独立 session 目录可写、`session.gc_maxlifetime` 实际生效（`php -i` 复核）
4. 观察 72 小时 `auth_event` 分布
5. **回滚**：代码回退即可（索引变更对旧代码向后兼容）

## Open Questions

### 已由实测解决（2026-11-15，生产 `php -i`）

```text
session.gc_maxlifetime => 1440 => 1440      # 24 分钟
session.gc_probability => 1 => 1            # 概率式 GC（divisor 默认 100）
session.save_path      => no value          # 回落到 sys_get_temp_dir()，即 /tmp
```

**结论：`GC 型掉线` 确认成立，且是当前主因。**

- 会话文件 24 分钟未写入即成为 GC 候选；cookie 声明 30 天，服务器只给 24 分钟，两者严重不一致
- `save_path` 无值意味着落到 `/tmp`，与同机所有站点共用同一目录：**任一站点按其自身 `gc_maxlifetime`（多为默认 1440）触发 GC，会连带删除本站会话文件**
- 这解释了「勾选 30 天仍频繁重登」：session 被清后能否无感恢复，完全取决于 `remember_me`，而该链路本身还有互踢与「失败即删 cookie」两处缺陷（D5/D6）

**影响优先级调整**：服务器侧配置（独立 `save_path` + 足够大的 `gc_maxlifetime`）收益最大且零代码，应作为第一步；代码侧 `api/city_geo.php` 降级 cookie（D1）与失败分级（D6）紧随其后。

剩余待确认：

- 是否进一步引入 Redis session（当前几百并发下 MySQL + 文件足够，暂不引入）
- 后台超时 2 小时是否合适 —— 可按实际编辑时长调整
