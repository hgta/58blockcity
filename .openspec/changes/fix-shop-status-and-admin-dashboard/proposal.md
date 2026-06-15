创建时间: 2026-06-15

## 问题摘要

用户报告两个独立但都需要立即解决的问题：

### 问题 A: 新建店铺显示"审核中"

当前 `Shop::createShop()` 在插入数据库时未设置 `status` 字段，导致数据库默认值 `pending`（审核中）生效。用户明确要求"新建店铺不用审核直接开"。

### 问题 B: admin/dashboard.php 完全不可用

`admin/dashboard.php` 文件内容被错误地复制了 `mall/shop/create.php`（创建店铺页面）的完整内容。这是一个严重的文件混淆问题。需要完全重写为真正的后台管理总控面板。

---

## 问题 A 分析

- 根因：`createShop()` 的 INSERT 语句缺少 `status` 字段，数据库默认值为 `pending`
- 影响：用户创建店铺后看到"审核中"状态，无法立即使用
- 修复方式：在 `createShop()` 中显式设置 `status = 'active'`
- 关联检查：`getUserShop()` 方法中硬编码 `WHERE s.status = 'active'`，如果店铺状态不是 active 会被过滤掉，这进一步加剧了问题

## 问题 B 分析

- 根因：文件内容被错误复制
- 影响：https://mall.58.tl/admin/dashboard.php 完全打不开或打开后显示创建店铺页面
- 修复方式：重写 `admin/dashboard.php` 为完整的后台管理首页
- 范围：admin 目录下目前只有 `index.php`（重定向到 dashboard）和 `dashboard.php`（错误内容），缺少完整的后台管理页面和菜单

---

## 实施范围

### 任务 1: 修复新建店铺状态
- 修改 `classes/Shop.php`：`createShop()` 中显式设置 `status = 'active'`

### 任务 2: 构建 admin 后台管理首页
- 重写 `admin/dashboard.php`：包含以下模块
  - 顶部数据看板（总用户数、总店铺数、总订单数、总商品数）
  - 侧边导航菜单（仪表板、用户管理、店铺管理、订单管理、商品管理、财务管理、系统设置）
  - 最近注册的用户列表
  - 待审核的店铺列表（虽然现在新建直接 active，但保留审核功能便于后续管理违规店铺）
  - 最近订单列表
  - 数据趋势图表（使用纯 CSS/HTML 或简单 JS 实现，不引入外部依赖）
- 增加 `admin/includes/header.php` 和 `admin/includes/footer.php`：后台统一布局
- 增加 `admin/includes/sidebar.php`：后台侧边导航
- 增加 `admin/users.php`：用户管理列表（简单实现）
- 增加 `admin/shops.php`：店铺管理列表（简单实现）

### 任务 3: 权限控制
- admin 页面需要检查用户是否为管理员角色（`role = 'admin'`），否则重定向到首页
- 使用 `$_SESSION['user_role']` 进行判断

---

## 技术决策

- 设计风格：与前端 mall 风格一致，使用橙色 `#ff6b00` 主题色，但后台使用更紧凑的数据看板风格
- 布局：左侧固定侧边栏 + 右侧主内容区，响应式设计（移动端侧边栏隐藏）
- 数据获取：使用现有 `Shop`、`User`、`Order`、`Product` 类直接查询
- 图表：使用纯 CSS 进度条或简单 HTML 表格，不引入 Chart.js 等外部库（保持简单）
- 安全：所有 admin 页面顶部检查 `isAdmin()`，未登录或角色非 admin 直接重定向

---

## 验收标准

- [ ] 新建店铺后状态立即为 `active`，用户可在"我的店铺"中看到并管理
- [ ] admin/dashboard.php 能正常打开并显示真实的总数据看板
- [ ] admin 页面有统一导航和侧边栏
- [ ] 非管理员用户无法访问 admin 目录下的任何页面
- [ ] 移动端能正常访问（侧边栏可收起）
