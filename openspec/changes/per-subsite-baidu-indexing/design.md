# 设计：各子站独立接入百度收录

## Context

前置变更 `fix-baidu-push-pipeline` 已修复推送链路本身（token、日志、统一入口、主站 sitemap 合规）。本变更解决**多子域的接入架构**问题。

关键架构事实：

- 每个子域是独立 nginx vhost，`root` 指向项目内对应子目录（`mall/`、`block/` 等），`v.58.tl` 的 root 指向项目根目录。
- 百度侧只验证了 `www.58.tl`。
- 子站 sitemap 仅 `model` 存在；nginx 却对 `mall` 也配置了 rewrite，导致 404。
- 子站 robots.txt 全部缺失。
- `task.58.tl` 用户确认对外开放，但此前无 vhost 配置。

## Goals / Non-Goals

**Goals**

- 每个子域能独立向百度推送自己的 URL，使用自己验证所得的 token。
- 每个子域有自己的 sitemap 与 robots.txt，形成完整可发现链路。
- 支持分阶段启用，可逐个子域验收。

**Non-Goals**

- 不改动子域的业务逻辑与 URL 结构。
- 不做跨子域的权重导流设计（保持各域内容独立）。
- 不自动完成百度平台的子域验证（需人工操作）。

## Decisions

### D1：配置结构从「单 token」升级为「按域映射」

**决策**：`config/seo.php` 的推送配置改为按 host 键控：

```php
'sites' => [
    'www.58.tl'   => ['token' => '...', 'enabled' => true],
    'mall.58.tl'  => ['token' => '', 'enabled' => false],
    // ...
],
```

保留向后兼容：无 `sites` 时回退顶层 `baidu_token` / `baidu_site`。

**理由**：按站点键控便于独立控制启用状态（分阶段上线），`enabled` 支持逐个验收。

**替代方案**：复用现有 `subdomains` 数组 — 被否决，其语义是 sitemap 生成用的域名清单，不应混用。

**实测修正（2026-09）**：原设计假设「token 与站点强绑定，各子域需独立 token」。实测发现同一百度账号下多个站点**共用同一 token**，靠请求中的 `site=` 参数区分站点身份。因此配置结构保留（便于独立开关与配额观测），但 token 值可复用同一字符串。

### D1·补：配额按站点独立（实测）

**实测数据**：

| 顺序 | `site` 参数 | 返回 |
|------|------------|------|
| 1 | `mall.58.tl` | `{"remain":8,"success":1}` |
| 2 | `block.58.tl` | `{"remain":9,"success":1}` |
| 3 | `www.58.tl` | `{"remain":9,"success":1}` |

`mall` 消耗后 `remain` 变为 8，`block` 与 `www` 仍独立显示 9。

**结论**：每个 `site` 对应独立配额池，互不干扰，各站点可放心启用。

**重要限制**：单站点配额约 **10 条/天**，属最低档。这决定了推送只能作为「新内容提示信号」，**不能用于批量提交内页**。内页收录依赖 sitemap + 内链 + 内容质量，而非推送。

### D2：推送按 URL 的 host 选择 token

**决策**：`pushContentUrl($url)` 解析 host，在 `sites` 中查找配置；找不到或未启用则跳过并记录日志，**绝不回退到 www 的 token**。

**理由**：推送必须使用「与 URL 归属一致」的 `site` 参数，否则会被百度拒绝（`not_same_site`）或计入错误站点配额。宁可跳过并留日志，也不要发出注定失败的请求，浪费配额且污染诊断。

**实测修正（2026-09）**：因 token 实际通用，此处选择 `site` 参数（而非 token）才是关键——`site` 决定归属与配额池，必须与 URL 的 host 严格一致。

### D3：子站 sitemap 复用 `model/sitemap.php` 模式

**决策**：为每个子域新建 `sitemap.php`，结构参照 `model/sitemap.php`，只输出本域 URL。

| 子域 | sitemap 覆盖内容 |
|------|------------------|
| `mall.58.tl` | 首页、商品/店铺/作者列表、商品详情、店铺详情、作者详情、排行榜 |
| `block.58.tl` | 首页、城市榜单、城市页（`/city/{pinyin}.html`） |
| `bct.58.tl` | 首页、行情、交易、城市页（`city.php?city=`） |
| `nft.58.tl` | 首页、认领/售卖列表、排行榜、NFT 详情 |
| `v.58.tl` | 首页、互访圈列表、互访圈详情 |
| `bid.58.tl` | 首页、拍卖详情（`view.php?id=`） |
| `club.58.tl` | 首页、帖子详情 |
| `task.58.tl` | 首页、任务发现、任务详情（`view.php?id=`） |
| `model.58.tl` | （既有文件，无需改动） |

**注意**：子站 sitemap 中引用 `SeoHelper` 的路径为 `dirname(__DIR__) . '/classes/SeoHelper.php'`。

**替代方案**：共用生成脚本按 host 参数区分 — 被否决，各子域 root 不同、内容类型各异，显式分离更清晰。

### D4：`v.58.tl` 的 sitemap 冲突处理

**问题**：`v.58.tl` 与 `www.58.tl` 共用 root（项目根目录），无法在根目录放两个 `sitemap.php`。

**决策**：`v` 域 sitemap 放在 `hufang/sitemap.php`，由 `v.58.tl` vhost 将 `/sitemap.xml` 重写到 `/hufang/sitemap.php`。

**理由**：nginx rewrite 是 host 作用域的，同一物理路径可在不同 vhost 映射到不同文件，天然解决冲突。互访圈业务本身就在 `hufang/` 目录下，sitemap 随之放置语义也合理。

**替代方案**：`v.58.tl` 不提供 sitemap — 被否决，用户要求子域独立收录，sitemap 是必要环节。

### D5：nginx 配置补齐 + 文档化落地清单

**决策**：更新 `docs/nginx-rewrite.conf`：为 `block`/`bct`/`nft`/`bid`/`club` 补 sitemap rewrite，为 `v` 补 hufang 路径 rewrite，为 `task` 新增完整 vhost。

**关键约束**：`docs/nginx-rewrite.conf` 是**参考文档**，服务器上实际生效的配置需人工同步。因此配套产出 `docs/baidu-subsite-onboarding.md`，含「服务器侧需同步的规则清单」。

**理由**：代码仓库无法直接改服务器 nginx；明确文档化可避免遗漏。

### D6：robots.txt 按子域 root 重新表达路径

**决策**：各子域 `robots.txt` 继承主站爬虫策略（AI 爬虫全放行 + 后台屏蔽），但 `Disallow` 路径按本域 root 裁剪（如 `mall/robots.txt` 直接写 `/user/`，而非 `/mall/user/`）。`Sitemap:` 指向本域。

**理由**：robots.txt 的路径是相对该文件所在站点根的；照抄主站的 `/mall/user/` 前缀在 `mall.58.tl` 上会失效。

### D7：启用节奏控制（应对站群风险）

**决策**：配套文档给出分 4 批、每批间隔约 1 周的启用建议，并要求每批后监控主站收录。

**理由**：9 个同主体、同模板子域在百度侧有「站群」特征，集中上线可能触发整体降权并牵连主站。分批启用使影响可控、可回滚（单子域 `enabled=false`）。

## Risks / Trade-offs

| 风险 | 影响 | 缓解 |
|------|------|------|
| 站群判定 | 百度可能整体降权，牵连主站 | D7 分阶段启用；监控主站收录 |
| 服务器 nginx 未同步 | sitemap 404，rewrite 不生效 | D5 落地清单 + 上线验证 `curl -I` |
| 子域验证繁琐 | 9 域需逐个验证，周期长 | 文档指引；DNS 验证可批量加记录 |
| 各子站 sitemap 逻辑重复 | 维护成本 | 接受显式重复；公共函数提取留待后续 |
| `bid`/`task` 表结构假设 | sitemap 可能查不到数据 | 已核对真实表结构（`auctions`/`tasks`）；异常时静默跳过不报错 |

## Migration Plan

1. 完成 `fix-baidu-push-pipeline` 并验证主站推送可用。
2. 部署本变更代码（配置、sitemap、robots）。
3. 同步服务器 nginx 规则（按 `docs/baidu-subsite-onboarding.md` 清单）。
4. 逐个在百度平台验证子域、获取 token、填入配置并 `enabled = true`。
5. 每个子域启用后观察 3~7 天，确认主站未受负面影响，再推进下一个。

**回滚**：单子域置 `enabled=false` 即可停止推送；删除 sitemap/robots 文件即可撤销。

## Open Questions

- `block.58.tl` 与 `www.58.tl` 都有城市页（`block/city/` 与 `www/city/`），URL 语义重叠，是否会被百度判重复内容？需上线后观察。
- 各子域是否需在百度平台以「独立站点」还是「同一主体下的子站」提交？建议按独立站点处理（因 nginx 层面确为独立 vhost）。
