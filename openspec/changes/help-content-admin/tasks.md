# Tasks: help-content-admin

## 1. 数据层

- [x] 1.1 `init/migration-help-content-admin.sql`：新建 `help_eval_samples`（log_id 唯一、question、expected 编码、annotated_by、created_at）；加锁用设置键 `help_rebuild_locked_at`
- [x] 1.2 菜单项接入 `shared/admin/admin-menu-config.php`（语义检索控制台、检索标注台，挂在帮助文章/FAQ 之后）

## 2. 语义检索控制台（admin/help-semantic.php）

- [x] 2.1 知识块状态卡：各来源块数/已嵌入/维度/更新时间 + 「已发布但未入库」源清单（未入库源可单独补建）
- [x] 2.2 一键全量重建：请求内调用 HelpChunkSync::rebuildAll（锁 `help_rebuild_locked_at` 10 分钟 + set_time_limit），回显结果与耗时；失败自动解锁
- [x] 2.3 开关与阈值就地编辑：ai_semantic_rag_enabled / ai_semantic_min_score / ai_search_fallback_enabled / ai_search_model，保存即写 system_settings
- [x] 2.4 检索试验台：输入问题 → 展示混合检索命中块（标题/正文/cosine/RRF）、命中判定、旧 ngram 对照、未命中时的兜底提示
- [ ] 2.5 页面上线自检：本地 php -l + 部署后逐项点验（状态/开关/试验台/重建）

## 3. 检索标注台（admin/help-eval.php）

- [x] 3.1 样本列表：从 ai_chat_logs 拉取真实提问（按状态/是否已标注筛选，最近 100 条）
- [x] 3.2 标注交互：下拉选定期望条目（文章/FAQ/术语分组，写入 a#/g#/f# 编码）或「0 = 无对应内容」，保存入 help_eval_samples；行内「试检索」直接看新旧结果
- [x] 3.3 导出 CSV（eval-retrieval.php 可直接 --eval 消费）与「清空重标」入口
- [x] 3.4 与控制台联动：每行「试验台」链接带 q 参数跳转 help-semantic.php 并自动跑一次检索

## 4. 内容侧小增强（搭建期减负）

- [ ] 4.1 FAQ 列表增加关键词搜索与批量发布
- [ ] 4.2 术语表列表增加关键词搜索
- [ ] 4.3 三处内容页（文章/FAQ/术语）块同步失败的可见化提示（当前仅写日志，后台看不到）

## 5. 收尾

- [ ] 5.1 内容与标注稳定后，回到 `help-semantic-rag` 任务 6.2 跑评测 → 6.3 开开关 → 6.4 观测
