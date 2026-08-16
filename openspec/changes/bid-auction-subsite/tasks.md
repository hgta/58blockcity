# 实施任务：58拍卖子站（bid.58.tl）

## Task 1：数据表 + 迁移
- [ ] 创建 `init/migrate-auction.sql`：`auctions` 表 + `auction_bids` 表（含索引）。
- [ ] 在 `init/db-init.sql` 追加同样的建表语句（保持全量初始化一致）。

## Task 2：拍卖业务类 `classes/Auction.php`
- [ ] 实现 `createAuction()`：归属校验 + 互斥校验 + 写入。
- [ ] 实现 `placeBid()`：登录/active/加价幅度/不能自拍校验，事务写入出价并更新当前价。
- [ ] 实现 `settleExpired()` + `settle()`：惰性结算，成交转移所有权（block/nft 分支），流拍置 ended。
- [ ] 实现查询方法：`getActiveAuctions` / `getAuctionById` / `getBids` / `getMyAuctions` / `getMyBids`。
- [ ] 实现 `isItemInActiveAuction()` 供挂牌互斥校验。

## Task 3：联动——互斥校验
- [ ] `classes/BlockListing.php::createListing()` 开头调用 `Auction::isItemInActiveAuction()`，有 active 拍卖则拒绝。
- [ ] `classes/NFT.php::listForSale()` 同样校验，有 active 拍卖则拒绝。

## Task 4：子站骨架 bid/
- [ ] 创建 `bid/` 目录 + `includes/auth.php`（代理）+ `includes/header.php`（logo_sub='拍卖'）+ `includes/footer.php`。
- [ ] 创建 `bid/.htaccess`（RewriteBase + 伪静态 + 404）。
- [ ] `bid/index.php`：拍卖中列表（区块 + NFT 混排，展示当前价/倒计时/货币），加载时触发 `settleExpired()`。

## Task 5：发布拍卖 + 详情出价
- [ ] `bid/create.php`：选择拍卖品类型（区块/NFT），列出当前用户拥有的区块/NFT，填起拍价/底价/加价/起止时间/货币/接受城市，提交调 `createAuction()`。
- [ ] `bid/view.php`：拍卖详情（当前价、倒计时、出价记录），出价表单调 `placeBid()`，加载时触发 `settleExpired()`。
- [ ] 前端：倒计时显示、出价后局部刷新出价列表。

## Task 6：我的拍卖
- [ ] `bid/my.php`：两个 tab——「我发布的」「我出价的」，调 `getMyAuctions` / `getMyBids`。

## Task 7：共享配置 + SEO
- [ ] `config/seo.php` 的 `subdomains` 增加 `bid`。
- [ ] `sitemap.php` 增加拍卖列表页 URL。
- [ ] `docs/nginx-rewrite.conf` 增加 `bid.58.tl` server 块。

## Task 8：联调与验证
- [ ] 发布一个区块拍卖 → 另一个账号出价 → 到期后验证自动成交、所有权转移。
- [ ] 发布 NFT 拍卖（验证用 `nft_city_user.id`）→ 出价 → 成交 → `nft_city_user` 归属更新。
- [ ] 验证互斥：物品已有拍卖时无法挂牌；已有挂牌时无法发布拍卖。
- [ ] 验证流拍场景（无出价 / 低于底价）。
- [ ] 验证不能自拍、未登录不能出价、加价幅度校验。
- [ ] 验证人民币 / 人气值两种货币 + 接受城市配置。
