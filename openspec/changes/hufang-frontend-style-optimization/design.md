# 设计：hufang 前台用户页面优化

## bug 修复

### profile.php 密码字段不匹配

```php
// HTML (第232/240行)
<input name="password">
<input name="password_confirm">

// PHP (第112-113行) — 检查的是不同名字
$_POST['new_password']
$_POST['confirm_password']
```

修复：统一为 `new_password` / `confirm_password`（改 HTML，不动 PHP 逻辑）。

### profile.php session 消息位置

消息输出块（第139-147行）在 `require_once header.php` 之前。修复：移到 header 之后。

### visits.php Tab 切换

现状：4 个 `.tab-item` 按钮无 JS。修复：添加简单 JS 切换 `.active` 类和显示/隐藏 `.tab-pane`。

### visits.php 键名

`$visitsByStatus` 定义了 `visited` 键但 Tab 用 `completed`。修复：统一用 `completed`。

## 设计优化

### dashboard.php 渐变 banner

```
┌─────────────────────────────────────────────────┐
│  ╔═══════════════════════════════════════════╗  │
│  ║  渐变橙色 banner                            ║  │
│  ║  欢迎回来，用户名！                          ║  │
│  ║  管理互访圈和访问记录的中心      [创建] [设置] ║  │
│  ╚═══════════════════════════════════════════╝  │
│                                                  │
│  ┌────────┐ ┌────────┐ ┌────────┐ ┌────────┐   │
│  │统计卡 1│ │统计卡 2│ │统计卡 3│ │统计卡 4│   │
│  └────────┘ └────────┘ └────────┘ └────────┘   │
│                                                  │
│  ┌──────────────┐  ┌──────────────┐            │
│  │ 我的互访圈    │  │ 最近访问记录  │            │
│  └──────────────┘  └──────────────┘            │
│  ┌─────────────────────────────────┐            │
│  │ 待处理访问请求表格               │            │
│  └─────────────────────────────────┘            │
└─────────────────────────────────────────────────┘
```

优化点：
- 欢迎区用 `linear-gradient(135deg, #ff6b00, #ff9500)` 背景 + 白字
- 统计卡片 hover 上浮 `translateY(-4px)` + 阴影增强
- 卡片圆角统一 12px，间距 20px

### circles.php 卡片优化

- `.circle-card` hover 阴影增强 + 轻微上浮
- `.block-count-badge` 改为胶囊形（`border-radius: 20px`）
- 描述区截断显示（`-webkit-line-clamp: 2`）

### visits.php Tab + 列表卡片化

- Tab 按钮选中态加底部边框高亮
- 访问记录项改为卡片式（白底 + 圆角 + 间距）

## CSS 追加位置

在 `hufang/assets/css/main.css` 末尾追加优化样式，用 `.uf-opt-` 前缀避免冲突：
```css
/* === 设计优化 (2024) === */
.uf-opt-banner { ... }
.uf-opt-stat-card:hover { ... }
```
或者直接增强已有的 `.dashboard-header`、`.stat-card` 等类（hufang CSS 后加载，优先级已足够）。

倾向直接增强已有类——hufang CSS 现在是最后加载的，覆盖根 main.css 没问题。
