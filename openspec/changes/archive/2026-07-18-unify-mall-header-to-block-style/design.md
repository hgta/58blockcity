# Mall 头部统一设计

## 改动对照

### 一、CSS 改动（shared/header.php 内联样式）

| 选择器 | 属性 | 当前值 | 改为 |
|--------|------|--------|------|
| `header` | background | `#ff6b00` (动态 `$theme`) | `#fff` |
| `header` | color | `white` | `#333` |
| `header` | padding | `15px 0` | 删除（改由 inner 定高） |
| `header` | box-shadow | `0 2px 5px rgba(...)` | 删除 |
| `header` | border-bottom | 无 | `1px solid #e8e8e8` |
| `.header-container` | height | 无 | `56px` |
| `.logo-img` | background | `white` | `linear-gradient(135deg, #ff6b00, #e55a00)` |
| `.logo-img` | color | `#ff6b00` | `#fff` |
| `.logo-img` | width/height | `44px` | `34px` |
| `.logo-img` | font-size | `20px` | `15px` |
| `.logo-img` | border-radius | `8px` | `6px` |
| `.logo-text strong` | color | 继承 white | `#333` |
| `.logo-text span` | opacity | `0.8` | 删除，改为 `color: #999` |
| `.nav-button` | border-radius | `20px` | `6px` |
| `.nav-button` | color | `white` | `#666` |
| `.nav-button` | margin-left | `6px` | 删除（改用父级 gap） |
| `.nav-button:hover` | background | `rgba(255,255,255,0.2)` | `#fff9f0` |
| `.nav-button:hover` | color | `white` | `#ff6b00` |
| `.header-actions` | 新增 | 无 | `display:flex; align-items:center; gap:4px` |

### 二、HTML 结构改动

注册按钮改为实心橙色 CTA（与 Block 一致）：

```html
<!-- 当前：透明胶囊 -->
<a href="../auth/register.php" class="nav-button">注册</a>

<!-- 改为：实心橙色 CTA -->
<a href="../auth/register.php" style="background:#ff6b00;color:#fff;border-radius:6px;">注册</a>
```

### 三、保留不动的部分

- 城市定位条 `.city-location-bar`（Mall 站特有功能）
- 通知下拉菜单 `.notification-dropdown`
- 站内信未读数徽章
- 移动端遮罩层 `.menu-overlay`
- 移动端响应式布局逻辑
