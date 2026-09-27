# 模特库发现页 — 任务清单（已实现）

## 阶段 1：数据 + Model 类
- [x] 1.1 `init/db-init.sql` 新增 `model_follows` 表 DDL
- [x] 1.2 `init/migrate_model_follow.sql`：`follower_count` 改 INT（幂等迁移）
- [x] 1.3 `classes/Model.php` 新增 `follow` / `unfollow` / `isFollowed`
- [x] 1.4 `classes/Model.php` 新增 `getFollowedModels`
- [x] 1.5 `classes/Model.php` 新增 `getFilteredList`（叠加筛选 + 白名单排序）
- [x] 1.6 `classes/Model.php` 新增 `getFacets`
- [x] 1.7 `classes/Model.php` 新增 `getRelated` + `getModelImageStrips`

## 阶段 2：发现页 `mall/model/list.php`
- [x] 2.1 创建 `list.php`：读取筛选/排序，调用 `getFilteredList` + `getFacets`
- [x] 2.2 筛选条：性别 + 城市 chips + 星座 chips + 排序 + 昵称搜索，状态写 URL
- [x] 2.3 卡片网格：`card.php` 头像 + 图集缩略 + 昵称/性别/城市/星座 + 赞/粉丝/作品 + 关注
- [x] 2.4 AJAX「加载更多」：`history.replaceState` 同步 `?page=`
- [x] 2.5 卡片内关注 AJAX：未登录跳登录
- [x] 2.6 SEO：`canonical` 指回基础 URL

## 阶段 3：详情页 + 用户中心
- [x] 3.1 `view.php` 底部「相关模特」区块
- [x] 3.2 `view.php` 头部关注按钮（AJAX）
- [x] 3.3 创建 `mall/user/following.php`：我的关注，可取消关注
- [x] 3.4 dashboard 侧栏入口

## 阶段 4：导流 + SEO 基础设施
- [x] 4.1 `header.php` 导航加「模特库」
- [x] 4.2 `index.php` 首页「人气模特」横滑模块
- [x] 4.3 `sitemap.php` 增加 `model/list.php`
- [x] 4.4 发现页为 `.php`，无需额外 rewrite

## 阶段 5：验证与提交
- [x] 5.1~5.4 代码编写完成（环境无 PHP，未跑本地 lint；逻辑已人工复核）
- [x] 5.5 提交并推送
