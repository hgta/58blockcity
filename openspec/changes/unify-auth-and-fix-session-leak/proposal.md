# 统一认证层 & 修复 Session 串号漏洞

## 背景与问题

### 🔴 生产事故：新建店铺串号

用户"拂光小铺"（ID 368）注册后创建店铺，店主却被串到另一个用户（viphgta@qq.com）。经排查发现根本原因是：

1. **`mall/shop/create.php` 裸读 `$_SESSION['user_id']`**，没有经过 `isLoggedIn()` / `attemptAutoLogin()` 校验，也没有 `session_regenerate_id()`
2. **5 份 auth.php 副本不同步**，跨子站 cookie domain、session 过期策略不一致
3. **Cookie domain 混乱**导致跨子站 `remember_me` cookie 冲突

### 系统性缺陷

| # | 问题 | 文件 | 风险 |
|---|------|------|:---:|
| 1 | 5 份 auth.php 参数各不同 | `includes/`, `bct/`, `block/`, `hufang/`, `nft/` | 跨子站认证不一致 |
| 2 | Cookie domain 混乱 | `includes/auth.php` 动态计算 vs 子站硬编码 | remember_me 串号 |
| 3 | `create.php` 无认证校验 | `mall/shop/create.php:4-12` | **本次事故直接原因** |
| 4 | `User::login()` 不走标准流程 | `classes/User.php:26` | 不创建 remember token |
| 5 | `remember_tokens` 无唯一约束 | `init/db-init.sql:549` | 同用户多条 token |
| 6 | Session 安全加固缺失 | 各 auth.php | 无 ip/user-agent 绑定 |

### 具体差异对比

| 配置项 | `includes/auth.php` | `bct/block/hufang/nft/auth.php` |
|--------|--------------------|--------------------------------|
| session_regenerate_id 间隔 | 24 小时 | 30 分钟 |
| session 空闲过期 | 7 天 | 1 小时 |
| cookie domain | 动态计算（从 HTTP_HOST） | 空字符串 `''`（部分硬编码 `.58.tl`） |
| session 过期行为 | 清 session，保留 remember_me | 直接 `logout()` |
| handleLogin/checkLogin | 有 `attemptAutoLogin` | 有（但参数不同） |

## 目标

1. **消除 session 串号漏洞**：确保所有敏感操作都经过认证校验
2. **统一认证层**：消除 5 份 auth.php 副本差异，单一认证源
3. **加强 session 安全**：加入 session 固定攻击防护、用户绑定校验
4. **修复数据库约束**：`remember_tokens` 加唯一索引

## 范围

### 纳入范围

| 文件 | 操作 |
|------|------|
| `includes/auth.php` | **重构为唯一认证源**，统一 session 策略 |
| `bct/includes/auth.php` | **删除**，改为 `require '../../includes/auth.php'` |
| `block/includes/auth.php` | **删除**，改为 `require '../../includes/auth.php'` |
| `hufang/includes/auth.php` | **删除**，改为 `require '../../includes/auth.php'` |
| `nft/includes/auth.php` | **删除**，改为 `require '../../includes/auth.php'` |
| `mall/shop/create.php` | **修复**：改用 `require '../../includes/auth.php'` + `checkLogin()` |
| `classes/User.php` | `login()` 方法改用 `handleLogin()` 标准流程 |
| `init/db-init.sql` | `remember_tokens` 添加 `UNIQUE KEY (user_id)` |
| 全局搜索所有 `session_start() + isset($_SESSION['user_id'])` 裸读 | 统一替换 |

### 不纳入范围

- 各子站的前端 UI 改动
- OAuth 第三方登录集成
- 双因素认证
- 密码策略改造

## 成功标准

| 指标 | 当前 | 目标 |
|------|------|------|
| auth.php 副本数 | 5 份，参数不一致 | 1 份，所有子站共用 |
| cookie domain | 混用（动态/空/硬编码） | 统一 `.58.tl` |
| create.php 认证 | 裸读 session，无校验 | `checkLogin()` 标准流程 |
| `remember_tokens` 约束 | 无 user_id 唯一 | UNIQUE KEY |
| session 固定攻击防护 | 无 | `session_regenerate_id(true)` on login |

## 风险与假设

- **风险**：统一 auth.php 后，如果某子站有特殊逻辑被覆盖，可能导致登录异常。需要逐子站回归测试
- **风险**：删除子站 auth.php 后，如果有其他文件通过相对路径引用这些文件，需要搜索确认
- **假设**：所有子站共享同一套 users 表和 session 存储
