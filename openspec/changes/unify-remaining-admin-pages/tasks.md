# 统一剩余后台页面 — 任务清单

## 批1: mall/admin（3页）

- [ ] `mall/admin/categories.php` — Bootstrap → 统一框架
  - 移除 `header.php`/`footer.php`，接入 admin-header.php/admin-footer.php
  - 移除手写侧边栏（list-group）
  - Bootstrap class 转 admin-* class
  - 保留 modal 编辑器功能
  - POST 处理移到 header 之前
- [ ] `mall/admin/shops.php` — Bootstrap → 统一框架（同上）✅ POST 已前置
- [ ] `mall/admin/seed.php` — 自定义 → 统一框架
  - 移除自建 HTML 结构
  - 自定义 CSS 类名转 admin-* 类名

## 批2: bct/admin + menu-config（4项）

- [ ] `bct/admin/dashboard.php` — 内联样式 → 统一框架
  - 删除 ~200 行内联 `<style>` + 自建 sidebar HTML
  - 保留 BCT 统计查询和数据展示
- [ ] `bct/admin/bct_management.php` — Bootstrap → 统一框架
- [ ] `bct/admin/trigger_match.php` — Bootstrap → 统一框架
- [ ] `shared/admin/admin-menu-config.php` — 补 bct 菜单 + 修 URL

## 批3: admin/ 根级（3页）

- [ ] `admin/dashboard.php` — 内联样式 → 统一框架（减少 ~200 行重复代码）
- [ ] `admin/users.php` — 同上
- [ ] `admin/shops.php` — 同上

## 批4: hufang/admin（10页）

- [ ] `hufang/admin/users.php` — Bootstrap → 统一框架
- [ ] `hufang/admin/circles.php` — 同上
- [ ] `hufang/admin/visits.php` — 同上
- [ ] `hufang/admin/cities.php` — 同上
- [ ] `hufang/admin/city_add.php` — 同上
- [ ] `hufang/admin/city_edit.php` — 同上
- [ ] `hufang/admin/system_settings.php` — 同上
- [ ] `hufang/admin/user_detail.php` — 同上
- [ ] `hufang/admin/user_edit.php` — 同上
- [ ] `hufang/admin/visit_detail.php` — 同上
