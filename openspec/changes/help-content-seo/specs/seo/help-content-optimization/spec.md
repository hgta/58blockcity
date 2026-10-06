## Purpose

让 help 子站的内容具备可控的元信息、可被直接引用的直答结构、干净的页面集合（不含无限参数变体），以及与业务子站之间的双向内链，从而提升内容在搜索与生成式引擎中的可引用性。

## ADDED Requirements

### Requirement: 文章支持 SEO 专属字段

已发布文章页 MUST 优先使用文章自身的 `seo_title` / `meta_description`（若存在且非空）作为 `<title>` 与 `<meta name="description">`；字段缺失或为空时 MUST 回退到 `title` / `summary`。SEO 字段缺失（如迁移未执行）MUST NOT 导致页面报错。

#### Scenario: 使用 SEO 字段
- **WHEN** 某文章设置了 `seo_title` 与 `meta_description`
- **THEN** 页面 `<title>` 与 description 使用这两个字段的值

#### Scenario: 回退到通用字段
- **WHEN** 某文章的 `seo_title` 与 `meta_description` 均为空
- **THEN** 页面 `<title>` 使用文章 `title`，description 使用 `summary`

#### Scenario: 字段不存在
- **WHEN** 数据库尚未执行新增列的迁移
- **THEN** 页面仍正常渲染，不产生错误或警告

### Requirement: 摘要回退在句末收尾

由正文生成摘要时，MUST 在字数上限附近优先于句末标点处收尾；无法找到句末标点时才 MAY 硬截断。

#### Scenario: 句末收尾
- **WHEN** 文章无 `summary` 且正文首句长度小于上限
- **THEN** 生成的 description 在句末标点处结束，不出现半句

#### Scenario: 超长正文
- **WHEN** 正文首段远超上限且上限附近无句末标点
- **THEN** 生成的 description 不超过上限并补省略号

### Requirement: 文章提供直答段落

已发布文章页 MUST 在标题之后、正文之前输出一段直答内容（取自 `summary`），该内容 MUST 属于正文结构的一部分，而非独立的提示框。

#### Scenario: 直答段落位置
- **WHEN** 请求一篇有 `summary` 的文章
- **THEN** 在 `<h1>` 之后、正文区块之前存在包含该 `summary` 文本的段落

#### Scenario: 无摘要文章
- **WHEN** 请求一篇 `summary` 为空的文章
- **THEN** 不输出空的直答段落，页面结构正常

### Requirement: 搜索结果页不被索引

搜索结果页 MUST 输出 `noindex,follow` 的 robots 指令，且 MUST 仍可被爬虫抓取。

#### Scenario: robots 指令
- **WHEN** 请求 `https://help.58.tl/search?q={任意词}`
- **THEN** 页面含 `<meta name="robots" content="noindex,follow">`

#### Scenario: 可被抓取
- **WHEN** 爬虫请求搜索页
- **THEN** 不被 `robots.txt` 的 `Disallow` 屏蔽（否则 noindex 无法生效）

### Requirement: FAQ 分类变体收口

FAQ 页带 `?cat=` 参数的变体 MUST 输出指向 `https://help.58.tl/faq` 的 canonical。

#### Scenario: 分类变体
- **WHEN** 请求 `https://help.58.tl/faq?cat={某分类}`
- **THEN** 页面 canonical 为 `https://help.58.tl/faq`

### Requirement: 分类分页自引用

分类页带 `?page=` 参数时 MUST 输出指向自身（含该参数）的 canonical，MUST NOT 收口到第一页。

#### Scenario: 第二页
- **WHEN** 请求 `https://help.58.tl/category/{slug}?page=2`
- **THEN** 页面 canonical 为 `https://help.58.tl/category/{slug}?page=2`

### Requirement: 文章页提供跨子站功能入口

文章页 SHOULD 按文章所属分类输出指向对应业务子站的入口链接；分类无对应映射时 MUST 不输出该区块且 MUST NOT 报错。

#### Scenario: 有映射的分类
- **WHEN** 请求一篇属于已配置映射分类的文章
- **THEN** 页面出现指向对应业务子站的链接

#### Scenario: 无映射的分类
- **WHEN** 请求一篇属于未配置映射分类的文章
- **THEN** 页面不出现跨站入口区块，且无错误
