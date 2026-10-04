# Proposal: help-semantic-rag（小帮语义检索改造）

## Why

小帮的回答质量瓶颈在**检索层**而非模型层：`api/ai/chat.php` 用 MySQL ngram FULLTEXT 做纯词面匹配，同义改写的问法（"认领区块要钱吗"↔"购买区块的手续费"）检不出来，直接落入 unmatched；`help_glossary` 术语表（平台黑话定义）已建表但**完全未接入检索**。模型侧已就位（默认渠道 Hermes→火山 Agent Plan 已通、备用渠道直连 Agent Plan 已通），检索层成为唯一短板。用户已确认：语义检索（回答质量）为最高优先级，接受搜索兜底（带非官方标注，不稀释官方人设）。

## What Changes

- **向量嵌入管道（新增）**：`help_articles`（richtext 按 h2/h3 切块、steps 按步骤天然切块）+ `help_faq`（一问一答一块）+ `help_glossary`（术语+定义一块）→ 新表 `help_chunks`（embedding 以 BLOB 存储）；后台保存/发布文章时增量重嵌（content_hash 变更才重算），另配 CLI 全量重建脚本（换嵌入模型时用）。
- **EmbeddingProvider（新增）**：嵌入调用走方舟 `doubao-embedding` 系列（端点/计费由 spike 确认），复用 `ai_providers` 现成的 Key 加密存储与测试连接框架，新增"embedding 用途"维度。
- **混合检索（替换 chat.php 检索段）**：ngram FULLTEXT（**保留**，精确术语/型号是它的强项）+ 向量 cosine（PHP 内存计算，规模仅数百 chunk，不引入向量数据库）+ RRF 倒数排名融合；`help_glossary` 首次接入。
- **搜索兜底（新增）**：融合分低于阈值时调联网搜索（豆包搜索/方舟 AI 搜索 API，调用方式由 spike 确认），回答明确分节标注"以下为联网参考信息（非官方）"，仍记 unmatched（继续进 from_chat→FAQ 管道），并保留留言工单引导。
- **离线评测（新增）**：从 `ai_chat_logs` 抽真实问题标注期望命中文章，输出旧 ngram vs 新混合检索的命中率对比报告；上线后以 unmatched 率下降为监控指标。

**明确不做**（范围边界，来自探索阶段决策）：
- 不做 harness 侧召回（知识库不进 OpenViking、检索不搬进 Hermes）——知识注入与模型渠道解耦是双渠道故障切换架构的资产，且 app 层检索才有可引用 sources 与可评测性。
- 不引入向量数据库（Qdrant/Milvus/OpenViking 自托管）——chunk 规模数百级，MySQL BLOB + PHP cosine 足够。
- 前端（`help/pages/ask.php`、悬浮窗）零改动——`sources` 结构保持不变。
- 多模态输入（截图提问）、语音、查询改写为后续独立变更。

## Capabilities

### New Capabilities

- `help-semantic-rag`: 小帮语义检索能力——知识切块与嵌入管道、ngram+向量混合检索与 RRF 融合、检索未命中的联网搜索兜底（非官方标注）、检索质量离线评测。

### Modified Capabilities

（无——`openspec/specs/` 现有四个能力 city-data-sync / city-prices / self-managed-consumption / user-holdings 均不受影响）

## Impact

- **新增**：`init/migration-help-semantic-rag.sql`（help_chunks 表 + system_settings 键）、`classes/EmbeddingProvider.php`、混合检索类（如 `classes/HelpRetrieval.php`）、`tools/rebuild-help-chunks.php`（CLI 重建）。
- **修改**：`api/ai/chat.php`（RAG 检索段替换为混合检索，sources 结构不变）；`classes/AiProvider.php` 或渠道配置表（embedding 用途维度）；`admin/help-articles.php`（保存钩子触发增量重嵌）；`admin/ai-providers.php`（embedding 渠道配置与测试）。
- **外部依赖**：方舟 embedding API（doubao-embedding，普通 `api/v3` 按量或 Plan 端点，二选一由 spike 定）；联网搜索 API（方舟 AI 搜索或等价，由 spike 定）。
- **不动**：AiProvider 渠道路由与故障切换、Hermes/训练台、前端展示层。
- **风险**：嵌入/搜索 API 的可用性与费用待 spike 验证；阈值需用评测集调参而非拍脑袋。
