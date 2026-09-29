# 58blockcity
58区块同城1.0

## 拍品描述（Markdown）撰写指南

`bid.58.tl` 的拍品（区块、NFT 头像、商城商品）支持卖家在发布/编辑拍卖时撰写 Markdown 描述。该描述将在拍品详情页的「卖家描述」一栏中安全渲染。

### 语法速查

````markdown
# 一级标题
## 二级标题

**粗体** *斜体*

- 无序列表
- 项目二

1. 有序列表
2. 第二项

> 引用块：用于重要说明或免责条款

`行内代码` 用于短词；适合拍品编号、材质名：

```code
多行代码块
适合展示规格表
```

[链接文字](https://example.com)

![图片描述](https://example.com/image.jpg)

| 表头 A | 表头 B |
|-------|-------|
| 内容 1 | 内容 2 |

---
````

### 安全约束（平台自动强制）

平台对所有描述渲染前会自动过滤 XSS 与恶意链接。你**不需要**理解底层细节，但请遵守：

- **不要在描述中写可执行脚本——会被自动转义**，达不到预期效果
- 外链图片 URL 仅允许 `http` / `https` 协议；host 必须在 `config/markdown_hosts.php` 白名单中
- 外链 host 不在白名单时，链接或图片会被自动移除
- 描述长度上限 **5000 字符**

### 默认允许的 host

- 平台子域（`bid.58.tl` / `mall.58.tl` / `block.58.tl` / `nft.58.tl` / `bct.58.tl` / `hufang.58.tl` 等 58.tl 全域）
- `github.com` / `githubusercontent.com`
- `wikipedia.org` / `wikimedia.org`

需要新增白名单域时，编辑 `config/markdown_hosts.php`。

### 编辑窗口

仅「未开始」（pending 状态）的拍卖可编辑描述。拍卖进行中（active）后，描述只读。

### 渲染路径

1. 卖家在 `bid/create.php` 填写 Markdown 源文
2. 提交 → `Auction::createAuction()` 写入数据库
4. 买家访问 `bid/view.php` → `MarkdownSafe::render()` 服务端渲染 → 详情页「卖家描述」栏目输出
5. 渲染层做三层过滤：Parsedown safemode + scheme 白名单 + host 白名单 + on\* 事件属性兜底

### 测试

```bash
php tests/MarkdownSafeTest.php
```

所有用例应通过（17/17）。如添加新的白名单或新场景，请同步在测试用例里加一笔。