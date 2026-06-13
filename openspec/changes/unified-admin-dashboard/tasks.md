# 统一后台管理系统 — 任务清单

## Phase 1: 基础框架搭建

### 1.1 创建共享样式文件
- [ ] `assets/css/admin.css` — 统一后台样式（~300 行）
  - CSS 变量定义（色彩、间距、圆角、阴影）
  - 顶部导航栏 `.admin-topbar`
  - 侧边栏 `.admin-sidebar`
  - 主内容区 `.admin-main`
  - 统计卡片 `.admin-stat-card`
  - 数据表格 `.admin-data-table`
  - 表单组件 `.admin-form-*`
  - 按钮 `.admin-btn`
  - 状态标签 `.admin-badge`
  - 响应式断点
- [ ] `assets/js/admin.js` — 通用交互脚本
  - 侧边栏展开/收起
  - 移动端菜单切换
  - 表格排序、搜索过滤
  - 确认弹窗封装

### 1.2 创建共享管理组件
- [ ] `shared/admin/admin-auth.php` — 统一权限检查
  - `isLoggedIn()` 复用现有 auth
  - `isAdmin($site = 'all')` — 检查管理员权限
  - `checkAdminAccess($site)` — 入口守卫，无权限返回 403
  - `getCurrentAdminUser()` — 获取当前管理员信息
- [ ] `shared/admin/admin-header.php` — 统一头部模板
  - 接收 `$admin_site_config` 配置
  - 自动调用 `checkAdminAccess()`
  - 渲染顶部导航栏 + 站点切换下拉
  - 渲染侧边栏菜单（根据角色过滤）
  - 引入 `admin.css` + `admin.js`
- [ ] `shared/admin/admin-footer.php` — 统一尾部模板
  - 关闭 `admin-main` + `admin-wrapper`
- [ ] `shared/admin/admin-menu-config.php` — 菜单配置中心
  - 定义所有子站菜单结构数组
  - `getAdminMenus($role, $currentSite)` 函数
  - `getAllAdminSites()` 函数返回站点列表

### 1.3 数据库迁移
- [ ] 创建 `admin_roles` 表（初始化数据：super_admin, admin, moderator）
- [ ] 创建 `admin_assignments` 表
- [ ] 创建 `admin_logs` 表（操作日志）
- [ ] 初始化脚本：将 `users.role='admin'` 同步到 `admin_assignments`

---

## Phase 2: 主站总控后台

### 2.1 创建总控后台入口
- [ ] `admin/index.php` — 登录检查 + 重定向到 dashboard
- [ ] `admin/dashboard.php` — 总控数据看板
  - 查询所有子站汇总数据
  - 6 张顶部统计卡片
  - 活跃度趋势区域（占位，后续接入图表）
  - 待审核事件列表
- [ ] `admin/includes/` — 总控后台专属包含文件

### 2.2 总控后台专属页面
- [ ] `admin/sites.php` — 各子站状态监控（在线/数据概览）
- [ ] `admin/users.php` — 全站用户管理（汇总搜索）
- [ ] `admin/logs.php` — 管理员操作日志查看

---

## Phase 3: block 管理后台

### 3.1 创建 block 管理后台
- [ ] `block/admin/dashboard.php` — 区块管理看板
  - 各城市激活率统计
  - 今日认领/交易数
  - 活跃城市 Top 10
- [ ] `block/admin/cities.php` — 城市管理列表
- [ ] `block/admin/city_edit.php` — 城市编辑表单
- [ ] `block/admin/blocks.php` — 区块查询与管理
- [ ] `block/admin/transactions.php` — 交易记录审核
- [ ] `block/admin/includes/` — block 后台专属包含

### 3.2 block 管理菜单配置
- [ ] 在 `admin-menu-config.php` 中定义 block 专属菜单

---

## Phase 4: 现有后台迁移

### 4.1 mall 后台迁移
- [ ] `mall/admin/dashboard.php` — 接入统一框架
  - 移除内联 CSS（~400 行）
  - 替换为 `admin-header.php` / `admin-footer.php`
  - 保留原有数据查询和卡片结构
  - 样式类名映射到 admin.css 对应类
- [ ] `mall/admin/categories.php` — 同步迁移
- [ ] `mall/admin/shops.php` — 同步迁移
- [ ] `mall/admin/seed.php` — 同步迁移

### 4.2 nft 后台迁移
- [ ] `nft/admin/dashboard.php` — 接入统一框架
  - 移除内联 CSS（~400 行）
  - 替换为统一头部/尾部
- [ ] `nft/admin/appeal_review.php` — 同步迁移

### 4.3 hufang 后台迁移
- [ ] `hufang/admin/dashboard.php` — 接入统一框架
  - 逐步替换 Bootstrap 类名为 admin.css 类名
  - 保留业务逻辑
- [ ] `hufang/admin/users.php` — 同步迁移
- [ ] `hufang/admin/circles.php` — 同步迁移
- [ ] `hufang/admin/visits.php` — 同步迁移
- [ ] `hufang/admin/cities.php` — 同步迁移
- [ ] `hufang/admin/*.php` — 其余管理页面同步迁移

---

## Phase 5: 统一权限与导航

### 5.1 权限检查统一
- [ ] 所有后台入口文件添加 `require_once '../../shared/admin/admin-auth.php'`
- [ ] 移除各子站独立的权限检查代码
- [ ] 超级管理员 (`super_admin`) 可访问所有站点
- [ ] 普通管理员 (`admin`) 仅可访问被分配的站点

### 5.2 跨站点导航
- [ ] `admin-header.php` 中实现站点切换下拉
- [ ] 根据当前管理员权限动态渲染可访问站点
- [ ] 当前站点高亮显示

### 5.3 操作日志
- [ ] `AdminAuth` 类添加 `logAdminAction()` 方法
- [ ] 所有敏感操作（删除、审核、修改）自动记录日志

---

## Phase 6: 测试与验收

### 6.1 功能测试
- [ ] 每个后台页面都能正常加载，无样式错乱
- [ ] 跨站点导航能正确跳转
- [ ] 权限检查能正确拦截无权限用户
- [ ] 操作日志正确记录

### 6.2 兼容性测试
- [ ] 桌面端 Chrome/Firefox/Safari 正常
- [ ] 平板端侧边栏收起/展开正常
- [ ] 无内联 CSS 残留（搜索 `<style>` 标签）

### 6.3 性能检查
- [ ] admin.css 被浏览器缓存
- [ ] 各后台页面不再重复加载相同样式

---

## 任务优先级

```
P0 (必须先完成):
  ├─ 1.1 assets/css/admin.css
  ├─ 1.2 shared/admin/*.php (共享组件)
  ├─ 1.3 数据库迁移
  └─ 3.1 block/admin/dashboard.php (新增后台)

P1 (框架完成后立即做):
  ├─ 2.1 admin/dashboard.php (总控)
  ├─ 4.1 mall/admin/ 迁移
  └─ 4.2 nft/admin/ 迁移

P2 (后续迭代):
  ├─ 4.3 hufang/admin/ 迁移
  ├─ 3.2 block 管理菜单扩展
  └─ 5.3 操作日志完善
```

## 预估工作量

| 阶段 | 预估文件数 | 预估工时 |
|------|-----------|---------|
| Phase 1 | 6 新文件 | 2-3 小时 |
| Phase 2 | 4 新文件 | 1-2 小时 |
| Phase 3 | 6 新文件 | 2-3 小时 |
| Phase 4 | 修改 ~10 文件 | 3-4 小时 |
| Phase 5 | 修改 ~10 文件 | 1-2 小时 |
| Phase 6 | - | 1 小时 |
| **总计** | **~36 文件** | **10-15 小时** |
