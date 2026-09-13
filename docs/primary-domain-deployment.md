# 主域路由：服务器落地清单

本文档说明 `primary-domain-routing` 变更在**服务器侧**需要人工完成的配置。

代码侧改动（canonical 动态化、sitemap/robots/llms 切换）随代码部署即可生效；
但 **nginx 配置不在代码库中自动生效**，需按下文手动同步。

## 背景

`bct` 与 `hufang` 两组业务各有三个可访问域名，内容相同：

| 业务 | 主域（收录目标） | 别名域（应 301） | 收口子域（canonical 指向主域） |
|------|-----------------|-----------------|---------------------------|
| BCT  | `renqizhi.com`   | `renqizhi.cn`   | `bct.58.tl` |
| 互访圈 | `hufangquan.com` | `hufangquan.cn` | `v.58.tl` |

代码已通过 `config/seo.php` 的 `primary_domains` 实现 canonical 收口；
**301 重定向必须在 nginx 层完成**。

---

## 一、新增一级域名 vhost

### renqizhi.com（BCT 业务）

```nginx
server {
    listen 80;
    listen 443 ssl;
    server_name renqizhi.com;

    root /www/wwwroot/58git/58blockcity/bct;    # 与 bct.58.tl 同一目录
    index index.php index.html;

    if ($http_x_forwarded_proto = "http") {
        return 301 https://$host$request_uri;
    }

    rewrite ^/sitemap\.xml$ /sitemap.php last;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_param HTTPS $http_x_forwarded_proto;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

### hufangquan.com（互访圈业务）

```nginx
server {
    listen 80;
    listen 443 ssl;
    server_name hufangquan.com;

    root /www/wwwroot/58git/58blockcity/hufang;   # 与 v.58.tl 同一目录
    index index.php index.html;

    if ($http_x_forwarded_proto = "http") {
        return 301 https://$host$request_uri;
    }

    rewrite ^/sitemap\.xml$ /sitemap.php last;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_param HTTPS $http_x_forwarded_proto;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

> 注意：这两个域名需各自配置 SSL 证书（或使用多域证书），否则 `listen 443 ssl` 会导致 HTTPS 无法访问。

---

## 二、新增别名域 301 规则

每个业务的**所有非主域变体**（`.cn` 及其 www、`.com` 的 www）统一 301 到主域：

```nginx
# === renqizhi 业务：所有别名域 → renqizhi.com ===
server {
    listen 80;
    listen 443 ssl;
    server_name renqizhi.cn www.renqizhi.cn www.renqizhi.com;

    return 301 https://renqizhi.com$request_uri;
}

# === hufangquan 业务：所有别名域 → hufangquan.com ===
server {
    listen 80;
    listen 443 ssl;
    server_name hufangquan.cn www.hufangquan.cn www.hufangquan.com;

    return 301 https://hufangquan.com$request_uri;
}
```

> 关键点：
> - `return 301` 的目标必须是**不带 www 的主域**，不能写成 `https://www.renqizhi.com`。
>   写错会导致二次跳转（`.cn → www.com → com`）或死链（若 www 版本未部署）。
> - `server_name` 要覆盖**全部**别名变体，漏掉的那个会落到默认 vhost，可能返回错误内容。
> - **DNS 必须保留这些域名的解析记录**，否则 301 无法生效（域名无法到达服务器）。
> - 301 server 块需要 SSL 证书，否则 `listen 443 ssl` 会失败；可用多域证书或单域证书各配一份。

---

## 二·补、关于 www 与不带 www

### 它们是两个独立的域名

`renqizhi.com` 和 `www.renqizhi.com` **没有技术上的隶属关系**，是两个独立域名：

```
  对 DNS：
    renqizhi.com      → A 记录 → 服务器 IP
    www.renqizhi.com  → A 记录 → 服务器 IP   （单独一条记录）

  对 nginx：
    server_name renqizhi.com;        ← 需显式声明
    server_name www.renqizhi.com;    ← 需另声明（或与上者写在一行）

  对百度：
    视为两个不同的站点，构成「重复内容」判定对象
```

### 为什么必须二选一

若两个都能访问、内容相同、都返回 200，搜索引擎会困惑：

```
  ✗ 错误状态
  ┌─────────────────────────────────────────────────┐
  │  https://renqizhi.com/market.php      → 200 OK  │
  │  https://www.renqizhi.com/market.php  → 200 OK  │
  │                                                 │
  │  百度：内容一样，该收录哪个？                     │
  │       → 可能各收一部分                          │
  │       → 可能判重复内容                          │
  │       → 权重被切成两份                          │
  └─────────────────────────────────────────────────┘

  ✓ 正确状态（本项目的选择：不带 www 为主域）
  ┌─────────────────────────────────────────────────┐
  │  renqizhi.com          → 200（主域，正常收录）    │
  │  www.renqizhi.com      → 301 → renqizhi.com     │
  │                                                 │
  │  百度：所有信号汇总到 renqizhi.com               │
  └─────────────────────────────────────────────────┘
```

> 选带不带 www 没有硬性对错：国外习惯不带 www，国内习惯带 www。
> 本项目统一采用**不带 www** 作为主域（与 `bct.58.tl` 等子域体系风格一致）。

### 实务惯例：两个都注册、只用一个

```
  1. 注册两个（防止 www 版本被他人抢注）
  2. 两个都解析到服务器
  3. 只选一个作为收录主域
  4. 另一个 301 到主域
```

### 完整的收口规则

每个业务需要覆盖 **4 个域名**，全部指向同一个主域：

| 域名 | 期望行为 |
|------|---------|
| `renqizhi.com` | 200（主域） |
| `www.renqizhi.com` | 301 → `renqizhi.com` |
| `renqizhi.cn` | 301 → `renqizhi.com` |
| `www.renqizhi.cn` | 301 → `renqizhi.com` |

对应的 nginx 配置（两个 301 server 块）已在第二、二·补节给出。

> 若 `www.renqizhi.com` 未做 DNS 解析，也要去 DNS 加一条 A 记录指向服务器，
> 否则无法在 nginx 中接管它，且存在被他人抢注的风险。

---

## 三、确认 DNS 解析

四个一级域名及其 www 变体都需解析到服务器 IP：

| 域名 | 类型 | 指向 |
|------|------|------|
| `renqizhi.com` | A | 服务器 IP |
| `www.renqizhi.com` | A | 服务器 IP |
| `renqizhi.cn` | A | 服务器 IP |
| `www.renqizhi.cn` | A | 服务器 IP |
| `hufangquan.com` | A | 服务器 IP |
| `www.hufangquan.com` | A | 服务器 IP |
| `hufangquan.cn` | A | 服务器 IP |
| `www.hufangquan.cn` | A | 服务器 IP |

---

## 四、验证步骤

### 1. 一级域名可访问

```bash
curl -I https://renqizhi.com/
curl -I https://hufangquan.com/
# 期望：HTTP 200
```

### 2. 别名域全部 301 到主域

`renqizhi` 业务：

```bash
curl -I https://renqizhi.cn/
# 期望：301，Location: https://renqizhi.com/

curl -I https://www.renqizhi.cn/
# 期望：301，Location: https://renqizhi.com/

curl -I https://www.renqizhi.com/
# 期望：301，Location: https://renqizhi.com/
# 若返回 200 → 说明 www 版本未做 301，需补 server 块
# 若连接失败 → DNS 未解析，需补 A 记录后接管
```

`hufangquan` 业务：

```bash
curl -I https://hufangquan.cn/
curl -I https://www.hufangquan.cn/
curl -I https://www.hufangquan.com/
# 期望：均为 301，Location: https://hufangquan.com/
```

> 判定要点：**`Location` 必须是不带 www 的主域**。
> 若跳到 `www.renqizhi.com` 之类的地址，属于配置错误（会造成二次跳转或死链）。

### 2·补、主域自身为 200

```bash
curl -I https://renqizhi.com/
curl -I https://hufangquan.com/
# 期望：HTTP 200（不应是 301）
```

### 3. canonical 正确收口（核心验证）

```bash
# 主域访问 → canonical 指向自身
curl -s https://renqizhi.com/market.php | grep canonical
# 期望：<link rel="canonical" href="https://renqizhi.com/market.php">

# 子域访问 → canonical 指向主域（收口）
curl -s https://bct.58.tl/market.php | grep canonical
# 期望：<link rel="canonical" href="https://renqizhi.com/market.php">

# hufang 同样
curl -s https://hufangquan.com/ | grep canonical
# 期望：https://hufangquan.com/

curl -s https://v.58.tl/ | grep canonical
# 期望：https://hufangquan.com/
```

### 4. sitemap 域名正确

```bash
curl -s https://renqizhi.com/sitemap.xml | grep -o '<loc>[^<]*' | head -3
# 期望：<loc>https://renqizhi.com/...

curl -s https://hufangquan.com/sitemap.xml | grep -o '<loc>[^<]*' | head -3
# 期望：<loc>https://hufangquan.com/...
```

---

## 五、百度平台接入

### 1. 验证站点

在百度搜索资源平台分别添加并验证：

- `https://renqizhi.com`
- `https://hufangquan.com`

验证文件放置位置：

| 域名 | 验证文件放到 |
|------|-------------|
| `renqizhi.com` | `bct/` 目录 |
| `hufangquan.com` | `hufang/` 目录 |

### 2. 获取 token 并填入配置

编辑 `config/seo.php`（该文件不入库）：

```php
'sites' => [
    // ...
    'renqizhi.com'   => ['token' => '这里填 renqizhi.com 的 token',   'enabled' => true],
    'hufangquan.com' => ['token' => '这里填 hufangquan.com 的 token', 'enabled' => true],
],
```

### 3. 验证推送

```bash
cd /www/wwwroot/58git/58blockcity
php site.php https://renqizhi.com/
php site.php https://hufangquan.com/
```

期望看到 `success` 计数。若出现 `site is not valid`，说明平台验证未完成或 `site` 参数不匹配。

### 4. 提交 sitemap

在平台提交：

- `https://renqizhi.com/sitemap.xml`
- `https://hufangquan.com/sitemap.xml`

---

## 六、注意事项

### 子域为何不 301

`bct.58.tl` 与 `v.58.tl` **不 301**，仍可正常访问，但 canonical 指向主域。
这是有意设计：保留 58 生态的入口（主站 `index.php` 有导航指向它们），同时把权重集中到一级域名。

### 收录变化预期

- canonical 变更有**波动期**，短期内原收录可能下降。
- 变更后 **2~4 周**观察效果，不要因短期波动回滚。
- 未备案 + 境外服务器条件下，收录周期可能更长（2~6 个月）。

### 回滚方式

域名映射集中在 `config/seo.php` 的 `primary_domains`。
清空该表即可恢复「canonical 指向自身」的行为；删除 nginx 中对应的 301 server 块即可撤销重定向。
