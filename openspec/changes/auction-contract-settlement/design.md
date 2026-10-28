# 设计：拍卖契约化履约

## 决策前提（已确认）

| 决策点 | 结论 |
|---|---|
| 人民币怎么收进来 | 买卖双方**线下自行转账**，平台只做记录 + 双方确认 |
| 人气值 ⇄ 人民币 | **不自动兑换**，两套币种各自记账 |
| 落槌后是否动账 | **不动物权外的资金**。拍卖 = 契约，钱在线下结 |
| 契约页收款信息 | 展示「请与卖家联系约定收款方式」+ 卖家名 / 店铺链接（不建收款码设置） |
| 契约作废后物权 | 改成「收款后过户」，作废时物权仍在卖家手中，**无需回滚** |

这些决策把问题从「金融系统」降级成「契约系统」：不需要汇率表、不需要人民币账本、不需要支付接口、不需要支付回调。

---

## 1. 数据库迁移

### 1.1 `init/migrate-order-currency.sql`（阶段一）

```sql
ALTER TABLE orders ADD COLUMN currency enum('popularity','cny') NOT NULL DEFAULT 'popularity' COMMENT '订单币种';
ALTER TABLE orders ADD COLUMN auction_id int(11) NULL DEFAULT NULL COMMENT '来源拍卖单(NULL=常规订单)';
ALTER TABLE orders ADD INDEX idx_auction (auction_id);
ALTER TABLE transactions ADD COLUMN currency enum('popularity','cny') NOT NULL DEFAULT 'popularity' COMMENT '成交币种';
```

- `currency` 默认 `popularity`：存量订单全部是人气值语义，向下兼容
- `auction_id` 取代现有「按 `buyer_note` 前缀 `拍卖 LOT` 反查」的脆弱字符串约定

### 1.2 `init/migrate-auction-contract.sql`（阶段二）

```sql
ALTER TABLE auctions ADD COLUMN payment_status enum('unpaid','buyer_declared','settled','voided') NULL DEFAULT NULL COMMENT '契约履约状态(NULL=未落槌)';
ALTER TABLE auctions ADD COLUMN payment_declared_at datetime NULL COMMENT '买家声明付款时间';
ALTER TABLE auctions ADD COLUMN settled_at datetime NULL COMMENT '卖家确认收款时间';
ALTER TABLE auctions ADD COLUMN contract_expire_at datetime NULL COMMENT '契约付款期限';
ALTER TABLE auctions ADD INDEX idx_contract (status, payment_status, contract_expire_at);
```

沿用 `init/migrate-auction-featured.sql` 的 `information_schema` 判存在写法，保证可重复执行。

---

## 2. 契约状态机

```
                      落槌 settle()
                            │
                            ▼
                   ┌────────────────┐
                   │     unpaid     │   contract_expire_at = +7 天
                   └───────┬────────┘
                           │
         ┌─────────────────┼──────────────────┐
         │ 买家：我已付款                      │ 到期未声明
         ▼                                    ▼
 ┌───────────────────┐                 ┌─────────────┐
 │  buyer_declared   │                 │   voided    │
 └─────────┬─────────┘                 └─────────────┘
           │ 卖家：确认收款
           ▼
     ┌───────────┐
     │  settled  │  → 物权交割 + 写流水 + 释放占用
     └───────────┘
```

三条规则：

1. `unpaid` 满 7 天 → `voided`
2. `buyer_declared` **不自动超时推进**——卖家迟迟不确认时契约保持该状态，并在后台「超时契约」列表可见，由管理员人工介入。**避免买家虚假声明即可自动拿到物品**
3. `payment_status = NULL` 表示未落槌或流拍，不进契约流程

---

## 3. 物权交割时机（行为变更）

| item_type | 落槌时 `settle()` | `settled` 时 |
|---|---|---|
| `product` | 扣 `stock`、建 mall `pending` 订单（`currency` 继承） | `Order::markPaidByContract()` 置 `paid`；发货走 mall 现有流程 |
| `block` | **只置 `sold` + `unpaid`，不过户** | 过户 + 写 `transactions`（`currency` 继承） |
| `nft` | **只置 `sold` + `unpaid`，不过户** | 过户 + 写 `nft_transactions`（`currency` 用**真实值**，不再硬编码） |

契约只管「钱到没到」；物流归 mall 订单，契约到 `settled` 即终止。

---

## 4. 契约期占用锁定

`Auction::isItemInActiveAuction()` 扩展为：

```sql
WHERE item_type = ? AND item_id = ?
  AND ( status IN ('pending','active')
     OR (status = 'sold' AND payment_status IN ('unpaid','buyer_declared')) )
```

新增 `Auction::isItemInOpenContract()` 供一口价挂牌侧调用。block 挂牌逻辑在 `classes/BlockListing.php`，本 change 只提供方法，**接入列为后续 change**。

---

## 5. 契约页（单一入口）

`bid/user/contract.php?id=<auction_id>`，按当前用户角色渲染不同操作：

```
┌─────────────────────────────────────────────────┐
│  LOT 016 · 商城商品                              │
│  成交价 ¥ 6.04                                   │
├─────────────────────────────────────────────────┤
│  履约进度   落槌 → 待付款 → 已声明 → 已收款        │
│                                                 │
│  收款指引（人民币契约）                           │
│  · 请与卖家「58tl」联系约定收款方式                │
│    （微信 / 支付宝 / 银行转账）                    │
│  · 卖家店铺：加密二狗周边 →                        │
│  · 7 天内未完成付款，契约自动作废                  │
├─────────────────────────────────────────────────┤
│  [买家视角]  我已付款                             │
│  [卖家视角]  确认收到款项                         │
└─────────────────────────────────────────────────┘
```

人气值契约的收款指引换成：

```
  收款指引（人气值契约）
  · 请向卖家转账 Ⓟ 36 人气值至以下任一城市：
    北京 / 上海 …
  · 收款城市来自拍卖时设置的「接受城市」
```

城市来源为 `auctions.accept_cities`，落槌时已存在，无需新字段。

---

## 6. 过期驱动

`Auction::expireOverdueContracts()` 惰性扫描：

```sql
SELECT id FROM auctions
WHERE status = 'sold'
  AND payment_status = 'unpaid'
  AND contract_expire_at < NOW()
LIMIT 200
```

- 挂在 `bid/index.php`、`bid/my.php`、`bid/admin/dashboard.php`
- **不进 `bid/api/lot.php` 的 3 秒轮询路径**（避免高频写）
- 作废处理：
  - `block` / `nft`：物权未动，仅置 `voided` + 通知双方
  - `product`：额外调用 `Order::autoCancelExpiredOrder()` 回滚库存

---

## 7. 通知

| 时机 | 接收方 | 类型 | 文案要点 |
|---|---|---|---|
| 落槌 | 买家 | `auction_contract_created` | 「请在 7 天内与卖家完成付款」+ 契约页链接 |
| 买家声明付款 | 卖家 | `auction_contract_buyer_paid` | 「买家已标记付款，请核实收款后确认」 |
| 卖家确认收款 | 买家 | `auction_contract_settled` | block / nft 补一句「物品所有权已转移」 |
| 契约作废 | 双方 | `auction_contract_voided` | 商品契约另含库存已退回说明 |

通知文案中的金额统一用 `ac_money()`（`bid/includes/lot_helpers.php:15`）输出，带货币符号。

---

## 8. 商城展示层收敛（阶段一）

`mall/includes/functions.php` 新增：

```php
function order_money($amount, $currency = 'popularity') {
    return $currency === 'cny'
        ? '¥ ' . number_format(floatval($amount), 2)
        : 'Ⓟ ' . number_format(floatval($amount), 0);
}
```

替换 11 处硬编码（清单见 tasks.md §1.4）。

`mall/user/pay.php` 分流：

```
常规订单（auction_id IS NULL）        → 保持现有 BCT 支付引导
人气值契约订单（auction_id + popularity）→ 302 → 契约页
人民币契约订单（auction_id + cny）     → 302 → 契约页
```

即**契约订单的付款声明统一在拍卖站契约页完成**，mall 侧只保留物流与展示职责。

---

## Open Questions

1. **`buyer_declared` 超时处置**：当前设计为「不自动推进 + 后台可见 + 人工介入」。若后续希望自动化，可选的替代是「超时自动 `settled`」（保护买家，但引入虚假声明漏洞）或「超时自动 `voided`」（保护卖家，但买家已付款却被作废）。首期不自动化，观察实际纠纷量再定。
2. **一口价挂牌侧接入 `isItemInOpenContract()`**：本 change 只提供方法，`classes/BlockListing.php` 与 `nft` 挂售侧接入列为后续。
3. **存量已落槌的 block / nft 拍卖**：迁移后它们的 `payment_status` 为 NULL，不进契约流程。这些物品已过户且无契约记录，建议一次性标记为历史数据不予追溯，不做补录。
