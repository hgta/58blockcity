# 全站移动端优化 — 设计

## 通用标准

- 触摸目标 ≥ 44px
- 正文 ≥ 14px，辅助字 ≥ 12px
- 表格包裹 `<div class="table-responsive">` 或换卡片
- 表单元素小屏 full-width
- 每页 ≥ 768px + 480px 断点

## 各页面改动

### 1. hufang/index.php — 新增 @media
- `.user-header`: 768px↓ 纵向堆叠
- `.table`: 包裹 table-responsive
- `.city-tags a`: 字号 ≥ 14px

### 2. bct/index.php — 表格+筛选
- 筛选栏: 768px↓ `flex-direction:column`, `width:100%`
- 表格: 隐藏联系方式列

### 3. block/city.php — 单元格
- 992-1200px: font-size 8→10px
- 全景: cell 8→10px, min-width 800→600px

### 4. block/index.php — 网格字号
- td font-size: 9→11px
- 480px↓ td height: 26→22px, font-size: 10px

### 5. mall/product/detail.php — 新增480px断点
- 商品图片全宽，购买按钮 full-width

### 6. mall/user/dashboard.php — 新增断点
- 768px↓ 侧边栏隐藏改汉堡
- 480px↓ stat卡单列

### 7. mall/user/orders.php — 表格
- 包裹 table-responsive

### 8. bct/trade.php — 表单
- 提交按钮 full-width
