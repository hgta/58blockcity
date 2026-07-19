# 重构区块售卖市场（Block Sale Market）

## 背景

当前 `block/sale_list.php` 标题为"已售区块"，直接 `SELECT ... WHERE status='sold'` 把**所有已领取区块**当成"在售"列出——这与用户诉求的"售卖板块"完全是两回事。更糟的是，`block/block/sell.php`（`dashboard` 里"出售"按钮指向的页）实际执行的是 `UPDATE blocks SET status='available', owner_id=NULL`，即**放弃认领、把区块退回可认领池**，卖家收不到任何钱，买家重新认领即可，根本不存在"挂牌→付款→所有权转移→卖家收款"的闭环。

系统里从来没有"挂牌在售(listed)"这个状态，也没有"买家付款→所有权转移→卖家收款"的能力。用户领取区块后，应当能：自定义区块展示（图片/文字+红绿蓝）；按意愿挂牌出售；支持**人气值（非即时扣减，同 mall 支付模式）**或**人民币（pending 卖家确认，同 NFT 模式）**两种支付；并支持**合并块整体挂牌与整体转移**。

## 问题

1. `sale_list.php` 把"已认领(sold)"区块全部冒充"在售"列出，无挂牌概念。
2. `block/block/sell.php` 是"放弃认领"而非"挂牌"，价值无法转移。
3. `block/block/buy.php` 仅处理 `status='available'` 区块（重新认领），无法完成已售区块的交易。
4. `blocks` 表无展示字段（图片/文字/颜色），无法做区块个性化"皮肤"。
5. 无"人气值挂牌支付"的 pending→确认闭环（现成 `UserPopularity::transferPopularity` 是即时转账，不符合 mall 模式）。
6. 合并块（连块）无法作为一个整体挂牌、整体转移。

## 目标

打通完整的区块售卖闭环：

- **区块皮肤**：领取后 owner 可设图片 / 文字(红·绿·蓝底)，在 `city.php` 地图与挂牌卡片上一致显示。
- **挂牌上架**：一个"区块管理"页同时完成（展示配置 + 上架定价），价格 + 货币(人气值/人民币)。
- **售卖列表**：`sale_list.php` 只列 `block_listings.status='listed'`，卡片显示皮肤+价格+货币。
- **购买闭环**：买家下单(pending) → 支付（人气值走 mall 的"我已支付"确认模式；人民币走 NFT 的 cny pending 卖家确认模式）→ 卖家确认 → 所有权转移 + 卖家收款 + 写 `transactions` 记录。
- **合并块整体挂牌**：合并组作为一个 listing 挂牌，成交后整组 `owner_id` 与各组内区块统一转移。

## 已确认的关键决策（与用户对齐）

1. **展示生效范围 = (A)**：区块皮肤作为"区块属性"，在 `city.php` 城市地图和挂牌卡片上**都显示**。
2. **人民币支付 = (A)**：沿用 NFT `cny=pending` 模式——提交购买意向 → 卖家后台确认 → 结算；**不接真实支付网关**。
3. **人气值支付 = mall 模式、非即时扣减**：买家创建购买单(pending) → 在 blockcity.vip 用 BCT 人气值转账给卖家区块 → 点"我已支付" → 卖家确认收到 → 所有权转移。**不复用 `transferPopularity` 即时转账**。
4. **合并块 = (B)**：支持合并块（连块）整体作为一个 listing 挂牌与整体转移。
5. **上架页与展示配置合一**：合并现有 `edit.php`(皮肤) 与 `sell.php`(上架) 为单一"区块管理"页。
6. **交易记录落库**：新建 `block_listings` 管挂牌生命周期；成交时额外写一条 `transactions` 记录（不动现有表结构语义）。

## 范围

### 本次包含

- `blocks` 表新增展示字段：`display_type` / `display_image` / `display_text` / `display_color`。
- 新建 `block_listings` 表（对标 `nft_transactions` + `nft_sales`，增加 single/merged 双目标）。
- `city.php` 地图渲染：已设皮肤的区块显示图片/文字（红绿蓝底）。
- 新建"区块管理"页 `block/block/manage.php`：展示配置 + 上架挂牌（价格+货币），含合并块整体挂牌。
- 重写 `sale_list.php`：只列 `listed`，卡片显示皮肤 + 价格 + 货币。
- 重写购买页 `block/block/buy.php`：pending 下单 + 两种货币确认流程。
- 卖家确认页：确认收到人气值/人民币 → 所有权转移 + `block_listings` 完成 + 写 `transactions`。
- 合并块整体挂牌与整体转移逻辑。
- 复用：`UserPopularity`(查余额)、`Notification`、`nft_transactions` listed/cny 范式、mall `pay.php` 的"我已支付"确认范式。

### 本次不包含

- 真实支付网关（微信/支付宝）接入。
- 区块交易手续费/平台抽成（如需后续独立 change）。
- 区块租赁/拍卖等其它交易形态。
- 跨城市区块交易（挂牌与交易锁定在同一 `city_id`）。

## 成功指标

- `sale_list.php` 只展示真正 `listed` 的区块（含合并块整体），不再混入已认领未挂牌区块。
- 领取后的区块可在地图与卡片按 owner 配置的皮肤（图片或红/绿/蓝文字）显示。
- 上架 → 购买(pending) → 卖家确认 → 所有权转移 + 写 `transactions` 全链路可走通。
- 人气值支付为 pending→确认，非即时扣减；人民币为 cny pending 卖家确认。
- 合并块（连块）可整体挂牌、整体被购买、整组所有权转移。
