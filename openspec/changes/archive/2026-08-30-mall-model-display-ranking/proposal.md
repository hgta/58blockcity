# 提案：首页人气模特紧凑化 + 卡片样式优化 + 模特排行新增（粉丝/访问）

## 为什么做（Why）

当前商城首页「📸 人气模特」区块存在以下问题，影响首页信息密度与视觉质量：

1. **图片太大**：`mall/index.php` 用的是 `.model-strip` 横滑布局，每张卡固定 `200px` 宽、且隐藏缩略图，首页被撑得很长，信息密度低。
2. **样式不好看**：模特卡片（`mall/model/card.php` + `mall/model/style.css`）的关注按钮是通栏描边圆角按钮，在窄卡/横滑场景下发虚、不好点；整卡视觉也偏朴素。
3. **排行维度缺失**：`mall/rankings/index.php` 的「模特排行」只有「关联商品 / 点赞 / 评论」三种，缺少更受用户关注的 **粉丝排行**（库里已有 `follower_count`）和 **访问排行**（库里尚无浏览统计字段）。

## 目标（What）

1. **首页人气模特**：改为一行显示 5 个的紧凑网格（窄卡、小头像、保留关注按钮），不再横滑、不再撑大。
2. **卡片样式优化**：重新设计 `renderModelCard` 的视觉，重点优化关注按钮（更小巧、状态清晰、点击区域合理、移动端友好）。
3. **模特排行新增**：
   - 新增 **粉丝榜**：用现有 `models.follower_count` 排序。
   - 新增 **访问榜**：新增 `models.view_count` 字段，在访问模特个人页（`mall/model/view.php`）时累加浏览次数，并按其排序。
   - 在排行榜页「模特排行」子 tab 增加「粉丝榜」「访问榜」两个维度。

## 非目标（Non-goals）

- 不改动模特数据模型的其他字段（昵称、小红书等维持现状）。
- 不引入独立访问记录表（`model_views`），仅在 `models` 表加一个 `view_count` 整数字段 + 自增，足够轻量。
- 不做防刷（同一用户短时间重复访问去重）的复杂逻辑；仅做基础累加（与商品 `view_count` 现有处理方式一致）。

## 受影响文件

- `mall/index.php` — 首页人气模特区块的 HTML 结构与样式
- `mall/model/card.php` — `renderModelCard()` 卡片渲染
- `mall/model/style.css` — 卡片与关注按钮样式
- `mall/rankings/index.php` — 模特排行子 tab 与排序字段映射
- `classes/MallRanking.php` — `getModelRanking()` 支持 `follower_count` / `view_count`
- `classes/Model.php` — 新增 `recordView()` 累加浏览数
- `mall/model/view.php` — 在详情页调用 `recordView()`
- `init/migrate_model_view_count.sql` — 新增 `view_count` 字段迁移
