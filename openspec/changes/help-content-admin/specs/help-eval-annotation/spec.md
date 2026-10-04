# help-eval-annotation — Delta Spec

## ADDED Requirements

### Requirement: 真实提问样本列表
后台 SHALL 从 `ai_chat_logs` 拉取真实用户提问作为评测样本，展示问题原文、当前状态（命中/未命中）与提问时间，支持按状态筛选。

#### Scenario: 查看样本
- **WHEN** 管理员打开标注台
- **THEN** 页面列出最近真实提问，标注每条是否已被标注过

### Requirement: 期望条目标注持久化
后台 SHALL 允许管理员为每条样本选定期望命中的知识条目（文章/FAQ/术语，对应 `a#/g#/f#` 编码）或标记「知识库无对应内容」，标注结果 SHALL 持久化到 `help_eval_samples` 表而非仅存于 CSV 文件。

#### Scenario: 标注一条提问
- **WHEN** 管理员为「BCT 是什么？」选定期望条目为术语「BCT」并保存
- **THEN** 该标注写入 help_eval_samples，列表标记该条为已标注

#### Scenario: 标记为无对应内容
- **WHEN** 管理员判断某提问知识库确实无法回答
- **THEN** 可标注为「无对应内容」（等价 0），用于统计误报率

### Requirement: 导出评测脚本可消费的 CSV
后台 SHALL 支持将已标注样本导出为 `tools/eval-retrieval.php` 可直接消费的 CSV 格式。

#### Scenario: 导出并评测
- **WHEN** 管理员点击导出
- **THEN** 生成含 log_id、question、expected 编码的 CSV，可直接作为 `--eval` 参数运行

### Requirement: 内容更新后可重复评测
同一批标注样本 SHALL 可在内容扩充后重新评测，用于观察命中率随内容增长的变化。

#### Scenario: 内容扩充后复测
- **WHEN** 管理员新增若干文章/FAQ 后再次运行评测
- **THEN** 系统对同一批已标注样本重新检索，输出与上次可对比的命中率结果
