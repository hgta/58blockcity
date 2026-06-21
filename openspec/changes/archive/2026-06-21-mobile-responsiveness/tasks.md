# Tasks: 全站移动端响应式优化

## Task 1: 共享头部汉堡菜单 🔴 P0

**文件:** `shared/header.php`

- [x] 添加 `.menu-toggle` 按钮（☰ 图标），默认 `display:none`
- [x] `@media(max-width:768px)` 显示 toggle、隐藏 `.header-actions`
- [x] JS 点击 toggle 切换 `.header-actions` 显示/隐藏
- [x] 展开时 `.nav-button` 变为 `display:block; padding:12px 16px; font-size:15px`
- [x] 点击外部关闭菜单
- [x] 展开背景加半透明遮罩

**影响面:** 全站所有页面（mall、hufang、admin 都通过 shared/header.php）

---

## Task 2: 支付设置页表格适配 🟡 P1

**文件:** `mall/shop/payment-settings.php`

- [x] 添加 `@media(max-width:768px)` 规则
- [x] 表格 thead 隐藏
- [x] td 设 `display:block; width:100%`
- [x] 用 `::before` 加标签（城市/区块/启用）
- [x] 搜索添加区域在小屏堆叠

---

## Task 3: 商品管理工具栏适配 🟡 P1

**文件:** `mall/shop/products.php`

- [x] 添加 `@media(max-width:768px)` 规则
- [x] `.product-toolbar` 改为纵向堆叠
- [x] 筛选 tabs 可横向滚动
- [x] 搜索框全宽
- [x] 批量操作按钮缩小

---

## Task 4: 表单页通用响应式 🟡 P2

**文件:** `mall/user/profile.php`、`mall/user/security.php`、`mall/shop/create.php`

- [x] 各文件添加 `@media(max-width:768px)`
- [x] 表单元素 `width:100%`
- [x] 按钮 `min-height:44px`（符合触控标准）
- [x] 提交按钮全宽
