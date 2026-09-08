## 1. 子站脚手架与共享层

- [ ] 1.1 创建 `task/` 目录骨架与 `task/includes/{header,footer,auth}.php` 代理（参照 `bid/includes/`），页面 require 根 `shared/header.php`/`shared/footer.php`/`includes/auth.php`；验证空跑 `task/index.php` 能渲染共享站点头尾且无 PHP 错误
- [ ] 1.2 创建 `task/auth/{login,register,logout}.php` 与 `task/user/dashboard.php` 入口（复用根 auth 模板，配置 `$site_config` 与 `redirect_after_login`）；验证登录/注册流程与回跳正常、cookie 跨子站共享
- [ ] 1.3 将 `compressImage` 共享化：在根 `includes/functions.php` 追加同名函数（带 `function_exists` 守卫、逻辑与 `hufang/includes/functions.php` 一致）；验证 hufang 截图上传不回归、新站可调用
- [ ] 1.4 站点登记 4 处：`config/seo.php` subdomains 增加 `task`、根 `index.php` 顶部导航/eco-grid/页脚增加"任务广场"、`shared/footer.php` cross-site-links 增加卡片、`shared/admin/admin-menu-config.php` 增加 task 站点与菜单；验证主站与共享页脚展示新入口、sitemap 可解析 task 域名

## 2. 数据库与数据类

- [ ] 2.1 编写 `init/migrate-task.sql`：按 design D2 建 `task_categories`（含种子：代互访/代打卡/代做市长/其他）、`tasks`、`task_claims`、`task_skills`、`task_skill_categories`、`task_reviews`、`task_disputes`，统一 utf8mb4_unicode_ci 且幂等（IF NOT EXISTS/INSERT IGNORE）；验证在 MySQL 执行成功、表结构与约束（唯一键/索引）符合 design
- [ ] 2.2 实现 `classes/Task.php`：创建任务（含人气值任务必填 city、类别启用校验、有效期未来校验）、按条件分页查询广场任务（类别/城市/赏金类型/状态筛选、最新/赏金排序）、按 id 取详情、满额判断、雇主关闭任务；验证返回结构与 spec 场景一致
- [ ] 2.3 实现 `classes/TaskClaim.php`：领取（UNIQUE(task_id,worker_id) 防重、禁领自己的任务、满额/到期拒绝、事务内 claimed_count+1）、提交凭证（文字+可选图片）、雇主验收通过/驳回、状态机流转、结算划转与重试；验证各状态迁移与并发下名额正确
- [ ] 2.4 实现 `classes/TaskSkill.php`（技能卡增改/上下架/按类别列表）、`classes/TaskCategory.php`（启用类别列表/后台增改停用）、`classes/TaskReview.php`（结算后互评唯一性）、`classes/TaskDispute.php`（发起/裁决）；验证基本 CRUD 与唯一性约束

## 3. 任务广场（浏览/筛选/详情/导流）

- [ ] 3.1 实现 `task/index.php` 广场：启用任务分页列表、类别/城市/赏金类型/状态筛选、最新与赏金排序、空态与分页越界收敛；验证与 spec「广场浏览与筛选」场景一致
- [ ] 3.2 实现 `task/view.php` 任务详情：标题/类别/赏金（人气值/现金格式化）/名额剩余/有效期/验收说明/关联城市展示；有 target_type 时渲染"前往区块/互访圈"外链按钮；现金任务显著提示"平台不托管、线下完成"；验证导流链接可打开、详情信息完整
- [ ] 3.3 在关联对象目标侧做登记：城市目标复用 `cities` pinyin 详情链接、区块目标复用 block.58.tl 区块查看 URL、互访圈目标复用 v.58.tl 圈子详情 URL（实现时按各站真实路径核对）；验证三种链接均可达

## 4. 发布任务

- [ ] 4.1 实现 `task/create.php` 发布表单：类别单选（启用中）、标题/说明/验收说明、赏金类型切换（人气值→强制选城市并限制正整数；现金→金额以"分"存整）、名额 N、有效期、可选关联城市/区块/互访圈；验证「发布人气值任务必须关联城市」「类别停用不可发布」等 spec 场景
- [ ] 4.2 POST 落库走 CSRF + 预处理，创建成功后跳转详情页；验证重复提交/越权/非法金额被拒并提示

## 5. 领取、交付与验收

- [ ] 5.1 实现领取动作（view.php/my.php 内入口）：校验登录、非本人任务、未重复、未满额、未过期，成功后我的承接出现该认领；验证 spec「众包领取」全部场景
- [ ] 5.2 实现凭证提交（上传至 `task/uploads/task_proofs/`，校验图片类型并 `compressImage` 压缩，proof_text 必填）；验证提交后状态 submitted、图可访问、非归属者被拒
- [ ] 5.3 实现雇主验收（通过/驳回）：驳回必填原因、认领转 rejected 且 deadline 重置；通过转 settling；非雇主不可操作；验证 spec「凭证交付与验收」场景
- [ ] 5.4 交付后写 `review_due_at = 交件时间 + tasks.review_days`，并在雇主侧/列表标红逾期提醒；验证时间计算正确

## 6. 结算与通知

- [ ] 6.1 实现人气值认领结算：settling 时调用 `UserPopularity::transferPopularity(employer, worker, task.city, reward_amount)`，成功→completed+写评价窗口+双方通知；余额不足→保持 settling、通知雇主补足后可在详情页"重试结算"；验证账本双侧增减正确、不足时事务回滚无部分扣款
- [ ] 6.2 现金认领结算：settling→completed 仅状态推进，页面提示线下完成、不产生站内资金变动；验证现金任务结算不触碰 user_city_popularity
- [ ] 6.3 全流程接入既有 `Notification`/`Message`（领取成功、收到交付、被驳回、验收通过、争议结果、催补足）；验证关键事件均产生站内通知

## 7. 我的发布 / 我的承接 / 评价

- [ ] 7.1 实现 `task/my.php`（我的发布 tab）：任务列表按状态分组、查看各认领进度、对待验收认领执行验收、对未到期任务执行关闭；验证 spec「我的发布」场景含"关闭后未完结认领不再受理新交付但已提交仍可验收"
- [ ] 7.2 实现 `task/my.php`（我的承接 tab）：认领按状态筛选、待办入口（提交凭证/查看驳回原因/补交/发起争议）；验证 spec「我的承接」场景
- [ ] 7.3 实现结算完成后双向评价（星级+文字、各一次、不可改/删、未完成不可评）；验证 spec「双向评价」三个场景
- [ ] 7.4 在任务站用户视角展示承接人的历史评价与完成数（作为信誉参考）；验证详情/技能卡可看到对方评价摘要

## 8. 技能卡（求职侧）

- [ ] 8.1 实现技能卡维护页：选类别（多选，仅启用中）、必填"我能承接"说明、可选参考价与备注、下架开关；用户仅一张技能卡（UNIQUE）；验证 spec「技能卡创建与维护」
- [ ] 8.2 实现公开"找承接人"页（可按类别浏览、展示头像/用户名/类别/说明/参考价），提供跳转承接人既有主页与站内联系入口；验证按类别筛选正确、联系路径可达
- [ ] 8.3 任务发布页类别选中后展示"该类别下活跃技能卡 N 张"提示并可跳转找承接人页；验证提示与跳转

## 9. 后台管理与争议仲裁

- [ ] 9.1 实现 `task/admin/dashboard.php` 看板（任务/认领/争议统计）+ `task/admin/categories.php` 类别管理（增/停用/启用/排序）；验证停用后发布与技能卡不再可选、历史任务仍展示原类别
- [ ] 9.2 实现 `task/admin/tasks.php` 与 `task/admin/claims.php` 全量列表/详情查看（含凭证图、雇主与接单人信息）；验证后台可查看任意任务与认领
- [ ] 9.3 实现 `task/admin/disputes.php` 仲裁：对 disputed 认领裁决 settle（触发结算或标记完成）/cancel（认领取消、任务名额不自动回补），填写原因并通知双方；验证 spec「争议与后台仲裁」两裁决分支

## 10. 集成冒烟与部署

- [ ] 10.1 本地全流程冒烟：发布人气值任务→多人领取→各自交付→部分通过/部分驳回补交→结算划转→互评；现金任务另走一单仅状态推进；验证 spec 全场景（广场筛选、导流、超时争议由 admin 走通）
- [ ] 10.2 校验任务站全页面登录/未登录边界与 CSRF（含 admin 权限）、lint 无致命错误；验证未登录访问发布/领取被引导登录、普通用户访问 admin 被拒
- [ ] 10.3 执行 `openspec validate` 通过并提交推送；部署：服务器执行 `init/migrate-task.sql`，上线登记后的主站/生态页/后台可见任务广场入口；线上冒烟按 10.1 步骤过一遍并把 DNS/站点映射 task.58.tl 到位
