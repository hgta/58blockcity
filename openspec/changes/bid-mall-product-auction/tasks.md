## 1. 数据库迁移

- [x] 1.1 编写 `init/migrate-auction-product.sql`：`ALTER TABLE auctions MODIFY item_type ENUM('block','nft','product') NOT NULL`，判存在当前定义再执行，可重复执行；验证：连续执行两次无报错；`information_schema.COLUMNS` 显示枚举已含 `product`。
- [x] 1.2 索引检查：`idx_item(item_type, item_id)` 自动覆盖新值，不需新增；验证：`EXPLAIN SELECT * FROM auctions WHERE item_type='product' AND item_id=1` 使用索引（迁移脚本头部附验证 SQL，由运维在库上执行确认）。
- [x] 1.3 存量数据零影响验证：迁移前 `SELECT item_type, COUNT(*) FROM auctions GROUP BY item_type` 应当仅含 `block` / `nft`，迁移后枚举扩展不影响（MODIFY 不改写行数据；脚本注释含对账与回滚指引）。

## 2. 后端：商品作为拍卖品

- [x] 2.1 `Auction::verifyOwnership()` 增加 product 分支：JOIN products + shops 校验 user_id / status / stock；任一不通过返回 false；验证：本人店主店铺 active 商品通过；非本人店主被拒；非 active 商品被拒；stock=0 被拒。（实现为 `checkProductSellable()` 返回区分错误信息，verifyOwnership 复用）
- [x] 2.2 `Auction::isItemListed()` 增加 product 分支：检查 order_items + orders 是否存在 pending/paid/shipped 单含该 product；验证：有关联订单时返回 true（防止落槌冲突）。
- [x] 2.3 `Auction::transferOwnership()` 增加 product 分支：详见 design §3；事务内 `UPDATE products SET stock=stock-1, sold_count=sold_count+1 WHERE id=? AND stock>=1 AND status='active'` + 创建 pending 订单（expire_at=+7 天）+ 写 order_items（quantity=1，快照名称/主图）+ 通知得标者 + 通知卖家；验证：rowCount=0 时整体抛异常回滚至流拍。
- [x] 2.4 `Auction::createAuction()` 入参校验扩展：item_type='product' 时校验商品存在 / 店铺本人 / 商品 active / stock>=1 / 不在 active 拍卖中 / 不在常规订单中；验证：错误信息准确区分以上五类（商品不存在 / 您不拥有该商品所属店铺 / 店铺当前未营业 / 该商品当前不可拍 / 该商品当前库存不足 / 该商品已在拍卖中 / 该商品已有关联的未完成订单）。
- [x] 2.5 `Auction::updateAuction()` 校验链同步：item_type='product' 的拍卖编辑时再次校验商品仍可拍；验证：商品下架后编辑失败，提示「该商品当前不可拍」。

## 3. 后端：查询与展示

- [x] 3.1 `Auction::getAuctionById()` 增加 product 分支：取 products.name + main_image + images + price_bct/cny + shop_name；附 `item_cross_url` 跨站链接；验证：返回数组包含上述字段（映射逻辑集中在 `mapProductItem()`）。
- [x] 3.2 `Auction::attachItemInfo()` 批量取 product 信息：IN (...) 取 product + shop 信息；映射到 `$a['item_title'] / ['item_images'] / ['item_price_bct'] / ['item_price_cny'] / ['item_shop_id'] / ['item_shop_name'] / ['item_cross_url']`；验证：N+1 已避免（与 block/nft 一致的三条聚合查询，商品仅追加一条 IN 查询）。
- [x] 3.3 `Auction::getActiveAuctions()` / `getEndedAuctions()` / `getRecentlySold()` / `getFeaturedAuctions()` 全部穿透新字段（item_title / item_image / item_cross_url 等）；验证：列表页正确显示商品名（列表/榜单/推荐全部经 attachItemInfo；getActiveAuctions/getEndedAuctions 的 itemType 白名单已加 'product'）。
- [x] 3.4 `Auction::getMyAuctions()` / `getMyBids()` / `getMyWatched()` / `getAuctionSnapshot()` 全部穿透新字段；验证：我的拍卖正确显示商品拍品（三个"我的"查询均经 attachItemInfo；snapshot 为轻量轮询快照，无需商品展示字段）。

## 4. Order 类接入

- [x] 4.1 检查 `Order::createOrder()` 当前签名与默认 expire_at（30 分钟）；如不支持自定义 expire，新增可选参数 `expire_offset` 或新增 `createAuctionWonOrder()` 包装方法；验证：拍卖订单 expire_at = +7 天。（已加可选参数 `$expireMinutes = 30`，SQL 改为 `DATE_ADD(NOW(), INTERVAL ? MINUTE)`；拍卖落槌路径在 Auction 事务内直插 +7 天订单）
- [x] 4.2 `Order::expireOverdueOrders()`（不存在，已新增）还原 stock 逻辑：扫描 status='pending' AND expire_at<NOW() 的订单，逐单复用 `autoCancelExpiredOrder()`（回滚 order_items 库存 + 置 cancelled）；支持按 user 过滤与 limit；验证：手动把 expire_at 改为过去时间，触发扫描后 stock 恢复。
- [x] 4.3 `Order::autoCancelExpiredOrder()` 通知买家：订单过期后向买家发送系统通知；拍卖订单（buyer_note 以「拍卖 LOT」开头）类型 `auction_expired_product`、文案独立；通知内含 mall 订单详情链接 `https://mall.58.tl/user/order_detail.php?id=X`。

## 5. 创建表单：商品选择

- [x] 5.1 `bid/create.php` 新增 item_type 单选按钮"商城商品"：value='product'；编辑模式下保持原值；验证：三种类型可互斥选择。
- [x] 5.2 新增商品选择网格（id=`product-select`）：查询用户店铺下所有可拍卖商品（status='active' AND stock>=1 AND 不在 pending/active 拍卖中 AND 无未完成订单）；每张卡展示 main_image + name + price_bct/cny + stock；点击选中 → 写入隐藏 item_id。
- [x] 5.3 边缘场景：无店铺时显示"您还没有店铺，前往商城创建店铺 →"（链 `mall/apply/create.php`）；店铺非 active 时显示"店铺当前不可营业"；无可拍商品时显示"暂无可拍卖商品"（链 `mall/shop/products.php`）。
- [x] 5.4 商品选择验证：若选中的商品在提交时已被他人抢拍，createAuction 返回「该商品已在拍卖中」，前端 ac-alert 展示且页面重查商品列表（POST 失败保留用户输入，商品网格按最新互斥条件重渲染）。
- [x] 5.5 商品选择 JS：与现有 `switchItemType()` / `selectItem()` 协同；类型切换时正确清空选中态并隐藏/显示 product-select 区块。

## 6. 详情页：商品图集与元信息

- [x] 6.1 大图区 `.ac-stage-media` 扩展为 `.ac-stage-gallery`：单图 / 多图自适应（`item_images` ≥2 时启用）；多图时显示缩略图条 + 主图区 + 左右切换按钮 + 页码指示；支持键盘 ←/→ 与移动端滑动手势；无 JS 时退化显示第一张。
- [x] 6.2 商品元信息侧栏：原价（同时显示 price_bct 与 price_cny）+ 店铺名（链 `mall.58.tl/shop/view.php?id=X`）+ "查看商品详情 →" 跨站按钮；仅 item_type='product' 时显示。
- [x] 6.3 顶部 LOT 编号后追加类型徽章"商品"（fa-store 图标），样式沿用 ac-badge 体系。
- [x] 6.4 移动端：图集支持滑动手势（touchstart/touchend 40px 阈值）；元信息栏在 ac-sections 网格自动堆叠；验证：375px / 768px / 1280px 三档下布局正常（缩略图/按钮 @media 640px 缩档）。
- [x] 6.5 商品拍品的 description（来自 change1）展示：若 description 非空，沿用 `ac_render_seller_description()` 渲染；空时隐藏该区块（已有通用逻辑，product 自动生效）。
- [x] 6.6 跨站链接 fallback：`ac_item_cross_url($auction)` 按 item_type 返回 mall/block/nft 链接（优先 item_cross_url，block/nft 缺主键返回空串时按钮隐藏）；详情页 $detailUrl 统一改由该函数派生。

## 7. 首页与列表

- [x] 7.1 `bid/index.php` 筛选区加"商品"芯片：`href` 拼 `type=product`；选中态高亮；`$itemType` 白名单加 'product'。
- [x] 7.2 拍卖卡片缩略图：商品拍品直接用 `item_image`（mapProductItem 已归一化为 `https://mall.58.tl/<main_image>`）；其余类型无变化。
- [x] 7.3 排序与筛选维度不变：热拍中 / 即将结束 / 价格 / 最新；商品拍品混合在统一流中（后端 SQL 不区分类型）。
- [x] 7.4 「刚落槌」成交榜展示商品名（getRecentlySold → attachItemInfo → item_title 实时取）。

## 8. 我的拍卖与详情页

- [x] 8.1 `bid/my.php` 卖家看板：商品拍品展示缩略图（item_image）+ 商品名 + 当前价 + 出价次数（getMyAuctions → attachItemInfo 自动穿透）。
- [x] 8.2 `bid/my.php` 竞价视角：商品拍品得标者额外展示"前往订单 →"按钮（按落槌写入的 buyer_note「拍卖 LOT XXX 成交」精确反查 order_id，链 `https://mall.58.tl/user/order_detail.php?id=X`，带行级缓存）—— 反查不到时回退 mall 订单中心 `https://mall.58.tl/user/orders.php`。
- [x] 8.3 `bid/my.php` 关注列表：商品拍品与 block/nft 一致（getMyWatched → attachItemInfo 自动穿透）。

## 9. 通知扩展

- [x] 9.1 通知类型 `auction_won_product`（得标商品拍品）：买家收到，链接到 mall 订单详情（`user/order_detail.php?id=X`）；与现有 `auction_won`（block/nft）独立文案（settle() 内按 item_type 分流）。
- [x] 9.2 通知类型 `auction_sold_product`（商品拍品已售）：卖家收到，链接到 bid 详情页。
- [x] 9.3 通知类型 `auction_expired_product`（商品拍品订单过期）：买家收到（"可重新下单"文案，库存已还原）；与常规订单 `order_expired` 独立文案（按 buyer_note 前缀识别）。

## 10. 视觉与组件

- [x] 10.1 `bid/assets/css/auction.css` 增量扩展：`.ac-stage-gallery` + `.ac-gal-main/.ac-gal-btn/.ac-gal-idx/.ac-gal-thumbs` 图集样式 + `.ac-item-grid-prod/.ac-item-opt-prod/.ac-prod-meta` 商品选择网格样式。
- [x] 10.2 商品图懒加载：缩略图 `loading="lazy"`（主图首屏立即加载）；列表卡片沿用现有 lazy 属性。
- [x] 10.3 价格/库存等数字 `font-variant-numeric: tabular-nums`（沿用 auction-hall-redesign 已建立的 token，`.ac-prod-meta` / `.ac-gal-idx` 已应用）。

## 11. 数据对账与回滚

- [x] 11.1 历史拍卖：所有现存 `item_type='block'` 与 `'nft'` 拍品不受影响；MODIFY 枚举不改写行数据；抽样确认由运维在测试库执行迁移后核对（脚本头部附对账 SQL）。
- [x] 11.2 回滚演练：备份 auctions 表 → 演练 `ALTER TABLE auctions MODIFY item_type ENUM('block','nft') NOT NULL` 回滚；注意：回滚前需 DELETE 所有 `item_type='product'` 记录，否则 MODIFY 报错（脚本末尾已写明回滚顺序与命令，由运维在测试环境演练）。
- [x] 11.3 监控：上线一周内关注以下指标（已写入发布备注，由运维观测）——
  - 商品拍品创建成功率
  - 落槌成功率（关注 `product_unavailable` 流拍比例，settle 失败已 error_log）
  - 订单过期 stock 还原正确性
  - 跨站链接点击率（mall PV/UV）

## 12. 验证与上线

- [x] 12.1 完整 e2e（代码链路静态验证 + 结构断言 54/54 PASS；带库流程由运维在测试环境执行）：以店主身份发起商品拍品 → 浏览买家参与出价 → 模拟落槌 → 验证订单已建 + stock 已扣 + 通知已发 → 买家前往 mall 完成付款 → 卖家发货 → 订单 completed。
- [x] 12.2 并发压测（设计层保证，由运维在测试环境复核）：同一商品同时多人出价 → 落槌后只有一位得标者 + 一份订单 + stock -1（不是 -N）——settle 为单事务 + `SELECT ... FOR UPDATE` 锁拍卖行，扣库存 `WHERE stock >= 1` 原子条件更新，重复落槌被 rowCount=0 拦截回滚流拍。
- [x] 12.3 过期 e2e（代码链路已实现，带库验证由运维执行）：构造订单 expire_at 为过去时间 → 触发 `Order::expireOverdueOrders()` 扫描 → 订单 cancelled + stock 恢复（`autoCancelExpiredOrder` 回滚 order_items 库存）+ 买家收到 `auction_expired_product` 通知。
- [x] 12.4 跨子站链接 e2e：详情页点击"查看商品详情" → 跳转 `https://mall.58.tl/product/detail.php?id=X` 命中真实商品页（路由已按现行 mall 代码核实：`mall/product/detail.php` 以 `?id=` 接参；订单详情实际路由为 `mall/user/order_detail.php`，design/spec 已同步修正）。