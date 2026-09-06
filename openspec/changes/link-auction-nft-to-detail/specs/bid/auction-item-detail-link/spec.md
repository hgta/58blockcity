## Purpose

让 `bid.58.tl` 拍卖详情页中的拍品（NFT 头像与区块）能够直接点击跳转至对应子站详情页：NFT 头像跳转 `nft.58.tl`，区块跳转 `block.58.tl`；同时区块未设置展示图时显示默认占位图，避免破损图片影响体验。

## ADDED Requirements

### Requirement: NFT 头像拍品支持跳转 nft 子站详情页
拍卖详情页中，当拍品类型为 `nft` 时，系统 SHALL 提供一个可点击入口，跳转至 `nft.58.tl` 子站中该头像对应 NFT 的详情页。

#### Scenario: 用户点击 NFT 头像进入详情页
- **WHEN** 用户访问拍卖详情页且拍品类型为 NFT 头像
- **THEN** 页面展示可点击的头像预览图或标题链接
- **AND** 点击后在新标签页打开 `https://nft.58.tl/nft/view.php?id={nft_id}`

### Requirement: 区块拍品支持跳转 block 子站详情页
拍卖详情页中，当拍品类型为 `block` 时，系统 SHALL 提供一个可点击入口，跳转至 `block.58.tl` 子站中该区块的详情页。

#### Scenario: 用户点击区块进入详情页
- **WHEN** 用户访问拍卖详情页且拍品类型为区块
- **THEN** 页面展示可点击的区块预览图或标题链接
- **AND** 点击后在新标签页打开 `https://block.58.tl/block/view.php?id={block_id}`

### Requirement: 区块无图时显示默认占位图
当区块拍品未设置显示图片时，系统 SHALL 展示一张默认区块占位图，替代原有破损图片显示。

#### Scenario: 区块拍品缺少展示图
- **WHEN** 拍卖详情页加载一个区块拍品
- **AND** 该区块没有设置 `display_image`
- **THEN** 系统显示默认占位图
- **AND** 该占位图仍可点击跳转至 block 子站详情页

### Requirement: 使用子站可识别的标识
跳转链接所使用的 `id` 参数 SHALL 为子站详情页所需主键：NFT 使用 `nft_avatars.id`（即 `nft_city_user.nft_id`），区块使用 `blocks.id`（即 auctions.item_id）。

#### Scenario: NFT 头像跳转参数正确
- **WHEN** 系统生成 NFT 头像详情页跳转链接
- **THEN** 链接中的 `id` 等于该头像在 `nft_city_user.nft_id` 字段的值
- **AND** 打开链接后能正确展示对应的 NFT 详情

#### Scenario: 区块跳转参数正确
- **WHEN** 系统生成区块详情页跳转链接
- **THEN** 链接中的 `id` 等于 `auctions.item_id`
- **AND** 打开链接后能正确展示对应的区块详情
