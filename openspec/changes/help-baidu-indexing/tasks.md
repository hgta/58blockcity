# 实施任务：help 子站接入百度主动推送

## 0. 前置（含人工步骤）

- [ ] 0.1 确认 `help-seo-foundation`（批次 A）已上线，权威域为 `https://help.58.tl/`
      （验证：`https://www.58.tl/help/faq` 已 301 到 `https://help.58.tl/faq`）
- [ ] 0.2 **人工**：在百度搜索资源平台添加并验证 `help.58.tl` 站点归属，取得推送 token
      （验证：平台显示该站点已验证，可看到 token）
- [ ] 0.3 **人工**：部署环境 `config/seo.php` 的 `sites.help.58.tl` 填入 token 并置 `enabled=true`
      （验证：`php -r` 调用 `SeoHelper::resolvePushCredentials('help.58.tl')` 返回 `enabled=true`）

## 1. 配置结构

- [ ] 1.1 `config/seo-sample.php` 的 `sites` 增加 `'help.58.tl' => ['token' => '', 'enabled' => false]`
      （验证：示例文件含该项；`config/seo.php` 不入库）
- [ ] 1.2 编写 help 子站百度验证与取 token 的操作说明（DNS / HTML 文件 / CNAME 三种方式），并入既有子域接入指引
      （验证：文档可被运维按步骤执行）

## 2. 数据层

- [ ] 2.1 新增 `init/migration-help-baidu-push.sql`：`help_push_log(url PK, action, pushed_at, KEY idx_pushed)`
      （验证：SQL 可在测试库执行，`SHOW COLUMNS` 可见）
- [ ] 2.2 迁未执行时推送模块 MUST 降级为「不节流、直接推」而非报错
      （验证：表不存在时保存文章仍成功，仅日志记录）

## 3. 推送封装（节流 + 隔离）

- [ ] 3.1 `help/_init.php` 新增 `help_push_url($url, $action = 'push')`：查 `help_push_log` 做冷却期判断（默认 24h，可用 `system_settings.help_push_cooldown_hours` 覆盖）
      （验证：冷却期内二次调用被跳过并记日志；超时后正常推）
- [ ] 3.2 内部用 `try/catch` 包住 `SeoHelper::pushContentUrl()`，异常只记日志不向上抛
      （验证：模拟接口不可达时，后台保存仍返回成功）
- [ ] 3.3 推送成功后写入/更新 `help_push_log`
      （验证：推送成功后表中 `pushed_at` 更新）

## 4. 后台触发点

- [ ] 4.1 `admin/help-articles.php`：保存前读取旧 `status`，识别「新建且 published」与「非 published → published」两种跃迁，触发 `help_push_url(权威 URL)`
      （验证：两种跃迁均触发；已发布文章普通编辑不触发）
- [ ] 4.2 `admin/help-faq.php`：新增/发布 FAQ 后触发 `help_push_url('https://help.58.tl/faq')`
      （验证：新增一条 FAQ 后触发一次，受节流约束）
- [ ] 4.3 `admin/help-glossary.php`：新增术语后触发 `help_push_url('https://help.58.tl/glossary')`
      （验证：新增术语后触发一次，受节流约束）
- [ ] 4.4 推送调用的 URL 统一由 help 侧 URL 构造函数生成，不得拼 `www.58.tl/help/`
      （验证：代码检索无 `www.58.tl/help/` 拼接）

## 5. 批量工具修正

- [ ] 5.1 `site.php` 默认入口移除 `https://www.58.tl/help/help.html`，补入 `https://help.58.tl/`
      （验证：默认列表无 301 死链且含 help 入口）
- [ ] 5.2 用 `php site.php <url...>` 批量补推已发布的帮助文章 URL（历史存量）
      （验证：命令行输出显示 `help.58.tl` 分组推送成功，日志含 `success`）

## 6. 死链处理（可选，建议 C 完成后实施）

- [ ] 6.1 文章下架（published → 非 published）时，原 URL 写入 `help_push_log(action='dead')`
      （验证：下架后表中出现 dead 记录）
- [ ] 6.2 slug 变更时：旧 URL 入死链、新 URL 触发推送
      （验证：改 slug 后 dead 记录出现且新 URL 被推送）
- [ ] 6.3 新增 `help/deadlinks.php` 输出纯文本死链清单（每行一条 URL），并在 nginx/`.htaccess` 放行该路径
      （验证：访问返回 `text/plain` 且 URL 每行一条）
- [ ] 6.4 **人工**：在百度搜索资源平台死链提交入口登记 `https://help.58.tl/deadlinks.php`
      （验证：平台显示提交成功）

## 7. 验证与观测

- [ ] 7.1 部署后从服务器 error_log 抽查 `[SEO] [推送结果]` 记录，确认 `site=help.58.tl` 且含 `success` — 需部署服务器
      （验证：日志中出现 success 计数，`not_same_site` 为 0）
- [ ] 7.2 确认后台保存不因推送变慢（未配置 token 与接口超时两种场景各测一次）
      （验证：保存响应时间无明显增加）
- [ ] 7.3 上线后 2~4 周在百度站长平台记录 help.58.tl 的收录量与推送配额消耗
      （验证：产出基线对照数据；按 `per-subsite-baidu-indexing` 的站群缓解策略分阶段启用）
