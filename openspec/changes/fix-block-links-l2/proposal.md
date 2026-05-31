# 修复 block 子站断链 + L2 跨区合并

## 背景

block 子站有 17 个断链，其中导航栏中的 `sale_list.php` 等 3 个链接在每个页面都显示为 404，`buy.php` 被 4 处引用是核心购买入口。

## 问题

- 导航栏 3 个链接 (sale/purchase/claim) 全部 404
- 核心购买流程 (buy/sell/edit) 页面缺失
- 用户中心 (blocks/transactions/profile) 缺失
- 投票/消息系统缺失
- 404.php 缺失

## 目标

创建所有缺失页面，确保链接可达，同时实现 L2 跨区合并（相邻区域边界处的区块可合并）。

## 本次包含

### 断链修复 (17个)
- 导航页: sale_list.php / purchase_list.php / claim_list.php
- 区块操作: buy.php / sell.php / edit.php / cancel_request.php
- 用户页: blocks.php / transactions.php / profile.php / purchase_requests.php
- 其他: vote.php / top200city.php / forgot_password.php / messages/new.php / city/index.php / 404.php

### L2 跨区合并
- 区边界相邻区块可跨区合并认领
