## 1. 验证数据可用性

- [x] 1.1 确认 `Auction::getAuctionById()` 在 NFT 类型下已返回 `nft_id` 字段，且对应 `nft_city_user.nft_id`。
- [x] 1.2 确认 `Auction::getAuctionById()` 在区块类型下返回的 `id` 字段对应 `blocks.id`。
- [x] 1.3 子站详情页 URL 模式已确认：`https://nft.58.tl/nft/view.php?id={nft_id}` 与 `https://block.58.tl/block/view.php?id={block_id}`。

## 2. 准备默认区块图

- [x] 2.1 检查 `/assets/images/` 下是否已有合适的默认区块占位图。
- [x] 2.2 新增 `/assets/images/default-block.png`（1:1 简洁 3D 方块风格），代码中使用该路径。

## 3. 修改拍卖详情页

- [x] 3.1 在 `bid/view.php` 中找到 NFT 头像的预览图与标题渲染区域。
- [x] 3.2 当 `$auction['item_type'] === 'nft'` 且 `nft_id > 0` 时，将预览图与标题包裹在 `<a href="https://nft.58.tl/nft/view.php?id=<?= $auction['nft_id'] ?>" target="_blank">` 中。
- [x] 3.3 在 `bid/view.php` 中找到区块拍品的预览图与标题渲染区域。
- [x] 3.4 当 `$auction['item_type'] === 'block'` 且 `item_id > 0` 时，将预览图与标题包裹在 `<a href="https://block.58.tl/block/view.php?id=<?= $auction['item_id'] ?>" target="_blank">` 中。
- [x] 3.5 修改区块图片输出逻辑：当 `display_image` 为空时，显示默认占位图。

## 4. 可选：列表页同步增加入口

- [x] 4.1 在 `bid/index.php` 的 NFT 拍卖卡片中，为头像图增加 `nft.58.tl` 跳转入口（悬停显示「查看头像详情」，新标签页打开）。
- [x] 4.2 在 `bid/index.php` 的区块拍卖卡片中，为预览图增加 `block.58.tl` 跳转入口（悬停显示「查看区块详情」，新标签页打开）。
- [x] 4.3 确保列表页区块无图时也显示默认占位图。

## 5. 验证与部署

- [x] 5.1 代码已支持：NFT 头像详情页中点击头像/标题跳转 `https://nft.58.tl/nft/view.php?id={nft_id}`（新标签页）。待服务器 `git pull` 后线上验证。
- [x] 5.2 代码已支持：区块详情页中点击区块图/标题跳转 `https://block.58.tl/block/view.php?id={block_id}`（新标签页）。待服务器 `git pull` 后线上验证。
- [x] 5.3 代码已支持：区块无 `display_image` 时显示 `/assets/images/default-block.png`。待服务器 `git pull` 后线上验证。
- [x] 5.4 lint 检查通过（`bid/view.php`、`bid/index.php`、`classes/Auction.php`）。
- [x] 5.5 提交并推送代码（`4817469`）。
