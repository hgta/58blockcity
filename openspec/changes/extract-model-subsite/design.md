## Context

动机见 `proposal.md - Why`。设计前已确认的现状约束：

- 模特库现位于 `mall/model/`（`list.php` / `view.php` / `card.php` / `follow.php` / `style.css`），是**子目录**而非子站；主题色沿用商城电商橙 `#ff6b00`。
- `classes/Model.php` 是**跨目录共享类**，被 `mall/index.php`、`mall/admin/models.php`、`mall/user/following.php`、`classes/Product.php` 引用；**类名不可变更，也不能搬移文件**。
- `SeoHelper::modelUrl($id, $nickname)` 是全站**唯一**模特 URL 生成器，返回 `https://mall.58.tl/model/{id}-{slug}.html`；调用点至少 8 处（`mall/index.php`、`mall/model/*`、`mall/product/detail.php`、`mall/rankings/index.php`、`mall/admin/models.php`、`includes/city-portal-render.php`、`sitemap.php`）。
- 伪静态规则同时存在于 `mall/.htaccess`（Nginx 下不生效）与 `docs/nginx-rewrite.conf`（实际生效），两者都需同步。
- 已有成熟先例：`bid.58.tl` 为**独立子站 + 独立暗调主题 + 只作用内容区**（`openspec/changes/auction-hall-redesign/`）；`bid/` 为同级目录 + 独立 nginx server 块。
- 已有申请闭环：`classes/Application.php` + `mall/apply/model.php` + `mall/apply/my.php` + `mall/admin/applications.php`，状态机 `pending/contacted/approved/rejected`，照片直落 `assets/uploads/models/YYYYMM/`。
- 无构建链、无常驻进程、无 Redis；每页各自内联 `<style>`；共享 header 位于 `shared/header.php`（通过 `$site_config` 驱动），子站可复用。
- 已知缺陷：`model/view.php` 内既有 `$shareUrl`、`$dailyJsArr` 等未定义/依赖隐式顺序的变量；`mall/model/style.css` 与 `mall/author/style.css` 平行重复。

## Goals / Non-Goals

**Goals:**

- 让模特库成为**独立站点**：独立域名、独立导航、独立视觉，与商城解耦，与 `bid.` / `club.` / `nft.` 保持同级子站的一致模式。
- 补齐**短剧参演**能力，形成「模特 × 短剧」双向导流的内容结构，这是子站区别于普通模特展示站的核心钩子。
- 首页做到「引人入胜」：视频优先的全屏 Hero + 主推模特 + 热播短剧 + 新人引导，四段式叙事。
- 视觉走**亮调**，衬托人物；与 `mall` 电商橙、`bid` 暗场刻意区分。
- 迁移**不丢 SEO**：老 URL 全量 301，`SeoHelper` 单一来源收敛，sitemap 更新。

**Non-Goals:**

- 不做视频上传与转码存储——视频仅为管理员挂的 URL（直链或外链），无 CDN/OSS 接入、无 HLS 切片。
- 不做短剧的抓取与自动同步——红果地址仅为外链字段，不爬取剧名/封面，全部人工录入。
- 不做模特自助编辑资料——资料仍由管理员在后台维护（与现状一致），仅申请入口迁移。
- 不做作者库（`authors`）的同步抽取——本次只抽模特；作者库是否抽取另立变更。
- 不改 `classes/Model.php` 的类名与既有方法签名，不重构 `Application` 状态机。
- 不做评论/弹幕/点赞以外的社交功能，不做短视频 Feed 流。

## Decisions

### 1. 子站形态：同级目录 `model/` + 独立域名 `model.58.tl`

新增仓库同级目录 `model/`，Nginx 新增 `server_name model.58.tl`、`root .../58blockcity/model`。与 `bid/`、`club/`、`nft/` 完全同模式。

- 为什么不用 `mall/model/` 换皮：诉求明确是「独立子站」「参考优秀模特站点独立设计」，换皮仍是商城栏目，无法做独立导航与品牌感，且 mall 的 header/导航（商品浏览、购物车）与模特浏览动机冲突。
- 为什么不另起仓库：`classes/`、`shared/`、`assets/uploads/` 全部要复用，分仓会引入跨仓同步成本，仓库内其他子站均为同仓同级目录。
- 为什么目录名用 `model` 而非 `models`：与现有 `mall/model/` 命名和 `Model` 类保持一致，减少认知负担。

### 2. URL 治理：path 简化为 `/m/{id}-{slug}.html`，全量 301

| 类型 | 新规范 URL |
|------|-----------|
| 模特详情 | `https://model.58.tl/m/{id}-{slug}.html` |
| 模特列表 | `https://model.58.tl/list.php`（筛选参数照旧） |
| 短剧详情 | `https://model.58.tl/drama/{id}-{slug}.html` |
| 短剧列表 | `https://model.58.tl/dramas.php` |
| 排行榜 | `https://model.58.tl/rankings.php` |
| 申请 | `https://model.58.tl/apply.php` |
| 首页 | `https://model.58.tl/` |

老 URL 301（在 `mall` 的 server 块内，`try_files` 之前）：

```nginx
# mall.58.tl → model.58.tl 永久迁移
rewrite ^/model/list\.php$            https://model.58.tl/list.php permanent;
rewrite ^/model/([0-9]+)-.*\.html$    https://model.58.tl/m/$1 permanent;
rewrite ^/apply/model\.php$           https://model.58.tl/apply.php permanent;
rewrite ^/apply/my\.php$              https://model.58.tl/my.php permanent;
```

- 为什么用 `/m/` 而非 `/model/`：新域名下 `model.58.tl/model/1-x.html` 语义冗余；`/m/` 更短、更接近优秀模特站的做法（如 `/p/`、`/u/`）。你已确认 URL 可改变。
- 为什么 301 落在 `mall` 侧而非 `model` 侧：老 URL 的 Host 是 `mall.58.tl`，只有 mall 的 server 块能接住。
- 为什么不保留旧 URL「完全等价」路径：会失去独立子站的 SEO 归属感（canonical 指向 mall 域名），与独立子站目标矛盾。
- `SeoHelper::modelUrl()` 改为返回新域名新格式，并新增 `dramaUrl()`。这是**唯一**生成源，改一处全站收敛。

### 3. 视觉：亮调 + 独立 token，`.model-shell` 作用域

新增 `model/assets/css/model.css`，CSS 变量定义 token：

```
--bg        #FAFAF8   浅暖白底（衬托人物，不用纯白刺眼）
--surface   #FFFFFF   卡片
--surface-2 #F4F3F0   次级面
--line      #E8E6E1   分隔线
--text      #1A1A1A   主文本
--muted      #7A7A75   次文本
--brand     #E8467C   强调色（玫粉，取「时尚/镜头」语义）
--brand-ink #B52D5C   强调色深阶（hover/按下）
--gold      #C9A227   荣誉/排行榜语义（克制使用）
```

容器加作用域类 `.model-shell` 包裹内容区；沿用 `shared/header.php` 但通过 `$site_config` 覆写 `logo_sub=模特库`、`logo_tag=58 模特 · 短剧`、`theme_color=#E8467C`、`nav_links` 为子站导航。

- 为什么亮调：模特展示依赖皮肤与服装的色彩还原，暗底会造成视觉晕影、显得廉价；你已确认走亮调。
- 为什么品牌色用玫粉而非橙：橙色已被 mall（电商橙）与 bid（琥珀）占用；玫粉在模特/时尚语境自然，且与「摄像机红」同族。
- 为什么不用纯白 `#FFFFFF` 作底：纯白与人物肤色对比过强，浅暖白 `#FAFAF8` 观感更高级。
- 为什么复用 `shared/header.php`：仓库已有统一导航组件（通知、站内信、用户菜单全在其内），自建会重复维护并丢失登录态展示；`bid` 已验证「共享 header + 子站主题只作用内容区」可行。

### 4. 短剧建模：`dramas` 主表 + `model_dramas` 关联表（N:M）

```
dramas
  id, title, slug, cover, episodes, tags(JSON), hg_url, synopsis,
  status(active/inactive), created_at, updated_at

model_dramas
  id, model_id, drama_id, role_name, is_lead, sort_order, created_at
  UNIQUE(model_id, drama_id), KEY(drama_id)
```

- 关键理由：一部短剧天然有多个模特参演（红果页面本身也是「演员 → 多作品」），一对多会强制重复录入剧名/封面；N:M 才能支撑「短剧详情页聚合全部参演模特」这一反向导流入口。
- 反例（已排除）：`models.dramas` 存 JSON 一坨——无法「按剧找演员」、无法做短剧聚合页、剧名重复无一致性。
- `role_name` 对应红果的「饰演 XX」，`is_lead` 支持「主演」标识，`sort_order` 供人工排主演在前。
- `tags` 用 JSON 数组（与 `models.daily_photos` 的存储风格一致），便于后续按题材筛选；展示层 `json_decode` 后渲染 chips。
- `models` 新增 `drama_count` 冗余计数，在关联增删时同事务维护，列表页/卡片直接读，避免 N+1 聚合。

### 5. 视频：仅管理员挂 URL，前端三级降级

`models` 新增 `video_url`（varchar 500）与 `video_cover`（varchar 255，视频封面/降级图）。

前端降级链：

```
video_url 是直链（.mp4/.webm）  → <video src poster=video_cover muted loop playsinline>
video_url 是外链（含 ?/iframe）  → <iframe src>（懒加载）
video_url 为空 / 加载失败        → <img src=video_cover || avatar>（大图 Hero）
```

- 为什么不做上传：你已确认仅挂 URL；视频体积大、需转码与 CDN，成本远超当前收益。
- 为什么必须带 `video_cover`：Hero 在视频加载失败或移动端省流场景下需要兜底图，否则首屏出现黑洞，直接违背「引人入胜」。
- 为什么默认 `muted`：浏览器自动播放策略要求静音，同时避免访客被声吓到；提供显式静音/播放切换。
- 为什么不用第三方 `<script>` 播放器 SDK：引入外部依赖与体积，且直链/外链两种形态用原生标签即可覆盖。

### 6. 首页 Hero 主推选取：视频优先，其次粉丝数

```sql
ORDER BY (video_url IS NOT NULL AND video_url <> '') DESC,
         follower_count DESC
LIMIT 1
```

- 理由：你已确认「有视频优先，无视频时按粉丝数」。保证 Hero 在绝大多数时候有动态素材，同时退化路径仍有运营意义（粉丝最高者）。
- 兜底：完全没有活跃模特时，Hero 渲染静态品牌区（站点名 + 引导 CTA），不出现破图。

### 7. 首页结构：四段式叙事

```
① 全屏 Hero     视频/大图 + 模特名 + 简介 + 城市 + 粉丝 + [查看主页]
② 本期主推模特   横向滑轨 5–6 位（视频优先，其次粉丝数）
③ 正在热播短剧   横向滑轨：剧封面 + 剧名 + 集数 + 参演模特头像堆叠
④ 发现模特       筛选 chips（性别/城市/星座）+ 瀑布流 + 加载更多
⑤ 新人加入引导   三步说明 + CTA → /apply.php
```

- 为什么把「发现模特」放在第 4 段而非 Hero 下方：首页首要任务是「引人入胜」（内容吸引），搜索筛选是「已有明确目的」用户的工具，放底部符合浏览漏斗。
- 为什么「热播短剧」紧跟主推模特：它是本站独有内容，承载「追剧 → 认识模特 → 关注」的转化路径。

### 8. 短剧详情页：聚合参演模特（差异化入口）

`/drama/{id}-{slug}.html` 展示：封面大图、剧名、集数、题材标签、简介、红果观看入口（外链 `target=_blank rel="nofollow noopener"`）、**参演模特网格**（按 `is_lead DESC, sort_order ASC`）点击进入模特页。

- 为什么这是关键设计：红果是「平台本位」（演员只是剧的附属），本子站可以做「模特本位」——剧是模特的履历，用户从剧认识人、去关注人，形成回流。这是竞品不具备的结构。
- 外链必须 `rel="nofollow noopener"`：避免权重外泄与安全风险。

### 9. 排行榜：复用既有冗余计数，新增短剧维度

四个维度：`follower_count`（粉丝）、`like_count`（人气）、`drama_count`（参演短剧）、`product_count`（作品）。复用 `models` 已有索引 `idx_like_count` / `idx_product_count`，为 `follower_count`、`drama_count` 补索引。

- 为什么不新建排行快照表：现有 `getRanking()` 已是直查排序，模特量级（百级）不需要预计算。
- 与 `mall/rankings/` 的关系：那是**商城**排行（店铺/商品），模特排行放子站更内聚。

### 10. 迁移策略：数据零迁移 + 应用层收敛

- **数据层**：`models` 表原地增列（`video_url` / `video_cover` / `intro` / `drama_count`），**不搬运数据**；`model_likes` / `model_follows` / `applications` 表全部沿用（`user_id` 语义不变，登录态跨子站共享 Cookie）。
- **应用层**：新增 `model/` 目录页面（大部分逻辑从 `mall/model/*` 平移后重写）；`classes/Model.php` 增量加方法（`getDramas()`、`getHeroModel()`、`getTopDramas()`、榜单查询等），**不改类名、不动既有方法**。
- **引用层**：`SeoHelper::modelUrl()` 一处改域名与 path，全站 8 处调用点自动收敛；余下 4 处硬编码（`mall/index.php` 的 `model/list.php` 链接、`mall/user/following.php`、`includes/city-portal-render.php`、`llms.txt` / `mall/llms.txt`）手动改为绝对地址。
- **旧页面处置**：`mall/model/` 整个目录**保留一个版本周期**（列表页与详情页改为 301 跳转，或直接删除依赖 nginx 301），确认无残留跳转后再删。
- 为什么不做数据迁移脚本：表结构本就共用，无跨库搬迁需求，新增列有默认值即可，风险最低。

## Risks / Trade-offs

- [301 链式跳转 / 漏配规则] → nginx 与 `.htaccess` 双份规则同步；上线后抽样旧 URL（列表、详情、申请、我的申请）验证单跳；`sitemap.php` 同步输出新域名避免旧 URL 被重新收录。
- [`SeoHelper::modelUrl()` 改动影响面广] → 改动前后 `grep` 全量调用点核对；对 `mall/rankings/index.php`、`mall/product/detail.php` 等页面做链接回归（点击不 404）。
- [`mall/index.php` 人气模特位跨域跳转] → 改为绝对地址，社交/SEO 上均为外链跳转，需确认不影响 mall 首页的站内停留指标（可接受，本就是导流位）。
- [登录态跨子站] → `$_SESSION` 受 Cookie domain 限制，若各子站 Cookie 作用域为各自域名，则 `model.58.tl` 上用户可能未登录（关注/申请需重新登录）。**上线前须核对 session cookie domain 配置**，必要时统一为 `.58.tl`（这是迁移中最容易被忽略的坑）。
- [视频外链不可控] → 第三方外链可能失效或被防盗链；Hero 必须有封面图兜底，且失败静默降级不报错。
- [亮调对比度/无障碍] → `--text` 对 `--bg` 需达 WCAG AA；玫粉 `#E8467C` 不作唯一信息载体（状态同时有文案/图标）；移动端首屏保证 Hero 与主推模特可见。
- [`mall/model/` 与 `model/` 双份代码短期共存] → 明确「新目录为唯一事实来源」，旧目录仅作 301 壳，避免两边同时改产生分叉；在一个版本周期内清理。
- [短剧录入全靠人工] → 短剧字段较多（封面、集数、标签、外链），后台表单要做分组与默认值，避免录入摩擦导致数据稀疏；空数据时模特页「参演短剧」区块整体隐藏，不显示空壳。

## Migration Plan

1. 执行 `init/migrate-model-subsite.sql`：`models` 增列（带默认值，幂等判存在）、新建 `dramas` / `model_dramas`，并同步 `init/db-init.sql`。此时新列/表未被使用，可独立验证。
2. 部署 `classes/Drama.php` 与 `classes/Model.php` 增量方法、`SeoHelper::modelUrl()` / `dramaUrl()` 改动，以及后台 `mall/admin/dramas.php` 与 `mall/admin/models.php` 的字段扩展。可先在后台录入视频与短剧数据。
3. 部署 `model/` 子站页面与 `model/assets/`，新增 nginx `model.58.tl` server 块并 reload。此时子站可独立访问验证。
4. 部署 mall 侧 301 规则（`.htaccess` + nginx），并同步 `mall/index.php`、`mall/user/following.php`、`mall/product/detail.php`、`mall/rankings/index.php`、`includes/city-portal-render.php`、`sitemap.php`、`llms.txt` 的链接。验证旧 URL 单跳、新链接无 404。
5. 观察一个版本周期后清理 `mall/model/` 目录与 `mall/apply/` 中的模特相关页面。
6. 回滚策略：先回滚 nginx（移除 301 与子站 server 块），`mall/model/` 原页面立即恢复可用；数据层新增列/表为非破坏性，无需回滚；`SeoHelper` 改动需与页面部署同批回滚。

## Open Questions

- session cookie 的 domain 当前配置是什么？若为各子站独立，需先统一为 `.58.tl` 才能让 `model.58.tl` 共享登录态（这是上线前置条件，非设计选择）。
- 首页 Hero 是否需要「运营可配置主推位」（后台指定某位模特置顶），还是纯自动规则（视频优先 + 粉丝数）？当前按自动规则实现。
- 短剧「题材标签」是否需要受控词表（下拉选择）还是自由输入？当前按自由输入 + 逗号分隔录入、后端存 JSON。
- 模特详情页的「关联商品」区块在独立子站是否保留（模特本位 vs 商城导流的平衡）？当前保留，展示为「TA 推荐」并跳转 `mall.58.tl`。
- `mall/model/style.css` 与 `mall/author/style.css` 的重复是否借本次一并收敛为共享样式（需同步改动作者库，可能扩大范围）？当前不动，另立变更。
