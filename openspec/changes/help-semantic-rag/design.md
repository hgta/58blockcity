# Design: help-semantic-rag

## Context

小帮现状（探索阶段已核实的事实）：

```
用户 → api/ai/chat.php
        ├─ 频控（访客/用户/全站三层）
        ├─ RAG：MySQL ngram FULLTEXT（help_articles）+ LIKE 兜底 + FAQ LIKE
        │   ai_rag_topn=3，资料以 ≤800 字注入 system prompt
        └─ AiProvider 渠道（OpenAI 兼容 /chat/completions，故障切换）
             ├─[默认 ★] Hermes Agent（127.0.0.1:8642）→ 上游火山 Agent Plan
             └─[备用] 直连火山 Agent Plan（/api/plan/v3，已通）
```

- 检索短板：ngram 是字符级词面匹配，同义改写（"要钱吗"↔"手续费"）检不出 → unmatched。
- `help_glossary`（术语表）、`content_steps`（步骤模式）均未接入检索。
- `ai_chat_logs.question` 存完整问题原文 + `matched` 标记 → 现成的评测数据源。
- chunk 规模估算：帮助中心面向自家平台，文章+FAQ+术语表合计数百到一两千块。
- 用户已拍板：检索层留在 app 层；接受搜索兜底（带非官方标注）。

## Goals / Non-Goals

**Goals**
- 同义改写问法的检索命中率显著提升（离线评测可量化）
- 知识源补全：glossary、steps 模式接入
- 嵌入/搜索外部服务故障时小帮不瘫痪（降级路径）
- 前端与 sources 结构零改动

**Non-Goals**
- harness 侧召回（OpenViking/Hernes 内检索）——见 D1
- 向量数据库引入——见 D2
- 多模态输入、语音、查询改写（后续独立变更）

## Decisions

### D1：检索层留在 app 层，不做 harness 侧召回
备选：知识库进 OpenViking、Hermes 在 agent 循环内自行召回。
否决理由：① 知识注入与模型渠道解耦是双渠道故障切换的资产——检索若在 Hermes 内，切到直连备用渠道时知识库整个消失；② app 层检索才有结构化 `sources` 引用（前端现成）与离线可评测性；③ 召回过程在 agent 黑盒里无法量化。
长期分层：事实层（官方 KB，app 层，确定性可引用）+ 能力层（搜索/记忆/多模态，harness 层，已就位），两层不抢活。

### D2：向量存 MySQL BLOB，PHP 内存算 cosine，不引入向量库
备选：Qdrant/Milvus（过度设计）；自托管 OpenViking（它是 agent 上下文管理器，当向量库用是杀鸡牛刀，且多一个要养的服务）。
规模论证：≤2000 chunk × ≤2048 维 float32，PHP 余弦实测预期 <50ms；请求内静态缓存全量向量数组。规模涨到万级再换库，接口收敛在一个检索类内，替换成本低。

### D3：混合检索 = ngram FULLTEXT（保留）+ 向量 cosine + RRF 融合
ngram 不是负债是资产：精确术语/型号/"BCT"类查询它比 embedding 强（embedding 会模糊化专有名词）。两路各取 top-K（K=10），RRF（k=60）融合后取 top-N（沿用 ai_rag_topn）。无参数融合，避免调参地狱。

### D4：切分策略（三种正文形态 + 两个新接入源）
- richtext：strip_tags 后按 `<h2>/<h3>` 切段；无标题或超长段滑窗（500 字 / 80 字重叠）
- steps：每个 step `{title,text}` 天然一块
- help_faq：一问一答一块（question+answer 拼接）
- help_glossary：术语+定义一块
- 每块文本前置"【文章标题】"上下文头，缓解切块丢失主题的问题
- 存储：`help_chunks(id, source_type[article|faq|glossary], source_id, chunk_no, title, text, content_hash CHAR(64), embedding BLOB, dim INT, updated_at)`，UNIQUE(source_type, source_id, chunk_no)

### D5：EmbeddingProvider 复用 ai_providers 框架，加 purpose 维度
`ai_providers` 加列 `purpose ENUM('chat','embedding') DEFAULT 'chat'`（migration），复用现成的 AES-256-GCM Key 加密、测试连接、启停与排序。默认模型走方舟 doubao-embedding 系列；端点优先验证普通 `api/v3`（按量、必支持 /embeddings），Plan 端点 `/api/plan/v3` 是否开放 embedding 由 spike 验证，通了则可白嫖套餐额度。
注意：`AiProvider::baseUrl()` 的 `/v1` 自动补全逻辑对 `/api/v3`、`/api/plan/v3` 均兼容（结尾 /vN 不改写），无需改动。

### D6：增量更新 = 三处后台保存钩子 + CLI 全量重建
- `admin/help-articles.php`（save/set_status/delete）、`admin/help-faq.php`（save/delete）、`admin/help-glossary.php`（save/delete）保存后同步对应源的知识块（content_hash 逐块比对，未变不重算，省 API 费）；非发布态/删除则清块
- `tools/rebuild-help-chunks.php`：全量重建（换 embedding 模型 / 首次部署 / 修补用），幂等
- 三处钩子统一为"失败只记日志不阻断后台"，避免嵌入服务故障影响内容编辑

### D7：韧性设计——嵌入失败降级，阈值触发搜索兜底
- embedding API 调用失败/超时 → **本次请求降级为纯 ngram**（等于现状），记录 error log，不阻断回答。外部服务永远不能成为小帮的单点。
- 融合分低于阈值（`ai_semantic_min_score`，评测集调出默认值）→ 联网搜索兜底：
  - 回答强制分节："官方帮助中心暂无相关资料。以下为联网检索的参考信息（非官方，请以平台规则为准）：…"
  - 仍写 `status='unmatched'`（不因为搜到了就标记 ok，否则丢失 FAQ 管道原料）
  - 附留言工单引导
- 搜索 API 形态由 spike 定（方舟 AI 搜索 / 豆包搜索独立接口）；spike 失败则本项降级为"维持现状文案"，不阻塞主链路。

### D8：质量评测先于切换上线
`tools/eval-retrieval.php`：从 `ai_chat_logs` 抽样（unmatched + ok 各若干）→ 人工标注期望命中文章（CSV）→ 同一批问题分别跑旧 ngram 与新混合检索 → 输出命中率对比。切换上线后以 `ai_chat_logs` 的 unmatched 占比周环比为监控指标。

### D9：上线与回滚
`system_settings` 加开关 `ai_semantic_rag_enabled`（默认 0）。部署顺序：表结构 → embedding 渠道配置 → CLI 回填 → 评测 → 打开开关。回滚 = 关开关（chat.php 回到纯 ngram 路径），数据无破坏。

## Risks / Trade-offs

- [方舟 embedding 端点/计费不确定] → spike 先行；普通 api/v3 按量是兜底（嵌入费用极低，千 chunk 一次性嵌入约几毛钱）
- [搜索 API 不可直接调用（仅 harness 内可用）] → 兜底项降级为现状文案，主链路（语义检索）不受阻塞
- [RRF 阈值拍脑袋] → 用评测集调参，写进上线检查单
- [PHP cosine 性能] → 数百 chunk 实测；请求内静态缓存；超预期再考虑 APCu
- [切块破坏语义连贯] → 标题前缀 + 滑窗重叠；评测集验证
- [changelog 表膨胀] → 增量 hash 比对控制重算量

## Migration Plan

1. `init/migration-help-semantic-rag.sql`：help_chunks 表 + ai_providers.purpose 列 + system_settings 键
2. 后台配置 embedding 渠道（purpose=embedding）并测试连接
3. `tools/rebuild-help-chunks.php` 全量回填 → 抽查向量与切块质量
4. `tools/eval-retrieval.php` 跑对比报告 → 调阈值
5. 打开 `ai_semantic_rag_enabled`，线上观测 unmatched 率与延迟
6. 回滚：关开关即可

## Open Questions

### Spike 1.1 结论（2026-10-04，**已实测确认**）

- 离线阶段假 Key 探测法失败：方舟网关**先鉴权后路由**（`/v1`、`/api/v3`、`/api/plan/v3` 打不存在的路径同样返回 401），无法在无 Key 情况下区分端点存在性。
- **真 Key 实测结论（服务器 curl，2026-10-04）**：

  | 端点 | 模型 | 结果 |
  |---|---|---|
  | `https://ark.cn-beijing.volces.com/api/plan/v3` | `doubao-embedding-vision` | ✅ **成功返回向量，维度 2048**（首次回填实测确认） |
  | `https://ark.cn-beijing.volces.com/api/v3` | 同 | ❌ AuthenticationError（该 Key 为套餐专属 Key，非按量 Key） |
  | `https://ark.cn-beijing.volces.com/api/coding/v3` | 同 | ❌ AuthenticationError（非 Coding Plan 订阅） |

- 结论：**沿用现有 Agent Plan Key，端点 `/api/plan/v3`、模型 `doubao-embedding-vision`，零额外费用**（套餐内含 Embedding）。此前"Agent Plan 的 Embedding 只是 harness 工具、裸 API 不可调用"的担忧被推翻——计划端点对 OpenAI 兼容 `/embeddings` 是开放的。
- 实测补充：单请求 `input` 上限 **10 条**（超过报 `InvalidParameter: max 10, got 11`），`EmbeddingProvider::BATCH_SIZE` 已据此设为 10；**向量维度 2048**；首次回填 20 个知识源共 22 块，耗时约 37s。
- 代码层保持**不绑定端点**（endpoint/model 来自渠道配置行），后台已内置对应预置模板「火山方舟 嵌入（语义检索）」。

### Spike 1.2 结论（2026-10-04，文档级）

- 联网搜索**有服务端直调路径**：方舟 Responses API 支持内置工具 `web_search`（联网内容插件，文档 `ark-aihub/2646975`）；另有独立"火山引擎联网搜索 API"产品线。**搜索兜底不降级**。
- Responses API 的请求体格式（工具声明方式）文档为 SPA 无法抓取，实现 5.1 时抓不到就部署后实测确认。

