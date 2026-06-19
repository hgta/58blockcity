# hufang 前台用户页面样式恢复 + bug 修复 + 设计优化

## 问题

### 已紧急修复（本次 commit 前完成）
- `hufang/includes/header.php` 未加载 hufang 专用 CSS → 7 个前台用户页面样式缺失
- `hufang/user/circles.php` JS 引用已注释的 `#generatePoster` 元素

### 待修复的 bug（探索发现）

| 页面 | 严重度 | 问题 |
|------|--------|------|
| `user/profile.php` | 高 | 密码字段 name 不匹配：HTML 用 `password`/`password_confirm`，PHP 检查 `new_password`/`confirm_password` → 密码修改永远失效 |
| `user/profile.php` | 高 | session 消息在 `header.php` 之前输出 → HTML 结构错乱 |
| `user/visits.php` | 高 | 4 个 Tab 按钮无 JS 实现切换 → 点击无反应 |
| `user/visits.php` | 中 | `$visitsByStatus` 用 `visited` 键，Tab 用 `completed` → 可能未定义键 |

### 设计优化（CSS 恢复后）

hufang 专用 CSS 加载后，页面样式会恢复，但设计较老旧（2021 风格）。针对核心页面做布局美化：

1. **dashboard.php**：欢迎区改渐变 banner；统计卡片加 hover 动效；双栏卡片间距/圆角统一
2. **circles.php**：卡片网格 hover 提升阴影；区块数标签改为胶囊样式
3. **visits.php**：Tab 切换实现 + 列表卡片化

## 目标

1. 修复 profile.php 密码功能 + HTML 结构
2. 修复 visits.php Tab 切换功能
3. 优化 dashboard/circles/visits 视觉设计

## 范围

### In Scope
- `hufang/user/profile.php`：修复密码字段名、session 消息位置
- `hufang/user/visits.php`：实现 Tab 切换 JS、修复键名
- `hufang/user/dashboard.php`：布局优化（渐变 banner、卡片动效）
- `hufang/user/circles.php`：卡片视觉优化
- `hufang/assets/css/main.css`：追加优化样式

### Out of Scope
- PHP 业务逻辑变更（不改类方法、不改数据库查询）
- 后台 admin 页面（已单独迁移）
- 新增功能

## 成功标准

1. profile.php 密码修改功能正常
2. visits.php Tab 切换正常
3. dashboard/circles/visits 视觉焕然一新
4. 无 PHP 错误/JS 报错
