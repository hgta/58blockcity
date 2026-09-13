## 1. 数据层与迁移

- [x] 1.1 编写 `init/migrate-model-subsite.sql`：`models` 增列 `video_url` varchar(500)、`video_cover` varchar(255)、`intro` varchar(255)、`drama_count` int(11) 默认 0；判存在再 `ALTER`，可重复执行；为 `follower_count`、`drama_count` 补索引；验证：在测试库连续执行两次均无报错
- [x] 1.2 在同迁移中建 `dramas` 表：`id`、`title` varchar(200)、`slug` varchar(200)、`cover` varchar(255)、`episodes` int、`tags` text（JSON）、`hg_url` varchar(500)、`synopsis` text、`status` enum('active','inactive') 默认 active、`created_at`、`updated_at`，含 `KEY(idx_status)`；验证：重复执行不报错
- [x] 1.3 在同迁移中建 `model_dramas` 表：`id`、`model_id`、`drama_id`、`role_name` varchar(100)、`is_lead` tinyint(1) 默认 0、`sort_order` int 默认 0、`created_at`，含 `UNIQUE(model_id, drama_id)` 与 `KEY(drama_id)`；验证：重复插入同一模特同一剧被唯一键拒绝
- [x] 1.4 同步更新 `init/db-init.sql`：追加上述列与两张表的建表语句；验证：空库全新初始化后结构一致
- [x] 1.5 提供 `drama_count` 回填 SQL（按 `model_dramas` 分组回写）；验证：手动制造偏差后可被回填脚本纠正

## 2. 数据访问层

- [x] 2.1 新增 `classes/Drama.php`：`create($data)`、`update($id, $data)`、`getById($id)`、`softDelete($id)`、`getList($page, $perPage, $search)`、`findByTitle($title)`；验证：CRUD 全流程可用，`findByTitle` 支持录入时「选已有剧」
- [x] 2.2 在 `Drama.php` 实现参演关联：`attachModel($dramaId, $modelId, $roleName, $isLead, $sortOrder)`、`detachModel($dramaId, $modelId)`、`getModelsByDrama($dramaId)`（按 `is_lead DESC, sort_order ASC`）；关联增删与 `models.drama_count` 同事务维护；验证：增删后 `drama_count` 与实际关联数一致
- [x] 2.3 在 `Drama.php` 实现 `getDramasByModel($modelId)`（含 `role_name` / `is_lead`，按 `sort_order ASC`）；验证：模特页可列出全部参演剧
- [x] 2.4 在 `Drama.php` 实现热度查询 `getTopDramas($limit)`（按参演模特数与最新更新时间）、`getTaggableDramas()`；验证：首页滑轨按预期排序
- [x] 2.5 在 `classes/Model.php` 增量新增（**不改类名、不动既有方法**）：`getHeroModel()`（`ORDER BY (video_url<>'') DESC, follower_count DESC LIMIT 1`）、`getFeaturedModels($limit)`、`getRankingBy($type, $limit)` 支持 `follower|like|drama|product` 四维；验证：各方法返回结构与既有 `getFilteredList` 兼容
- [x] 2.6 扩展 `Model::update()` / `create()` 的 `$allowed` 字段白名单，纳入 `video_url`、`video_cover`、`intro`；验证：后台保存后字段落库

## 3. SEO 与 URL 治理

- [x] 3.1 修改 `classes/SeoHelper.php::modelUrl()`：返回 `https://model.58.tl/m/{id}-{slug}.html`；验证：`grep` 全部调用点行为正确
- [x] 3.2 在 `SeoHelper` 新增 `dramaUrl($id, $title)` 返回 `https://model.58.tl/drama/{id}-{slug}.html`，以及 `modelListUrl($params)` 生成带筛选参数的列表 URL；验证：URL 与 nginx 规则一致
- [x] 3.3 更新 `docs/nginx-rewrite.conf`：新增 `model.58.tl` server 块（root 指向 `.../58blockcity/model`），含 `^/m/([0-9]+)-.*\.html$ → /view.php?id=$1` 与 `^/drama/([0-9]+)-.*\.html$ → /drama.php?id=$1`；验证：规则置于 `location /` 之前
- [x] 3.4 在同文件 `mall.58.tl` server 块中，于 `try_files` 之前加入 301 规则：`/model/list.php`、`/model/{id}-{slug}.html`、`/apply/model.php`、`/apply/my.php` 四条指向 `model.58.tl`；验证：旧 URL 单跳不链式
- [x] 3.5 同步更新 `mall/.htaccess` 的等价 301 规则（Apache 环境兜底）；验证：语法与既有 RewriteRule 风格一致
- [x] 3.6 更新 `sitemap.php`：模特 URL 走新 `SeoHelper::modelUrl()`，列表/排行/短剧页替换为新域名，移除旧 `mall.58.tl/model/list.php` 条目；验证：生成的 XML 无旧域名残留
- [x] 3.7 更新 `llms.txt` 与 `mall/llms.txt` 中的模特库链接为新域名绝对地址；验证：无 `mall.58.tl/model` 残留
- [x] 3.8 修复 `includes/city-portal-render.php` 第 170 行的 `'mall' => 'https://mall.58.tl/model/list.php?city='` 深链与第 491 行的 `SeoHelper::modelUrl()` 调用，改为子站地址；验证：城市门户跳转到新子站

## 4. 迁移 mall 侧引用点

- [x] 4.1 `mall/index.php`：「📸 人气模特」区块链接改为 `https://model.58.tl/` 绝对地址，卡片链接走 `SeoHelper::modelUrl()`，关注按钮的登录回跳改为子站登录；验证：点击不再 404
- [x] 4.2 `mall/user/following.php`：「我的关注」中模特项链接走 `SeoHelper::modelUrl()`，分页与空状态正常；验证：列表无死链
- [x] 4.3 `mall/product/detail.php`：商品页「模特」入口走新 URL（跨域跳转）；验证：跳转目标存在
- [x] 4.4 `mall/rankings/index.php`：模特排行区块链接走新 URL 或整体改为导流到子站排行榜；验证：无 404
- [x] 4.5 `mall/admin/models.php`：`SeoHelper::pushContentUrl()` 推送的 URL 为新域名；验证：后台保存后推送内容正确
- [x] 4.6 `mall/model/` 目录处置：`list.php` / `view.php` / `card.php` / `follow.php` / `style.css` 在 301 生效后改为跳转壳或删除；验证：直接访问旧路径落入 301
- [x] 4.7 全站检索确认无残留 `mall.58.tl/model` 硬编码链接；验证：`grep -r "mall.58.tl/model"` 结果为空

## 5. 子站外壳与主题

- [x] 5.1 新增 `model/assets/css/model.css`：以 CSS 变量定义亮调 token（`--bg #FAFAF8`、`--surface #FFFFFF`、`--brand #E8467C`、`--gold #C9A227` 等，见 design 决策 3）；所有规则作用于 `.model-shell` 作用域；验证：不污染共享 header
- [x] 5.2 新增 `model/includes/header.php`：以 `$site_config` 覆写 `logo_sub` / `logo_tag` / `theme_color` / `nav_links`（首页 / 模特库 / 短剧 / 排行榜 / 申请加入）后 `require_once __DIR__ . '/../../shared/header.php'`；验证：导航高亮正确、登录态按钮可用
- [x] 5.3 新增 `model/includes/footer.php`：复用 `shared/footer.php` 并在 `.model-shell` 外收尾；验证：页脚无样式冲突
- [x] 5.4 建立页面骨架与响应式断点（桌面 / 平板 / 手机）；验证：三档宽度下无横向滚动与文字溢出

## 6. 首页

- [x] 6.1 全屏 Hero：按 `getHeroModel()` 取主推模特，按「直链 `<video>` → 外链 `<iframe>` → `<img>` 封面」三级降级渲染（见 design 决策 5）；带 `muted loop playsinline`、封面 `poster`、静音/播放切换按钮；验证：有视频、只有封面、两者皆无三种情况下首屏均完整不破图
- [x] 6.2 Hero 叠加信息：模特名、`intro`、城市、粉丝数、[查看主页] 按钮；移动端保证关键信息在首屏；验证：手机上 Hero 高度不超出视口导致信息被裁
- [x] 6.3 「本期主推模特」横向滑轨：`getFeaturedModels(6)`，视频优先标识、头像/主图、昵称、粉丝数、关注按钮（复用关注接口）；验证：未登录点击跳转登录
- [x] 6.4 「正在热播短剧」横向滑轨：`getTopDramas()` 输出剧封面、剧名、集数、参演模特头像堆叠；点击进入短剧详情；验证：无短剧数据时该区块整体隐藏
- [x] 6.5 「发现模特」区：筛选 chips（性别 / 城市 / 星座，来自 `getFacets()`）+ 瀑布流网格 + 加载更多（AJAX 分页）；验证：筛选与分页均可用且 URL 可分享
- [x] 6.6 「新⼈加入引导」CTA 区：三步说明（提交资料 → 审核沟通 → 上线展示）+ 按钮 → `/apply.php`；验证：未登录点击跳转登录并回跳

## 7. 模特库列表页

- [x] 7.1 新增 `model/list.php`：迁移 `mall/model/list.php` 的筛选/排序/AJAX/加载更多能力到新主题；验证：功能与旧页一致
- [x] 7.2 新增 `model/card.php`：重设计卡片（亮调、`--brand` 强调、显示 `drama_count`「参演 N 部」），保留 `renderModelCard` 函数签名以复用；验证：列表与首页滑轨共用渲染
- [x] 7.3 新增 `model/follow.php`：关注接口迁移，返回 JSON 与旧接口兼容；验证：前端关注按钮状态即时切换
- [x] 7.4 新增 `model/assets/js/follow.js`：关注交互脚本迁移并适配新 DOM；验证：未登录跳转登录、已登录即时切换

## 8. 模特个人页

- [x] 8.1 新增 `model/view.php`：重设计资料区（头像/大图、昵称、性别/城市/年龄/身高/体重/三围/星座、`intro`、社交链接），修复旧页 `$shareUrl` 未定义问题；验证：页面无 PHP 警告
- [x] 8.2 视频/主图展示区：模特有 `video_url` 时按三级降级展示，无则展示日常照片首图或头像；验证：三种情况均正常
- [x] 8.3 「参演短剧」区块：`getDramasByModel()` 输出剧封面、剧名、集数、饰演角色、主演标识，点击进入短剧详情；验证：无参演时区块隐藏
- [x] 8.4 图集与日常照片灯箱：迁移并重设计灯箱交互（键盘左右/ESC、移动端滑动手势）；验证：移动端可左右滑动
- [x] 8.5 留言区与关注/点赞：保留站内信留言与关注点赞，登录态与旧页一致；验证：留言成功送达模特关联用户
- [x] 8.6 「相关模特」推荐：复用 `getRelated()`，按新卡片渲染；验证：点击进入对应模特页
- [x] 8.7 「TA 推荐」关联商品区块：展示该模特关联商品并跨域跳转 `mall.58.tl`；验证：跳转目标存在
- [x] 8.8 SEO：`Person` JSON-LD（含 `video`、`performerIn` 指向短剧）+ `BreadcrumbList` + canonical 为新 URL + OG 图；验证：结构化数据校验通过

## 9. 短剧体系

- [x] 9.1 新增 `model/dramas.php`：短剧列表页，支持按题材标签筛选、分页；验证：筛选与分页可用
- [x] 9.2 新增 `model/drama.php`：短剧详情页，展示封面大图、剧名、集数、题材 chips、简介、红果外链（`target=_blank rel="nofollow noopener"`）、参演模特网格（`is_lead DESC, sort_order ASC`）；验证：聚合列表与关联数据一致
- [x] 9.3 短剧页 SEO：canonical、OG 图用剧封面、`BreadcrumbList`、`CreativeWork`/`TVSeries` 结构化数据；验证：结构化数据校验通过
- [x] 9.4 新增 `model/admin` 侧入口说明：短剧管理挂在 `mall/admin/dramas.php`（见任务 10）

## 10. 后台录入

- [x] 10.1 新增 `mall/admin/dramas.php`：短剧列表（搜索、分页、上架/下架）与新增/编辑表单（剧名、封面上传、集数、题材标签、红果地址、简介）；验证：CRUD 全流程可用
- [x] 10.2 在短剧编辑页实现「参演模特」管理：选择已有模特加入、填写角色名、勾选主演、调整排序、移除；验证：保存后 `model_dramas` 与 `drama_count` 同步
- [x] 10.3 在 `mall/admin/models.php` 表单新增：`intro`（个人简介）、`video_url`、`video_cover`（上传或填 URL）；验证：保存后子站模特页正确展示
- [x] 10.4 在 `mall/admin/models.php` 编辑态新增「参演短剧」区块：从已有短剧选择（`findByTitle` 搜索）或新建并关联，可设角色名/主演/排序；验证：无需切换到短剧页即可完成录入
- [x] 10.5 后台表单分组与提示文案（视频说明降级规则、短剧说明封面比例建议、题材标签录入格式）；验证：不熟悉的管理员可按提示完成录入

## 11. 排行榜与申请

- [x] 11.1 新增 `model/rankings.php`：四维 tab（粉丝 / 人气 / 参演短剧 / 作品），前三名特殊展示（`--gold` 荣誉语义），其余为列表；验证：各维度排序正确、空数据不破版
- [x] 11.2 新增 `model/apply.php`：迁移 `mall/apply/model.php`（含 CSRF、照片上传、重复申请校验），套用子站亮调主题；验证：提交成功、照片落 `assets/uploads/models/YYYYMM/`
- [x] 11.3 新增 `model/my.php`：迁移「我的申请」状态查询；验证：状态与驳回原因正确展示
- [x] 11.4 子站登录/注册入口：`$site_config` 的 `url_login` / `url_register` 指向 `mall.58.tl/auth/`（或统一登录页），登录后回跳子站；验证：未登录点击关注/申请可完整闭环

## 12. 验证与清理

- [x] 12.1 登录态跨子站验证：确认 session cookie domain 配置，未统一时先统一为 `.58.tl`；验证：在 `mall.58.tl` 登录后访问 `model.58.tl` 仍为登录态
- [x] 12.2 旧 URL 单跳验证：抽样 `/model/list.php`、`/model/{id}-{slug}.html`、`/apply/model.php`、`/apply/my.php` 确认 301 直达新地址且无链式跳转
- [x] 12.3 全站链接回归：`mall` 首页、商品页、店铺页、关注页、城市门户、排行榜点击模特入口均可达；验证：无 404
- [x] 12.4 响应式与无障碍：三档视口巡检；对比度达 WCAG AA；颜色不作唯一信息载体；验证：移动端可完成「浏览 → 关注 → 申请」全链路
- [x] 12.5 性能核对：首页列表与滑轨无 N+1 查询（`drama_count` / 参演模特走批量或冗余列）；验证：开启慢查询日志无逐条循环查询
- [x] 12.6 一个版本周期后清理 `mall/model/` 与 `mall/apply/` 中模特相关页面；验证：旧路径仍由 301 接住
