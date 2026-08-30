# 修复拍卖状态机断链与卖家操作能力缺失

## 问题陈述

拍卖模块存在两个独立但叠加的缺陷，已在线上暴露（如 `bid.58.tl/view.php?id=2` 拍卖时间已过仍显示「未开始」）：

1. **状态机断链（僵尸拍卖）**：`auctions.status` 的 `pending → active` 翻转只发生在 `createAuction()` 创建瞬间的一次性判断。若创建时 `start_time` 在未来，拍卖进入 `pending` 后，**没有任何代码在其到点后将其置为 `active`**。后果：
   - 拍卖到点后永远停在「未开始」，无法出价（`placeBid()` 要求 `status === 'active'`）；
   - `settleExpired()` 只结算 `status='active'` 的拍卖，过期的 `pending` 拍卖永远不会被结算，成为僵尸记录堆积；
   - `isItemInActiveAuction()` 将 `pending` 视为「拍卖中」，物品被冻结，卖家无法重新拍卖或一口价挂牌。

2. **卖家无任何操作能力**：设计文档（`bid-auction-subsite/design.md` 48-51 行）预留了 `pending/active → canceled`（卖家撤销）状态，但代码层**零实现**：`Auction` 类无 cancel/update 方法，`my.php` 无操作按钮，全库无任何写入 `canceled` 的路径。卖家发起拍卖后对自有拍卖完全失控。

## 目标

- 修复 `pending → active` 断链，让到点的拍卖自动激活，过期的僵尸 `pending` 自愈为流拍/成交。
- 为卖家提供对自有拍卖的取消能力（无出价时），释放被冻结的物品。
- 为卖家提供对 `pending`（未开始）拍卖的编辑能力（价格、时间等）。
- 明确授权矩阵，防止卖家恶意操作（有人出价后不可取消、进行中不可改价），保护竞拍者利益。

## 非目标

- 不做管理员后台拍卖管理页（后续单独评估）。
- 不允许「有人出价后」卖家取消或修改拍卖。
- 不改变拍卖成交/流拍结算逻辑本身（`settle()` 现有流程）。
- 不引入定时任务/cron（延续惰性结算机制，无运维负担）。

## 成功标准

1. `pending` 且 `start_time <= NOW()` 的拍卖在任意页面访问时自动变为 `active`。
2. 历史上已过期的僵尸 `pending` 拍卖在下次页面访问时被自动结算（无出价 → `ended`）。
3. 卖家在「我的拍卖」可对 `pending`（未开始）拍卖执行「取消」与「编辑」。
4. 卖家可对「竞拍中且无人出价」的拍卖执行「取消」。
5. 「竞拍中且有人出价」的拍卖禁止卖家取消/编辑，并给出明确提示。
6. 取消后的拍卖状态为 `canceled`，物品立即解除互斥，可重新拍卖/挂牌。
7. 编辑仅限 `pending` 状态，保存后保持 `pending`（若新开始时间已过则自动 `active`）。
8. 所有卖家操作均有归属校验（仅 `seller_id` 本人），并带 CSRF 防护。

## 影响范围

- `classes/Auction.php`：新增 `tick()` / `activateStarted()` / `cancelAuction()` / `updateAuction()`。
- `bid/index.php` / `bid/view.php` / `bid/my.php`：`settleExpired()` 调用升级为 `tick()`。
- `bid/my.php`：列表行内新增「取消 / 编辑」操作（按状态授权）。
- `bid/create.php`：支持 `?edit=ID` 复用表单编辑（物品锁定，其余字段可改）。
- `bid/view.php`：卖家视角展示管理入口（跳转取消/编辑）。
- 数据表 `auctions` 无需变更（`canceled` 状态已存在）。
