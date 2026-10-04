# help-semantic-rag — Delta Spec

## ADDED Requirements

### Requirement: 混合检索命中官方知识库
小帮的检索层 SHALL 同时执行 ngram 全文检索与向量语义检索，并以 RRF 倒数排名融合两路结果；命中时回答 SHALL 附带 `sources` 引用列表（结构与现状一致，前端零改动）。

#### Scenario: 同义改写问法命中
- **WHEN** 用户问题与文章用词不同义但语义相同（如"认领区块要钱吗"对应"购买区块的手续费"文章）
- **THEN** 向量检索路命中该文章，融合结果包含它，回答引用其链接

#### Scenario: 精确术语问法仍命中
- **WHEN** 用户问题包含精确术语或型号（如"BCT"、"顺延"）
- **THEN** ngram 检索路命中对应内容，混合融合后不劣于纯 ngram 的结果

#### Scenario: sources 结构保持兼容
- **WHEN** 混合检索返回结果
- **THEN** `sources` 数组元素仍为 `{title, url}`，指向 help 文章/FAQ 页面，前端与悬浮窗无需任何修改

### Requirement: 知识切块与嵌入管道
系统 SHALL 将 help_articles（richtext 与 steps 两种模式）、help_faq、help_glossary 切块并向量化，存入 help_chunks 表；每个块的 content_hash 变更时 SHALL 重新嵌入，未变更的块 MUST NOT 重复调用嵌入 API。

#### Scenario: 富文本文章按标题切块
- **WHEN** 一篇 richtext 文章包含多个 h2/h3 小节
- **THEN** 按小节切为多个块，每块文本前置"【文章标题】"上下文头，超长段滑窗（500 字/80 字重叠）

#### Scenario: 步骤模式文章按步骤切块
- **WHEN** 文章为 steps 模式（content_steps JSON）
- **THEN** 每个步骤的 title+text 成为独立块

#### Scenario: 术语表与 FAQ 接入检索
- **WHEN** 向量索引构建完成
- **THEN** help_glossary 术语定义与 help_faq 问答对作为独立块参与检索

#### Scenario: 文章保存触发增量重嵌
- **WHEN** 管理员在后台保存/发布一篇文章
- **THEN** 系统比对该文章各块的 content_hash，仅对变更块重新嵌入

#### Scenario: FAQ 与术语保存/删除触发同步
- **WHEN** 管理员在后台（admin/help-faq.php、admin/help-glossary.php）新建、修改或删除一条 FAQ / 术语
- **THEN** 该条目对应块即时重建（未变更块不重嵌）或清除，无需手动运行 CLI
- **AND** 同步失败时后台操作仍成功完成，错误仅记入日志

#### Scenario: CLI 全量重建
- **WHEN** 运行 tools/rebuild-help-chunks.php
- **THEN** 全部已发布知识源重新切块并嵌入，操作幂等，可用于更换嵌入模型后的重建

### Requirement: 嵌入服务降级韧性
嵌入 API 调用失败或超时时，系统 SHALL 降级为纯 ngram 检索并正常完成回答，MUST NOT 因嵌入服务故障导致小帮不可用。

#### Scenario: 嵌入 API 故障
- **WHEN** embedding 渠道请求失败或超时
- **THEN** 本次请求回退到纯 ngram FULLTEXT 检索路径，回答正常返回，错误记入日志

### Requirement: 检索未命中的联网搜索兜底
当融合检索得分低于配置阈值时，系统 SHALL 调用联网搜索并以明确分节标注"联网参考信息（非官方）"作答，同时该对话 MUST 仍记录为 unmatched（保持 from_chat→FAQ 管道原料），并附带留言工单引导。

#### Scenario: 知识库未覆盖的问题
- **WHEN** 融合得分低于阈值（如平台规则外的通用问题）
- **THEN** 回答开头声明"官方帮助中心暂无相关资料"，联网参考信息单独分节并标注非官方，末尾引导留言工单

#### Scenario: 命中官方资料时不联网
- **WHEN** 融合得分达到阈值
- **THEN** 不调用联网搜索，按官方资料路径回答

#### Scenario: 搜索服务不可用时
- **WHEN** 联网搜索 API 失败
- **THEN** 回答保持现状文案（告知暂无官方资料并引导留言），不报错

### Requirement: 检索质量可评测
系统 SHALL 提供离线评测工具，用真实历史问题对比新旧检索的命中率，并以上线后 unmatched 率变化作为可观测的质量指标。

#### Scenario: 离线命中率对比
- **WHEN** 对标注好的历史问题样本集分别运行旧 ngram 检索与新混合检索
- **THEN** 评测脚本输出两者 top-N 命中率对比报告

#### Scenario: 上线后质量可观测
- **WHEN** 混合检索上线运行
- **THEN** ai_chat_logs 中 unmatched 占比可按时间窗统计对比，作为效果验证指标
