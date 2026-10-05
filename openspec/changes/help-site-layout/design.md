# Design: help-site-layout

## Context

盘点结论（`help/` 子站）：

```
_layout.php   ← 唯一布局文件：header(logo+搜索+nav) / .hc-container(1080px) / footer
                所有 CSS 内联在此，类名前缀 hc-；≤860px 隐藏 .hc-search 与 .hc-nav（nav 靠按钮展开）
pages/  home      hero(橙色大块) → 分类网格 → AI推广条 → 热门教程 → 最近更新(大卡片) → 大家都在问
        category  面包屑 → 两栏(侧栏分类 + 文章卡片 + 分页)
        article   面包屑 → 两栏(目录/相关 + 文章卡) → 继续阅读
        search    面包屑 → hero(内联改 padding) → 结果卡片 → FAQ → AI推广条
        faq       面包屑 → .hc-section-title(内联 margin-top:0) → 分类Tab → FAQ折叠 → AI推广条
        glossary  面包屑 → .hc-section-title(内联 margin-top:0) → 字母Tab → 术语分组 → AI推广条
        ask       面包屑 → 独立 <style>(ask-*) → 对话区(容器 780px)
```

主要问题：hero 过大且与头部搜索重复；**≤860px 除首页/搜索页外无搜索入口**（功能缺失）；标题区 3 种写法 + 内联覆盖；FAQ 折叠与 AI 推广条各重复 4 处。

## Goals / Non-Goals

**Goals**
- 首屏紧凑：标题条 ≤ ~90px（含热门词行）
- 移动端**每页**可搜索
- 页面骨架统一（标题区/面包屑/容器/组件），内联覆盖清零
- 首页桌面两栏、移动单列，纵向更短

**Non-Goals**
- 不改路由/URL/.htaccess；不动遗留静态 HTML（已 301）；不改 ask 的对话逻辑；不引入构建链

## Decisions

### D1：`.hc-hero` 就地改造为紧凑标题条（不新增类名）

保留类名、换掉尺寸与配色，好处是**首页与搜索页自动同步**，避免遗漏其中一处：

```css
.hc-hero { background:#fff; border:1px solid var(--line); border-left:4px solid var(--brand);
           border-radius:var(--radius); padding:14px 18px; text-align:left; color:var(--ink);
           margin-bottom:18px; }
.hc-hero h1 { font-size:19px; margin:0 0 2px; }
.hc-hero p  { font-size:13px; color:var(--muted); margin:0; }
.hc-hero-search { display:none; }              /* 桌面端不重复搜索（头部已有） */
.hc-hero-tags { margin-top:10px; font-size:13px; color:var(--muted); }
.hc-hero-tags a { color:var(--brand); margin:0 6px 0 0; border:none; }
@media (max-width:860px) { .hc-hero-search { display:flex; margin-top:10px; } }  /* 手机端唯一搜索入口 */
```

搜索页保留搜索表单（该页主操作），用修饰类 `.hc-hero.is-search` 覆盖为始终显示。

### D2：移动端把搜索放进头部第二行（解决"5 个页面无法搜索"）

```css
@media (max-width:860px) {
  .hc-header-inner { flex-wrap:wrap; height:auto; padding:8px 16px; row-gap:8px; }
  .hc-search { display:flex; order:3; flex:1 0 100%; max-width:none; }  /* 关键：不再隐藏 */
  .hc-menu-btn { order:2; }
  .hc-nav { top:auto; }   /* nav 展开区随两行头部下移 */
}
```
理由：比"每页各加一个搜索框"改动面小、且**天然覆盖全部页面**（含未来的新页面）。

### D3：统一页面标题区 `.hc-pagehead`

```css
.hc-pagehead { display:flex; align-items:center; gap:12px; margin:2px 0 18px; }
.hc-pagehead h1 { font-size:20px; margin:0; }
.hc-pagehead .sub { font-size:13px; color:var(--muted); }
.hc-pagehead .acts { margin-left:auto; font-size:13px; }
```
- 分类页/FAQ/术语表/搜索页/AI 问答页 改用 `.hc-pagehead`（替换 `.hc-section-title` + 内联 `margin-top:0` 的写法）
- 文章页保留卡内 `h1`（详情页常规），但其字号/间距与 `.hc-pagehead h1` 取同一组值，保证观感一致
- `.hc-pagehead + .hc-section-title { margin-top: 14px; }` 统一"标题区之后第一个小节标题"的距离

### D4：首页两栏用 grid-template-areas（保证移动端顺序符合预期）

```css
.hc-home { display:grid; grid-template-columns:minmax(0,1fr) 320px; gap:22px; align-items:start;
           grid-template-areas: "cats ai" "hot asks" "latest asks"; }
@media (max-width:860px) { .hc-home { grid-template-columns:1fr; grid-template-areas:none; } }
```
DOM 顺序 = `分类 → AI助手 → 热门教程 → 最近更新 → 大家都在问`（移动端单列顺序自然正确），桌面端由 areas 把 AI 卡与"大家都在问"排到右列。

- 「最近更新」从 `.hc-art-card` 大卡片改为 `.hc-list-item` 紧凑行（与热门教程区分：用图标而非序号）
- 侧列 AI 卡：品牌浅底卡 + 按钮，替代原来独占一行的推广条（首页不再重复渲染推广条）

### D4b：AI 问答页（ask）保留自身头部，只做结构对齐（例外说明）

`ask.php` 的 `.ask-head`（头像 + 标题 + 副标题 + 右侧操作）**在结构上与 `.hc-pagehead` 同构**，且其 780px 居中列宽是聊天页的刻意选择（1080px 宽度下气泡会长得难读）。因此：
- 不替换为 `.hc-pagehead`（替换会丢掉头像与操作区）
- 不做宽度统一（保持 780px）
- 仅确保标题字号/副标题颜色沿用同一组变量（已有）

这是本次唯一有意保留的差异，写在此处以免后续又被当作"不一致"反复讨论。

### D5：公共组件函数下沉到 `_layout.php`

```php
help_faq_item(string $q, string $a, string $moreUrl = ''): void   // FAQ 折叠项（替换 4 处重复）
help_ai_cta(string $text = ''): void                             // AI 助手推广条（替换 3~4 处）
```
`home/search/faq/article/glossary` 改为调用函数，样式与结构单点维护（spec: 公共组件单一来源）。

### D6：内联样式清理范围

清理与布局相关的内联覆盖：search 的 hero padding、faq/glossary/article 的 `margin-top:0`、nav 的 `.hc-ask-btn` 重复样式。
**保留**：语义色（如状态红/绿）、一次性宽度微调；不为了"消内联"而制造无用类。

## Risks / Trade-offs

- [布局改动面广，易出现单页错位] → 任务清单包含**逐页视觉回归**（7 个页面 × 桌面/375px 两档）
- [`.hc-hero` 同时被首页与搜索页使用] → D1 采用"改类名不换类名"策略，两页自动同步；搜索页另加修饰类控制搜索框显示
- [移动端头部变两行，sticky 高度变化影响 `.hc-side` 的 top 偏移] → 同步调整 `@media` 内 `.hc-side{position:static}`（已是 static，无需改）与 nav 展开定位 `top:auto`
- [两栏布局在小屏笔记本（861~1000px）显得拥挤] → 右列 320px，主列使用 `minmax(0,1fr)` 防溢出；必要时断点仍是 860px，不做中间态

## Migration Plan

纯展示层改动，无数据迁移。部署即生效；回滚 = `git revert`。上线后按任务清单逐页核对（桌面 + 375px）。
