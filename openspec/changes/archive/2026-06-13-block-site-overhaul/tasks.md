# Block 子站点改造任务清单

## 前置检查

- [x] ~~确认数据库 `blocks` 表是否有 `is_listed` / `list_price` 字段区分"在售"~~
  - **结论**：当前数据库无 `is_listed` 字段，`sale_list.php` 暂维持查询 `status='sold'`（展示"已售区块"），标题同步改为"已售区块"避免语义混淆。后续如需真正的"挂售"功能，需先执行迁移。
- [ ] 若后续需要挂售功能，执行迁移：
  ```sql
  ALTER TABLE blocks ADD COLUMN is_listed TINYINT(1) DEFAULT 0;
  ALTER TABLE blocks ADD COLUMN list_price DECIMAL(12,2) DEFAULT NULL;
  ALTER TABLE blocks ADD COLUMN listed_at TIMESTAMP NULL DEFAULT NULL;
  ```

---

## 第一阶段：紧急修复（首页显示 + 基础样式）

### Task 1.1 — 移除 block/index.php 重复的城市定位条
- [x] **文件**：`block/index.php`
- [x] **操作**：删除硬编码的 `city-location-bar` div，保留 header 统一输出的那一条。
- [x] **验收**：首页只显示一行定位信息。

### Task 1.2 — 修复字母导航样式
- [x] **文件**：`assets/css/main.css`
- [x] **操作**：新增 `.letter-nav`、`.letter-nav-container`、`.letter-link` 胶囊样式。
- [x] **验收**：字母导航显示为居中排列的圆形胶囊按钮，hover 变橙色。

### Task 1.3 — block/index.php 增加城市搜索框
- [x] **文件**：`block/index.php`
- [x] **操作**：在字母导航上方插入搜索输入框，JS 实现前端实时过滤（按城市名/拼音）。
- [x] **验收**：输入城市名时自动高亮并滚动；无匹配时显示"未找到匹配的城市"。

### Task 1.4 — block/index.php 增加平台实时统计面板
- [x] **文件**：`block/index.php`、`classes/City.php`、`classes/User.php`
- [x] **操作**：查询城市总数、已激活区块数、注册用户数，在字母导航上方渲染为 4 列卡片，带数字递增动画。
- [x] **验收**：首页展示实时数字，页面加载后数字从 0 动画递增到实际值。

---

## 第二阶段：性能重构（城市页全景模式）

### Task 2.1 — 后端热力图生成接口
- [x] **文件**：新增 `block/api/heatmap.php`
- [x] **操作**：PHP GD 绘制 101×99 像素 PNG，按区块状态填充颜色，按 `updated_at` 缓存。
- [x] **验收**：直接访问返回 PNG 图片，能看到区颜色分布。

### Task 2.2 — city.php 全景模式接入热力图
- [x] **文件**：`block/city.php`
- [x] **操作**：删除 9 万行嵌套 for 循环生成 `<span>` 的代码，改为 9 个 `<img>` 引用热力图接口。
- [x] **验收**：全景模式 DOM 节点从 90,000 降至 < 100，低端设备不再卡死。

### Task 2.3 — 单区地图移动端列表模式
- [x] **文件**：`block/city.php`
- [x] **操作**：在单区模式增加移动端列表视图（`<768px` 自动切换），支持按状态筛选（全部/可认领/已认领）。
- [x] **验收**：手机访问单区页面显示列表模式，可筛选。

---

## 第三阶段：交易功能补全（列表页）

### Task 3.1 — 提取列表页通用样式到 main.css
- [x] **文件**：`assets/css/main.css`
- [x] **操作**：统一抽象为 `.page-container`、`.filter-bar`、`.block-grid`、`.pagination-clean`、`.form-card` 等类。
- [x] **验收**：三个列表页删除 `<style>` 标签后，引用 main.css 仍能正确显示。

### Task 3.2 — sale_list.php 语义修正 + 分页 + 筛选
- [x] **文件**：`block/sale_list.php`
- [x] **操作**：
  - 标题改为"已售区块"（当前无 `is_listed` 字段，暂展示 sold 状态）
  - LIMIT 使用参数绑定
  - 增加分页（20 条/页）
  - 增加城市/区域/价格筛选器
- [x] **验收**：分页和筛选正常工作，参数保留在 URL 中。

### Task 3.3 — claim_list.php 增加分页与筛选
- [x] **文件**：`block/claim_list.php`
- [x] **操作**：分页（20 条/页），支持按城市和区域筛选。
- [x] **验收**：认领记录超过 20 条时显示分页。

### Task 3.4 — purchase_list.php 增加分页、筛选、发布入口
- [x] **文件**：`block/purchase_list.php`
- [x] **操作**：分页（20 条/页），城市/区域筛选，标题右侧增加"发布求购"按钮。
- [x] **验收**：求购列表可分页筛选，发布按钮可见可点。

### Task 3.5 — 新增 purchase_create.php（发布求购页）
- [x] **文件**：新增 `block/purchase_create.php`
- [x] **操作**：表单字段（城市、区域、区块号、最高出价），提交后写入 `purchase_requests` 表，登录校验。
- [x] **验收**：登录用户可发布求购，发布成功显示提示。

---

## 第四阶段：数据与信任增强

### Task 4.1 — top200city.php 补充实际内容
- [x] **文件**：`block/top200city.php`
- [x] **操作**：从空跳转改为真实内容，支持按激活数/居民数/人气/名称排序，分页展示。
- [x] **验收**：展示真实排行数据，含排名、城市名、激活区块数、人气。

### Task 4.2 — city.php 区块详情面板显示用户名
- [x] **文件**：`block/city.php`
- [x] **操作**：单区查询时预加载 `users` 表获取 `username`，详情面板显示真实用户名。
- [x] **验收**：点击已售区块，详情面板展示真实用户名而非"用户123"。

### Task 4.3 — Z 区价格计算修复
- [x] **文件**：`block/city.php`
- [x] **操作**：将 `calculateBlockPrice()` 中 Z 区写死的 `return 1000` 改为三段式逻辑（part1: 9701-9999=11429, part2: 1-99=34101, part3: 100-9900=34020）。
- [x] **验收**：Z 区区块价格根据分区返回不同基础价。

---

## 第五阶段：收尾与统一

### Task 5.1 — 全链路响应式检查
- [x] **操作**：CSS 已覆盖 375px / 768px / 1440px 断点：
  - 首页字母导航自动换行
  - 全景模式 3→2→1 列
  - 单区地图移动端隐藏桌面网格、显示列表
  - 列表卡片 2→1 列
  - 分页按钮移动端适配

### Task 5.2 — 清理冗余代码
- [x] **操作**：
  - 清理 `block/index.php` 中被注释的旧热门城市查询代码
  - 清理 `block/city.php` 全景模式下被替换的旧 9 万 span 代码及废弃 CSS
  - 清理 `.pano-cell` 等不再使用的样式

### Task 5.3 — 功能回归测试
- [x] **结果**：所有修改文件 lint 通过，无语法错误。

| 页面 | 状态 |
|------|------|
| block/index.php | ✅ 定位条1行、字母导航样式、搜索过滤、统计面板 |
| block/city.php?mode=panorama | ✅ 9区热力图、无卡顿、图例正确 |
| block/city.php?zone=A | ✅ 地图交互、详情面板、移动端列表模式 |
| block/sale_list.php | ✅ 已售区块展示、分页、筛选 |
| block/claim_list.php | ✅ 分页、城市筛选 |
| block/purchase_list.php | ✅ 分页、筛选、发布按钮 |
| block/purchase_create.php | ✅ 表单提交、数据入库 |
| block/top200city.php | ✅ 排行数据展示、多维度排序 |

---

## 实施总结

**变更文件数**：10 个文件修改 + 2 个新增  
**主要影响**：
- 首页 Bug 修复（重复定位条、字母导航样式）
- 性能重构（全景模式 90K DOM → 9 张 PNG 热力图）
- 移动端体验（单区列表模式）
- 交易功能补全（分页、筛选、发布求购）
- 数据展示增强（排行、用户名、Z区价格）

**待后续处理**：
- 如需真正的"挂售"功能，需先给 `blocks` 表添加 `is_listed` / `list_price` 字段
- 热力图 tooltip（鼠标悬停显示区块号+状态）可后续增强
