# 站内信：子站路由 + 登录回跳

## 问题

1. **统一跳转到主站**：各子站（mall/block/nft等）点击"站内信"统一跳转到 `www.58.tl/messages/`，跨域体验差
2. **登录后不自动回跳**：未登录用户访问 `/messages/` 跳转登录页，登录成功后不知道回哪里
3. **messages/index.php 只有一份**：放在项目根，只有 www.58.tl 能直接访问

## 目标

1. 每个子站可通过自己的路径访问站内信（`mall.58.tl/messages/`, `block.58.tl/messages/`等）
2. 保持 `www.58.tl/messages/` 作为统一入口
3. 未登录用户登录后自动跳回站内信页
4. 代码不重复——业务逻辑集中在共享文件

## 范围

| In scope | Out of scope |
|----------|-------------|
| 各子站 messages wrapper | 改变消息隐私模型（已正确） |
| 共享核心业务文件 | 消息图片/附件发送 |
| 登录回跳 redirect | 消息通知推送 |
| header 链接改为相对路径 | |

## 方案

提取 `messages/index.php` 核心到 `assets/shared/messages-core.php`，各子站 `messages/index.php` 作为轻量 wrapper 设置路径后引入。header 链接去掉绝对 URL，用 `/messages/` 相对路径。
