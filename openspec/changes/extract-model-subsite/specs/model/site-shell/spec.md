## Purpose

定义 `model.58.tl` 作为独立子站的外壳：独立导航、亮调主题 token 与作用域纪律、共享 header/footer 的复用方式、响应式规则。本能力保证子站视觉与商城解耦，同时不破坏全站导航体系。

## ADDED Requirements

### Requirement: 独立导航

子站 SHALL 拥有独立于商城的导航结构，至少包含：首页、模特库、短剧、排行榜、申请加入。

#### Scenario: 导航项可达

- **WHEN** 用户在任意子站页面查看导航
- **THEN** 导航包含首页、模特库、短剧、排行榜、申请加入五个入口
- **AND** 每个入口可正确跳转到对应页面

#### Scenario: 当前页高亮

- **WHEN** 用户处于导航中的某一个页面
- **THEN** 对应当前页面的导航项呈现激活态

### Requirement: 亮调主题与作用域隔离

子站 SHALL 采用亮调视觉（浅底 + 高级灰 + 单一高饱和强调色 `--brand`），主题样式 SHALL 仅作用于内容区容器，SHALL NOT 污染全站共享 header/footer。

#### Scenario: 主题不泄漏

- **WHEN** 任意子站页面渲染
- **THEN** 共享 header 的样式与电商站保持一致的观感
- **AND** 内容区呈现亮调主题

#### Scenario: 色彩语义克制

- **WHEN** 页面使用强调色
- **THEN** 强调色用于主要行动与关键信息，装饰性元素不使用强调色
- **AND** `--gold` 仅用于荣誉/排行榜语义

### Requirement: 对比度与无障碍基线

子站文本与背景的对比度 SHALL 达到 WCAG AA；颜色 SHALL NOT 作为唯一的信息载体。

#### Scenario: 状态同时有文案

- **WHEN** 展示关注、主演、禁用等状态
- **THEN** 状态同时通过文案或图标表达，而不仅依赖颜色差异

### Requirement: 响应式布局

子站 SHALL 在桌面、平板、手机三档视口下可用，SHALL NOT 出现横向滚动或文字溢出。

#### Scenario: 手机端完整可用

- **WHEN** 用户在手机宽度下访问子站
- **THEN** 页面无横向滚动
- **AND** 用户可以完成浏览、关注、申请操作
