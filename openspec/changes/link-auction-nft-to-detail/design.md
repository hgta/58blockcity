## Context

`bid.58.tl/view.php` 通过 `Auction::getAuctionById()` 获取拍卖详情。该方法会区分 `item_type` 查询不同数据源：
- NFT 类型：查询 `nft_city_user`，已返回 `ncu.id`、`ncu.nft_id`、`n.code`、`n.base_image` 等字段。
- 区块类型：查询 `blocks` 表，已返回 `id`、`city_name`、`zone`、`block_number`、`display_image` 等字段。

此前两类拍品都只用于本地展示，未提供外链入口。nft 子站详情页路由为 `https://nft.58.tl/nft/view.php?id={nft_id}`；block 子站详情页路由为 `https://block.58.tl/block/view.php?id={block_id}`。

## Goals / Non-Goals

**Goals:**
- 在 `bid/view.php` 的 NFT 头像与区块展示区域均增加可点击跳转入口。
- NFT 链接指向 `nft.58.tl/nft/view.php?id={nft_id}`，区块链接指向 `block.58.tl/block/view.php?id={block_id}`，均在新标签页打开。
- 区块无 `display_image` 时展示默认占位图。

**Non-Goals:**
- 不改造 nft 子站与 block 子站详情页本身。
- 不新增数据表或字段；复用 `Auction::getAuctionById()` 已查询的 `nft_id` / `blocks.id`。
- 不修改拍卖出价、结算等核心流程。

## Decisions

1. **拍卖详情页跳转入口放在预览图与标题上，并给出明确提示**
   - 预览图和标题是用户最自然的点击目标。
   - 为避免突兀，图片悬停时显示半透明遮罩提示「点击查看头像详情 / 区块详情」，并常驻右上角外部链接角标；标题右侧显示外部链接小图标。
   - 列表页不放置外部跳转：首页卡片任何位置点击都进入拍卖详情页，避免与列表预期行为冲突。

2. **新标签页打开（`target="_blank"`）**
   - 避免用户离开拍卖出价流程。

3. **复用 `Auction::getAuctionById()` 已返回的标识字段**
   - NFT 使用 `nft_id`（`nft_city_user.nft_id`）。
   - 区块使用 `block_id`（对应 `blocks.id`）。
   - 无需额外 SQL。

4. **默认图资源路径**
   - 项目内 `/assets/images/default.jpg` 为用户头像占位图，不适合区块。
   - 新增 `/assets/images/default-block.png` 作为区块默认占位图，1:1 尺寸，简洁的 3D 方块风格。

## Risks / Trade-offs

- [Risk] 标识字段缺失导致链接失效 → Mitigation: 仅在 `nft_id > 0` 或 `item_id > 0` 时渲染链接，否则回退为静态展示。
- [Risk] 默认图路径不存在 → Mitigation: 实现前检查文件是否存在，不存在则创建一张默认图。
- [Risk] 子站详情页 URL 后续变更 → Mitigation: 这两个 URL 模式在各自子站已广泛使用，变更概率低。
