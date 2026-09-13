# 任务清单：补齐 GEO 实体覆盖与内容结构化

## 1. 补齐 llms.txt（P0）

- [x] 1.1 `model/llms.txt`：编写完成，声明主实体、模特库/短剧/排行榜入口；验证：含 `https://www.58.tl/` 主实体声明 ✅
- [x] 1.2 `hufang/llms.txt`（v 域）：编写完成（v.58.tl 与 www 共用 root，文件置于 hufang/）；验证：结构一致 ✅
- [x] 1.3 `task/llms.txt`：用户确认 task.58.tl 对外开放，按公开子域编写；验证：策略与域定位一致 ✅
- [x] 1.4 根 `llms.txt`：同步补充 `task` 子域，子域清单更新为十个；验证：✅ 已更新

## 2. 子域全局实体注入（P0 —— 核查后确认已覆盖，无需改动）

- [x] 2.1 核查结论：`shared/header.php:112` 统一输出 `orgJsonLd`；`bct`/`bid`/`club`/`hufang`/`mall`/`model`/`nft`/`task` 共 8 个 header 均 `require` 了 `shared/header.php`，注入已全覆盖；`block` 为独立实现，已自行 `require shared/organization.php` 并输出。**无需代码改动。**
- [ ] 2.2 上线后抽查各子域首页源码，确认含 `@id: https://www.58.tl/#organization`；验证：9/9 子域均有该锚点

## 3. 统一实体描述（P1）

- [x] 3.1 批量修正 15 个静态页内联 JSON-LD 的子域描述，统一为「涵盖 www、block、bct、mall、model、nft、v、bid、club、task 十个子域」；验证：`grep -rl "八个子域"` 无残留 ✅
- [x] 3.2 `shared/organization.php` 描述同步为十个子域（含 model、task）；验证：与静态页一致 ✅
- [ ] 3.3 校验静态页与 `organization.php` 的关键字段（description / alternateName / url / logo）一次性比对无差异；验证：产出比对结果
- [x] 3.4 `shared/organization.php`：更新维护约定注释（公众号非 URL 不进 sameAs；静态页需同步）；验证：注释已说明 ✅

## 4. 公众号与 sameAs 处理（P1）

- [x] 4.1 用户提供公众号 ID `www58tl`；验证：信息明确 ✅
- [x] 4.2 因公众号 ID **不是可解析 URL**（schema.org 要求 `sameAs` 为 URL），写入 `ContactPoint.identifier` + `name`（客户服务类型）；验证：`organization.php` 含 `identifier: www58tl` ✅
- [x] 4.3 在 `organization.php` 注释中记录「当前无其他可引用的自有平台主页，`sameAs` 暂不声明」的决策；验证：决策留痕 ✅

## 5. 结构化数据核查（P1 —— 核查后确认已覆盖）

- [x] 5.1 核查 `city.php` 与 `block/city.php`：均输出 `Place`/`City`（前者经 `includes/city-portal-render.php:192`）；验证：✅ 已覆盖
- [x] 5.2 核查榜单页：`rankings/rankings.html` 有 `CollectionPage`+`ItemList`；`rankings/gdp-total.html` 有 `Table`+`ItemList`；`top100city.html` 有 5 处；验证：✅ 已覆盖
- [x] 5.3 核查列表页与详情页：`mall/product/detail.php`、`mall/shop/view.php`、`model/view.php`、`nft/nft/view.php`、`club/post.php`、`news.php`、`index.php` 均有结构化数据；验证：✅ 已覆盖
- [ ] 5.4 上线后用结构化数据校验工具抽查关键页面无语法错误；验证：JSON 可解析、无必填字段缺失

## 6. 度量基线（P2）

- [ ] 6.1 从服务器 access log 统计 `GPTBot` / `OAI-SearchBot` / `PerplexityBot` / `ClaudeBot` / `Bytespider` 等各 AI Bot 的访问量与抓取路径；验证：产出基线数字
- [ ] 6.2 记录基线数据作为后续 GEO 改动的对照依据；验证：数据留档（含统计时段）
- [ ] 6.3 变更完成后 2~4 周复查同类指标，对比变化；验证：产出对比结论
