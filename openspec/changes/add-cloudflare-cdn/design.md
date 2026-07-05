# Cloudflare CDN — 设计文档

## 1. 架构变化

```
迁移前：用户 → 源站（单点）
迁移后：用户 → [CF边缘节点] → 源站
                 ├ 缓存 /assets/*
                 ├ SSL 终结（CF边缘证书）
                 └ 传递 X-Forwarded-Proto, CF-Connecting-IP
```

## 2. Nginx 源站改动

### 2.1 真实 IP 还原

在 `docs/nginx-rewrite.conf` 的 http 块中添加：

```nginx
# Cloudflare IP 段
set_real_ip_from 173.245.48.0/20;
set_real_ip_from 103.21.244.0/22;
set_real_ip_from 103.22.200.0/22;
set_real_ip_from 103.31.4.0/22;
set_real_ip_from 141.101.64.0/18;
set_real_ip_from 108.162.192.0/18;
set_real_ip_from 190.93.240.0/20;
set_real_ip_from 188.114.96.0/20;
set_real_ip_from 197.234.240.0/22;
set_real_ip_from 198.41.128.0/17;
set_real_ip_from 162.158.0.0/15;
set_real_ip_from 104.16.0.0/13;
set_real_ip_from 104.24.0.0/14;
set_real_ip_from 172.64.0.0/13;
set_real_ip_from 131.0.72.0/22;
set_real_ip_from 2400:cb00::/32;
set_real_ip_from 2606:4700::/32;
set_real_ip_from 2803:f800::/32;
set_real_ip_from 2405:b500::/32;
set_real_ip_from 2405:8100::/32;
set_real_ip_from 2a06:98c0::/29;
set_real_ip_from 2c0f:f248::/32;
real_ip_header CF-Connecting-IP;
```

### 2.2 HTTPS 传递

在 `location ~ \.php$` 块中添加：

```nginx
fastcgi_param HTTPS $http_x_forwarded_proto;
```

### 2.3 完整 Nginx 变更

在 `docs/nginx-rewrite.conf` 文件头部添加 http 块配置，在所有 `location ~ \.php$` 块中添加 HTTPS param。

## 3. PHP 代码改动

### 3.1 getClientIp() 修复

当前函数：
```php
function getClientIp() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) { … }
    elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) { … }
    else { return $_SERVER['REMOTE_ADDR']; }
}
```

修复为（CF-Connecting-IP 最优先）：
```php
function getClientIp() {
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        return $_SERVER['HTTP_CF_CONNECTING_IP'];
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}
```

**受影响文件（重复定义的函数，6处）：**
- `includes/functions.php`
- `block/includes/functions.php`
- `bct/includes/functions.php`
- `nft/includes/functions.php`
- `hufang/includes/functions.php`
- `shared/admin/admin-auth.php`（直接使用 `$_SERVER['REMOTE_ADDR']` 记录日志）

### 3.2 secure cookie 修复（可选，Nginx 修复后自动生效）

如果 Nginx 已传递 `HTTPS` 变量，PHP 的 `isset($_SERVER['HTTPS'])` 正常，无需改代码。

兜底方案（如果不改 Nginx）：
```php
$is_https = ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https' 
         || ($_SERVER['HTTPS'] ?? '') === 'on';
```

### 3.3 子站 Session Cookie Domain 统一

当前子站（block/bct/nft/hufang）的 `auth.php` 使用 `$_SERVER['HTTP_HOST']` 隔离 session，跨子站不共享登录。

统一为 `.58.tl` 可跨子站保持登录状态：
```php
session_set_cookie_params([
    'domain' => '.58.tl',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Lax'
]);
```

## 4. Cloudflare 控制台操作

| 步骤 | 操作 |
|------|------|
| 1 | 添加站点 `58.tl`，Cloudflare 扫描 DNS |
| 2 | 域名注册商修改 NS 指向 CF（等待生效） |
| 3 | SSL/TLS → Full (strict) |
| 4 | Edge Certificates → Always Use HTTPS |
| 5 | Cache Rules: `*58.tl/assets/*` → Edge TTL 30天 |
| 6 | 可选：Speed → Auto Minify (JS/CSS/HTML)、Brotli |
