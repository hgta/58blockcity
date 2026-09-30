## Purpose

让每个子域拥有真实可访问的站点代表图（og:image）、合法唯一的页面 head 结构、正确域名的图片 URL，并使确有内容图的页面在 sitemap 中携带图片信息，最终改善搜索结果缩略图与社交分享卡片的出图率。

## ADDED Requirements

### Requirement: 站点代表图真实可访问

每个对外子域的页面 MUST 声明 `og:image`，且其指向的图片 MUST 以 HTTP 200 返回栅格图片（JPEG/PNG），MUST NOT 指向 404 页面或 SVG 文件。

#### Scenario: 子域首页 og:image 可访问
- **WHEN** 请求任一子域首页并解析其 `og:image`
- **THEN** 该 URL 返回 200 且 `Content-Type` 为 `image/jpeg` 或 `image/png`

#### Scenario: 十个子域全覆盖
- **WHEN** 依次检查 www / mall / block / bct / nft / v / bid / club / model / task 十个子域首页
- **THEN** 每个首页都存在 `og:image` 声明且指向可访问的栅格图

#### Scenario: 帖子详情页 og:image 与正文图同域
- **WHEN** 一个含图帖子的详情页声明 `og:image`
- **THEN** 其域名与该帖子图片实际存储的子域一致，URL 可访问

### Requirement: 页面 head 结构唯一合法

每个对外页面 MUST 只输出一套 `<!DOCTYPE>` / `<head>` / `<title>`，所有 `og:*` meta 与 JSON-LD MUST 位于 `</head>` 之前，MUST NOT 出现嵌套的第二套文档结构。

#### Scenario: mall 首页单 head
- **WHEN** 请求 `https://mall.58.tl/` 并统计 `<title>` 与 `<!DOCTYPE` 出现次数
- **THEN** 二者各恰好出现一次，且 `og:*` 标签位于 `</head>` 之前

#### Scenario: 页面自设 og_image 不被覆盖
- **WHEN** 任一子站页面自行设置了 `$site_config['og_image']`
- **THEN** 该站 `includes/header.php` 的默认值 MUST NOT 覆盖页面设置

### Requirement: 内容列表页渲染内容图片

支持图片上传的内容列表页 SHALL 在列表行内渲染内容首图缩略图，使列表页具备可被图片索引的栅格内容图；无图内容 MUST 保持原有布局不塌陷。

#### Scenario: club 信息流有图帖子显示缩略图
- **WHEN** club 信息流中某帖子含图片
- **THEN** 该行渲染帖子首图的 `<img>`（栅格格式、根相对路径、懒加载）

#### Scenario: 无图帖子布局不变
- **WHEN** club 信息流中某帖子无图片
- **THEN** 该行渲染结果与改造前一致，无占位空位导致的布局异常

### Requirement: 首页具备可索引的代表图

每个对外子域的首页 SHALL 在文档中渲染至少一张栅格格式的 `<img>` 内容图（非二维码、非用户头像、非 SVG），MUST NOT 仅依赖 CSS 背景图或 emoji 作为首页视觉元素。

#### Scenario: 主站首页 hero 使用栅格图片
- **WHEN** 请求 `https://www.58.tl/` 并枚举 `<img>`
- **THEN** 除页脚二维码外，首屏 hero 区域存在至少一张 HTTP 200 的栅格图片，且带非空 `alt`

#### Scenario: 首页不只有二维码
- **WHEN** 统计任一子域首页的 `<img>` 及其用途
- **THEN** 至少一张图片的用途不属于二维码 / 用户头像 / SVG 图标

### Requirement: sitemap 携带图片信息

确有栅格内容图的详情页，在其所属子域 sitemap 中 SHALL 通过 `image` 扩展命名空间输出 `<image:loc>`；无栅格图的页面 MUST NOT 输出图片节点。

#### Scenario: mall 商品详情带图片节点
- **WHEN** 请求 `https://mall.58.tl/sitemap.xml`
- **THEN** 商品详情 `<url>` 节点包含指向该商品主图绝对 URL 的 `<image:loc>`

#### Scenario: 无图页面不带图片节点
- **WHEN** 检查任一子域 sitemap 中无栅格内容图的页面（如 nft 的 SVG 详情页）
- **THEN** 该 `<url>` 节点 MUST NOT 包含 `<image:image>`

#### Scenario: sitemap 保持合法 XML
- **WHEN** 请求任一增加了图片节点的子域 sitemap
- **THEN** 输出为可解析的合法 XML，`urlset` 声明 `xmlns:image` 命名空间

### Requirement: 站点 LOGO 资产齐备

每个对外子域 SHALL 备有 121×75 与 200×133 两种规格的 LOGO PNG，供百度搜索资源平台「站点属性-LOGO」提交使用。

#### Scenario: LOGO 文件规格正确
- **WHEN** 检查 `assets/images/logo/` 下任一子域的 LOGO 文件
- **THEN** 存在两种规格文件，实际尺寸分别为 121×75 与 200×133
