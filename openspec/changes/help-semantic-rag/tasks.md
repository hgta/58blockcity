# Tasks: help-semantic-rag

## 1. Spike 验证（先行，结论写回 design.md Open Questions）

- [x] 1.1 验证方舟 embedding API：普通 `api/v3` 与 Plan 端点 `/api/plan/v3` 哪个支持 `/embeddings`，确认模型名（doubao-embedding 系列）、维度、计费；结论回写 design.md
  （**已实测确认**：Agent Plan Key @ `/api/plan/v3` + `doubao-embedding-vision` 成功返回向量；`/api/v3` 与 `/api/coding/v3` 鉴权失败。结论见 design.md）
- [x] 1.2 验证联网搜索 API 形态：方舟 AI 搜索 / 豆包搜索是否可被服务端直接调用、费用与返回结构；不可用则记录"兜底项降级为现状文案"
  （结论：Responses API 内置 web_search 工具可服务端直调，兜底不降级；请求体格式实现 5.1 时确认）

## 2. 数据层

- [x] 2.1 编写 `init/migration-help-semantic-rag.sql`：help_chunks 表（source_type/source_id/chunk_no/title/text/content_hash/embedding BLOB/dim/updated_at，UNIQUE(source_type,source_id,chunk_no)）、ai_providers 加 purpose 列、system_settings 键（ai_semantic_rag_enabled=0、ai_semantic_min_score、ai_search_fallback_enabled）
- [x] 2.2 实现切块器（richtext 按 h2/h3 + 500/80 滑窗、steps 按步骤、faq/glossary 整条），每块前置"【标题】"上下文头（classes/HelpChunker.php，19 项离线单测通过）
- [x] 2.3 实现 `classes/EmbeddingProvider.php`：复用 ai_providers 加密存储，purpose=embedding 渠道，embed(texts[]) 批量接口与测试连接
- [x] 2.4 扩展 `admin/ai-providers.php`：purpose 维度（聊天/嵌入分组展示），embedding 渠道测试按钮（调真实 /embeddings 验通）；AiProvider::routeList 排除嵌入渠道；新增方舟直连/嵌入预置模板

## 3. 嵌入管道

- [x] 3.1 实现 `tools/rebuild-help-chunks.php` CLI 全量重建：发布态 articles/faq/glossary → 切块 → content_hash 比对增量嵌入 → 写 help_chunks，幂等（含 --stats/--force；同步逻辑收敛在 classes/HelpChunkSync.php，后台钩子共用）
- [x] 3.2 三处后台保存钩子：文章（save/set_status/delete）+ **FAQ（admin/help-faq.php）** + **术语表（admin/help-glossary.php）** 保存/删除后同步对应块，仅变更块重嵌，失败只记日志不阻断后台
      （2026-10-04 补记：此前误判"FAQ/术语表无后台页"，实为搜索通配符未匹配导致的假阴性——两个管理页一直存在，故钩子补齐到三处）
- [ ] 3.3 首次全量回填线上数据，抽查切块质量与向量维度（**待部署**：跑 migration → 配嵌入渠道 → php tools/rebuild-help-chunks.php）

## 4. 检索层

- [x] 4.1 实现混合检索类（`classes/HelpRetrieval.php`）：ngram FULLTEXT top-10 + 向量 cosine top-10（请求内静态缓存全量向量）+ RRF(k=60) 融合 → top-N（ai_rag_topn），sources 结构与现状一致（SQLite 全链路 17 项自检通过，含降级路径）
- [x] 4.2 `api/ai/chat.php` 检索段接入：ai_semantic_rag_enabled 开关，开=混合检索，关=现状 ngram 路径（回滚开关）；命中判定 = 融合 top1 的 cosine ≥ ai_semantic_min_score
- [x] 4.3 降级路径：embedding 调用失败/超时 → 本次请求回退纯 ngram，error_log 记录，回答不中断（mode=ngram 时落回 legacy 路径）

## 5. 搜索兜底

- [x] 5.1 实现联网搜索调用（`classes/ArkWebSearch.php`：方舟 Responses API + 内置 web_search 工具，端点失败自动回退普通 api/v3；凭据复用嵌入渠道）
- [x] 5.2 chat.php 兜底分支：融合分 < ai_semantic_min_score → 搜索 → 回答分节标注"联网参考信息（非官方）" + 留言工单引导 + 仍记 unmatched；搜索失败回退现状文案（system prompt 注入分节标注要求）

## 6. 评测与上线

- [x] 6.1 实现 `tools/eval-retrieval.php`：从 ai_chat_logs 抽样（unmatched+ok）→ 人工标注 CSV → 同批问题跑新旧检索 → 输出命中率对比（含 ai_semantic_min_score 阈值 F1 扫描建议）
- [ ] 6.2 跑对比报告，据此定 ai_semantic_min_score 默认值（回写 design.md）
      ⏸ **推迟**：帮助内容（文章/FAQ/术语表）仍在搭建中，此时评测的命中率不代表最终效果，等 `help-content-admin` 完成、内容稳定后再跑
      注：内容增长**不会**让已交付代码失效——知识块是 hash 增量重嵌（后台保存即同步）、CLI 幂等可随时全量重建；需要重做的只有评测数字本身
- [ ] 6.3 打开 ai_semantic_rag_enabled，线上验证：同义改写问题命中、sources 正常、延迟可接受（<500ms 增量）（⏸ 依赖 6.2）
- [ ] 6.4 观测一周 ai_chat_logs unmatched 占比变化，输出效果结论（⏸ 依赖 6.3）
