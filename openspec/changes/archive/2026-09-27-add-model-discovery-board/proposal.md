# 增加模特库发现页（模特库板块）

## 问题

已有的 `add-model-library` change 完成了模特数据底座、详情页 `view.php`、点赞、排行榜，但**缺少一个面向用户的前台"发现/浏览"入口**：

- 商城导航、首页都没有"模特库"入口，用户只能从商品详情页间接跳进单个模特。
- 没有按**性别 / 城市 / 星座**等维度筛选、把"所有模特"集中展示并让用户"看到想看的"的板块。
- 互动只有"点赞"，缺少更轻量的"关注"和"我的关注"沉淀，难以形成回访。

本 change 在前置 `add-model-library` 之上，补上**发现页 + 真实关注系统 + 相关推荐 + 首页/导航导流**，目标是让模特库成为一个独立可逛、能留存用户的板块。

## 目标

1. **模特发现页** `/model/list.php`：集中展示所有 active 模特，支持性别/城市/星座/搜索叠加筛选 + 多种排序，URL 可分享。
2. **真实关注系统** `model_follows` 表：关注/取关，维护 `follower_count`，驱动粉丝排序与"我的关注"。
3. **卡片内即时关注**：列表卡片与详情页均可一键关注（AJAX），未登录引导登录。
4. **用户中心「我的关注」** `mall/user/following.php`：聚合当前用户关注的模特，形成回访入口。
5. **详情页「相关模特」**：同 城市+星座+性别 推荐，延长停留。
6. **首页 + 导航导流**：首页加"人气模特"模块，导航加"模特库"入口。
7. **SEO**：发现页 canonical 指回基础 URL，sitemap 收录基础 URL。

## 范围

**In scope**:
- `model_follows` 表 DDL
- `models.follower_count` 由 varchar 改为 INT 并由关注行为维护（弃用手填值）
- `Model` 类新增：`follow` / `unfollow` / `isFollowed` / `getFollowedModels` / `getFilteredList` / `getFacets` / `getRelated`
- 发现页 `mall/model/list.php`（筛选条 + 卡片网格 + AJAX 加载更多）
- 详情页 `mall/model/view.php` 增加「相关模特」区块 + 关注按钮
- 用户中心 `mall/user/following.php`
- 导航入口 + 首页模块
- `SeoHelper` / `sitemap.php` 适配

**Out of scope**:
- 新增模特封面图 / 人设标签字段（本期直接复用头像 + 商品图集 + 日常照片）
- 模特自助申请 / 审核流程（沿用后台 CRUD）
- 关注后的站内信通知（可后续迭代）
- 过滤组合页进 sitemap（仅基础 URL 收录，防重复页）

## 依赖

- 前置 change：`add-model-library`（models 表、Model 类、view.php、排行榜、点赞均已就绪）
