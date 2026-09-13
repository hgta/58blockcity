# 任务清单：各子站独立接入百度收录

> 前置条件：`fix-baidu-push-pipeline` 已完成，主站推送链路已验证可用。

## 1. 多子域推送配置（P0）

- [x] 1.1 `config/seo.php`：已扩展为按 host 键控的 `sites` 映射（含 `token` 与 `enabled`），保留对旧顶层 `baidu_token` 的向后兼容；验证：`resolvePushCredentials()` 优先读 sites，无 sites 时回退顶层 ✅
- [x] 1.2 `config/seo-sample.php`：已同步为多子域结构模板并补充字段注释；验证：新环境按模板初始化可运行 ✅
- [x] 1.3 `classes/SeoHelper.php`：`pushContentUrl()` 已改为按 URL 的 host 查找 token，匹配不到或 `enabled` 为假时跳过并记录日志；验证：不回退到 www token ✅
- [x] 1.4 `classes/SeoHelper.php`：`baiduPush()` 已支持显式传入 site/token；验证：`pushContentUrl()` 与 `site.php` 两条路径均正常 ✅

## 2. 子站 sitemap（P0）

- [x] 2.1 `mall/sitemap.php`：首页、商品/店铺/作者列表、商品详情、店铺详情、作者详情、排行榜 ✅
- [x] 2.2 `block/sitemap.php`：首页、城市榜单、城市页（`/city/{pinyin}.html`）✅
- [x] 2.3 `bct/sitemap.php`：首页、行情（market）、交易（trade）、城市页（`city.php?city=`）✅
- [x] 2.4 `nft/sitemap.php`：首页、认领/售卖列表、排行榜、NFT 详情 ✅
- [x] 2.5 `hufang/sitemap.php`（v 域）：首页、互访圈列表、互访圈详情；置于 hufang/ 以避开与 www 共用 root 的冲突 ✅
- [x] 2.6 `bid/sitemap.php`：首页、拍卖详情（`view.php?id=`，已核对 `auctions` 表无 title 字段故用 id）✅
- [x] 2.7 `club/sitemap.php`：首页、帖子详情 ✅
- [x] 2.8 `task/sitemap.php`：首页、任务发现（find）、任务详情（`view.php?id=`，已核对 `tasks.status` 为 `open`/`closed`）✅
- [x] 2.9 `model/sitemap.php`：既有文件，`<loc>` 全为 model.58.tl，无需改动 ✅
- [ ] 2.10 上线后逐个验证各子域 `/sitemap.xml` 返回 200 且 `<loc>` 全为本域；验证：`curl -sI https://{sub}.58.tl/sitemap.xml`

## 3. 子站 robots.txt（P1）

- [x] 3.1 `mall/robots.txt`：`Sitemap:` 指向 mall 域，`Disallow` 按 mall root 裁剪 ✅
- [x] 3.2 `block/robots.txt` ✅
- [x] 3.3 `bct/robots.txt` ✅
- [x] 3.4 `nft/robots.txt` ✅
- [x] 3.5 `bid/robots.txt` ✅
- [x] 3.6 `club/robots.txt` ✅
- [x] 3.7 `model/robots.txt` ✅
- [x] 3.8 `task/robots.txt` ✅
- [x] 3.9 `hufang/robots.txt`（v 域）：`Sitemap:` 指向 `https://v.58.tl/sitemap.xml` ✅

## 4. nginx 配置补齐（P1）

- [x] 4.1 `docs/nginx-rewrite.conf`：为 `block`/`bct`/`nft`/`bid`/`club` 补 `rewrite ^/sitemap\.xml$ /sitemap.php last;` ✅
- [x] 4.2 `docs/nginx-rewrite.conf`：`v.58.tl` 的 sitemap 重写指向 `/hufang/sitemap.php`（解决共用 root 冲突）✅
- [x] 4.3 `docs/nginx-rewrite.conf`：新增 `task.58.tl` vhost（含 sitemap 重写）✅
- [x] 4.4 输出「服务器落地清单」：`docs/baidu-subsite-onboarding.md` 含服务器侧需同步的 nginx 规则 ✅

## 5. 子域验证接入（P2，需人工配合）

- [x] 5.1 编写 `docs/baidu-subsite-onboarding.md`：验证方式（DNS/HTML/CNAME）、token 获取、配置填写、启用开关、排查手册 ✅
- [ ] 5.2 逐个在百度搜索资源平台验证子域（建议顺序：`mall` → `model` → `club` → `block` → 其余）；验证：各子域平台状态为已验证
- [ ] 5.3 每验证一个子域即填入 token 并 `enabled = true`，间隔数天；验证：配置中已启用项与平台已验证项一致
- [ ] 5.4 每个子域启用后检查推送日志确认 `success` 或明确错误；验证：error log 无 `not_same_site` 类错误

## 6. 验证与风险监控（P2）

- [ ] 6.1 同步服务器 nginx 规则后，验证各子域 `/sitemap.xml` 可访问；验证：10 个子域全部返回 200
- [ ] 6.2 监控主站收录数量在子域启用前后的变化，确认无负面影响；验证：主站收录未下降
- [ ] 6.3 检查各子域是否存在与其他子域完全重复的页面内容；验证：抽查无大面积雷同
- [ ] 6.4 确认各子域页面 canonical 指向自身域而非 www；验证：抽查源码 canonical 正确
- [ ] 6.5 记录各子域收录基线，作为后续对比依据；验证：基线数字留档
