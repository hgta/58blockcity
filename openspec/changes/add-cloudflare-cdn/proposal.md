# Cloudflare CDN 全球加速

## 背景

当前 58.tl 全部流量直连源站（单点国内服务器），海外用户访问延迟高。Cloudflare 提供全球 300+ 边缘节点、免费 SSL、DDoS 防护和静态资源缓存，可以有效提升全球访问速度并减轻源站负载。

## 目标

1. DNS 迁移到 Cloudflare，所有流量经 CF 代理
2. 源站正确识别真实用户 IP（而非 CF 边缘 IP）
3. HTTPS 状态正确传递到源站 PHP（Cookie secure 标志）
4. 静态资源 `/assets/*` 设置长缓存减轻源站负担
5. 域名不变，业务逻辑无感知

## 范围

| In scope | Out of scope |
|----------|-------------|
| Nginx 配置：real_ip，HTTPS fastcgi_param | 更换域名 |
| PHP 修复：getClientIp() 增加 CF-Connecting-IP | 硬编码 URL 域名修改（不需要） |
| PHP 修复：session secure cookie 统一 | robots.txt / sitemap（不需要改） |
| Cloudflare 控制台：DNS、SSL、Cache Rules | WAF / Rate Limiting（后续） |
| Nginx 配置文档更新 | Auto Minify / Rocket Loader（可选） |

## 风险

- **低风险**：域名不变，Cloudflare 透明代理，回退只需改 DNS
- **最大风险**：忘记配 `real_ip_header` 导致日志/风控 IP 全变成 CF IP
