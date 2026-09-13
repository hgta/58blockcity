# 设计：补齐 GEO 实体覆盖与内容结构化

## Context

`site-geo-optimization` 变更建立了 GEO 基础设施，但覆盖落地不完整。本设计解决「基础设施已有，多数子域未接入」的问题。

关键核查结论（已实测）：

```
                     orgJsonLd 注入      llms.txt
  ─────────────────────────────────────────────────
  www (shared)        ✅                ✅
  block               ✅                ✅
  bct                 ❌                ✅
  bid                 ❌                ✅
  club                ❌                ✅
  hufang (v)          ❌                ❌
  mall                ❌                ✅
  model               ❌                ❌
  nft                 ❌                ✅
  task                ❌                ❌
  
  覆盖率              2/10              7/10
```

## Goals / Non-Goals

**Goals**

- 全局实体在全部对外子域可被生成式引擎识别，`@id` 一致。
- `llms.txt` 覆盖全部公开子域。
- 实体描述单一可信来源，无跨文件矛盾。
- 结构化数据覆盖与 `SeoHelper` 已提供的能力相匹配。

**Non-Goals**

- 不做任何百度收录相关改动（见另外两个变更）。
- 不改动页面视觉与业务逻辑。
- 不追求一次性补齐全部 schema 类型（按核查结果与实际价值排序）。

## Decisions

### D1：全局实体注入统一走 `shared/organization.php`

**决策**：所有子域 header 通过 `require_once` 引入 `shared/organization.php` 并输出 `organization_json_ld()`，与 `shared/header.php`、`block/includes/header.php` 的既有做法一致。

**理由**：`organization.php` 已是本项目的「单一来源」设计（其文件头注释明确声明）。未接入的子域只是「漏了」，不是「需要不同实现」。

**实现注意**：子域 header 的 `require_once` 路径不同（如 `bct/includes/header.php` 到 `shared/` 为 `../../shared/organization.php`），需逐一确认相对路径正确。若各子域 header 已有 `bootstrap`/`config` 引入机制，优先复用该机制中的路径常量。

### D2：`llms.txt` 采用「主实体 + 本域清单」结构

**决策**：新建的 `model/llms.txt`、`task/llms.txt`、`v/llms.txt` 参照已有子域 `llms.txt` 的结构：声明所属主实体（指向 `https://www.58.tl/`）、本域的核心内容与入口、关联平台声明。

**理由**：保持与既有 7 份 `llms.txt` 一致，避免风格分裂。生成式引擎对 `llms.txt` 的解析偏好一致性。

### D3：消除实体描述双份维护 —— 以 `organization.php` 为准

**决策**：静态页（`help/*.html` 等）内联的 JSON-LD 与 `shared/organization.php` 输出对齐；发现不一致时**以 `organization.php` 为权威**。

**已发现的真实不一致**：

| 字段 | `organization.php` | 静态 HTML | 处置 |
|------|-------------------|-----------|------|
| description 子域数 | 九个子域（含 model） | 八个子域（无 model） | 统一为九个 |
| sameAs | 已移除 | 已移除 | 一致，无需处理 |

**理由**：`model` 子域已通过 `extract-model-subsite` 变更抽出并上线，静态页仍在描述「八个子域」属滞后。以 `organization.php` 为准符合实际架构。

**长期方案**：静态页无法 include PHP，本变更采用「人工同步 + 校验脚本」；彻底的模板化留待后续（成本高于收益）。

### D4：`sameAs` 替代信号的谨慎处理

**决策**：仅当用户能提供**可公开访问的自有平台主页 URL**（如官方微博、公众号主页、站内关于页）时才补充 `sameAs`；若无可提供，**保持 `sameAs` 为空并记录该决策**，不强行填充。

**理由**：`sameAs` 的语义是「同一实体的另一个 URL」，是强声明。填入非自有平台（如 `BlockCity.vip`）会触发实体错误合并（先前变更已为此移除过内容）。宁缺毋滥。

**候选方向**（待用户确认可用性）：
- 站内「关于我们」页（若存在且可被引用）
- 官方微博 / 公众号主页（若有）
- 其他由本站运营的平台账号主页

### D5：结构化数据按「方法已备 vs 页面未用」逐页核查

**决策**：不预设要补哪些，而是先核查 `SeoHelper` 已提供的 `Place` / `ItemList` / `Dataset` / `FAQPage` 等方法在对应页面的实际使用情况，产出「未使用清单」，再按价值排序补齐。

**理由**：避免为了「补 schema」而补。GEO 的价值在于让 AI 能准确理解页面实体，无对应实体的页面强加 schema 反而制造噪声。

**重点核查对象**：
- `city.php` / `block/city.php` — 是否输出 `Place`
- `rankings/rankings.html` / `rankings/gdp-total.html` — 是否输出 `Dataset`（列表页已有 `ItemList` 的迹象）
- `mall/product/list.php`、`model/list.php` 等列表页 — 是否输出 `ItemList`

### D6：度量基线取自 access log，不引入埋点

**决策**：从服务器 access log 按 UA 统计 AI Bot 访问，不修改应用代码埋点。

**理由**：零侵入，且 access log 是唯一能反映「AI 真的来抓了」的客观数据。已有 `docs/browse.sh` 可用于日志提取。

## Risks / Trade-offs

| 风险 | 影响 | 缓解 |
|------|------|------|
| 子域 header 相对路径错误 | 页面报错 | 逐个验证页面可正常加载 |
| 静态页手工同步遗漏 | 实体描述仍不一致 | 产出校验脚本比对关键字段 |
| 补充 `sameAs` 引入错误实体关系 | AI 归因混乱 | 仅限确认为自有的 URL，宁缺毋滥 |
| GEO 效果无法短期量化 | 难以判断收益 | 建立 access log 基线，观察趋势而非即时数字 |

## Migration Plan

1. 先补 `llms.txt`（低风险、纯新增）。
2. 再补子域实体注入（逐个子域验证页面正常）。
3. 统一实体描述并加校验。
4. 核查补齐结构化数据。
5. 建立 access log 基线。

**回滚**：全部为新增或对齐改动，回滚按文件粒度即可。

## Open Questions

- 是否有可供引用的自有平台主页 URL 用于 `sameAs`？（无则跳过）
- `model` / `task` / `v` 三个子域的实际业务范围与内容入口清单？（编写 `llms.txt` 需要）
- `task.58.tl` 是否对外公开？若为内部功能域，`llms.txt` 与 robots 策略应不同。
