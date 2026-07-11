# NFT 售卖购买流程 — 技术设计

## 一、数据层：getSaleList() 补充字段

在已有 JOIN `cities` 和 `purchase_count` 子查询的基础上，SELECT 新增两个字段：

```sql
-- 新增
t.id AS transaction_id,
t.seller_id
```

修改后的完整 SQL：
```sql
SELECT
    n.id AS nft_id, n.code, n.base_image,
    t.price, t.currency, t.created_at,
    u.username AS seller_name,
    c.id AS city_id, c.name AS city_name,
    t.id AS transaction_id,       -- 新增
    t.seller_id,                   -- 新增
    (SELECT COUNT(*) FROM nft_purchase_requests WHERE nft_id=n.id AND status='active') AS purchase_count
FROM nft_avatars n
JOIN nft_transactions t ON n.id = t.nft_id
JOIN users u ON t.seller_id = u.id
JOIN cities c ON t.city_id = c.id
WHERE t.status = 'listed'
```

文件：`classes/NFT.php` `getSaleList()` 方法。

---

## 二、sale_list.php 卡片链接

当前：
```html
<a href="/nft/view.php?id=<?= $s['nft_id'] ?>" class="sale-card">
```

改为：
```html
<a href="/nft/buy.php?tx=<?= $s['transaction_id'] ?>" class="sale-card">
```

`transaction_id` 是 `nft_transactions` 的主键，唯一标识一条挂售记录。

文件：`nft/nft/sale_list.php`。

---

## 三、buy.php 重写

### 3.1 数据获取（GET 阶段）

根据 `$_GET['tx']` 查询挂售详情：

```sql
SELECT
    n.id AS nft_id, n.code, n.base_image,
    t.id AS transaction_id, t.price, t.currency,
    t.transaction_type, t.status, t.seller_id,
    u.username AS seller_name,
    c.id AS city_id, c.name AS city_name
FROM nft_transactions t
JOIN nft_avatars n ON t.nft_id = n.id
JOIN users u ON t.seller_id = u.id
JOIN cities c ON t.city_id = c.id
WHERE t.id = ? AND t.status = 'listed'
```

未查到记录（已售出/不存在）→ 提示"该NFT已售出或不存在"。

### 3.2 页面展示

```
┌─────────────────────────────────────────────────┐
│  ← 返回市场                     🛒 确认购买    │
├─────────────────────────────────────────────────┤
│                                                 │
│          ┌──────────────┐                       │
│          │      ○       │                       │
│          │    SVG       │    AB01               │
│          │              │    📍 北京            │
│          └──────────────┘    @seller            │
│                                                 │
│          ════════════════════════════           │
│                                                 │
│  Ⓟ 人气值购买:                                  │
│       售价:     Ⓟ 3,500                        │
│       余额:     Ⓟ 12,000                       │
│       购买后:   Ⓟ 8,500                        │
│                                                 │
│  ┌─────────────────────────────────────────┐   │
│  │        确认购买  Ⓟ 3,500                │   │
│  └─────────────────────────────────────────┘   │
│  购买后该 NFT 将出现在您的收藏                    │
│                                                 │
└─────────────────────────────────────────────────┘

¥ 人民币购买时：
│                                                 │
│  ¥ 人民币购买:                                  │
│       售价:     ¥ 99.00                         │
│                                                 │
│  ┌─────────────────────────────────────────┐   │
│  │    提交购买意向（等待卖家确认）          │   │
│  └─────────────────────────────────────────┘   │
│  卖家确认后该 NFT 将转移到您的收藏               │
│                                                 │
```

### 3.3 购买处理（POST 阶段）

```
POST buy.php?tx=123

校验链:
  1. 登录检查   → 未登录跳 /auth/login.php?redirect=...
  2. 交易存在   → SELECT WHERE id=? AND status='listed'
  3. 不是自买   → $tx['seller_id'] !== $_SESSION['user_id']
  4. 货币分支:
     ├─ popularity (Ⓟ):
     │   a. 查询余额: SELECT popularity FROM users WHERE id=?
     │   b. 余额 >= price?
     │   c. BEGIN TRANSACTION
     │   d. UPDATE nft_transactions SET status='completed',
     │      buyer_id=?, completed_at=NOW() WHERE id=?
     │   e. UPDATE users SET popularity=popularity-? WHERE id=?  (买家)
     │   f. UPDATE users SET popularity=popularity+? WHERE id=?  (卖家)
     │   g. UPDATE nft_avatars SET owner_id=? WHERE id=?
     │   h. COMMIT
     │   i. 跳转 buy_success.php?tx=xxx&result=completed
     │
     └─ cny (¥):
        a. UPDATE nft_transactions SET status='pending',
           buyer_id=? WHERE id=?
        b. 跳转 buy_success.php?tx=xxx&result=pending
```

文件：`nft/nft/buy.php`。

---

## 四、buy_success.php 结果页

简洁的成功反馈页。根据 `$_GET['result']` 区分状态：

| result | 图标 | 文案 | 操作 |
|--------|------|------|------|
| `completed` | ✅ | 购买成功！NFT 已添加到您的收藏 | 查看我的收藏 |
| `pending` | ⏳ | 购买意向已提交 | 返回市场 / 查看交易 |

页面展示交易摘要（NFT 头像+编号+价格）。

文件：`nft/nft/buy_success.php`（新建）。

---

## 五、完整用户路径

```
sale_list → buy.php → buy_success.php
                            │
                      ┌─────┴─────┐
                      │  completed │ pending
                      │     ✅     │   ⏳
                      │ 查收藏     │ 等确认
                      └───────────┘
```

---

## 六、文件变更清单

| 操作 | 文件 | 说明 |
|------|------|------|
| 修改 | `classes/NFT.php` | getSaleList() 加 transaction_id, seller_id |
| 修改 | `nft/nft/sale_list.php` | 卡片链接 view.php?id= → buy.php?tx= |
| 重写 | `nft/nft/buy.php` | 基于 nft_transactions 模型的完整购买页 |
| 新建 | `nft/nft/buy_success.php` | 购买结果页 |

总计 **4 个文件**，1 新建 + 3 修改。
