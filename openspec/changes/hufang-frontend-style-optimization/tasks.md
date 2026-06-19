# 任务：hufang 前台用户页面优化

## 阶段 1：bug 修复

- [ ] 1.1 `hufang/user/profile.php`：密码字段 name `password`→`new_password`、`password_confirm`→`confirm_password`
- [ ] 1.2 `hufang/user/profile.php`：session 消息输出块移到 `require_once header.php` 之后
- [ ] 1.3 `hufang/user/visits.php`：添加 Tab 切换 JS（4 个 tab-item + tab-pane）
- [ ] 1.4 `hufang/user/visits.php`：修复 `$visitsByStatus` 键名 `visited`→`completed`

## 阶段 2：设计优化 CSS

- [ ] 2.1 在 `hufang/assets/css/main.css` 末尾追加优化样式：
  - dashboard-header 渐变 banner
  - stat-card hover 上浮动效
  - dashboard-card / circle-card 圆角/阴影统一
  - block-count-badge 胶囊样式
  - visit-tabs 选中态高亮

## 阶段 3：页面布局优化

- [ ] 3.1 `hufang/user/dashboard.php`：欢迎区改渐变 banner 结构
- [ ] 3.2 `hufang/user/circles.php`：描述区截断、卡片间距
- [ ] 3.3 `hufang/user/visits.php`：访问记录项卡片化

## 阶段 4：验证与提交

- [ ] 4.1 检查所有页面无 PHP lint 错误
- [ ] 4.2 提交到 git 远程仓库
