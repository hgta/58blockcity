# 设计：迁移 hufang/admin 到统一框架

## admin.css 补全

blocks.php 已用但未定义的 4 个类：

```css
/* 表单行（横向排列多个表单组） */
.admin-form-row {
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
    align-items: flex-end;
    padding: 16px 20px;
}
.admin-form-row .admin-form-group { flex: 1; min-width: 180px; }

/* 辅助文本色 class（非 CSS 变量） */
.admin-text-muted { color: var(--admin-text-muted); }

/* 表格滚动容器 */
.admin-table-responsive { overflow-x: auto; }

/* 默认按钮（次要操作） */
.admin-btn-default {
    background: var(--admin-bg);
    color: var(--admin-text);
    border: 1px solid var(--admin-border);
}
.admin-btn-default:hover { background: var(--admin-bg-hover); }
```

## 页面迁移策略

每个页面的改造模式一致，以 `users.php` 为例：

### 改造前（Bootstrap）
```html
<div class="container admin-container">
    <div class="admin-header"><h1>...</h1><nav>面包屑</nav></div>
    <div class="alert alert-success">...</div>
    <div class="card mb-4"><div class="card-body">
        <div class="row"><div class="col-md-8">
            <div class="input-group">
                <input class="form-control">
                <div class="input-group-append"><button class="btn btn-primary">搜索</button></div>
            </div>
        </div></div>
    </div></div>
    <div class="card">
        <div class="card-header bg-white"><h5>用户列表</h5></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0"><thead class="thead-light">...</thead>...</table>
            </div>
        </div>
        <ul class="pagination">...</ul>
    </div>
</div>
```

### 改造后（admin-* 框架）
```html
<!-- 框架 admin-header 已提供 page-header，移除 container/admin-header/面包屑 -->
<?php if (isset($_SESSION['message'])): ?>
    <div class="admin-alert success">...</div>
<?php endif; ?>

<!-- 筛选卡片 -->
<div class="admin-card">
    <div class="admin-card-header"><span class="admin-card-title">筛选</span></div>
    <div class="admin-card-body">
        <form method="get" class="admin-form-row">
            <div class="admin-form-group" style="flex:2;">
                <input class="admin-form-input" name="search" placeholder="...">
            </div>
            <div class="admin-form-group">
                <button class="admin-btn admin-btn-primary"><i class="fas fa-search"></i> 搜索</button>
                <a href="users.php" class="admin-btn admin-btn-default">重置</a>
            </div>
        </form>
    </div>
</div>

<!-- 数据表格 -->
<div class="admin-card">
    <div class="admin-card-header" style="justify-content:space-between;">
        <span class="admin-card-title">用户列表</span>
        <span class="admin-text-muted">共 N 条</span>
    </div>
    <div class="admin-table-responsive">
        <table class="admin-data-table">
            <thead><tr>...</tr></thead>
            <tbody>
                <tr>... <span class="admin-badge success">活跃</span> ...</tr>
            </tbody>
        </table>
    </div>
    <!-- 分页 -->
    <div class="admin-pagination">
        <div class="admin-page-info">第 N 页 / 共 M 页</div>
        <div class="admin-page-buttons">
            <a class="admin-btn admin-btn-sm admin-btn-default">上一页</a>
            <a class="admin-btn admin-btn-sm admin-btn-primary">2</a>
            <a class="admin-btn admin-btn-sm admin-btn-default">下一页</a>
        </div>
    </div>
</div>
```

## 页面分类

| 类型 | 页面 | 复杂度 |
|------|------|--------|
| 列表+分页 | users, circles, visits, cities | 中（表格+筛选+分页） |
| 详情页 | user_detail, visit_detail | 低（只读展示） |
| 表单页 | city_add, city_edit, user_edit, system_settings | 中（表单提交） |

## 分页统一

admin.css 的 `.admin-pagination` 是 flex 容器（`a`/`span` 子元素横向排列）。hufang 现有的 Bootstrap 分页（`ul.pagination > li.page-item > a.page-link`）改为 `<div class="admin-pagination">` 内放 `<a>` 元素，参考 blocks.php 的分页写法。

## 风险

| 风险 | 缓解 |
|------|------|
| 表单页 POST 提交逻辑受影响 | 仅改 HTML 类名，不动 form action/method/input name |
| 表格列宽变化 | admin-data-table 自适应，必要时用 inline style 微调 |
| 操作按钮组 btn-group | 改为 inline-flex 容器包裹多个 admin-btn-sm |
| 图标按钮 title 属性 | 保留 |
