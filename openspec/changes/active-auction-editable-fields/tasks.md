## 1. 服务端编辑分级（classes/Auction.php）

- [x] 1.1 `updateAuction()` 重构为状态分叉：pending 分支保持现有行为不变（全字段 + `current_price=起拍价` + 状态重算）；读取记录后按 `$a['status']` 分派
- [x] 1.2 新增 active 编辑分支：仅接受 description / accept_cities / end_time / reserve_price 四字段，独立 UPDATE 语句不触碰 `current_price` / `status` / `start_price` / `bid_increment` / `currency`
- [x] 1.3 实现方向校验（以库中现值为基准）：accept_cities 新集合 ⊇ 旧集合（旧值为空=不限城市时放行合法集合）、end_time 新值 ≥ 现值、reserve_price 新值 ≤ 现值且 ≥ 起拍价；违规返回与约束一一对应的错误文案
- [x] 1.4 sold/ended/canceled 状态返回「拍卖已结束」类拒绝文案（替代现在的「仅未开始可编辑」）；归属与商品可拍性校验保持

## 2. 编辑表单状态感知（bid/create.php）

- [x] 2.1 编辑模式读取拍卖 status：sold/ended/canceled 重定向回 my.php；pending 维持现状全字段可改；active 进入锁定渲染模式
- [x] 2.2 active 模式渲染：description / accept_cities / end_time / reserve_price 可交互；start_price、bid_increment、currency、start_time、物品选择控件 disabled + 行内灰字「竞拍中不可修改」
- [x] 2.3 active 模式的 POST 提交复用 `updateAuction()`（服务端已分叉），成功提示区分「修改已保存」与 active 场景文案

## 3. 编辑入口放开（bid/my.php）

- [x] 3.1 操作区按钮：`pending || active` 显示「编辑拍卖」（pending 且描述为空保留「写描述」引导文案），active 不再隐藏入口
- [x] 3.2 按钮可点击性核对：active 拍卖从 my.php 进入 `create.php?edit=X` 能正确进入锁定渲染模式

## 4. 验证与回归

- [x] 4.1 新增/更新 tests/ 下的静态断言测试：updateAuction 含状态分叉与方向校验关键字样；active 分支 UPDATE 列清单不含 current_price/status/start_price/bid_increment/currency
- [x] 4.2 功能用例核对：active 改描述成功、城市只增成功/收紧拒绝、延长时间成功/缩短拒绝、底价下调成功/上调拒绝、提交锁定字段被拒、sold 编辑被拒
- [x] 4.3 回归核对：pending 编辑行为与改动前一致（全字段、current_price 重置、到点开拍）；active 编辑后轮询接口 current_price/bid_count 不变、倒计时随新 end_time 刷新；契约期（sold）编辑被拒
