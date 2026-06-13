# 统一后台管理系统 — 设计文档

## 架构设计

```
┌─────────────────────────────────────────────────────────────────────┐
│                        统一后台管理框架架构                           │
├─────────────────────────────────────────────────────────────────────┤
│                                                                      │
│  ┌──────────────┐    ┌──────────────┐    ┌──────────────┐           │
│  │ 主站总控后台  │    │ block管理后台 │    │ 各子站后台    │           │
│  │ admin/       │    │ block/admin/ │    │ mall/nft/    │           │
│  │              │    │              │    │ hufang/admin/│           │
│  └──────┬───────┘    └──────┬───────┘    └──────┬───────┘           │
│         │                   │                   │                   │
│         └───────────────────┼───────────────────┘                   │
│                             │                                       │
│              ┌──────────────┴──────────────┐                        │
│              │      shared/admin/           │                        │
│              │  ├─ admin-header.php         │                        │
│              │  ├─ admin-footer.php         │                        │
│              │  ├─ admin-menu-config.php    │                        │
│              │  └─ admin-auth.php           │                        │
│              └──────────────┬──────────────┘                        │
│                             │                                       │
│              ┌──────────────┴──────────────┐                        │
│              │      assets/css/admin.css    │                        │
│              │  统一现代化深色主题样式        │                        │
│              └─────────────────────────────┘                        │
│                                                                      │
│  ┌────────────────────────────────────────────────────────────┐     │
│  │              classes/AdminAuth.php (RBAC)                   │     │
│  │  ├─ isAdmin() / isSuperAdmin()                             │     │
│  │  ├─ checkPermission($permission)                           │     │
│  │  └─ getAdminMenus($role)                                   │     │
│  └────────────────────────────────────────────────────────────┘     │
│                                                                      │
└─────────────────────────────────────────────────────────────────────┘
```

## 数据库设计

### 新增表 `admin_roles`

```sql
CREATE TABLE admin_roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role_key VARCHAR(32) NOT NULL UNIQUE COMMENT 'super_admin, admin, moderator',
    role_name VARCHAR(64) NOT NULL COMMENT '显示名称',
    permissions JSON NOT NULL COMMENT '权限列表',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
```

### 新增表 `admin_assignments`

```sql
CREATE TABLE admin_assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    role_key VARCHAR(32) NOT NULL DEFAULT 'admin',
    site_scope VARCHAR(32) DEFAULT 'all' COMMENT 'all / block / mall / nft / hufang / bct',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_role (user_id, role_key, site_scope)
);
```

### users 表兼容

保持现有 `users.role` 字段兼容，初始化脚本将 `role='admin'` 的用户同步到 `admin_assignments`。

## 视觉设计系统

### 色彩方案

| Token | 值 | 用途 |
|-------|-----|------|
| `--admin-bg` | `#0f172a` | 页面背景 |
| `--admin-sidebar` | `#1e293b` | 侧边栏背景 |
| `--admin-sidebar-hover` | `#334155` | 菜单 hover |
| `--admin-accent` | `#ff6b00` | 品牌高亮色 |
| `--admin-accent-light` | `#ff9500` | 渐变辅助色 |
| `--admin-card` | `#1e293b` | 卡片背景 |
| `--admin-card-border` | `#334155` | 卡片边框 |
| `--admin-text` | `#f1f5f9` | 主文字 |
| `--admin-text-muted` | `#94a3b8` | 次要文字 |
| `--admin-success` | `#22c55e` | 成功状态 |
| `--admin-warning` | `#f59e0b` | 警告状态 |
| `--admin-danger` | `#ef4444` | 危险状态 |

### 布局结构

```
┌────────────────────────────────────────────────────────────┐
│  🔶 Logo    58 管理后台          [站点切换 ▼]  👤 admin ▼  │ ← 顶部导航栏 (60px)
├──────────┬─────────────────────────────────────────────────┤
│          │                                                 │
│  📊 概览  │                                                 │
│  👥 用户  │              主内容区域                          │
│  🏪 商城  │              (padding: 24px)                     │
│  🎨 NFT  │                                                 │
│  ⬛ 区块 │                                                 │
│  🤝 互访 │                                                 │
│  💰 BCT  │                                                 │
│          │                                                 │
│  ────────│                                                 │
│  ⚙️ 设置 │                                                 │
│          │                                                 │
│  Sidebar │                                                 │
│  (240px) │                                                 │
│          │                                                 │
└──────────┴─────────────────────────────────────────────────┘
```

### 组件规范

**统计卡片 (Stat Card)**
- 圆角：16px
- 背景：`#1e293b`
- 边框：1px solid `#334155`
- 阴影：`0 4px 20px rgba(0,0,0,0.15)`
- 悬停：`transform: translateY(-2px)` + 阴影加深
- 数字：36px bold，渐变色文字
- 标签：14px `#94a3b8`

**数据表格 (Data Table)**
- 表头：14px 600 weight，背景 `#1e293b`，文字 `#94a3b8`
- 行高：52px
- 行 hover：`#334155`
- 分隔线：1px solid `#334155`
- 状态标签：圆角 20px，padding 4px 12px

**侧边栏菜单 (Sidebar Menu)**
- 项高：44px
- 图标：20px，右侧 12px margin
- 当前项：左侧 3px `#ff6b00` 竖线 + 背景 `#334155`
- 站点分组：菜单项间用分隔线区分不同子站

## 共享组件设计

### `shared/admin/admin-header.php`

```php
<?php
/**
 * 统一后台头部
 * 用法: 在各后台页面顶部 require_once
 * 
 * 前置条件: $admin_site_config 数组已定义
 *   - site: 'mall'|'nft'|'hufang'|'block'|'bct'|'main'
 *   - page_title: 当前页面标题
 *   - extra_head: 额外 CSS/JS（可选）
 */

require_once __DIR__ . '/admin-auth.php';
checkAdminAccess($admin_site_config['site'] ?? 'all');

$sites = getAllAdminSites();  // 返回所有可访问的后台站点列表
$currentUser = getCurrentAdminUser();
$sidebarMenus = getAdminMenus($currentUser['role'], $admin_site_config['site']);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>...</head>
<body class="admin-body">
  <div class="admin-wrapper">
    <!-- 顶部导航栏 -->
    <header class="admin-topbar">...</header>
    <!-- 侧边栏 -->
    <aside class="admin-sidebar">...</aside>
    <!-- 主内容区 -->
    <main class="admin-main">
```

### `shared/admin/admin-footer.php`

```php
    </main>
  </div>
  <script src="/assets/js/admin.js"></script>
</body>
</html>
```

### `shared/admin/admin-menu-config.php`

定义所有子站的菜单结构，按角色权限过滤展示。

## 各后台页面设计

### 主站总控后台 (`admin/dashboard.php`)

**核心汇总指标**（顶部 6 张统计卡片）：
1. 平台总用户数（所有子站去重）
2. 今日活跃用户数
3. 总区块交易量 / 交易金额
4. 总 BCT 交易额
5. 总商城订单数 / 金额
6. 总 NFT 交易数

**下方区域**（2 列布局）：
- 左侧：各子站活跃度趋势图（最近 7 天）
- 右侧：最新异常事件 / 待审核列表（跨站点汇总）

### block 管理后台 (`block/admin/dashboard.php`)

**专属指标**：
- 各城市区块激活率
- 今日新认领区块数
- 待审核交易数
- 活跃城市排名 Top 10

**管理菜单**：
- 城市管理（增删改查城市）
- 区块管理（查看/调整区块状态）
- 交易审核（claim/sale/purchase 审核）
- 用户区块查询

### 现有后台迁移策略

**mall/admin/** 和 **nft/admin/**：
- 移除所有内联 CSS（~400 行）
- 将 `<style>...</style>` 替换为 `<?php require_once '../../shared/admin/admin-header.php'; ?>`
- 保留原有数据查询逻辑和 HTML 结构
- 将 `<div class="admin-container">` 内容放入 `admin-main`

**hufang/admin/**：
- 逐步替换 Bootstrap 类名为统一 admin 类名
- 保留原有业务逻辑

## 跨站点导航设计

顶部导航栏右侧放置 **"站点切换"下拉菜单**：

```
[站点切换 ▼]
  ├─ 🏠 总控后台     → /admin/dashboard.php
  ├─ ⬛ 区块交易后台  → /block/admin/dashboard.php
  ├─ 💰 BCT 市场后台 → /bct/user/dashboard.php
  ├─ 🤝 互访圈后台   → /hufang/admin/dashboard.php
  ├─ 🛒 人气商城后台  → /mall/admin/dashboard.php
  └─ 🎨 NFT 头像后台  → /nft/admin/dashboard.php
```

仅展示当前管理员有权限访问的站点。无权限的站点显示为灰色不可点。

## 响应式设计

后台以桌面端为主，但基础响应式：
- ≥1024px：侧边栏 240px 常驻
- 768-1023px：侧边栏可收起为图标栏 64px
- <768px：侧边栏隐藏，通过汉堡菜单触发

## 安全设计

1. 所有后台页面入口必须先 `require_once admin-auth.php`
2. `checkAdminAccess()` 检查 session + 数据库双重验证
3. 敏感操作（删除、审核通过）需二次确认 + CSRF token
4. 操作日志写入 `admin_logs` 表（用户、时间、IP、操作、影响对象）
