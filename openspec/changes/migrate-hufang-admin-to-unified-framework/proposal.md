# 迁移 hufang/admin 10 页面到统一后台框架 + 补全 admin.css

## 问题

`?>` 泄漏 bug 已修复（commit 2b969af），后台框架能加载了。但 hufang/admin 的 10 个页面内容区仍在用 Bootstrap 类，而 `shared/admin/admin-header.php` 只加载 `assets/css/admin.css`（不含 Bootstrap），导致：

- `card`/`card-body`/`card-header` 无样式 → 卡片无边框/阴影
- `table table-hover`/`thead-light` 无样式 → 表格无条纹/悬停效果
- `btn`/`btn-primary`/`btn-outline-*` 无样式 → 按钮退化为普通链接
- `badge`/`badge-success` 无样式 → 状态标签无背景色
- `input-group`/`form-control` 无样式 → 表单输入框无边框
- `pagination`/`page-item`/`page-link` 无样式 → 分页无样式

同时 `block/admin/blocks.php`（已用 admin-* 类）引用了 4 个 admin.css 未定义的类：`.admin-form-row`、`.admin-text-muted`(class)、`.admin-table-responsive`、`.admin-btn-default`，导致其筛选栏/分页样式异常。

## 目标

1. 补全 `admin.css` 缺失的类定义
2. 将 10 个 hufang admin 页面的 Bootstrap 类迁移为统一框架的 `admin-*` 类

## 范围

### In Scope
- `assets/css/admin.css`：补 `.admin-form-row`、`.admin-text-muted`、`.admin-table-responsive`、`.admin-btn-default`
- `hufang/admin/` 10 个页面的 HTML 类名迁移：
  - `circles.php`、`visits.php`、`cities.php`、`city_add.php`、`city_edit.php`
  - `users.php`、`user_detail.php`、`user_edit.php`
  - `system_settings.php`、`visit_detail.php`
- 类映射：`card`→`admin-card`、`table`→`admin-data-table`、`btn`→`admin-btn`、`badge`→`admin-badge`、`input-group/form-control`→`admin-form-*`、`pagination`→`admin-pagination`、`alert`→`admin-alert`、`breadcrumb`→移除（框架已有 page-header）

### Out of Scope
- PHP 业务逻辑变更
- 数据库变更
- 前台页面
- 新增功能

## 类映射表

| Bootstrap 类 | admin.css 统一类 | 说明 |
|-------------|-----------------|------|
| `card` | `admin-card` | 卡片容器 |
| `card-body` | `admin-card-body` | 卡片内容 |
| `card-header` | `admin-card-header` | 卡片头部 |
| `table table-hover` | `admin-data-table` | 数据表格 |
| `thead-light` | (table 自带) | 移除 |
| `table-responsive` | `admin-table-responsive` | 表格滚动容器 |
| `btn btn-primary` | `admin-btn admin-btn-primary` | 主按钮 |
| `btn btn-success` | `admin-btn admin-btn-primary` | 成功→主色 |
| `btn btn-outline-*` | `admin-btn admin-btn-sm` | 轮廓按钮→小按钮 |
| `btn btn-sm` | `admin-btn admin-btn-sm` | 小按钮 |
| `badge badge-success` | `admin-badge success` | 成功徽章 |
| `badge badge-secondary` | `admin-badge default` | 次要徽章 |
| `input-group`+`form-control` | `admin-form-group`+`admin-form-input` | 表单组 |
| `pagination`+`page-item` | `admin-pagination`+`a` | 分页 |
| `alert alert-success` | `admin-alert success` | 提示框 |
| `container admin-container` | (移除，框架已包裹) | 布局 |
| `breadcrumb` | (移除，框架有 page-header) | 面包屑 |

## 成功标准

1. 10 个 hufang admin 页面表格/卡片/按钮/徽章/分页正常显示
2. `block/admin/blocks.php` 筛选栏和分页样式正常
3. 无 PHP 错误/警告
4. 菜单高亮、侧边栏、顶栏不受影响
