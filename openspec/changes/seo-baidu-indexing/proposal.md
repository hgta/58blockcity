# 提案：百度收录优化 — 让内页被百度爬取和收录

## 问题

百度目前只收录主站和各子站首页，所有内页（商品详情、模特详情、店铺详情、互访圈、NFT、城市页等）均未被收录。

## 根因分析

| 问题 | 严重度 | 影响 |
|------|--------|------|
| 模特页 `.htaccess` 缺少 RewriteRule | 🔴 P0 | 所有模特详情 URL 404，sitemap 里的模特链接全部不可访问 |
| 排行榜页用 `<div onclick>` 而非 `<a href>` | 🔴 P0 | 爬虫无法跟随链接进入详情页 |
| 业务代码从未调用 `baiduPush()` | 🟡 P1 | 新内容发布后百度不知道 |
| Sitemap 未主动通知百度 | 🟡 P1 | 百度不知道 sitemap 更新 |
| 详情页缺乏交叉内链 | 🟡 P1 | 爬虫难以从一个页面发现其他页面 |
| 未强制 HTTPS | 🟢 P2 | HTTP/HTTPS 重复索引 |

## 方案

按优先级分三批实施：P0 修复致命问题 → P1 建推送机制 → P2 收尾优化。

## 受影响文件

- `.htaccess` / `mall/.htaccess` — 模特页 RewriteRule
- `mall/rankings/index.php` — 排行榜链接改为 `<a href>`
- `mall/admin/models.php` / `mall/shop/products.php` / `mall/admin/` — 内容发布时调用 `baiduPush()`
- `sitemap.php` — 增加百度 ping 通知
- `mall/product/detail.php` / `mall/model/view.php` / `mall/shop/view.php` — 交叉内链
- `config/seo.php` — 百度推送 token 配置
