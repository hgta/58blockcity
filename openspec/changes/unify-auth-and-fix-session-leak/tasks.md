# 统一认证层任务清单

## 前置检查

- [x] 全局搜索 `session_start()` 裸调用位置，确认没有其他未受保护的入口
- [x] 全局搜索 `$_SESSION['user_id']` 赋值点，确保只有 `handleLogin()` 写 session
- [x] 检查各子站是否有特殊的登录后跳转逻辑需要保留
- [x] 确认生产环境 session 存储方式（file/redis），确保 `session_regenerate_id(true)` 兼容

---

## Task 1 — 重构 `includes/auth.php` 为唯一认证源

**文件**：`includes/auth.php`

**操作**：
- 统一 `session_regenerate_id` 间隔为 30 分钟
- 统一 session 空闲过期时间为 1 小时
- 统一 cookie domain 为 `'.58.tl'` 常量
- 新增 `AUTH_COOKIE_DOMAIN` 常量
- 新增 `requireLogin()` 函数（`isLoggedIn()` + `session_regenerate_id` + redirect）
- 新增 `assertLogin()` 函数（`isLoggedIn()` + 无 redirect，返回 bool 用于 API）
- `handleLogin()` 增加 `session_regenerate_id(true)` + 记录 `login_ip`/`login_time`
- `createRememberMeToken()` 改为 `INSERT ... ON DUPLICATE KEY UPDATE`
- `attemptAutoLogin()` 增加 token 失效时删除过期 cookie

**验收**：
- 所有函数定义完整，无语法错误
- 常量 `AUTH_COOKIE_DOMAIN` 正确

---

## Task 2 — 各子站 auth.php 改为透明代理

**文件**：`bct/includes/auth.php`, `block/includes/auth.php`, `hufang/includes/auth.php`, `nft/includes/auth.php`

**操作**：
- 备份原文件内容
- 替换为单行：`<?php require_once __DIR__ . '/../../includes/auth.php';`

**验收**：
- 各子站访问正常，登录/登出功能正常
- 跨子站跳转 cookie 不冲突

---

## Task 3 — 修复 `mall/shop/create.php` 认证漏洞

**文件**：`mall/shop/create.php`

**操作**：
- 删除手动 `session_start()` + `isset($_SESSION['user_id'])` 检查（line 3-12）
- 添加 `require_once '../../includes/auth.php';`（放在 require database 之前）
- 添加 `requireLogin();` 调用

**验收**：
- 未登录用户访问 create.php 自动跳转登录页
- 已登录用户正常创建店铺，店主 user_id 正确

---

## Task 4 — 修复 `classes/User.php::login()` 走标准流程

**文件**：`classes/User.php`

**操作**：
- `login()` 方法中删除直接 `$_SESSION['user_id'] = ...` 等操作
- 改为调用 `handleLogin($user)`（需先引入 `includes/auth.php` 或提取 handleLogin 为全局函数）

**验收**：
- 登录后 session 包含 `login_ip`、`login_time`
- remember_me cookie 正常创建

---

## Task 5 — 全局搜索替换裸 session 检查

**操作**：
- 搜索所有 `isset($_SESSION['user_id'])` 直接判断的代码
- 评估哪些可以替换为 `isLoggedIn()` / `requireLogin()` / `assertLogin()`
- 对于 `header("Location: login")` 模式的，替换为 `requireLogin()`
- 对于仅判断而不跳转的，替换为 `isLoggedIn()`

**预期影响文件**（待确认）：
- `mall/product/create.php`
- `block/block/buy.php`
- `hufang/circles/create.php`

**验收**：
- 所有创建/写入操作的入口都经过 `requireLogin()` 保护

---

## Task 6 — 数据库 `remember_tokens` 添加唯一约束

**文件**：`init/db-init.sql` + 生产库迁移

**操作**：
- `init/db-init.sql` 添加 `ALTER TABLE remember_tokens ADD UNIQUE KEY uk_user_id (user_id);`
- 生产环境执行对应 SQL

**验收**：
- 同一 user_id 不会有多条 token 记录
- `INSERT ... ON DUPLICATE KEY UPDATE` 正常工作

---

## Task 7 — 全站回归测试

**操作**：
- 在 `block.58.tl` 注册新用户 → 登录 → 创建店铺，验证店主 ID 正确
- 在 `nft.58.tl` 登录 → 认领 NFT → 验证 owner 正确
- 在 `hufang.58.tl` 登录 → 发布房源 → 验证发布者正确
- 跨子站跳转（block → mall → nft），确认无需重新登录
- 手动过期 session（等待 1 小时），确认 remember_me 自动登录
- Cookie domain 验证：检查所有子站的 cookie 是否使用统一的 `.58.tl`

**验收**：
- 所有子站认证行为一致
- 无 session 串号
- 无 remember_me 冲突
