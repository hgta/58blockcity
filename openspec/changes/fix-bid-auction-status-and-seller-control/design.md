# 设计：拍卖状态机修复 + 卖家操作能力

## 1. 状态机修复（僵尸拍卖自愈）

### 1.1 根因

```php
// createAuction() 第 78-80 行 —— 唯一的 pending/active 判定，仅创建瞬间执行一次
$now = time();
$status = strtotime($startTime) > $now ? 'pending' : 'active';
```

```php
// settleExpired() 第 203-210 行 —— 只结算 active 且已到期的拍卖
WHERE status = 'active' AND end_time < NOW()
```

**断链点**：`pending` 拍卖到点后无人将其翻转为 `active`；同时 `settleExpired` 不处理过期 `pending`，形成僵尸记录。

### 1.2 修复：`tick()` 统一入口

在 `Auction` 类新增：

```php
/**
 * 惰性推进状态机：先激活到点的 pending，再结算到点的 active。
 * 保持纯惰性、无 cron，与现有机制一致。
 */
public function tick() {
    $this->activateStarted();
    $this->settleExpired();
}

/**
 * pending → active：开始时间已到的拍卖自动开拍
 */
public function activateStarted() {
    $stmt = $this->pdo->prepare("
        UPDATE auctions SET status = 'active', updated_at = NOW()
        WHERE status = 'pending' AND start_time <= NOW()");
    $stmt->execute();
}
```

`settleExpired()` 保持原逻辑不变。由于 `tick()` 先激活后结算：
- 正常拍卖：`pending → active`（到点）→ `settleExpired` 到点结算；
- 僵尸 `pending`（已过 `end_time`）：先转 `active`，随即被 `settleExpired()` 结算 → 无出价即 `ended`，自愈完成。

### 1.3 调用点替换

| 文件 | 原调用 | 改为 |
|------|--------|------|
| `bid/index.php` | `$auction->settleExpired()` | `$auction->tick()` |
| `bid/view.php` | `$auction->settleExpired()` | `$auction->tick()` |
| `bid/my.php` | `$auction->settleExpired()` | `$auction->tick()` |

### 1.4 状态机全貌（修复后）

```
                tick(): activateStarted 到点自转
  ┌──────────┐ ───────────────────────────────▶ ┌──────────┐
  │ pending  │                                    │ active   │
  │ 未开始   │◀─────── 卖家编辑（仅此态可改）       │ 竞拍中   │
  └────┬─────┘                                    └────┬─────┘
       │ 卖家取消（pending 可取消）                       │ 卖家取消（仅无人出价）
       ▼                                                ▼
  ┌──────────┐   settle(): 无出价或<底价      ┌──────────┐
  │ canceled │◀───────────────────────────────│  ended   │ 已流拍
  └──────────┘                                └──────────┘
       ▲                                            ▲
       └──── 卖家取消（无出价时）────────────────────┘
                                        settle(): 有出价且≥底价
                                        ┌──────────┐
                                        │  sold    │ 已成交
                                        └──────────┘
```

## 2. 卖家操作授权矩阵

| 状态 | 取消 | 编辑 | 说明 |
|------|------|------|------|
| `pending`（未开始） | ✅ | ✅ | 无任何出价，无利益相关方 |
| `active` 且 `current_bidder_id IS NULL`（无人出价） | ✅ | ❌ | 尚未有人参与，可撤；开拍后价格/时间不可改 |
| `active` 且有人出价 | ❌ | ❌ | 保护竞拍者，提示联系管理员 |
| `sold` / `ended` / `canceled` | ❌ | ❌ | 终态只读 |

**产品决策**：采纳保守方案——「有人出价后不允许卖家取消」，防止卖家见价高撤拍、破坏公平性。如需干预走管理员线下处理（本 change 不做管理员页面）。

## 3. 新增方法设计

### 3.1 `cancelAuction($auctionId, $sellerId)`

```php
public function cancelAuction($auctionId, $sellerId): array
```

- 归属校验：`seller_id === $sellerId`，否则返回 `['ok'=>false,'msg'=>'无权操作']`。
- 状态校验：
  - `pending` → 允许；
  - `active` 且 `current_bidder_id IS NULL` → 允许；
  - `active` 且有人出价 → 拒绝，`msg='该拍卖已有人出价，无法取消'`；
  - 其他终态 → 拒绝。
- 通过后 `UPDATE auctions SET status='canceled', updated_at=NOW() WHERE id=? AND status IN ('pending','active')`（条件更新防并发竞态，影响行数 0 则视为被并发修改）。
- 返回 `['ok'=>bool, 'msg'=>string]`。

**互斥自动解除**：`isItemInActiveAuction()` 只查 `status IN ('pending','active')`，取消后物品立即解冻，可重新拍卖/挂牌，无需额外逻辑。

### 3.2 `updateAuction($auctionId, $sellerId, $data)`

```php
public function updateAuction($auctionId, $sellerId, $data): array
```

- 归属校验同上。
- 仅 `pending` 状态允许编辑。
- 允许修改字段：`start_price`、`reserve_price`、`bid_increment`、`start_time`、`end_time`、`currency`、`accept_cities`。**物品（item_type/item_id）不可换**。
- 复用 `createAuction()` 的校验规则：起拍价/加价>0、`end_time > start_time`、底价 ≥ 起拍价。
- 保存后重算状态：`strtotime(start_time) > now ? 'pending' : 'active'`（与创建逻辑一致，编辑到点时间即直接开拍）。
- `accept_cities` 仅 `popularity` 货币生效，规则同创建。

## 4. 页面改动

### 4.1 `bid/my.php`（我发布的 tab）

- 每行操作区新增按钮（按授权矩阵渲染）：
  - 「✏️ 编辑」→ `create.php?edit=<id>`（仅 `pending` 显示）
  - 「🗑 取消」→ POST 表单 `action=cancel&id=<id>`，JS `confirm('确定取消该拍卖吗？')`（`pending` / 无出价 `active` 显示）
- 表单提交到 `my.php` 自身，处理逻辑在列表渲染前：
  ```php
  if ($_POST['action'] === 'cancel') {
      $r = $auction->cancelAuction((int)$_POST['id'], $userId);
      // 成功/失败 flash 消息
  }
  ```
- 需要 `includes/auth.php` 的 CSRF 校验（若现有提供 `verifyCsrf()`）或跟随站点既有 POST 防护约定。

### 4.2 `bid/create.php`（复用为编辑页）

- 支持 `?edit=<id>`：
  - 加载时读取 `$auction->getAuctionById($id)`，校验归属 + 状态为 `pending`（否则拒绝并提示）；
  - 表单全部预填（含 `item_type`/`item_id` 隐藏域）；
  - **物品选择区隐藏**，显示「拍卖品：XXX（不可更换）」说明条；
  - 提交按钮文案「保存修改」，标题「编辑拍卖」；
  - POST 处理分支：`edit` 存在走 `updateAuction()`，否则走 `createAuction()`。
- 页面顶部提示：仅「未开始」的拍卖可编辑。

### 4.3 `bid/view.php`（卖家视角）

- 卖家且状态为 `pending`：显示「编辑 / 取消」快捷按钮（同 my.php 授权）。
- 卖家且状态为 `active` 无出价：显示「取消」按钮。
- 卖家且 `active` 有人出价：显示提示「拍卖进行中，如需取消请联系管理员」。
- 取消操作 POST 到 `view.php` 自身，与 `my.php` 共用 `cancelAuction()`。

## 5. 安全与一致性

- **归属**：所有操作以 `$_SESSION['user_id']` 与 `auctions.seller_id` 强校验，防越权。
- **CSRF**：取消/编辑均走 POST，按站点既有约定加 CSRF token。
- **并发**：取消用条件 `UPDATE ... WHERE status IN ('pending','active')`，防止「取消瞬间有人出价」的竞态；`placeBid` 的事务锁（`SELECT ... FOR UPDATE`）与之配合。
- **状态一致性**：编辑保存后状态重算，不破坏 `tick()` 的推进。
- **无表结构变更**：`canceled` 已存在于 `auctions.status` 枚举。

## 6. 不变量

- 任意时刻，`pending` 拍卖必然满足 `start_time > NOW()`（否则应已被 `tick()` 激活）。
- 任意时刻，`active` 拍卖必然满足 `start_time <= NOW() < end_time`。
- `canceled` 拍卖必然无出价记录（无人出价才可取消）——此为业务不变量，由授权矩阵保证。
