## Why

模特库目前在 `mall.58.tl/model/` 下作为**商城的一个栏目**存在：视觉上沿用电商橙 `#ff6b00`（与商品列表无差别）、没有独立品牌感、首页无处承载「模特」这一内容品类的吸引力。与此同时，模特已开始参演短剧（如红果短剧），但现有 `models` 表完全没有承载「参演作品」的结构——短剧信息无处录入，更无法形成「模特 × 短剧」的内容联动，错失了一个天然的内容钩子。

模特业务（展示、关注、申请）与商城商品业务（购物车、BCT 支付、订单）在目标用户与浏览动机上差异明显：前者是「看人、追人、追剧」，后者是「买东西」。继续共用一个站点的视觉与信息架构，会同时拖累两边。因此把模特库抽为独立子站 `model.58.tl`，重做视觉与首页，并补齐短剧参演能力。

## What Changes

- **BREAKING** 模特库从 `mall.58.tl/model/` 抽取为独立子站 `model.58.tl`（与 `bid.` / `club.` / `nft.` 同模式）。老的 `mall.58.tl/model/*` 与 `mall.58.tl/apply/model.php` 全部 301 跳转到新域名对应地址，保留 SEO 权重。模特 URL 格式由 `model/{id}-{slug}.html` 改为 `/m/{id}-{slug}.html`。
- **SHALL 新增短剧数据能力**：新增 `dramas`（短剧主表）与 `model_dramas`（参演关联表，含角色名、是否主演、排序），由**后台管理员**在模特编辑页与短剧管理页录入。短剧字段含剧名、封面、集数、题材标签（JSON）、红果地址、简介。
- **SHALL 新增短剧详情页**：`/drama/{id}-{slug}.html` 聚合该剧全部参演模特，反向为模特导流（红果不具备的「模特本位」差异化）。
- **SHALL 重做子站视觉为亮调**：浅底 + 高级灰 + 单一高饱和强调色，独立于 `mall` 的电商橙与 `bid` 的暗场聚光灯；不复用 mall 的 header，子站自建导航（首页 / 模特库 / 短剧 / 排行榜 / 申请加入）。
- **SHALL 重做首页为「引人入胜」的门面**：全屏 Hero（模特视频优先，无视频降级大图）→ 本期主推模特横排 → 正在热播短剧滑轨 → 发现模特（筛选 + 瀑布流）→ 新人加入引导 CTA。
- **SHALL 新增模特排行榜页**：按粉丝数 / 点赞数 / 参演短剧数 / 作品数多维排行。
- **SHALL 支持模特视频**：`models` 新增 `video_url` 与 `video_cover`，仅由管理员后台挂 URL（直链 mp4 优先，兼容 iframe 外链，最终降级封面图），不新建视频上传存储。
- **SHALL 支持新人引导**：现有申请闭环（`Application` + `mall/apply/model.php`）迁移为子站 `/apply.php`，并在首页设置引导入口。
- **同步修改**：`SeoHelper::modelUrl()` 改为指向 `model.58.tl`；`mall/index.php` 人气模特导流位改绝对链接；`mall/user/following.php`、`mall/product/detail.php`、`mall/rankings/index.php`、`includes/city-portal-render.php`、`sitemap.php`、`llms.txt` 等引用点同步。`classes/Model.php` 类名**不改**（被 `Product` / admin / user 多处引用），仅在子站新增独立类与方法。

## Capabilities

### New Capabilities

- `model/site-shell`：模特子站外壳——独立导航、亮调主题 token、布局与响应式规范、共享 header/footer 复用策略。
- `model/home-landing`：首页门面——全屏 Hero（视频优先降级链）、本期主推模特、热播短剧滑轨、发现模特入口、新人加入引导。
- `model/model-directory`：模特库浏览——迁移自 `list.php` 的能力，筛选（性别/城市/星座）、排序、瀑布流网格、加载更多、关注。
- `model/model-profile`：模特个人页——重设计资料区、视频/大图展示、图集、参演短剧、关联商品、留言、关注与分享。
- `model/drama-catalog`：短剧体系——`dramas` + `model_dramas` 数据模型、短剧详情页（聚合参演模特）、短剧列表/滑轨。
- `model/model-ranking`：模特排行榜——粉丝 / 点赞 / 参演短剧 / 作品多维排行。
- `model/join-guidance`：新人加入引导——子站申请入口、首页引导区块、申请状态查询。
- `model/subsite-migration`：子站迁移与 URL 治理——新旧 URL 映射、301 规则、SEO 引用点同步、canonical 与 sitemap 更新。

## 影响范围

- **新增文件**：
  - `init/migrate-model-subsite.sql`（`models` 增列 + `dramas` + `model_dramas` 建表，幂等）
  - `model/`（子站目录）：`index.php`、`list.php`、`view.php`、`drama.php`、`dramas.php`、`rankings.php`、`apply.php`、`follow.php`、`card.php`、`includes/header.php`、`includes/footer.php`、`assets/css/model.css`、`assets/js/*.js`
  - `classes/Drama.php`（短剧 CRUD + 参演关联）
  - `mall/admin/dramas.php`（短剧管理）
- **修改文件**：
  - `classes/SeoHelper.php`（`modelUrl()` 改域名，新增 `dramaUrl()`）
  - `init/db-init.sql`（同步表结构）
  - `docs/nginx-rewrite.conf`（新增 `model.58.tl` server 块 + mall 301 规则）
  - `mall/.htaccess`（301 规则）
  - `mall/index.php`、`mall/user/following.php`、`mall/product/detail.php`、`mall/rankings/index.php`、`includes/city-portal-render.php`、`sitemap.php`、`llms.txt`
  - `mall/admin/models.php`（增视频字段 + 参演短剧录入区）
- **数据库变更**：`models` 增 `video_url` / `video_cover` / `intro` / `drama_count`；新增 `dramas`、`model_dramas` 两表。

## 成功标准

1. `model.58.tl` 首页在无视频模特存在时也能完整渲染（降级封面图），有视频时优先展示视频且不自动播放带声。
2. 访问老地址 `mall.58.tl/model/list.php` 与 `mall.58.tl/model/{id}-{slug}.html` 均 301 到新域名对应地址，无 404、无链式跳转。
3. 管理员可在后台为模特挂视频 URL、录入参演短剧（选择已有剧或新建剧），保存后模特页展示「参演短剧」区块。
4. 短剧详情页正确聚合该剧全部参演模特，并可反向跳转。
5. 模特排行榜四个维度排序正确，空数据不出现破损布局。
6. 未登录用户点击关注/申请跳转登录，登录后回到原子站页面。
7. 全站不存在指向 `mall.58.tl/model/` 的残留链接（`SeoHelper::modelUrl()` 为唯一生成源）。
8. 全部输入输出 `htmlspecialchars` 防 XSS，SQL 走 PDO 预处理。

## 参考

- `openspec/changes/add-model-library/`、`add-model-discovery-board/`、`model-author-apply/` — 模特库与申请的历史设计与现状
- `openspec/changes/auction-hall-redesign/` — 子站独立主题（暗调）先例与 spec 组织方式
- `docs/nginx-rewrite.conf` — 现有子站 server 块与伪静态规则
- `classes/Model.php` — 现有模特数据层（不改类名，增量扩展）
- `classes/Application.php` — 现有申请闭环
- `mall/admin/models.php` — 模特录入表单与 GD 上传模式
- `https://hongguoduanju.com/character/7560259432706217278` — 红果短剧艺人页信息结构参考
