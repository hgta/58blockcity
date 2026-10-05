# Proposal: help-site-layout（帮助中心布局优化）

## Why

帮助中心（`help.58.tl`）的布局存在两个层面的问题：

1. **首页 hero 块占位过大且信息价值低**（用户反馈）：`home.php:43-55` 的橙色大块 padding 40px、含标题+泛化副标题+搜索框+热门词，实测约占首屏 1/4（移动端更多）；同时它与头部搜索（`_layout.php:182`）**功能重复**——桌面端一屏内出现两个搜索框。
2. **移动端存在功能缺失**（盘点发现，比美观问题更严重）：`@media(max-width:860px)` 把头部 `.hc-search` 设为 `display:none`（`_layout.php:172`），且导航里没有替代搜索；结果只有首页与搜索页（自带 hero 搜索）能搜，**分类页、文章页、FAQ、术语表、AI 问答页在手机上完全没有搜索入口**。
3. **页面骨架不统一、可维护性差**：标题区存在 3 种写法（`.hc-section-title` + 内联 `margin-top:0`、`.hc-layout` 内标题、`.hc-article h1`）；面包屑仅首页缺失；FAQ 折叠 markup 重复 4 处；AI 助手推广条重复 4 处；hero 被搜索页复用却用内联 padding 覆盖尺寸（`search.php:71`）；散落大量内联 style 覆盖 CSS。

## What Changes

- **hero 压缩为紧凑标题条（`hc-hero` 重做）**：高度从约 250px 降至约 90px；桌面端不再重复搜索（搜索以头部为准），移动端保留搜索（作为手机端唯一搜索入口）；热门词降为一行小字灰底，去掉泛化副标题。
- **移动端搜索入口补齐**：≤860px 时头部改为两行（第一行 logo + 菜单，第二行搜索框），**全站每页都可搜索**。
- **统一页面骨架组件**：新增 `.hc-page-head`（标题 + 可选副标题 + 可选右侧操作）与 `.hc-breadcrumb` 统一惯例，替换现有 3 种标题写法与内联覆盖；首页补面包屑位（首页可省略）。
- **首页信息架构调整**：桌面两栏（主列：浏览分类 + 热门教程 + 最近更新；侧列：AI 助手卡 + 大家都在问 + 术语表入口），降低纵向长度；「最近更新」由大卡片改为紧凑列表行。
- **抽公共组件消除重复**：`help_faq_item()`（FAQ 折叠项，替换 4 处重复 markup）、`help_ai_cta()`（AI 助手推广条，替换 4 处）。
- **清理内联样式**：把散落的内联 `style` 归入 `_layout.php` 的类规则（hero padding 覆盖、section-title margin 覆盖等）。

**明确不做**：不改路由与 URL 形态（`index.php` + `.htaccess` 保持不变）；不动遗留静态 HTML（已被 301 重定向，物理文件保留）；不改 `ask.php` 的对话交互逻辑；不引入前端框架或构建链（保持内联 CSS/JS 风格）。

## Capabilities

### New Capabilities

- `help-site-layout`: 帮助中心子站的展示布局能力——统一的页面骨架（标题条/面包屑/容器/组件）、响应式规则（移动端搜索可用性）、首页信息架构与公共组件的复用约定。

### Modified Capabilities

（无——`help-center` 尚未归档进 `openspec/specs/`，本次以新能力形式沉淀布局约定）

## Impact

- **修改**：`help/_layout.php`（CSS + header/footer + 新增组件函数）、`help/pages/home.php`（hero 与信息架构重排）、`help/pages/search.php`（复用新标题条，去掉内联 padding）、`help/pages/category.php`、`help/pages/article.php`、`help/pages/faq.php`、`help/pages/glossary.php`、`help/pages/ask.php`（统一页面标题区与容器宽度）。
- **前台可见变化**：首屏更紧凑；移动端每页可搜索；页面之间标题风格一致。
- **风险**：布局改动面广（7 个页面文件 + 布局文件），需逐页视觉回归；`.hc-hero` 类在首页与搜索页同时被替换，二者需一并改以免样式失配。
