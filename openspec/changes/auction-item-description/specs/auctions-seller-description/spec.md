## ADDED Requirements

### Requirement: 卖家可为拍品撰写 Markdown 描述

系统 SHALL 允许已登录卖家在发布或编辑拍卖时为拍品填写 Markdown 描述，范围对所有 `item_type`（block / nft / product）通用；描述 SHALL 存储于 `auctions.description`（MEDIUMTEXT NULL）字段。

系统 SHALL 在创建与编辑表单中提供 Markdown 编辑器（textarea + 字数统计 + 5000 字符上限 + 粗体/斜体/链接/代码块/列表工具栏），并 SHALL NOT 引入前端 marked.js 或 DOMPurify。

#### Scenario: 卖家发布拍品时填写描述

- **WHEN** 卖家在 `bid/create.php` 表单的描述 textarea 中输入 `# 全新未拆封\n限量首发` 并提交
- **THEN** 该描述被持久化到 `auctions.description`，且长度校验通过

#### Scenario: 描述对三种 item_type 通用

- **WHEN** 卖家分别为 block / nft / product 三种 item_type 发布拍卖并在描述字段填写内容
- **THEN** 三条拍卖记录均成功创建，`auctions.description` 字段均被写入对应内容

#### Scenario: 无描述时字段为 NULL

- **WHEN** 卖家发布拍品时不填写 description 字段
- **THEN** `auctions.description` 为 NULL，前端详情页不展示「卖家描述」区块

### Requirement: 描述长度上限 5000 字符

系统 SHALL 校验卖家填写的描述长度不超过 5000 字符（按 `mb_strlen` 计）；超过上限 SHALL 拒绝提交并返回错误信息。

#### Scenario: 5000 字符以内接受

- **WHEN** 卖家提交一份恰好 5000 字符的描述
- **THEN** 系统接受并成功落库

#### Scenario: 超过 5000 字符拒绝

- **WHEN** 卖家提交一份 5001 字符的描述
- **THEN** 系统拒绝并返回错误，前端展示长度越限提示

### Requirement: 描述渲染防 XSS

系统 SHALL 在服务端用 Parsedown safemode 解析 Markdown，并通过二次过滤保证输出 HTML 不含 XSS 向量。具体 SHALL 满足：

- `<a href>` 仅允许 `http` / `https` / `mailto` scheme；`javascript:` / `data:` / `vbscript:` 等 scheme MUST 被过滤为纯文本或去除
- `<img src>` 仅允许 `http` / `https` scheme，且 host 必须在白名单（默认含 `58.tl` 全域）
- 所有 `on*=` 事件属性 MUST 被去除
- `<a target="_blank">` SHALL 自动追加 `rel="noopener noreferrer"`

#### Scenario: script 标签被去除

- **WHEN** 卖家描述包含 `<script>alert(1)</script>`
- **THEN** 详情页渲染结果中不存在 `<script>` 元素

#### Scenario: javascript 协议被过滤

- **WHEN** 卖家描述包含 `[点我](javascript:alert(1))`
- **THEN** 详情页渲染结果中该 `<a>` 不含 `javascript:` href

#### Scenario: 非白名单 host 的图片被去除

- **WHEN** 卖家描述包含 `<img src="https://evil.com/x.png" alt="x">`
- **THEN** 详情页渲染结果中该 `<img>` 被去除或退化为 alt 文本

#### Scenario: 合法链接正常工作

- **WHEN** 卖家描述包含 `[GitHub](https://github.com)`
- **THEN** 详情页渲染结果中包含一个指向 `https://github.com` 的 `<a>` 元素

#### Scenario: target_blank 自动追加 noopener

- **WHEN** 卖家描述包含 `<a href="https://github.com" target="_blank">`
- **THEN** 详情页渲染结果中该 `<a>` 同时具备 `rel="noopener noreferrer"`

### Requirement: 描述仅 pending 状态可编辑

系统 SHALL 允许卖家在拍卖状态为 `pending`（未开始）时修改 description；状态变为 `active`（进行中）后，编辑表单 SHALL NOT 接受 description 的修改（沿用 `Auction::updateAuction()` 既有规则）。

#### Scenario: pending 状态可编辑描述

- **WHEN** 卖家对一笔 pending 状态的拍卖点击「编辑」并修改描述
- **THEN** 修改被持久化，拍卖保持 pending 状态

#### Scenario: active 状态不可编辑描述

- **WHEN** 卖家尝试对一笔 active 状态的拍卖编辑描述（通过编辑表单或直接 POST）
- **THEN** 系统拒绝并返回「仅未开始的拍卖可编辑」

### Requirement: 详情页安全渲染卖家描述

系统 SHALL 在 `bid/view.php` 详情页输出 description 字段的服务端渲染结果；空描述 SHALL NOT 渲染「卖家描述」区块。渲染 SHALL 使用既有样式 `.ac-seller-desc` 包裹，且 SHALL 与既有 `.ac-section` 视觉一致。

#### Scenario: 有描述时展示渲染结果

- **WHEN** 详情页 `$a['description']` 非空
- **THEN** 详情页在「拍品信息」下方展示「卖家描述」区块，内含 Parsedown 渲染后的 HTML

#### Scenario: 无描述时不展示该区块

- **WHEN** 详情页 `$a['description']` 为 NULL 或空串
- **THEN** 详情页不展示「卖家描述」区块（DOM 中不存在该元素）