# 统一剩余后台页面风格

**父变更**: `unified-admin-dashboard`

## 问题

`unified-admin-dashboard` 完成了共享框架搭建和部分页面迁移，但仍有 **19 个页面** 使用旧 Bootstrap 或自建内联样式：

- **mall/admin/**: `categories.php`, `shops.php`, `seed.php`（3页）
- **hufang/admin/**: `users.php`, `circles.php`, `visits.php`, `cities.php`, `city_add.php`, `city_edit.php`, `system_settings.php`, `user_detail.php`, `user_edit.php`, `visit_detail.php`（10页）
- **bct/admin/**: `dashboard.php`, `bct_management.php`, `trigger_match.php`（3页）
- **admin/ (根级)**: `dashboard.php`, `users.php`, `shops.php`（3页）

这导致视觉割裂、维护成本高，且部分页面（如 `categories.php`）存在 headers already sent 风险（header 引入在权限检查之前）。

## 目标

将所有剩余后台页面统一迁移到 `shared/admin/admin-header.php` + `admin-footer.php` 框架。

## 范围

### In Scope
- 迁移 3 种风格 → 统一框架：旧 Bootstrap、自建内联、独立自定义
- 修复迁移过程中发现的路径错误、类名错误
- 补充 `admin-menu-config.php` 缺失的菜单项
- 修复 BCT 站点 URL（`/bct/user/dashboard.php` → `/bct/admin/dashboard.php`）

### Out of Scope
- 新增功能
- 数据库变更
- 前台页面

## 分批策略

| 批次 | 子站 | 文件数 | 风险 |
|------|------|--------|------|
| 1 | mall/admin | 3 | 低（含 modal 编辑器的 categories.php 需验证） |
| 2 | bct/admin + menu-config | 3+1 | 低 |
| 3 | admin/ 根级 | 3 | 低 |
| 4 | hufang/admin | 10 | 低（纯表格页，结构简单） |

## 成功标准

1. 所有后台页面加载无样式错乱
2. 无 "headers already sent" 警告
3. 侧边栏菜单覆盖所有已有页面
4. 跨站点导航正常工作
