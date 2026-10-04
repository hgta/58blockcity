# Proposal: help-content-admin（小帮内容运营与评测后台）

## Why

帮助内容（文章/FAQ/术语表）**仍在持续搭建中**，此时跑语义检索评测意义有限（命中率不代表最终效果，`help-semantic-rag` 的 6.2/6.3/6.4 已推迟）。但语义检索的代码与数据管道已交付，运营侧缺少三样东西：

1. **没有可视化控制台**：`ai_semantic_rag_enabled`、`ai_semantic_min_score`、`ai_search_fallback_enabled` 等开关/阈值只能改数据库，无法自助开关、调参、验证效果。
2. **无法直观验收检索**：改完阈值要看效果，只能去线上问小帮，看不到命中了哪块、cosine 多少、该不该走搜索兜底。
3. **标注成本过高**：评测需要人工标注「问题的期望条目」，现在靠导出 CSV 手工填，内容天天在变，标注往返成本高。

内容搭建期正好是把后台补齐的窗口：等内容稳定后，评测与上线只需在后台点几下。

## What Changes

- **语义检索控制台（新增页 `admin/help-semantic.php`）**：
  - 知识块状态：各来源（文章/FAQ/术语）块数、已嵌入数、向量维度、最后更新时间；列出「已发布但未入库」的源
  - 一键全量重建（后台触发 CLI，异步执行并回显结果）
  - 开关与阈值：`ai_semantic_rag_enabled`、`ai_semantic_min_score`、`ai_search_fallback_enabled`、`ai_search_model` 就地编辑
  - **检索试验台**：输入问题 → 展示混合检索命中的块（标题/正文/cosine/RRF 分）、命中判定结果、以及旧 ngram 检索的对照结果，便于直接调阈值
- **标注台（新增页 `admin/help-eval.php`，并与 `admin/ai-chat-logs.php` 互通）**：
  - 从 `ai_chat_logs` 拉取真实问题，逐条展示旧/新检索结果
  - 管理员用下拉框直接选定期望条目（文章/FAQ/术语，对应 `a#/g#/f#` 编码），存新表 `help_eval_samples`
  - 导出/生成 `tools/eval-retrieval.php` 可直接消费的 CSV；内容更新后可对同一批问题重跑，观察命中率变化
- 内容侧小增强：FAQ/术语表列表增加关键词搜索与批量发布（减少搭建期往返）

**明确不做**：不改检索算法本身（属于 `help-semantic-rag`）；不动前台展示层；不引入新的外部依赖。

## Capabilities

### New Capabilities

- `help-semantic-console`: 语义检索后台控制台——知识块状态可视化、一键重建、开关与阈值配置、检索试验台（新旧对照）。
- `help-eval-annotation`: 检索评测标注台——基于真实提问的期望条目标注、样本持久化与 CSV 导出，支持内容更新后重复评测。

### Modified Capabilities

（无）

## Impact

- **新增**：`admin/help-semantic.php`、`admin/help-eval.php`、`init/migration-help-content-admin.sql`（help_eval_samples 表）、菜单项（shared/admin/admin-menu-config.php）。
- **复用**：`classes/HelpRetrieval.php`、`HelpChunkSync.php`、`EmbeddingProvider.php`、现有 `system_settings` KV 机制。
- **不动**：`api/ai/chat.php` 检索逻辑、前台、AiProvider 路由。
- **风险**：后台触发 CLI 需控制执行时间与并发（加简单锁与超时）；一键重建会调用嵌入 API 产生费用（量级极小，显式确认后执行）。
