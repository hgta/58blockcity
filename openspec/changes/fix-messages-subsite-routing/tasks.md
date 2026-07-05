# 站内信子站路由 — 任务

## 阶段 1：提取核心文件

- [ ] 1.1 将 `messages/index.php` 核心逻辑提取到 `assets/shared/messages-core.php`
- [ ] 1.2 将 `messages/ajax.php` 核心逻辑提取到 `assets/shared/messages-ajax.php`
- [ ] 1.3 核心文件内路径统一使用 `MSG_ROOT` 常量
- [ ] 1.4 核心文件增加登录回跳 `$_SESSION['redirect_url']`

## 阶段 2：各子站 wrapper

- [ ] 2.1 创建 `mall/messages/index.php` + `mall/messages/ajax.php`
- [ ] 2.2 创建 `block/messages/index.php` + `block/messages/ajax.php`
- [ ] 2.3 创建 `nft/messages/index.php` + `nft/messages/ajax.php`
- [ ] 2.4 创建 `hufang/messages/index.php` + `hufang/messages/ajax.php`
- [ ] 2.5 创建 `bct/messages/index.php` + `bct/messages/ajax.php`
- [ ] 2.6 保留 `messages/`（项目根）作为 www.58.tl 入口

## 阶段 3：链接修复

- [ ] 3.1 `shared/header.php`：站内信链接改为 `/messages/`
- [ ] 3.2 `assets/js/message-modal.js`：AJAX URL 改为相对路径 `/messages/ajax.php`
- [ ] 3.3 各子站 Nginx 增加 `/messages/` 路由（已有 try_files，无需改动）

## 阶段 4：验证

- [ ] 4.1 各子站 `/messages/` 可访问
- [ ] 4.2 未登录跳登录后自动回到站内信
- [ ] 4.3 发送/接收消息正常
