## Why

`bid.58.tl` 当前不支持卖家为「拍品」写一段专属说明。`block` 类拍品只能展示「城市+区+编号」拼出的标题（连一句「全新未拆封 / 限量首版」都没法说），`nft` 拍品同此窘境。这让拍卖详情页缺一个对收藏者决策最重要的内容——**卖家本人的承诺与描述**——等同于把拍卖体验压回了"货架"。

更关键的是：商城商品未来也准备接入拍卖，没有描述能力，新商品拍卖一上来就是「无描述」裸卖，体验断崖。

## What Changes

- **新增列** `auctions.description MEDIUMTEXT NULL COMMENT '拍品描述（Markdown，卖家专写）'`。
- **新增能力** `auctions/seller-description`：卖家在发布 / 编辑拍卖时填写 Markdown 描述，详情页安全渲染；范围**对所有 `item_type` 通用**（block / nft / 后续 product 均可使用）。
- 创建表单新增 Markdown 编辑器（textarea + 语法提示 + 字数计数 + 5000 字上限）。
- 详情页新增「卖家描述」区块，服务端用 Parsedown safemode 解析后输出。
- 不接评论、不接 @、不做多人协作——单卖家一次发布。
- 不改落槌/成交/履约逻辑。

## Capabilities

### New Capabilities

- `auctions/seller-description`: 卖家为拍品撰写的 Markdown 描述，覆盖三种 item_type 的发布与编辑表单，详情页安全渲染。

### Modified Capabilities

- 无（`auctions` 表加列不破坏既有契约，`Auction::getAuctionById` 等方法签名兼容；新字段为可选）。

## Impact

- **数据库**：新增迁移 `init/migrate-auction-description.sql`，单列 `ADD COLUMN description MEDIUMTEXT NULL`，可重复执行。
- **后端类**：`classes/Auction.php` 的 `createAuction` / `updateAuction` 接收 `description` 字段，长度校验（≤ 5000 字符），不传入则保持 NULL。
- **渲染层**：新建 `classes/MarkdownSafe.php`（Parsedown safemode + scheme/url 白名单 + href/src 二次过滤），提供 `renderSafe($markdown): string`。`bid/includes/lot_helpers.php` 增加 `ac_render_seller_description($auction)` 辅助函数。
- **依赖**：新增 Parsedown（`composer require erusev/parsedown`，autoload 引入；也可 vendor/ 手动放置 Parsedown.php 单文件）。
- **前端页面**：`bid/create.php` 新增 Markdown 编辑器（textarea + 字数统计 + 工具栏按钮「粗体/链接/代码块」）；`bid/view.php` 详情页新增「卖家描述」折叠区块（无描述时不显示该区块）。
- **接口**：仅服务端渲染，无新接口；现有 `/api/lot.php` 不返回原始 markdown（防止前端被注）。
- **依赖/基建**：无新增构建工具、无新增常驻服务；仅引入 Parsedown 单文件库（约 50KB）。