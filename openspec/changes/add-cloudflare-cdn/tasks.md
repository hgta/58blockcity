# Cloudflare CDN — 任务清单

## 阶段 1：Nginx 配置

- [ ] 1.1 `docs/nginx-rewrite.conf` 顶部添加 Cloudflare IP 段 + `real_ip_header CF-Connecting-IP`
- [ ] 1.2 所有 `location ~ \.php$` 块添加 `fastcgi_param HTTPS $http_x_forwarded_proto`
- [ ] 1.3 服务器更新 Nginx 配置 + 重载

## 阶段 2：PHP 代码修复

- [ ] 2.1 `includes/functions.php` — getClientIp() 增加 `HTTP_CF_CONNECTING_IP` 优先
- [ ] 2.2 `block/includes/functions.php` — 同上
- [ ] 2.3 `bct/includes/functions.php` — 同上
- [ ] 2.4 `nft/includes/functions.php` — 同上
- [ ] 2.5 `hufang/includes/functions.php` — 同上
- [ ] 2.6 `shared/admin/admin-auth.php` — 日志 IP 改用 getClientIp()

## 阶段 3：Session Cookie 统一（建议）

- [ ] 3.1 `block/includes/auth.php` — domain 改为 `.58.tl`
- [ ] 3.2 `bct/includes/auth.php` — 同上
- [ ] 3.3 `nft/includes/auth.php` — 同上
- [ ] 3.4 `hufang/includes/auth.php` — 同上

## 阶段 4：Cloudflare 控制台

- [ ] 4.1 添加站点 + DNS 扫描
- [ ] 4.2 域名注册商修改 NS
- [ ] 4.3 SSL/TLS → Full (strict)
- [ ] 4.4 开启 Always Use HTTPS
- [ ] 4.5 配置 Cache Rules（/assets/* 30天）

## 阶段 5：验证

- [ ] 5.1 DNS 生效后，curl 检查 CF 头部
- [ ] 5.2 源站日志中 IP 为真实用户 IP
- [ ] 5.3 HTTPS cookie secure 标志正常
- [ ] 5.4 各子站功能正常（登录、购物、发帖）
- [ ] 5.5 提交代码并推送
