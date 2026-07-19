# 任务清单：重构区块售卖市场

## T1. 数据模型迁移
- [ ] 在 `init/db-init.sql` 为 `blocks` 表新增 `display_type` / `display_image` / `display_text` / `display_color` 字段。
- [ ] 在 `init/db-init.sql` 新增 `block_listings` 表（含 `block_id` / `merged_block_id` / `price` / `currency` / `status` / `buyer_id` / 联系方式等）。
- [ ] 在测试库执行迁移并校验字段。

## T2. 区块皮肤后端能力
- [ ] 在 `classes/Block.php`（或新建 `BlockListing.php`）实现：读取/更新 `blocks.display_*`（仅 owner）。
- [ ] 实现按 `block_number` 查询皮肤的方法，供 `city.php` 与 `sale_list.php` 复用。
- [ ] 实现图片上传落库（复用 `nft/nft/create.php` 上传范式，存 `block/uploads/`）。

## T3. 区块管理页（展示配置 + 上架合一）
- [ ] 新建 `block/block/manage.php`：权限校验（owner）、区块信息展示。
- [ ] 实现展示配置区：display_type 单选 + 图片上传 + 文字输入 + 红绿蓝选色，保存更新 `blocks.display_*`。
- [ ] 实现挂牌区：价格 + 货币(Ⓟ/¥) + 联系方式 + "上架出售"（写 `block_listings.status='listed'`）+ 已挂牌时"取消挂牌"。
- [ ] 合并块入口 `manage.php?merged_id=`：解析 `merged_blocks`，整组作为一个 listing 上架。
- [ ] 将 `block/block/edit.php`、`block/block/sell.php` 重定向到 `manage.php`；`block/user/dashboard.php` "出售"按钮改指 `manage.php`。

## T4. 城市地图皮肤渲染
- [ ] 在 `block/city.php` 单区网格渲染中：区块已配置皮肤时，按图片/文字(红绿蓝)覆盖渲染。
- [ ] 在 `block/city.php` 九区全景渲染中：合并块首格与单块均已配置皮肤时同样渲染皮肤（与单区一致，无多余边框）。

## T5. 售卖列表重写
- [ ] 重写 `block/sale_list.php`：仅 `SELECT block_listings WHERE city_id=? AND status='listed'`。
- [ ] 卡片显示：区块号/合并尺寸、皮肤缩略（图片或红绿蓝文字块）、价格+货币符号、卖家信息。
- [ ] 卡片点击进入 `buy.php?listing=<id>`；移除原"已售区块/立即认领"逻辑。

## T6. 购买流程（pending 下单 + 双货币确认）
- [ ] 重写 `block/block/buy.php`：登录检查、自买/重复购买/合并块可购性防呆。
- [ ] 展示区块/合并块信息 + 皮肤 + 价格 + 货币 + 卖家联系方式。
- [ ] "立即购买" → 写 `block_listings.status='pending'`, `buyer_id=buyer`（**不即时扣减任何余额**）。
- [ ] 人气值Ⓟ：展示 mall `pay.php` 同款引导（blockcity.vip 转账 + "我已支付"），**不复用 `transferPopularity` 即时转账**；点"我已支付"保持 pending 并通知卖家。
- [ ] 人民币¥：下单即提交意向并通知卖家（cny pending 模式）。

## T7. 卖家确认 + 所有权转移
- [ ] 新建 `block/block/confirm_sale.php`：卖家查看 `pending` 订单，确认收款（人气值/人民币）。
- [ ] 确认动作（事务）：`listing.status='completed'` + 所有权转移（单块改 `blocks.owner_id`；合并块改 `merged_blocks.owner_id` 及组内所有 `blocks.owner_id`）。
- [ ] 写 `transactions` 成交记录（`classes/Transaction.php`）。
- [ ] 通知买家购买成功；卖家可取消 pending 订单（`canceled`）。

## T8. 联调与校验
- [ ] 单块：领取 → 设皮肤(图/文) → 地图与卡片一致显示 → 上架 → 购买(pending) → 确认 → 所有权转移 + transactions。
- [ ] 合并块：整组上架 → 购买 → 整组转移。
- [ ] 两种货币：Ⓟ(pending→确认，非即时扣减) / ¥(意向→卖家确认) 全链路。
- [ ] 异常：自买、重复购买、未登录、非 owner 改皮肤等拦截正确。
