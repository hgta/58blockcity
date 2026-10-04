# help-semantic-console — Delta Spec

## ADDED Requirements

### Requirement: 知识块状态可视化
后台 SHALL 提供页面展示 help_chunks 的现状：按来源（article/faq/glossary）的块数、已嵌入数、向量维度、最后更新时间，并 MUST 列出「已发布但未生成块」的源清单。

#### Scenario: 查看块状态
- **WHEN** 管理员打开语义检索控制台
- **THEN** 页面显示各来源块数/已嵌入数/维度/更新时间

#### Scenario: 发现未入库的已发布内容
- **WHEN** 某篇已发布文章（或已发布 FAQ）在 help_chunks 中没有对应块
- **THEN** 该源出现在「未入库清单」中，可直接一键重建补齐

### Requirement: 一键全量重建
后台 SHALL 提供触发全量重建的操作，等价于执行 `tools/rebuild-help-chunks.php`，并 MUST 在执行期间加锁防止并发重复执行。

#### Scenario: 触发重建
- **WHEN** 管理员点击「全量重建」并确认
- **THEN** 系统重建全部知识源，页面回显处理结果与耗时

#### Scenario: 重建进行中重复点击
- **WHEN** 一次重建尚未结束而管理员再次触发
- **THEN** 系统拒绝第二次执行并提示「重建进行中」

### Requirement: 开关与阈值就地配置
后台 SHALL 允许就地编辑 `ai_semantic_rag_enabled`（混合检索开关）、`ai_semantic_min_score`（命中阈值）、`ai_search_fallback_enabled`（搜索兜底开关）、`ai_search_model`（联网搜索模型），保存后立即生效，无需改数据库。

#### Scenario: 修改阈值
- **WHEN** 管理员将 ai_semantic_min_score 由 0.45 改为 0.55 并保存
- **THEN** 该值写入 system_settings，前台小帮的命中判定立即按新阈值执行

#### Scenario: 关闭混合检索
- **WHEN** 管理员将 ai_semantic_rag_enabled 置为关闭
- **THEN** 小帮检索回落到原 ngram 路径（回滚手段），页面明确提示当前处于关闭状态

### Requirement: 检索试验台
后台 SHALL 提供输入任意问题进行检索试验的功能，展示混合检索命中的块（标题、正文、cosine、RRF 分）、命中判定结果，以及旧 ngram 检索的对照结果。

#### Scenario: 用试验台验证阈值
- **WHEN** 管理员输入一个同义改写问题（如"认领区块要钱吗"）并提交
- **THEN** 页面显示命中块的标题、cosine 与是否被判定为命中，同时显示旧 ngram 检索的对照结果

#### Scenario: 未命中时提示会走兜底
- **WHEN** 试验结果显示融合 top1 的 cosine 低于当前阈值
- **THEN** 页面提示「判定未命中 → 线上将走联网搜索兜底（非官方标注）」
