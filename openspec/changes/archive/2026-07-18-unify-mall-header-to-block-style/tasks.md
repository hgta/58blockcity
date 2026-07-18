# Mall 头部统一任务清单

## Task 1 — 修改 `shared/header.php` CSS

**文件**：`shared/header.php`

**操作**：
- header 背景改为 `#fff`，颜色改为 `#333`
- 移除 `padding: 15px 0`，移除 `box-shadow`
- 添加 `border-bottom: 1px solid #e8e8e8`
- `.header-container` 添加 `height: 56px`
- Logo 图标改为橙色渐变底+白字，缩小到 34px
- Logo 文字颜色适配白底
- 导航按钮 `.nav-button` 改为 6px 圆角、深灰色 `#666`
- 导航 hover 改为 `#fff9f0` 底 + `#ff6b00` 字
- `.header-actions` 添加 `gap: 4px`

**验收**：在 `mall.58.tl` 任意页面，头部与 `block.58.tl` 视觉一致

---

## Task 2 — 注册按钮改为实心 CTA

**文件**：`shared/header.php`

**操作**：
- 未登录分支的注册 `<a>` 添加内联样式 `background:#ff6b00; color:#fff; border-radius:6px`

**验收**：注册按钮为实心橙色，与其他导航按钮明显区分

---

## Task 3 — 验证

**操作**：
- 登录状态和未登录状态分别检查
- 移动端汉堡菜单展开后样式正常
- 城市定位条不受影响
- 通知下拉菜单正常

**验收**：所有功能正常，视觉与 Block 一致
