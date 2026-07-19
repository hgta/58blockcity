# 设计：重构区块售卖市场

## 1. 数据模型

### 1.1 `blocks` 表新增展示字段（区块"皮肤"）

```sql
ALTER TABLE blocks
  ADD COLUMN display_type ENUM('none','image','text') NOT NULL DEFAULT 'none' COMMENT 'none=默认/未配置, image=图片, text=文字',
  ADD COLUMN display_image VARCHAR(255) NULL COMMENT '图片模式下的图片路径',
  ADD COLUMN display_text VARCHAR(50) NULL COMMENT '文字模式下的文字内容',
  ADD COLUMN display_color ENUM('red','green','blue') NULL COMMENT '文字模式背景色';
```

- 仅 `owner_id` 本人可修改这些字段（认领后）。
- `display_type='none'` 时地图/卡片沿用现有状态色渲染（own=绿/blue=蓝/red=红/available=白）。

### 1.2 新建 `block_listings` 表（挂牌生命周期，对标 `nft_transactions`+`nft_sales`）

```sql
CREATE TABLE block_listings (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  city_id         INT NOT NULL,
  seller_id       INT NOT NULL,
  -- 目标：单块或合并块二选一
  block_id        INT NULL COMMENT '单块挂牌时指向 blocks.id',
  merged_block_id INT NULL COMMENT '合并块整体挂牌时指向 merged_blocks.id',
  price           DECIMAL(20,2) NOT NULL,
  currency        ENUM('popularity','cny') NOT NULL COMMENT '人气值 / 人民币',
  status          ENUM('listed','pending','completed','canceled') NOT NULL DEFAULT 'listed',
  buyer_id        INT NULL,
  contact_phone   VARCHAR(20) NULL,
  contact_wechat  VARCHAR(50) NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  completed_at    DATETIME NULL,
  updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_city_status (city_id, status),
  KEY idx_seller (seller_id),
  KEY idx_block (block_id),
  KEY idx_merged (merged_block_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='区块挂牌表';
```

- 单块挂牌：`block_id` 填值，`merged_block_id` 为 NULL。
- 合并块挂牌：解析 `merged_blocks.merged_blocks`（逗号串）对应的所有 `blocks.id`，取组首块或任一块 `block_id` 存入 `block_id`（便于地图定位），同时 `merged_block_id` 指向 `merged_blocks.id`；成交时整组转移。
- `status` 状态机：`listed → (买家下单) pending → (卖家确认) completed`；任意方在 pending 前可 `canceled`。

### 1.3 成交记录

成交时写一条 `transactions` 记录（表已存在），字段沿用现有约定：`from_user=buyer, to_user=seller, city, amount, type='block_sale', related_id=listing.id` 等。实现时按 `classes/Transaction.php` 现有接口落库。

## 2. 区块皮肤（展示配置）

- **存储**：`blocks.display_*` 由 owner 在"区块管理"页设置。
- **图片上传**：复用 NFT 创建页（`nft/nft/create.php`）的图片上传范式（`move_uploaded_file` 到 `block/uploads/` 或 `assets/uploads/`，存相对路径）。
- **文字模式**：`display_text`（≤50 字）+ `display_color` ∈ {red,green,blue}，对应背景色与 A 区合并块编号色系一致（红 `#ff6060` / 绿 `#35cc2d` / 蓝 `#337be6`）。
- **地图渲染（`city.php`）**：
  - 渲染每个区块单元格时，若该 `block_number` 对应 `blocks.display_type != 'none'`，优先按皮肤渲染：图片模式 → 单元格背景图（或 `<img>` 覆盖）；文字模式 → 单元格背景填 `display_color`、`display_text` 居中显示。
  - 皮肤叠加在现有状态色之上（自己绿/别人蓝红仅当 `display_type='none'` 时区分；已配置皮肤则直接显示皮肤）。
  - 合并块（跨格首格）同样支持皮肤，整块显示同一皮肤。
- **挂牌卡片**：`sale_list.php` 卡片缩略图优先用皮肤（图片/文字色块）。

## 3. "区块管理"页（展示配置 + 上架合一）

合并现有 `block/block/edit.php`(皮肤) 与 `block/block/sell.php`(上架) 为单一页面 **`block/block/manage.php?id=<block_id>`**（合并块用 `?merged_id=<merged_blocks.id>`）：

- **权限**：仅 `blocks.owner_id`（或合并块 `merged_blocks.owner_id`）可访问。
- **区块信息**：显示区块号/合并尺寸/当前状态。
- **展示配置区**：`display_type` 单选（无/图片/文字）；图片上传控件；文字输入 + 红绿蓝单色选择。保存 → 更新 `blocks.display_*`。
- **挂牌上架区**：
  - 价格输入（数字）。
  - 货币单选：人气值Ⓟ / 人民币¥。
  - 联系方式（电话/微信，选填，交给买家意向联系）。
  - "上架出售"按钮 → 写入 `block_listings`（status=`listed`）。
  - 若已挂牌：显示当前挂牌状态 + "取消挂牌"（`canceled`）。
- 合并块：整组作为一个 listing 上架（见 §6）。

## 4. `sale_list.php` 重写

- 仅 `SELECT ... FROM block_listings WHERE city_id=? AND status='listed'`（按当前城市）。
- 卡片内容：区块号/合并尺寸、皮肤缩略（图片或红绿蓝文字块）、价格 + 货币符号（Ⓟ 人气值 / ¥ 人民币）、卖家信息、点击进入 `buy.php?listing=<id>`。
- 合并块挂牌卡片：显示合并尺寸（如 2×2）与组内最小编号，整组作为一个卡片。
- 移除原 `WHERE status='sold'` 的"已售区块"逻辑与"立即认领"入口。

## 5. 购买流程 `block/block/buy.php`

入口：`sale_list.php` 卡片 → `buy.php?listing=<id>`。

- **登录检查**：未登录跳转 `auth/login.php?redirect=...`。
- **防呆**：自买拦截（buyer=seller）、重复购买拦截（status≠listed）、合并块需整组可购买校验。
- **展示**：区块/合并块信息 + 皮肤 + 价格 + 货币 + 卖家联系方式（意向联系）。
- **下单（pending）**：点击"立即购买" → `block_listings.status='pending'`, `buyer_id=buyer`。**不在此处扣任何余额**（符合"非即时扣减"）。

### 5.1 人气值Ⓟ 支付（同 mall 模式，非即时扣减）

- 下单后展示 mall `pay.php` 同款引导：在 **blockcity.vip** 用 BCT 人气值向**卖家收款区块**转账 → 返回点"我已支付"。
- 买家点"我已支付" → `listing` 维持 `pending`（或置 `awaiting_confirm`），通知卖家。
- **不调用 `UserPopularity::transferPopularity` 即时转账**；真实人气值划转由用户在 blockcity.vip 自行完成，平台仅做确认。

### 5.2 人民币¥ 支付（同 NFT cny 模式）

- 下单(pending) 即提交购买意向 → 通知卖家。
- 卖家在管理/确认页确认收到人民币后完成（见 §6）。

## 6. 卖家确认 + 所有权转移

卖家在 `block/block/manage.php`（或独立 `block/block/confirm_sale.php?listing=<id>`）看到 `pending` 订单：

- **确认收款**：
  - 人气值：确认买家已在 blockcity.vip 完成转账 → 完成。
  - 人民币：确认已线下/对接收到款项 → 完成。
- **完成动作（事务）**：
  1. `block_listings.status='completed'`, `completed_at=now()`, `buyer_id` 落定。
  2. 所有权转移：
     - 单块：`blocks.owner_id=buyer`，`status` 保持 `sold`。
     - 合并块：更新 `merged_blocks.owner_id=buyer`，并将 `merged_blocks.merged_blocks` 解析出的**所有** `blocks` 行 `owner_id=buyer`。
  3. 写 `transactions` 记录（buyer→seller，金额=price，city，type=block_sale）。
  4. 通知买家"购买成功"。
- **取消/拒绝**：卖家可取消 pending 订单 → `listing.status='canceled'`，区块回到 `listed` 可再次被买（或回退为未挂牌由 owner 决定）。

## 7. 合并块整体挂牌与转移

- **上架**：`manage.php?merged_id=<id>` 解析 `merged_blocks`，写一条 `block_listings`（`merged_block_id=<id>`，`block_id`=组内任一 block 便于定位）。
- **展示**：`sale_list.php` 与地图按合并尺寸渲染整块皮肤。
- **购买/转移**：约束整组一次性交易；成交后 §6.2 整组 `owner_id` 转移。
- **校验**：合并块挂牌期间，组内任何单块不可被单独认领/重复挂牌（依赖 `merged_blocks` 现有归属约束）。

## 8. 复用的现有基础设施

- `UserPopularity`：仅用于**查询**买家在该城市人气值余额（展示"是否足够"提示），不做即时扣减。
- `Notification`（`classes/Notification.php`）：下单/确认/完成通知买卖家。
- `nft_transactions` / `nft_sales`：挂牌 `listed` / `pending` / `completed` / `canceled` 状态机范式。
- mall `mall/user/pay.php`：人气值"我已支付"确认范式（离线转账 + 确认）。
- NFT `cny=pending`：人民币意向+卖家确认范式。
- `merged_blocks` 表：合并组归属与整组转移数据源。

## 9. 关键文件清单

| 文件 | 变更 |
|------|------|
| `init/db-init.sql` | `blocks` 加展示字段；新增 `block_listings` |
| `block/city.php` | 地图渲染区块皮肤（单块+合并块） |
| `block/block/manage.php`（新） | 展示配置 + 上架合一页（替换 edit/sell） |
| `block/block/edit.php` | 废弃/重定向到 manage.php |
| `block/block/sell.php` | 废弃/重定向到 manage.php |
| `block/sale_list.php` | 重写为只列 `listed`，卡片显示皮肤+价格+货币 |
| `block/block/buy.php` | 重写为 pending 下单 + 双货币确认 |
| `block/block/confirm_sale.php`（新） | 卖家确认 + 所有权转移 + 写 transactions |
| `block/user/dashboard.php` | "出售"按钮改指 `manage.php` |
| `classes/Block.php`（或新 `BlockListing.php`） | 挂牌 CRUD + 转移逻辑 |
| `classes/Transaction.php` | 成交记录写入 |
