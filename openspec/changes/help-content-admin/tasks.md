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

## 6. 文章富文本编辑器（内容搭建期体验）

- [x] 6.1 自托管 wangEditor v5 到 `admin/assets/wangeditor/`（不依赖 CDN）；CSS 经 `$admin_site_config['extra_head']` 引入
- [x] 6.2 富文本模式改为所见即所得：标题/加粗/列表/链接/表格/图片等按钮；图片粘贴或拖拽上传复用现有 `admin/help-upload.php`
- [x] 6.3 保留「HTML 源码」切换（可视化⇄源码双向同步），提交前强制同步进 textarea；编辑器脚本加载失败自动退回纯 textarea，保证可编辑
- [ ] 6.4 部署后点验：新建与编辑文章的可视化编辑、插图上传、切源码、保存后前台渲染与知识块同步正常

## 7. Slug 自动生成

- [x] 7.1 自托管 pinyin-pro 到 `admin/assets/pinyin-pro/`（315KB，仅后台）；输入标题时自动生成拼音 slug（无声调、连字符分隔、限 60 字符）
- [x] 7.2 保护人工值：slug 一旦被手动改过即停止自动覆盖；编辑已有 slug 的老文章不覆盖
- [x] 7.3 Slug 唯一键冲突时给出可操作提示（原为裸 MySQL 报错）
- [ ] 7.4 部署后点验：新建文章填标题即出 slug、手动改后不再被覆盖
- [x] 7.5 「AI 英文」按钮：新端点 `admin/help-slug-ai.php` 复用聊天渠道把标题译成英文 slug；服务端强制规整（只留 a-z0-9-、限 60 字符、清理模型客套词）+ 唯一性兜底（撞车自动加 -2/-3）；失败保留当前 slug 不打断录入
