# 统一认证层设计方案

## 一、架构调整

### 1.1 认证层统一

```
Before (5份auth，互有差异):
┌──────────┐  ┌──────────┐  ┌──────────┐  ┌──────────┐  ┌──────────┐
│includes/ │  │  bct/    │  │ block/   │  │ hufang/  │  │  nft/    │
│auth.php  │  │includes/ │  │includes/ │  │includes/ │  │includes/ │
│          │  │auth.php  │  │auth.php  │  │auth.php  │  │auth.php  │
│sess: 24h │  │sess: 30m │  │sess: 30m │  │sess: 30m │  │sess: 30m │
│domain:动 │  │domain:'' │  │domain:'' │  │domain:'' │  │domain:'' │
│exp: 7d   │  │exp: 1h   │  │exp: 1h   │  │exp: 1h   │  │exp: 1h   │
└──────────┘  └──────────┘  └──────────┘  └──────────┘  └──────────┘

After (1份，统一参数):
┌────────────────────────────────────────────────┐
│              includes/auth.php                  │
│  session_regenerate_id: 30 min                 │
│  session 空闲过期: 1 hour                       │
│  remember_me: 30 days                          │
│  cookie domain: .58.tl (统一)                   │
│  session 安全: ip/user-agent 绑定 (可选)         │
└────────────────────┬───────────────────────────┘
                     │ require_once
         ┌───────────┼───────────┬───────────┐
         ▼           ▼           ▼           ▼
    ┌────────┐  ┌────────┐  ┌────────┐  ┌────────┐
    │ bct/   │  │ block/ │  │ hufang/│  │  nft/  │
    │includes│  │includes│  │includes│  │includes│
    │auth.php│  │auth.php│  │auth.php│  │auth.php│
    │(壳)    │  │(壳)    │  │(壳)    │  │(壳)    │
    └────────┘  └────────┘  └────────┘  └────────┘
```

### 1.2 子站 auth.php 改为透明代理

每个子站的 `auth.php` 只保留一行：

```php
<?php require_once __DIR__ . '/../../includes/auth.php';
```

这样各子站如果被硬编码引用，不会报 file not found，但实际逻辑全走统一版本。

## 二、Session 安全加固

### 2.1 登录时强制 regenerate

```php
function handleLogin($user) {
    session_regenerate_id(true);  // 销毁旧 session，防止固定攻击
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['login_time'] = time();
    $_SESSION['login_ip'] = $_SERVER['REMOTE_ADDR'] ?? '';
    // 可选: $_SESSION['user_agent_hash'] = md5($_SERVER['HTTP_USER_AGENT'] ?? '');
    createRememberMeToken($user['id']);
}
```

### 2.2 敏感操作二次校验

对于创建店铺、发布房源、认领区块、NFT挂售等操作，增加防护函数：

```php
function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: /auth/login.php");
        exit();
    }
}
```

替代各处重复的 `if (!isset($_SESSION['user_id'])) { ... }`。

### 2.3 统一 cookie domain

```php
define('AUTH_COOKIE_DOMAIN', '.58.tl');
```

所有 `setcookie()` 调用统一使用此常量。

## 三、数据库修复

### 3.1 `remember_tokens` 表约束

```sql
ALTER TABLE remember_tokens ADD UNIQUE KEY uk_user_id (user_id);
```

`createRememberMeToken()` 改为 `INSERT ... ON DUPLICATE KEY UPDATE`：

```php
function createRememberMeToken($userId) {
    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', strtotime('+30 days'));
    $stmt = $pdo->prepare("
        INSERT INTO remember_tokens (user_id, token, expires_at) 
        VALUES (:uid, :token, :expires)
        ON DUPLICATE KEY UPDATE token = :token2, expires_at = :expires2
    ");
    $stmt->execute([
        ':uid' => $userId, ':token' => $token, ':expires' => $expires,
        ':token2' => $token, ':expires2' => $expires,
    ]);
    setcookie('remember_me', $token, strtotime('+30 days'), '/', AUTH_COOKIE_DOMAIN, true, true);
}
```

## 四、`User::login()` 标准化

将 `classes/User.php` 的 `login()` 改为调用 `handleLogin()`：

```php
public function login($username, $password) {
    $stmt = $this->pdo->prepare("SELECT id, username, email, role, password FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password'])) {
        handleLogin($user);  // 统一入口，不再直接操作 $_SESSION
        return true;
    }
    return false;
}
```

## 五、文件变更清单

| 文件 | 操作 | 说明 |
|------|------|------|
| `includes/auth.php` | **重构** | 统一参数 + `requireLogin()` + ip绑定 |
| `bct/includes/auth.php` | 替换为壳 | `require_once` 到统一版 |
| `block/includes/auth.php` | 替换为壳 | 同上 |
| `hufang/includes/auth.php` | 替换为壳 | 同上 |
| `nft/includes/auth.php` | 替换为壳 | 同上 |
| `mall/shop/create.php` | 修复 | 改用 `requireLogin()` |
| `classes/User.php` | 修改 | `login()` 改用 `handleLogin()` |
| `init/db-init.sql` | 修改 | `remember_tokens` 加 UNIQUE KEY |
