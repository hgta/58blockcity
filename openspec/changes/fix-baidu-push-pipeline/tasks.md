# 任务清单：修复百度推送链路

## 1. 配置与链路打通（P0）

- [ ] 1.1 `config/seo.php`：将 `baidu_token` 由 `'YOUR_BAIDU_TOKEN'` 改为百度搜索资源平台的真实 token；确认 `baidu_site` 为 `'www.58.tl'`、`auto_push_enabled` 为 `true`；验证：`php -r` 输出该配置数组，token 非占位符
- [ ] 1.2 `classes/SeoHelper.php`：`baiduPush()` 增加推送结果日志，解析百度返回 JSON 并记录 `success` / `remain` / `message` / 错误字段；验证：命令行推送后 error log 出现 `[SEO]` 记录
- [ ] 1.3 `classes/SeoHelper.php`：`baiduPush()` 增加 HTTP 层错误记录（`curl_error` / 非 200 响应）；验证：断网或错误 token 时日志有明确错误信息
- [ ] 1.4 `site.php`：命令行输出百度返回的原始 JSON（格式化），非 0 success 时退出码非 0；验证：`php site.php https://www.58.tl/` 打印百度响应

## 2. 统一推送入口（P1）

- [ ] 2.1 `classes/SeoHelper.php`：明确 `pushContentUrl()` 为业务侧唯一入口，统一守卫语义（`auto_push_enabled` 开启且 token 非占位符才推送）；验证：token 为占位符时静默跳过，非占位符时推送
- [ ] 2.2 将直接调用 `baiduPush()` 的业务点改为 `pushContentUrl()`：`hufang/circles/create.php`、`hufang/circles/edit.php`、`nft/nft/claim_detail.php`、`nft/nft/sell.php`、`block/city.php`（2 处）、`mall/shop/create.php`、`mall/shop/manage.php`；验证：各点推送行为与守卫一致
- [ ] 2.3 全站检索确认无新增直接调用底层 `baiduPush()` 的业务代码（`site.php` 除外）；验证：`grep -rn "baiduPush" --include="*.php"` 结果仅剩 `SeoHelper.php` 与 `site.php`

## 3. 修复 pingSitemap（P1）

- [ ] 3.1 `classes/SeoHelper.php`：移除 `pingSitemap()` 中的百度 ping 分支（百度无 sitemap ping API）；验证：函数内不再出现 `data.zz.baidu.com`
- [ ] 3.2 `classes/SeoHelper.php`：`pingSitemap()` 保留 Google ping，并增加开关判断（默认关闭或受配置控制）；验证：默认不发出外呼，避免无意义请求
- [ ] 3.3 `sitemap.php`：确认 `pingSitemap()` 调用行为符合新语义；验证：请求 `/sitemap.xml` 不再向百度发无效请求

## 4. sitemap 归属合规（P1）

- [ ] 4.1 `sitemap.php`：移除所有非 `www.58.tl` 域的 `urlNode()` 调用（`block.58.tl/`、`block.58.tl/top200city.php`、`bct.58.tl/`、`bct.58.tl/market.php`、`mall.58.tl/`、`mall.58.tl/product/list.php`、`mall.58.tl/shop/list.php`、`model.58.tl/list.php`、`model.58.tl/rankings.php`、`model.58.tl/dramas.php`、`mall.58.tl/author/list.php`、`nft.58.tl/`、`v.58.tl/`、`v.58.tl/circles/all.php`、`bid.58.tl/`、`club.58.tl/`）；验证：输出中所有 `<loc>` 均以 `https://www.58.tl/` 开头
- [ ] 4.2 `sitemap.php`：移除跨域动态节点输出——第 7 节模特页、7b 短剧页、8 作者页（均属 `model.58.tl` / `mall.58.tl` 子域）；验证：`model.58.tl` 与 `mall.58.tl` 的 URL 不再出现
- [ ] 4.3 `sitemap.php`：保留 `www.58.tl` 域下的城市页、社区帖、互访圈、NFT、商品、店铺节点；验证：`city/`、`post/`、`circle/`、`nft/`、`product/`、`shop/` 类型 URL 仍在
- [ ] 4.4 校验 sitemap 输出为合法 XML 且 `<loc>` 全部同域；验证：`curl -s https://www.58.tl/sitemap.xml | xmllint --noout -` 通过，且跨域 URL 数为 0

## 5. 验证与基线（P2）

> ⚠️ **需在服务器执行**：本地开发机无 PHP 运行时（已确认无 `php` 命令），
> 以下验证项必须在部署环境完成。

- [ ] 5.1 命令行推送 `www.58.tl` 核心页面并确认百度返回 `success` 大于 0；验证：`php site.php` 输出含 success 计数
- [ ] 5.2 在百度搜索资源平台确认 `www.58.tl` 的 sitemap 提交状态正常（无「格式错误」「跨域」告警）；验证：平台 sitemap 状态为正常
- [ ] 5.3 记录推送前的百度收录数量作为基线，变更后 3~7 天对比；验证：留下基线数字记录
- [ ] 5.4 触达一次真实业务推送路径（如发布一条社区帖），确认 error log 出现对应 `[SEO]` 推送记录；验证：日志含该帖 URL 与百度返回
