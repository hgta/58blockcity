# 设计：修复 admin users.php 与子站切换器

## 修复点 1：`hufang/admin/users.php` PHP 标签位置

### 现状（错误）

```php
// 第 20-49 行
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // ... POST 处理 ...
    header('Location: users.php');
    exit();
    } else {
        $_SESSION['error'] = '不能修改超级管理员状态';
    }
}
?>                                                  ← 第 45 行，提前关闭

$admin_site_config = ['site' => 'hufang', 'page_title' => '用户管理'];  ← 泄漏
require_once '../../shared/admin/admin-header.php';                    ← 未执行

<div class="container admin-container">                                ← 无框架渲染
```

### 修复后

删除第 45 行的 `?>`，让 PHP 连续执行到 `require` 之后，再在进入 HTML 前关闭：

```php
    }
}                                                     ← 删除原 ?> 

$admin_site_config = ['site' => 'hufang', 'page_title' => '用户管理'];
require_once '../../shared/admin/admin-header.php';
?>                                                    ← 移到这里

<div class="container admin-container">
```

### 为什么不直接删 `?>`

保留 `?>` 后接 HTML 是该文件现有风格（与 `dashboard.php` 一致），也便于 IDE 语法高亮切换。关键是位置要正确。

### 分页计数 bug（附带发现）

第 203 行 `共 <?= $totalCities ?> 条记录` 使用了未定义变量 `$totalCities`（应为 `$totalUsers`）。该变量在 `users.php` 中从未赋值，会在 `error_reporting` 严格模式下报 Notice。本次一并修复，因为它就在受影响的分页块内。

## 修复点 2：`admin-menu-config.php` 子站切换器 URL

### 现状（错误）

```php
$ADMIN_SITES = [
    'main'   => ['url' => '/admin/dashboard.php'],
    'block'  => ['url' => '/block/admin/dashboard.php'],
    'bct'    => ['url' => '/bct/admin/dashboard.php'],
    'hufang' => ['url' => '/hufang/admin/dashboard.php'],
    'mall'   => ['url' => '/mall/admin/dashboard.php'],
    'nft'    => ['url' => '/nft/admin/dashboard.php'],
];
```

这些相对路径在任意子站下都会拼成 `当前域名 + 路径`，跳转到不存在的地址。

### 修复后

改为子域名绝对 URL：

```php
$ADMIN_SITES = [
    'main'   => ['name'=>'总控后台','url'=>'https://58.tl/admin/dashboard.php',     'icon'=>'fa-home'],
    'block'  => ['name'=>'区块交易','url'=>'https://block.58.tl/admin/dashboard.php','icon'=>'fa-cubes'],
    'bct'    => ['name'=>'BCT市场','url'=>'https://bct.58.tl/admin/dashboard.php',  'icon'=>'fa-coins'],
    'hufang' => ['name'=>'互访圈','url'=>'https://v.58.tl/admin/dashboard.php',      'icon'=>'fa-users'],
    'mall'   => ['name'=>'人气商城','url'=>'https://mall.58.tl/admin/dashboard.php','icon'=>'fa-shopping-bag'],
    'nft'    => ['name'=>'NFT头像','url'=>'https://nft.58.tl/admin/dashboard.php',  'icon'=>'fa-image'],
];
```

### 跨子站登录态

无需额外处理。`includes/auth.php` 已将 session cookie domain 设为 `.58.tl`，跨子站共享登录态。`shared/admin/admin-auth.php` 的 `checkAdminAccess()` 基于 `$_SESSION`，跨子站有效。

### 不修改的部分

侧边栏菜单 `$ADMIN_MENUS` 中的 `url`（如 `dashboard.php`、`users.php`）是站内相对路径，正确，不动。

## 风险

| 风险 | 评估 | 缓解 |
|------|------|------|
| `main` 站点 `58.tl/admin/dashboard.php` 不存在 | 低 — 根级 `admin/` 目录有 dashboard.php（见项目布局） | 如不存在，切换器会 404，但这是独立的既有问题，本次不扩大范围 |
| 修改 menu-config 影响所有子站后台 | 低 — 仅改 URL 字符串，菜单结构不变 | 改动后逐站验证切换器 |
