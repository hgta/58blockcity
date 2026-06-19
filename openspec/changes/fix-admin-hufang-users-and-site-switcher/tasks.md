# 任务：修复 admin users.php 与子站切换器

## 任务 1：修复 `hufang/admin/users.php` PHP 标签闭合

- [ ] 1.1 删除第 45 行的 `?>`，将其移至 `require_once '../../shared/admin/admin-header.php';` 之后
- [ ] 1.2 修复第 203 行分页计数变量：`$totalCities` → `$totalUsers`
- [ ] 1.3 验证页面在浏览器中正常加载后台框架（无 PHP 代码泄漏）

## 任务 2：修复 `shared/admin/admin-menu-config.php` 子站切换器 URL

- [ ] 2.1 将 `$ADMIN_SITES` 中 6 个站点的 `url` 改为子域名绝对 URL：
  - main → `https://58.tl/admin/dashboard.php`
  - block → `https://block.58.tl/admin/dashboard.php`
  - bct → `https://bct.58.tl/admin/dashboard.php`
  - hufang → `https://v.58.tl/admin/dashboard.php`
  - mall → `https://mall.58.tl/admin/dashboard.php`
  - nft → `https://nft.58.tl/admin/dashboard.php`
- [ ] 2.2 保留 `name` 和 `icon` 字段不变
- [ ] 2.3 不修改 `$ADMIN_MENUS`（站内相对路径正确）

## 任务 3：验证

- [ ] 3.1 访问 `https://v.58.tl/admin/users.php` 确认后台框架正常、无代码泄漏
- [ ] 3.2 在 v.58.tl 后台右上角切换器点击"区块交易"，确认跳转到 `https://block.58.tl/admin/dashboard.php`
- [ ] 3.3 反向验证：从 block.58.tl 后台切回"互访圈"，确认跳转到 `https://v.58.tl/admin/dashboard.php`
- [ ] 3.4 提交到 git 远程仓库
