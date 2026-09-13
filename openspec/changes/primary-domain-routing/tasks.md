# 任务清单：主域路由（一级域名作为主收录目标）

> 涉及业务：`bct`（renqizhi）与 `v/hufang`（hufangquan）两组。
> 前置：`fix-baidu-push-pipeline` 已完成；服务器需人工同步 nginx。

## 1. 域名映射配置（P0）

- [x] 1.1 `config/seo-sample.php`：新增 `primary_domains` 映射表（主域 / 别名域 / 收口子域），含字段注释 ✅
- [x] 1.2 `config/seo.php`：同步新增 `primary_domains`（真实值）；验证：`bct` → `renqizhi.com`，`hufang` → `hufangquan.com` ✅
- [x] 1.3 `classes/SeoHelper.php`：新增 `primaryDomainMap()` / `primaryDomainByHost()` / `canonicalTargetHost()` / `canonicalTargetUrl()` / `isAliasHost()` ✅
- [x] 1.4 `classes/SeoHelper.php`：`circleUrl()` 改为按 `primaryDomainMap('hufang')` 返回主域；验证：输出 `https://hufangquan.com/circle/...` ✅

## 2. canonical 动态化（P0，核心）

- [x] 2.1 `bct/includes/header.php`：canonical 由写死改为 `SeoHelper::canonicalTargetUrl()`（页面可传值覆盖）✅
- [x] 2.2 `hufang/includes/header.php`：同上 ✅
- [x] 2.3 `hufang/index.php`、`hufang/circles/all.php`、`hufang/circles/view.php`：canonical 与面包屑改用主域；view.php 引入 `$hufangBase` ✅
- [x] 2.4 `bct/includes/header.php`：静态资源域名评估——`og_image` 保留 `58.tl` CDN 域名（不强制改动），canonical 已动态化 ✅

## 3. sitemap / robots / llms 域名切换（P0）

- [x] 3.1 `bct/sitemap.php`：`BCT_BASE` 改为按 `primaryDomainMap('bct')` 动态解析（回退 `bct.58.tl`）✅
- [x] 3.2 `bct/robots.txt`：`Sitemap:` 改为 `https://renqizhi.com/sitemap.xml` ✅
- [x] 3.3 `bct/llms.txt`：入口链接改为 `renqizhi.com`，保留 `www.58.tl` 主实体引用 ✅
- [x] 3.4 `hufang/sitemap.php`：`V_BASE` 改为按 `primaryDomainMap('hufang')` 动态解析 ✅
- [x] 3.5 `hufang/robots.txt`：`Sitemap:` 改为 `https://hufangquan.com/sitemap.xml` ✅
- [x] 3.6 `hufang/llms.txt`：入口链接与描述改为 `hufangquan.com`，保留主实体引用 ✅

## 4. nginx 配置（P1，需服务器同步）

- [x] 4.1 `docs/nginx-rewrite.conf`：新增 `renqizhi.com` vhost（root 指向 `bct/`）✅
- [x] 4.2 `docs/nginx-rewrite.conf`：新增 `hufangquan.com` vhost（root 指向 `hufang/`）✅
- [x] 4.3 `docs/nginx-rewrite.conf`：新增 `renqizhi.cn`(+www) → 301 → `renqizhi.com` ✅
- [x] 4.4 `docs/nginx-rewrite.conf`：新增 `hufangquan.cn`(+www) → 301 → `hufangquan.com` ✅
- [x] 4.5 输出服务器落地清单：`docs/primary-domain-deployment.md`（含 DNS、证书、验证步骤）✅
- [ ] 4.6 服务器同步后验证：`curl -I https://renqizhi.cn` 返回 301 到 `.com`；验证：返回码正确

## 5. 推送配置与百度接入（P1）

- [x] 5.1 `config/seo.php`：`sites` 增加 `renqizhi.com` 条目（`enabled=false`，待填 token）✅
- [x] 5.2 `config/seo.php`：`sites` 增加 `hufangquan.com` 条目（`enabled=false`，待填 token）✅
- [ ] 5.3 百度平台验证 `renqizhi.com`，获取 token 并填入，`enabled=true`；验证：验证状态通过
- [ ] 5.4 命令行验证推送：`php site.php https://renqizhi.com/` 返回 `success`；验证：无 `site is not valid`
- [ ] 5.5 百度平台提交 `renqizhi.com` 的 sitemap；验证：平台状态正常
- [ ] 5.6 百度平台验证 `hufangquan.com`（验证文件置于 `hufang/`），获取 token 并填入，`enabled=true`；验证：验证状态通过
- [ ] 5.7 命令行验证推送：`php site.php https://hufangquan.com/` 返回 `success`；验证：无 `site is not valid`
- [ ] 5.8 百度平台提交 `hufangquan.com` 的 sitemap；验证：平台状态正常

## 6. 生态关联与导航（P2）

- [x] 6.1 主站 `index.php` 生态导航决策：**保留指向子域**（符合「58 生态子品牌」定位，子域不 301 仍可访问）✅
- [ ] 6.2 `includes/city-portal-render.php` 的 bct/circles 链接按 6.1 决策检查确认；验证：链接目标一致
- [x] 6.3 `Organization` 实体保留 `isRelatedTo` 与主实体引用（未改动 `shared/organization.php`）✅

## 7. 验证与基线（P2，需服务器/百度平台）

- [ ] 7.1 变更前记录基线：`site:renqizhi.com` / `site:bct.58.tl` / `site:hufangquan.com` / `site:v.58.tl` 的收录数；验证：数字留档
- [ ] 7.2 用百度「抓取诊断」测试 `renqizhi.com` 与 `bct.58.tl`，确认爬虫可正常抓取；验证：返回码正常
- [ ] 7.3 验证 canonical 收口生效：`curl -s https://bct.58.tl/market.php | grep canonical` 应指向 `renqizhi.com`；验证：源码正确
- [ ] 7.4 变更后 2~4 周复查收录，对比基线；验证：产出对比结论
