# 修复互访圈后台 users.php 代码泄漏 & 子站切换器 URL

## 问题

两个独立但相关联的后台缺陷：

### 1. `hufang/admin/users.php` PHP 代码泄漏到页面

第 45 行的 `?>` 提前关闭了 PHP 标签，导致第 47–48 行被当成纯文本输出：

```php
45:}
45:?>                              ← 提前关闭 PHP
46:
47:$admin_site_config = ['site' => 'hufang', 'page_title' => '用户管理'];   ← 当作纯文本输出！
48:require_once '../../shared/admin/admin-header.php';                      ← 未执行
```

后果：
- 页面顶部直接显示 `$admin_site_config = [...]` 文本
- `admin-header.php` 未被 require，后台框架（样式、侧边栏、菜单）完全缺失
- 后续 HTML 直接渲染，无任何后台样式

对比正确的 `hufang/admin/dashboard.php`（第 36–42 行）：PHP 代码连续执行到 `require` 之后才用 `?>` 切换到 HTML。

### 2. 后台子站切换器 URL 指向同域名相对路径

`shared/admin/admin-menu-config.php` 中 `$ADMIN_SITES` 的 `url` 使用了同域相对路径：

```php
'block'  => ['url' => '/block/admin/dashboard.php'],   // ❌
'hufang' => ['url' => '/hufang/admin/dashboard.php'],  // ❌
...
```

在 `v.58.tl` 后台点击"区块交易"会跳转到 `https://v.58.tl/block/admin/dashboard.php`（路径不存在），而非正确的 `https://block.58.tl/admin/dashboard.php`。

各子站实际域名映射（从各 `includes/header.php` 的 `canonical_url` 确认）：

| 站点 key | 正确域名 |
|---------|---------|
| main | 58.tl |
| block | block.58.tl |
| bct | bct.58.tl |
| hufang | v.58.tl |
| mall | mall.58.tl |
| nft | nft.58.tl |

## 目标

- 让 `hufang/admin/users.php` 正常加载统一后台框架
- 让后台右上角子站切换器跳转到正确的子域名

## 范围

### In Scope
- 修复 `hufang/admin/users.php` 的 PHP 标签闭合位置
- 修复 `shared/admin/admin-menu-config.php` 中 `$ADMIN_SITES` 的 URL 为子域名绝对 URL

### Out of Scope
- 侧边栏菜单内 `url`（如 `dashboard.php`、`users.php`）——这些是站内相对路径，正确
- 新增功能、数据库变更
- 其它 admin 页面的检查（探索阶段已确认仅 users.php 有此 `?>` bug）

## 成功标准

1. 访问 `https://v.58.tl/admin/users.php` 不再显示 PHP 代码，正常加载后台框架
2. 在任意子站后台右上角切换器点击其它子站，跳转到对应子域名（如 `block.58.tl/admin/dashboard.php`）
3. 跨子站切换无需重新登录（依赖已有的 `.58.tl` session cookie domain）
