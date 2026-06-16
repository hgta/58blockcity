# 统一剩余后台页面 — 设计文档

## 迁移模式

每个页面遵循统一迁移模板：

```php
<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

// 1. 权限检查
checkAdmin(); // 或手动检查

// 2. 类加载
require_once '../../classes/XXX.php';

// 3. POST 处理（在所有 HTML 输出之前）
if ($_SERVER['REQUEST_METHOD'] === 'POST') { ... }

// 4. 数据查询
$data = ...;

// 5. 统一框架 header（最后引入，确保无输出）
$admin_site_config = ['site'=>'xxx', 'page_title'=>'xxx'];
require_once '../../shared/admin/admin-header.php';
?>
<!-- 6. 页面内容 -->
...
<?php require_once '../../shared/admin/admin-footer.php'; ?>
```

## CSS 类名映射

| 旧类名 (Bootstrap/自建) | 新类名 (统一框架) |
|-------------------------|-------------------|
| `card` | `admin-card` |
| `card-header` | `admin-card-header` |
| `card-body` | `admin-card-body` |
| `card-title` | `admin-card-title` |
| `table table-hover` | `admin-data-table` |
| `thead-light` | 删除（统一框架自带） |
| `badge badge-success` | `admin-badge success` |
| `badge badge-warning` | `admin-badge warning` |
| `badge badge-danger` | `admin-badge danger` |
| `badge badge-info` | `admin-badge info` |
| `badge badge-secondary` | `admin-badge default` |
| `btn btn-primary` | `admin-btn admin-btn-primary` |
| `btn btn-outline-*` | `admin-btn admin-btn-secondary` |
| `btn btn-sm` | `admin-btn admin-btn-sm` |
| `btn btn-block` | `admin-btn` + 宽度 100% |
| `alert alert-success` | `admin-card` + 绿色文字 |
| `alert alert-danger` | `admin-card` + 红色文字 |
| `container-fluid` | 删除（框架已有布局） |
| `row` / `col-md-*` | 删除（框架已有布局） |
| `list-group`（侧边栏） | 删除（框架自带侧边栏） |
| `form-control` | 保留或使用内联样式 |
| `form-group` | 保留 |
| `<style>...</style>`（内联） | 删除 |

## 特殊页面处理

### mall/admin/categories.php
- 有 Bootstrap modal 编辑器 → 模态框保留，class 转换为统一风格
- 内联 JS 编辑器 → 保留，功能不变

### mall/admin/seed.php
- 使用完全自定义 CSS 类名（`form-input`, `btn` 等） → 全部转换为 `admin-*` 类名
- 自建 header/body 结构 → 删除，使用统一框架

### bct/admin/dashboard.php
- 内联 `<style>` ~200 行 sidebar + topbar HTML → 全部删除，使用统一框架
- BCT 专属统计卡片保留，class 转换

### admin/(根级)/dashboard.php, users.php, shops.php
- 三份几乎相同的 ~200 行内联样式 → 全部删除
- 使用统一框架后代码体积缩减 ~60%

## admin-menu-config.php 补充

bct 站点需要补充菜单项：

```php
'bct' => [
    ['icon' => 'fa-home',        'text' => 'BCT看板',    'url' => 'dashboard.php'],
    ['icon' => 'fa-wallet',      'text' => '余额管理',    'url' => 'bct_management.php'],
    ['icon' => 'fa-exchange-alt','text' => '触发匹配',    'url' => 'trigger_match.php'],
],
```

BCT 站点 URL 修复：
```php
'bct' => [
    ...
    'url'  => '/bct/admin/dashboard.php',  // 原为 /bct/user/dashboard.php
]
```
