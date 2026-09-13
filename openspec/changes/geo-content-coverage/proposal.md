# 补齐 GEO 实体覆盖与内容结构化

## Why

`site-geo-optimization` 变更已建立了 GEO 的基础设施（`llms.txt`、`robots.txt` AI 放行、`shared/organization.php` 全局实体、11 个结构化数据方法、帮助中心内容改造）。但落地覆盖并不完整——基础设施建好了，多数子域**没有真正接入**。

经全站核查，当前 GEO 存在以下具体缺口：

1. ~~**全局实体注入仅覆盖 2/10 个入口**~~ **（核查修正：实际无缺口）**：`orgJsonLd` 由 `shared/header.php:112` 统一输出，而 `bct` / `bid` / `club` / `hufang(v)` / `mall` / `model` / `nft` / `task` 共 8 个子域的 header 均 `require` 了 `shared/header.php`，因此**实体注入已全覆盖**（`block` 为独立实现，已自行注入）。此前的「仅 2/10」为误判——直接检索变量名而未跟踪 include 链所致。

2. **`llms.txt` 缺 3 个子域**：根域、`block` / `bct` / `mall` / `nft` / `bid` / `club` 已有，但 `model` / `task` / `v` 缺失，AI 到这三个子域找不到站点说明。

3. **`sameAs` 已清空后无明确替代策略**：上一轮变更移除了 GitHub 地址（用户决策）。用户提供微信公众号 ID `www58tl`，但**公众号 ID 不是可解析 URL**（schema.org 要求 `sameAs` 为 URL），故应写入 `ContactPoint.identifier` 而非 `sameAs`。

4. ~~**结构化数据覆盖不均**~~ **（核查修正：实际已覆盖）**：逐页核查结果——`city.php`（经 `includes/city-portal-render.php`）与 `block/city.php` 均输出 `Place`/`City`；`rankings/rankings.html` 输出 `CollectionPage` + `ItemList`；`rankings/gdp-total.html` 输出 `Table` + `ItemList`；`top100city.html`、`news.php`、`index.php`、`mall/product/detail.php`、`mall/shop/view.php`、`model/view.php`、`nft/nft/view.php`、`club/post.php` 均有结构化数据。**无「方法已备、页面未用」缺口。**

5. **静态页结构化数据为手工内联，存在描述不一致**：`help/*.html`、`rankings/*.html`、`news/*.html` 等静态页的 JSON-LD 是硬编码字符串（无模板可 include），与 `shared/organization.php` 存在**双份维护**风险。已实测发现不一致：**15 个静态页描述为「八个子域」（无 model），而 `organization.php` 为「九个子域」（含 model）**，且均未包含 `task` 子域。

**重要说明**：本变更是 **GEO（生成式引擎优化）**，与百度收录**无关**。百度不解析 JSON-LD，不参与实体归一。提升百度收录应执行 `fix-baidu-push-pipeline` 与 `per-subsite-baidu-indexing`。本变更的价值在于 ChatGPT / Perplexity / 豆包 / Kimi 等生成式引擎的引用与归因。

## What Changes

- ~~补齐全局实体注入~~ **（核查后确认无需改动，已由 `shared/header.php` 统一覆盖）**
- **补齐 `llms.txt`**：为 `model` / `task` / `v` 三个子域编写 `llms.txt`，指向主实体。
- **消除实体双份维护**：统一静态页与 `shared/organization.php` 的实体描述，修正「八个子域 / 九个子域」等不一致。
- **处理公众号标识**：将公众号 ID `www58tl` 写入 `ContactPoint.identifier`（非 `sameAs`，因其不可解析为 URL），并在文档中记录 `sameAs` 暂不声明的决策。
- **核查并补齐结构化数据**：逐页核查城市页、排行榜页、列表页的 `Place` / `ItemList` / `Dataset` 覆盖，补齐「方法已备但未使用」的页面。
- **建立 GEO 度量基线**：从服务器 access log 统计各 AI Bot（`GPTBot` / `Bytespider` / `PerplexityBot` 等）的访问量与抓取路径，作为效果对照。

## Capabilities

### New Capabilities

- `seo/geo-entity-coverage`: 全局品牌实体在各子域的注入覆盖率与一致性。
- `seo/geo-content-discovery`: `llms.txt` 对各子域的覆盖与引荐完整性。
- `seo/structured-data`: 结构化数据在实际页面中的生效覆盖与输出有效性。

### Modified Capabilities

<!-- 无：site-geo-optimization 尚未归档，其 seo/* 能力未进入主规格，
     故本变更对应的结构化数据要求以独立能力增量表达，不做 MODIFIED。 -->

## Impact

**修改文件**

- `shared/organization.php` — 实体描述统一（子域清单）、公众号写入 `ContactPoint`
- `block/includes/header.php` — 与 `shared/organization.php` 对齐（若描述不一致）
- `help/*.html`（9 篇）、`rankings/*.html`（2 篇）、`news/*.html`、`top100city.html` — 内联 JSON-LD 与全局实体对齐
- `city.php` / `block/city.php` / `rankings/*.html` — 结构化数据补齐（按核查结果）

**新增文件**

- `model/llms.txt`、`task/llms.txt`、`v/llms.txt`

**依赖与风险**

- 无新增外部依赖；纯服务端渲染与静态文件改动。
- 无 **BREAKING** 变更。
- **公众号 ID 处理**：`www58tl` 不是 URL，写入 `ContactPoint.identifier`，不进 `sameAs`。
- **不影响百度收录**：本变更对百度收录零影响，勿与百度变更混淆。
