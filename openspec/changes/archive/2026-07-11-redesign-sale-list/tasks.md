# NFT 售卖页重新设计 — 实施任务

## 任务列表

### 任务 1：修复 getSaleList() 和 getTotalSaleCount() SQL

**文件**: `classes/NFT.php`

**内容**:
- [x] `getSaleList()` SQL 新增 JOIN `cities c ON t.city_id = c.id`，SELECT 加 `c.id AS city_id, c.name AS city_name`
- [x] 新增求购数子查询: `(SELECT COUNT(*) FROM nft_purchase_requests WHERE nft_id = n.id AND status = 'active') AS purchase_count`
- [x] 启用 city 筛选: 取消 city 条件注释，改为 `c.name = ?`
- [x] 删除 rarity 筛选注释代码
- [x] 新增 `hot` 排序: `ORDER BY purchase_count DESC, t.created_at DESC`
- [x] 删除 `rare` 排序 case
- [x] `getTotalSaleCount()` 同步 JOIN cities 并加 city 筛选

**依赖**: 无

**验收**:
- 调用 `getSaleList('北京')` 只返回该城市的挂售记录
- 返回数据包含 `city_name`、`city_id`、`purchase_count` 字段
- 排序 `sort=hot` 按求购数降序排列

---

### 任务 2：重写 sale_list.php 布局和样式

**文件**: `nft/nft/sale_list.php`

**内容**:
- [x] PHP 部分：去掉 rarity 相关参数和调用，去掉 `$nft->getAllRarities()` 调用
- [x] 新增顶部统计栏（在售总数、覆盖城市数），橙色品牌渐变底
- [x] 新增水平筛选栏：编号搜索、城市下拉、货币、排序（含"求购热度"）
- [x] 重写卡片网格：CSS Grid `auto-fill, minmax(155px, 1fr)` 自适应列数
- [x] 每张卡片布局（从上到下）：
  - 圆形 SVG 头像（78px, 3px 橙色描边, `object-fit: contain`）
  - 编号（粗体, `#ff6b00`）
  - 城市归属（灰色圆角徽章 `📍 北京`）
  - 价格（大字, 渐变强调色, Ⓟ/¥ 区分货币）
  - 卖家和求购热度行（`@seller 🔥N求购`）
- [x] 整张卡片 `<a>` 包裹，点击跳 `/nft/view.php?id=xxx`
- [x] 空状态：带图标的无数据提示
- [x] 重写分页：带省略号窗口（当前 ±2），上一页/下一页

**依赖**: 任务 1

**验收**:
- 页面视觉有明显市场感，卡片排列整齐
- 城市筛选生效，选中后仅显示该城市 NFT
- 每张卡片正确显示城市归属和求购热度
- 点击卡片跳转详情页
- 分页导航正确翻页

---

### 任务 3：验证

**内容**:
- [x] 检查 PHP 无报错/警告（lint 通过）
- [x] 确认城市筛选和排序全部生效
- [x] 确认移动端布局正常（卡片自适应换行）
- [x] 确认卡片 hover 效果和点击跳转正常

**依赖**: 任务 1-2 全部完成

**验收**:
- 所有筛选+排序组合正确
- 无 regression（不影响其他页面）
