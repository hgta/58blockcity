# Mall 商品/店铺排行榜

## 背景

mall.58.tl 首页有热门推荐/新品/热门店铺版块，但缺少独立的排行榜页面。数据库已具备全部排行字段：
- `products`: `view_count`(浏览)、`sold_count`(销量)、`rating`(评分)、`review_count`(评价数)、`created_at`(新品)
- `shops`: `total_sales`(销量)、`rating`(评分)、`review_count`(评价数)

## 目标

创建 `mall/rankings/index.php` 排行榜页面，包含商品排行和店铺排行两大板块。

## 排行榜类型

### 商品排行榜
| 榜单 | 排序字段 | 说明 |
|------|---------|------|
| 人气榜 | `view_count DESC` | 最多人浏览 |
| 销量榜 | `sold_count DESC` | 最多人购买 |
| 评分榜 | `rating DESC, review_count DESC` | 最高评分 |
| 口碑榜 | `review_count DESC` | 最多评价 |
| 新品榜 | `created_at DESC` | 最新上架 |

### 店铺排行榜
| 榜单 | 排序字段 | 说明 |
|------|---------|------|
| 销量榜 | `total_sales DESC` | 总销量最高 |
| 评分榜 | `rating DESC, review_count DESC` | 店铺评分最高 |

## 范围

### In Scope
- 新建 `classes/MallRanking.php` 数据类
- 新建 `mall/rankings/index.php` 排行榜页面
- `mall/index.php` 添加排行榜入口链接
- `mall/includes/header.php` 导航添加排行榜链接

### Out of Scope
- 时间周期筛选（日/周/月）— 后续可扩展
- ECharts 图表 — 先做列表版
- 数据库变更 — 全部用已有字段

## 成功标准
1. `mall.58.tl/rankings/` 可访问，显示商品和店铺排行榜
2. Tab 切换正常，5 种商品榜 + 2 种店铺榜
3. Top 3 有奖牌样式，其余有序号
4. 从首页和导航可进入排行榜
