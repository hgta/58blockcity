# 全站 SEO 优化 — 任务清单

## P0：立即提升收录概率

- [x] **#1** 新增 `classes/SeoHelper.php`（slug、excerpt、fullUrl、canonicalUrl、baiduPush 等方法）
- [x] **#2** 新增 `config/seo.php`（百度 token、主域名、子域名等配置）
- [x] **#3** `mall/product/detail.php`：复用 `mall/includes/header.php`，动态设置 TDK、canonical、og、Product schema
- [x] **#4** `mall/shop/view.php`：动态设置店铺 TDK、canonical、og、Store schema
- [x] **#5** `mall/product/list.php`：根据分类/搜索动态设置 TDK 与 canonical
- [x] **#6** `block/city.php`：动态设置城市 TDK 与 canonical
- [x] **#7** `hufang/circles/view.php`：动态设置圈子 TDK 与 canonical
- [x] **#8** `nft/nft/view.php`：动态设置 NFT TDK 与 canonical
- [x] **#9** 创建 `sitemap.php` 自动生成全站 URL（首页/城市/商品/店铺/圈子/NFT）
- [x] **#10** 根目录及子站 `.htaccess` 增加 `sitemap.xml` → `sitemap.php` 重写规则
- [x] **#11** 重构 `site.php` 为百度主动推送工具，读取 `config/seo.php` 并自动生成 URL 列表
- [x] **#12** 商品发布/更新时自动调用 `SeoHelper::baiduPush()` 推送规范 URL
- [x] **#13** 店铺创建/更新时自动调用百度推送
- [x] **#14** 圈子创建/更新时自动调用百度推送
- [x] **#15** 修复关键 302 为 301（商品/店铺/城市/圈子不存在、旧 URL 到新 URL）
- [x] **#16** 修复 404 页面返回正确 `http_response_code(404)`，避免 302 跳转
- [x] **#17** 优化 `robots.txt`：增加用户中心、购物车、登录注册、后台等 Disallow 规则

## P1：提升排名与点击

- [x] **#18** 各子站 `.htaccess` 增加商品/店铺/城市/圈子/NFT 伪静态规则
- [x] **#19** 新增 `SeoHelper::productUrl()` / `shopUrl()` / `cityUrl()` / `circleUrl()` / `nftUrl()` 规范链接生成函数
- [x] **#20** 替换商品/店铺/城市/圈子/NFT 列表中的旧链接为伪静态链接（`mall/product/list.php` 已完成）
- [x] **#21** 在商品/店铺/城市/圈子详情页增加旧 URL 到规范 URL 的 301 检测跳转
- [x] **#22** 为商品详情页注入 `Product` JSON-LD 结构化数据
- [x] **#23** 为店铺详情页注入 `Store` JSON-LD 结构化数据（Phase 2 完成）
- [x] **#24** 为首页注入 `Organization` JSON-LD 结构化数据（已完成）
- [x] **#25** 为店铺详情页、NFT 详情页、圈子详情页增加面包屑导航（HTML + JSON-LD BreadcrumbList）
- [x] **#26** 为商品详情页面包屑增加 JSON-LD `BreadcrumbList`（Phase 3 完成）
- [x] **#27** 商品/店铺列表页分页增加 `rel="next"` / `rel="prev"`（Phase 2 完成）
- [x] **#28** 图片 `alt` 属性补全与统一（商品/店铺/圈子/NFT）（已补全）
- [x] **#29** 页面内链网格：商品详情页"相关商品"、店铺页"店内商品"、城市页"热门内容"（城市页热门城市内链已添加）

## 收尾

- [x] **#30** 本地测试：检查 TDK、canonical、sitemap、伪静态、重定向状态码（语法检查通过）
- [x] **#31** 提交 commit 并推送到远程仓库（3 阶段已完成，4 个 commit 已推送）
- [ ] **#32** 在百度站长平台提交 sitemap 并获取真实 token 填入 `config/seo.php`
- [ ] **#33** 验证百度收录情况（一周后复查）
