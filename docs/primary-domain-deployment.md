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

## 二、新增 .cn 301 规则

```nginx
# renqizhi.cn → renqizhi.com
server {
    listen 80;
    listen 443 ssl;
    server_name renqizhi.cn www.renqizhi.cn;

    return 301 https://renqizhi.com$request_uri;
}

# hufangquan.cn → hufangquan.com
server {
    listen 80;
    listen 443 ssl;
    server_name hufangquan.cn www.hufangquan.cn;

    return 301 https://hufangquan.com$request_uri;
}
```

> 注意：**DNS 必须保留 .cn 的解析记录**，否则 301 无法生效（域名无法到达服务器）。

---

## 三、确认 DNS 解析

四个一级域名都需解析到服务器 IP：

| 域名 | 类型 | 指向 |
|------|------|------|
| `renqizhi.com` | A | 服务器 IP |
| `renqizhi.cn` | A | 服务器 IP |
| `hufangquan.com` | A | 服务器 IP |
| `hufangquan.cn` | A | 服务器 IP |

---

## 四、验证步骤

### 1. 一级域名可访问

```bash
curl -I https://renqizhi.com/
curl -I https://hufangquan.com/
# 期望：HTTP 200
```

### 2. .cn 正确 301

```bash
curl -I https://renqizhi.cn/
# 期望：301，Location: https://renqizhi.com/

curl -I https://hufangquan.cn/
# 期望：301，Location: https://hufangquan.com/
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
