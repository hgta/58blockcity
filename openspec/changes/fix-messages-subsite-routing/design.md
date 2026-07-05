# 站内信子站路由 — 设计

## 1. 架构

```
assets/shared/messages-core.php    ← 业务逻辑（一次编写）
        ↑            ↑
mall/messages/   block/messages/ ... ← 各子站 wrapper（路径适配）
index.php        index.php
(wrapper)        (wrapper)
```

### wrapper 模板（如 `mall/messages/index.php`）

```php
<?php
$ROOT_DIR = dirname(dirname(__DIR__)); // 指向项目根
define('MSG_ROOT', $ROOT_DIR);
require_once $ROOT_DIR . '/assets/shared/messages-core.php';
```

### messages-core.php 改动

- 去除硬编码的 HTML `<head>` → 改为 $head_content 变量
- 去除 HTML → 改为可被 wrapper 引入的纯逻辑文件（或保持 HTML 但路径统一）

**决定：保持 HTML 输出**，但 include 路径统一使用 `MSG_ROOT` 常量。

```php
// messages-core.php
require_once MSG_ROOT . '/config/database.php';
require_once MSG_ROOT . '/classes/Message.php';
// ... 业务逻辑 ...
// 登录跳转带 redirect
if (!$userId) {
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
    header('Location: /auth/login.php');
    exit;
}
```

## 2. 各子站 wrapper 清单

| 子站 | 文件路径 | ROOT 常量 |
|------|---------|----------|
| www.58.tl | `messages/index.php`（原位） | `dirname(__DIR__)` |
| mall.58.tl | `mall/messages/index.php` | `dirname(dirname(__DIR__))` |
| block.58.tl | `block/messages/index.php` | `dirname(dirname(__DIR__))` |
| nft.58.tl | `nft/messages/index.php` | `dirname(dirname(__DIR__))` |
| v.58.tl | `hufang/messages/index.php` | `dirname(dirname(__DIR__))` |
| bct.58.tl | `bct/messages/index.php` | `dirname(dirname(__DIR__))` |

## 3. Header 链接

```php
<!-- 改前 -->
<a href="https://www.58.tl/messages/">站内信</a>

<!-- 改后 -->
<a href="/messages/">站内信</a>
```

去掉绝对 URL，用相对路径 `/messages/`，自动保持在当前子域名。

## 4. 登录回跳

```php
// messages-core.php
if (!$userId) {
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
    header('Location: /auth/login.php');
    exit;
}
```

`auth/login.php` 已支持 `$_SESSION['redirect_url']`，无需改动登录页。

## 5. AJAX 端点

`messages/ajax.php` 与 `messages-core.php` 一起提取到 `assets/shared/`：

```
assets/shared/messages-ajax.php  ← AJAX 处理器
```

各子站 `messages/ajax.php` 作为 wrapper。

同时更新 `message-modal.js` 中的 AJAX URL → 相对路径 `/messages/ajax.php`。

## 6. 文件汇总

| 操作 | 文件 |
|------|------|
| **新建** | `assets/shared/messages-core.php`（核心） |
| **新建** | `assets/shared/messages-ajax.php`（AJAX） |
| **新建** | `mall/messages/index.php`, `block/messages/index.php`, `nft/messages/index.php`, `hufang/messages/index.php`, `bct/messages/index.php` |
| **新建** | `mall/messages/ajax.php`, `block/messages/ajax.php`, ... |
| **修改** | `messages/index.php` → 改为 wrapper 或直接删掉（www.58.tl 用原位） |
| **修改** | `shared/header.php` → `/messages/` 去掉绝对 URL |
| **修改** | `assets/js/message-modal.js` → AJAX 改相对路径 |
