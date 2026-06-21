# Tasks: 全站移动端响应式优化

## Task 1: 共享头部汉堡菜单 🔴 P0

**文件:** `shared/header.php`

- [ ] 添加 `.menu-toggle` 按钮（☰ 图标），默认 `display:none`
- [ ] `@media(max-width:768px)` 显示 toggle、隐藏 `.header-actions`
- [ ] JS 点击 toggle 切换 `.header-actions` 显示/隐藏
- [ ] 展开时 `.nav-button` 变为 `display:block; padding:12px 16px; font-size:15px`
- [ ] 点击外部关闭菜单
- [ ] 展开背景加半透明遮罩

**影响面:** 全站所有页面（mall、hufang、admin 都通过 shared/header.php）

---

## Task 2: 支付设置页表格适配 🟡 P1

**文件:** `mall/shop/payment-settings.php`

- [ ] 添加 `@media(max-width:768px)` 规则
- [ ] 表格 thead 隐藏
- [ ] td 设 `display:block; width:100%`
- [ ] 用 `::before` 加标签（城市/区块/启用）
- [ ] 搜索添加区域在小屏堆叠

---

## Task 3: 商品管理工具栏适配 🟡 P1

**文件:** `mall/shop/products.php`

- [ ] 添加 `@media(max-width:768px)` 规则
- [ ] `.product-toolbar` 改为纵向堆叠
- [ ] 筛选 tabs 可横向滚动
- [ ] 搜索框全宽
- [ ] 批量操作按钮缩小

---

## Task 4: 表单页通用响应式 🟡 P2

**文件:** `mall/user/profile.php`、`mall/user/security.php`、`mall/shop/create.php`

- [ ] 各文件添加 `@media(max-width:768px)`
- [ ] 表单元素 `width:100%`
- [ ] 按钮 `min-height:44px`（符合触控标准）
- [ ] 提交按钮全宽
