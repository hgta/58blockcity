# 统一城市门户页：动态抽取子站内容，替代静态 HTML

## 问题陈述

城市页当前分两层：

1. `www.58.tl/city/{pinyin}.html` 由 `city.php` 处理：**有静态文件**（`city/` 下 67 个 HTML）→ 读文件并正则替换 4 个数字；**无静态文件** → 输出极简兜底页。静态 HTML 为一次性人工生成、无生成脚本、无内容更新通道，67 城中仅约 26 城有「真实城市数据指标/特色/介绍」，其余 133 城（DB 共 200 城）只能看到单薄兜底页。
2. 六条子站内容线（区块 block / NFT nft / BCT bct / 社区 club / 互访圈 hufang(v) / 商城 mall）各自独立，**没有任何一个入口按城市聚合**展示「这座城市在 58 生态里发生了什么」，跨线用户/SEO 价值缺失。
3. `cities` 表只存元宇宙数据（rank/resident_count/activated_blocks/current_balance/area_code/popularity），**无真实城市资料字段**，页面无法低成本承载人口/GDP/地标/介绍等充实内容。
4. 部分 `blocks` 区块已有「命名/皮肤」内容，NFT、圈子、club 帖子等均有城市维度数据，但缺少统一读取与呈现层。

## 目标

把城市页改造成「58 生态城市门户」：任意城市由**统一模板动态生成**，内容 = 主库元宇宙关键数据 + 真实城市资料 + 六条子站聚合模块，数据缺失自动优雅降级；静态 HTML 不再使用。

- 一套 `city.php` 渲染逻辑覆盖全部城市（不再有"富静态页 / 兜底页"两种形态）。
- 页面区块：城市 hero 关键数据（4 指标）→ 真实城市资料卡（`city_profiles`，缺失即隐藏）→ 🏘 区块街景（最新命名区块）→ 💰 BCT 行情 → 🖼 NFT 热卖 → 💬 同城动态（club）→ 🔄 互访圈 → 🛍 城市好店（mall）→ SEO 长文本（城市详细介绍，可选）。
- 每个聚合模块允许空态：「该城暂无xx，去逛逛」，页面仍完整渲染。
- 生成结果落静态缓存，配合 cron/定时脚本重生成（性能 + 可控更新）。
- 删除 `city/*.html` 静态文件（URL 由 nginx rewrite → `city.php` 承载，路径不变）。

## 范围

### In Scope

| 模块 | 内容 |
|------|------|
| 数据模型 | 新增 `city_profiles` 表（city_id 1:1，存真实城市资料：面积/人口/GDP/特色/介绍 JSON 等）；城市名↔city_id/拼音换算工具类 |
| 聚合数据层 | 新增 `classes/CityPortal.php`：统一装配「该城 6 子站聚合数据 + 空态判断」；分别复用/新增各子站查询（blocks、block_listings、nft、bct、club、hufang、mall） |
| 页面渲染 | 重写 `city.php` 为统一模板渲染器（含 SEO meta/schema 沿用现有模式 B 骨架）；将 `city/` 下静态 HTML 从分支中移除 |
| 模板与样式 | 基于 `city/beijing.html` 现有视觉体系 + `city/city.css`，制作城市门户聚合模板与新增 CSS |
| 资料采集 | 新增离线采集器：从公开结构化源（默认 Wikidata SPARQL：面积/人口/GDP；备选中文维基百科摘要补简介）一次性抓取全量 200 城真实资料，产出 JSON 落 `data/city-profiles/` |
| 缓存与重生成 | `city.php` 优先读 `city/cache/{pinyin}.html`；新增 CLI 脚本 `city/build-static.php` 支持全量/单城重生成 |
| 资料补充 | 采集结果 JSON → 同步入库 `city_profiles`（upsert by city_id）；提供 admin 手工/CSV 校对入口；不再沿用旧富静态页里可能过时的人工文案 |

### Out of Scope

- 不做「实时每次请求在线爬取网页」（合规与稳定性）；采用**一次性/低频离线批量采集**，产出 JSON 由同步脚本入库，运行节奏由人控制。
- 数据质量以「结构优先、缺失留空」为准：自动源无法可靠取到的字段（如部分美食/地标类富文案）不强造，交由 admin 人工校对补充。
- 不接管各子站自身页面（block/bct/nft/club/hufang/mall 渲染逻辑不动），门户只做「摘要抽取 + 深链」。
- 不做个性化（无登录态差异）。
- 不新增地图大图（现只有北上杭深 map/ 图），区块地图区块以「按区聚合 mini 卡/列表」代替，图片缺失时用文字卡。

## 影响范围

- **新增文件**：
  - `init/migrate-city-profiles.sql`
  - `classes/CityPortal.php`
  - `classes/CityKey.php`（城市名/拼音/区号 ↔ city_id 换算 + 名称清洗）
  - `city/build-static.php`（CLI/定时重生成）
  - `tools/import-city-profiles.php`（一次性从现有富静态页抽取资料）
  - `assets/css/` 或 `city/city-portal.css`（聚合门户新增样式）
- **修改文件**：
  - `city.php`（统一渲染模板，删除静态文件读取分支）
  - `includes/` 或页面骨架按需引用新 CSS
  - `city/city.css`（如需统一视觉变量，尽量最小改动）
- **数据库变更**：新增 `city_profiles` 表
- **删除文件**：`city/{67个}.html`（Git 移除，URL 依赖 rewrite 不变）

## 成功标准

1. `city/beijing.html`（及任意城市）经 rewrite 后返回 200，由 `city.php` 动态渲染；页面含 4 指标 + 至少 4 个聚合模块 + 资料卡。
2. 页面结构统一：全站城市（含 DB 有、无静态文件的 200 城）使用同一模板，无内容的城市不报错、模块显示空态。
3. `city/` 静态 HTML 删除后，全部原 URL 仍正常（经 `city.php`），无 404 回退到兜底形态。
4. 北京页聚合数据与各子站真实数据一致（区块最新命名来自 `blocks`、BCT 行情来自 `city_bct`、club 帖子来自 `posts`、圈子来自 `circles`）。
5. 资料卡：北京等已采集城市显示真实城市资料（含 `data_year` 标注）；未采集城市资料卡自动隐藏或显示「补充中」。
6. 缓存生效：首次访问生成 `city/cache/beijing.html`，后续直接读缓存；`city/build-static.php beijing` 可强制重生成。
7. 安全：所有输出 `htmlspecialchars`，SQL 全部 PDO 预处理，静态文件写入路径白名单校验。

## 参考

- `city.php` — 现有双模式逻辑（模式 B 的 SEO/骨架可复用）
- `city/beijing.html` — 富静态页结构（资料卡/特色/地图/详细介绍的 section 样式与文案来源）
- `city/city.css`、`city/city.js` — 现有样式与交互
- `classes/City.php`、`classes/CityBCT.php`、`classes/Block.php`、`classes/BlockListing.php`、`classes/NFT.php`、`classes/Post.php`、`classes/Circle.php`、`classes/Model.php`、`classes/Author.php` — 现有按城市查询能力
- `docs/nginx-rewrite.conf` — city 路由与子站域名映射
