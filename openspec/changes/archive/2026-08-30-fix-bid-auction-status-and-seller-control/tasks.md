# 任务：修复拍卖状态机断链 + 卖家操作能力

## Phase 1：Auction 类
- [x] `activateStarted()`：`pending → active`（`start_time <= NOW()`）批量更新。
- [x] `tick()`：先 `activateStarted()` 再 `settleExpired()`。
- [x] `cancelAuction($auctionId, $sellerId)`：归属校验 + 状态授权（pending 可撤、active 无人出价可撤、有人出价拒绝）+ 条件更新防并发。
- [x] `updateAuction($auctionId, $sellerId, $data)`：仅 pending 可改价格/时间/货币/接受城市（物品锁定），复用创建校验，保存后重算状态。

## Phase 2：入口调用点
- [x] `bid/index.php`：`settleExpired()` → `tick()`。
- [x] `bid/view.php`：`settleExpired()` → `tick()`。
- [x] `bid/my.php`：`settleExpired()` → `tick()`。

## Phase 3：卖家取消（my.php + view.php）
- [x] `bid/my.php` 我发布的列表：按授权矩阵渲染「取消」按钮 + JS 确认弹窗，POST 处理调 `cancelAuction()`，成功/失败 flash 提示。
- [x] `bid/view.php` 卖家视角：pending / 无出价 active 显示「取消」；有人出价 active 显示「联系管理员」提示。

## Phase 4：卖家编辑（create.php 复用）
- [x] `bid/create.php` 支持 `?edit=<id>`：归属 + pending 校验，表单预填，物品选择区隐藏（显示不可更换说明），提交走 `updateAuction()`。
- [x] 编辑保存后状态重算（到点时间即直接 `active`），表单校验规则与创建一致。

## Phase 5：验证（部署后手动）
- [ ] 无出价 pending 拍卖：取消 → `canceled`，物品可重新发起拍卖（互斥解除）。
- [ ] 无出价 active 拍卖：可取消 → `canceled`。
- [ ] 有人出价的 active 拍卖：取消被拒并提示。
- [ ] 已结算（sold/ended）拍卖：无操作入口。
- [ ] 僵尸 pending（已过 end_time）：访问任意页后自动变为 `ended`（tick 激活→结算）。
- [ ] 到点 pending 拍卖：访问任意页后自动变为 `active`，可正常出价。
- [ ] 编辑仅限 pending；编辑起止时间/价格后详情页数据正确。
- [ ] 非卖家访问他人拍卖：无任何管理按钮，POST 直接拒绝。
