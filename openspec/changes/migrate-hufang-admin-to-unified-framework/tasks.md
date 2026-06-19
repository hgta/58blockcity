# 任务：迁移 hufang/admin 10 页面 + 补全 admin.css

## 阶段 1：补全 admin.css

- [ ] 1.1 在 `assets/css/admin.css` 添加 `.admin-form-row`、`.admin-text-muted`、`.admin-table-responsive`、`.admin-btn-default` 定义
- [ ] 1.2 验证 `block/admin/blocks.php` 筛选栏和分页样式正常

## 阶段 2：迁移列表页（4 页）

- [ ] 2.1 `hufang/admin/users.php`：card→admin-card、table→admin-data-table、btn→admin-btn、badge→admin-badge、pagination→admin-pagination、alert→admin-alert，移除 container/breadcrumb
- [ ] 2.2 `hufang/admin/circles.php`：同上模式
- [ ] 2.3 `hufang/admin/visits.php`：同上模式
- [ ] 2.4 `hufang/admin/cities.php`：同上模式

## 阶段 3：迁移详情页（2 页）

- [ ] 3.1 `hufang/admin/user_detail.php`：card→admin-card、badge→admin-badge、btn→admin-btn
- [ ] 3.2 `hufang/admin/visit_detail.php`：同上模式

## 阶段 4：迁移表单页（4 页）

- [ ] 4.1 `hufang/admin/city_add.php`：form-control→admin-form-input、btn→admin-btn、card→admin-card
- [ ] 4.2 `hufang/admin/city_edit.php`：同上模式
- [ ] 4.3 `hufang/admin/user_edit.php`：同上模式
- [ ] 4.4 `hufang/admin/system_settings.php`：同上模式

## 阶段 5：验证与提交

- [ ] 5.1 检查所有 10 个页面无 PHP lint 错误
- [ ] 5.2 提交到 git 远程仓库
