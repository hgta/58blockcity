# 实施任务：首页人气模特紧凑化 + 卡片样式优化 + 模特排行新增

## Task 1：首页人气模特改为一行 5 个紧凑网格
- [ ] 在 `mall/index.php` 将 `topModels` 取数上限由 10 改为 **5**（`getFilteredList(['sort'=>'follower'], 1, 5)`）。
- [ ] 人气模特区块外层容器由 `.model-strip` 改为 `.model-mini-grid home-model-strip`，容器内用 `renderModelCard()` 渲染 5 张卡。
- [ ] 在 `mall/index.php` 的 `<style>` 中新增 `.model-mini-grid`（桌面 `repeat(5,1fr)`、≤1024px `repeat(3,1fr)`、≤480px `repeat(2,1fr)`、gap 12px）。
- [ ] 新增 `.home-model-strip .model-card` 覆盖规则：缩小头像比例、隐藏 `.mc-thumbs`、收紧内边距，适配 1/5 宽度。

## Task 2：模特卡片与关注按钮样式优化
- [ ] 编辑 `mall/model/style.css` 的 `.model-follow-btn`：由通栏改为紧凑胶囊（与昵称同行），加 `:hover` 反馈、`min-height` 触摸友好。
- [ ] 在 `renderModelCard()`（`mall/model/card.php`）中，将昵称 `<a class="mc-name">` 与关注按钮包进同一行 flex 容器（`justify-content:space-between; align-items:center`），昵称加 `flex:1; min-width:0; ellipsis`，按钮 `flex-shrink:0`。
- [ ] 统一统计行（❤/👥/📦）字号与间距，提升窄卡可读性。
- [ ] 验证 `mall/model/list.php` 与详情页相关模特区样式正常（外层 class 区分，不影响原有网格）。

## Task 3：模特浏览统计字段 + 记录访问
- [ ] 创建 `init/migrate_model_view_count.sql`：向 `models` 表 `AFTER follower_count` 新增 `view_count int(11) NOT NULL DEFAULT 0`，使用 `information_schema` 判断实现幂等可重复执行。
- [ ] 在 `classes/Model.php` 新增 `recordView($modelId)`：`UPDATE models SET view_count = view_count + 1 WHERE id = ?`。
- [ ] 在 `mall/model/view.php` 取得 `$modelInfo` 后调用 `$model->recordView($modelId)`（仅对存在的 active 模特计数）。

## Task 4：模特排行新增粉丝榜 / 访问榜
- [ ] `classes/MallRanking.php` 的 `getModelRanking()` 白名单 `allowed` 增加 `'follower_count'` 与 `'view_count'`（SQL 已用 `m.{$type}` 动态排序，无需改结构）。
- [ ] `mall/rankings/index.php` 的 `$modelTypes` 增加：
  - `'follower_count' => ['name'=>'粉丝榜','icon'=>'users','desc'=>'粉丝数最多的模特']`
  - `'view_count'     => ['name'=>'访问榜','icon'=>'eye','desc'=>'个人主页访问量最高']`
- [ ] 模特排行主 tab 默认 `type` 改为 `follower_count`（链接与 `$modelType` 默认值同步）。
- [ ] 模特排行项（`.shop-rank-item`）按当前 `$modelType` 动态高亮/展示主指标（粉丝榜显示粉丝数、访问榜显示访问量，格式化走 `Model::formatFollower`），其余保留点赞/关联商品。

## Task 5：联调与验证
- [ ] 检查 `mall/index.php` 首页人气模特一行 5 个、图片缩小、关注按钮可点。
- [ ] 检查模特库发现页（`list.php`）卡片与关注按钮样式符合预期、AJAX 加载更多正常。
- [ ] 访问某模特详情页后，到排行榜「访问榜」确认该模特 `view_count` 自增并正确排序。
- [ ] 「粉丝榜」确认按 `follower_count` 正确排序。
- [ ] 运维在生产执行 `init/migrate_model_view_count.sql`（或部署流程跑迁移），确认 `view_count` 字段存在。
