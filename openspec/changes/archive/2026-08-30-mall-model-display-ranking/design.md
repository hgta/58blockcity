# 设计：首页人气模特紧凑化 + 卡片样式优化 + 模特排行新增

## 1. 首页人气模特改为一行 5 个紧凑网格

**现状**（`mall/index.php`）：
- 数据取 `getFilteredList(['sort'=>'follower'], 1, 10)`，渲染用 `.model-strip`（横滑，`flex:0 0 200px`，`.mc-thumbs` 隐藏）。
- 目标：一行 5 个，不再横滑。

**方案**：
- 把 `topModels` 取数上限改为 **5**（首页只展示 5 个）：`getFilteredList(['sort'=>'follower'], 1, 5)['list']`。
- 新增首页专用紧凑样式，替换 `.model-strip` 用法：
  - 容器用 CSS Grid：`.model-mini-grid { display:grid; grid-template-columns: repeat(5, 1fr); gap:12px; }`
  - 响应式：≤1024px 变 `repeat(3,1fr)`；≤480px 变 `repeat(2,1fr)`。
- 为紧凑卡提供一套更窄的样式（小头像、小字），复用 `renderModelCard()` 但通过外层 class 控制尺寸，避免改动卡片核心结构影响模特库页面。
  - 具体：在 `mall/index.php` 的人气模特区块外层包 `<div class="model-mini-grid home-model-strip">`，并在 `style.css` 或 `index.php` 内联 `<style>` 中写针对 `.home-model-strip .model-card` 的覆盖规则（头像更小、隐藏图集、关注按钮紧凑）。
  - 关注按钮：改为底部内联小块（不再通栏），参考第 2 节样式。

## 2. 卡片与关注按钮样式优化

**现状**（`mall/model/card.php` + `style.css`）：
- 关注按钮 `.model-follow-btn`：`width:100%`、通栏描边圆角、`margin-top:10px`。
- 整卡在窄场景（首页 1/5 宽度）下文字挤压、按钮细长不好点。

**优化点**：
- **关注按钮**：
  - 由「通栏大按钮」改为「紧凑胶囊按钮」，放在昵称行右侧（`display:flex` 让昵称与按钮同一行）。
  - 未关注：`background:#fff; color:#ff6b00; border:1px solid #ff6b00`。
  - 已关注：`background:#ff6b00; color:#fff`（保持现状语义）。
  - 加 `:hover` 轻微反色、`transition`，提升反馈；保持 `border-radius:20px`、`font-size:13px`、`padding:5px 14px`、`min-width` 保证可点。
  - 移动端：按钮最小高度 32px，保证可点区域（44px 触摸友好可放宽到 `min-height:34px`）。
- **卡片整体**：
  - 头像区域保持 1:1，但首页紧凑模式下通过外层 class 缩小到合适比例。
  - 昵称行与关注按钮同一行（`align-items:center; justify-content:space-between`）。
  - 统计行（❤ / 👥 / 📦）字号 12px、颜色更柔和、间距统一。
- 样式写入 `mall/model/style.css`（与现有 `.model-card` 体系同文件），首页通过外层包裹 class 复用并覆盖尺寸。

## 3. 模特排行新增粉丝榜 + 访问榜

### 3.1 数据层：新增浏览统计字段

**迁移文件** `init/migrate_model_view_count.sql`：
```sql
-- 模特个人页浏览统计计数
ALTER TABLE `models`
  ADD COLUMN `view_count` int(11) NOT NULL DEFAULT 0 COMMENT '模特个人页访问次数' AFTER `follower_count`;
```
- 幂等：线上若已存在则 `ADD COLUMN` 会报错；用 `IF NOT EXISTS` 不适用 MySQL ADD COLUMN，因此用 `CREATE PROCEDURE`/try-catch 或文档注明「已执行则跳过」。采用简单写法并附说明：若执行报错 `Duplicate column` 可忽略。
- 备选稳妥写法：在迁移脚本开头判断 `information_schema` 是否已存在该列，不存在再 `ADD`。提供该判断版本以保证可重复执行。

### 3.2 记录访问（`classes/Model.php`）

新增方法：
```php
public function recordView($modelId) {
    $modelId = intval($modelId);
    $this->pdo->prepare("UPDATE models SET view_count = view_count + 1 WHERE id = ?")
              ->execute([$modelId]);
}
```
- 在 `mall/model/view.php` 取得 `$modelInfo` 之后调用（仅对 `status='active'` 且存在的模特计数，已满足）。
- 不做登录校验、不做去重（与商品详情页 `view_count` 累加逻辑保持一致，保持轻量）。

### 3.3 排行查询（`classes/MallRanking.php`）

`getModelRanking($type, $limit)` 当前 `allowed = ['product_count','like_count','review_count']`：
- 扩展 `allowed` 增加 `'follower_count'` 和 `'view_count'`。
- SQL 已用 `m.{$type} as sort_value` 动态排序，无需改结构，只需把这两个字段加入白名单即可。
- `WHERE m.status='active' AND m.{$type} > 0` 对粉丝/访问都适用（0 的不参与排行）。

### 3.4 排行榜页 UI（`mall/rankings/index.php`）

`$modelTypes` 增加：
```php
'follower_count' => ['name'=>'粉丝榜','icon'=>'users','desc'=>'粉丝数最多的模特'],
'view_count'     => ['name'=>'访问榜','icon'=>'eye','desc'=>'个人主页访问量最高'],
```
- 排行项展示：把 `.shop-metrics` 中原有的「关联商品/点赞数/评论数」改为展示当前维度指标，或固定展示三项并在当前排序维度高亮。
  - 简化方案：在模特排行项里把第三个 metric 区按 `$modelType` 动态显示主指标值（如粉丝榜显示粉丝数、访问榜显示访问量），其余保留点赞/关联商品。
- 顶部「模特排行」主 tab 默认 `type` 改为 `follower_count`（更贴近「人气」语义），其余子 tab 链接保持。

## 4. 数据流与兼容性

- `models.view_count` 为新增列，默认值 0，不影响现有逻辑。
- `renderModelCard()` 改动兼容所有调用方（`mall/index.php`、`mall/model/list.php`、`mall/model/view.php` 相关模特区）；通过外层包裹 class 控制首页紧凑尺寸，避免影响模特库发现页网格。
- `follow.js` 关注按钮选择器 `.model-follow-btn[data-model-id]` 不变，样式改动不影响其绑定逻辑。

## 5. 风险与注意

- 首页取数从 10 改为 5，仅影响首页展示数量，不影响模特库。
- 迁移脚本需运维在生产执行（或随部署跑），`view_count` 字段缺失会导致 `getModelRanking('view_count')` 报错——故 UI 默认维度不强制依赖 `view_count`，且迁移脚本优先保证幂等。
- 关注按钮与昵称同行后，昵称过长会挤压按钮：昵称加 `flex:1; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap`，按钮 `flex-shrink:0`。
