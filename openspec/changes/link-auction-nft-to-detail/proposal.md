## Why

在 `bid.58.tl` 拍卖详情页中，无论是 NFT 头像还是区块拍品，用户都只能看到静态展示，无法直接跳转到对应子站查看完整详情。NFT 头像需要进入 `nft.58.tl` 查看属性/编号/历史，区块需要进入 `block.58.tl` 查看位置/等级/加成等信息。增加跳转入口可让买家出价前充分了解拍品，提升转化率与用户体验。同时，区块拍品若未设置显示图，当前会显示破损图，需要回退到默认图。

## What Changes

- 在 `bid/view.php` 的 NFT 头像拍品区域，为头像预览图/标题增加可点击链接，跳转至 `nft.58.tl/nft/view.php?id={nft_id}`。
- 在 `bid/view.php` 的区块拍品区域，为预览图/标题增加可点击链接，跳转至 `block.58.tl/block/view.php?id={block_id}`。
- 当区块拍品没有设置显示图片和文字时，展示一张默认占位图，替代当前的破损图片。
- 所有跳转均在新标签页打开，避免中断拍卖浏览流程。

## Capabilities

### New Capabilities
- `bid/auction-item-detail-link`: 拍卖详情页中的 NFT 头像与区块拍品均支持点击跳转至对应子站详情页，且区块无图时显示默认图。

### Modified Capabilities
- 无

## Impact

- `bid/view.php`：拍品展示区域 HTML、链接逻辑与默认图回退。
- `classes/Auction.php::getAuctionById()`：确认已返回 `nft_id`（`nft_city_user.nft_id`）与区块 `id`（`blocks.id`）。
- `block` 子站详情页路由：`https://block.58.tl/block/view.php?id={block_id}`。
- `nft` 子站详情页路由：`https://nft.58.tl/nft/view.php?id={nft_id}`。
- 默认区块图资源：需确认或新增一张默认区块占位图（如 `/assets/images/default-block.png`）。
