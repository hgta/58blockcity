# NFT 售卖购买流程 — 实施任务

## 任务列表

### 任务 1：getSaleList() 新增 transaction_id + seller_id

**文件**: `classes/NFT.php`

**内容**:
- [x] SELECT 新增 `t.id AS transaction_id, t.seller_id`

**依赖**: 无

**验收**: `getSaleList()` 返回数据包含 `transaction_id`, `seller_id`

---

### 任务 2：售卖卡片链接改为 buy.php

**文件**: `nft/nft/sale_list.php`

**内容**:
- [x] 卡片 `<a href>` 从 `/nft/view.php?id=<?= $s['nft_id'] ?>` 改为 `/nft/buy.php?tx=<?= $s['transaction_id'] ?>`

**依赖**: 任务 1

**验收**: 点击售卖卡片跳转 `buy.php?tx=123`

---

### 任务 3：重写 buy.php — 购买确认+处理

**文件**: `nft/nft/buy.php`

**内容**:
- [x] GET 阶段：根据 `tx` 查询 `nft_transactions` JOIN `nft_avatars` JOIN `users` JOIN `cities`，status='listed'
- [x] 未登录 → 跳 `/auth/login.php?redirect=...`
- [x] 已售/不存在 → 错误提示
- [x] 展示确认页面：圆形头像 + 编号 + 城市 + 卖家 + 价格
- [x] Ⓟ 人气值模式：显示余额、购买后余额
- [x] ¥ 人民币模式：显示售价，标注"提交意向"
- [x] CSS 样式：简洁卡片式布局，橙色品牌主题
- [x] POST 处理：
  - 登录校验、自买拦截、状态校验
  - Ⓟ 人气值：余额检查 → 事务扣款+转账+status=completed+转移owner → 跳 success
  - ¥ 人民币：status=pending, buyer_id → 跳 success

**依赖**: 任务 1

**验收**:
- 人气值余额充足时一键成交，ownership 转移
- 余额不足时提示错误
- 人民币提交后创建 pending 记录
- 自己不能买自己的
- 已售出不能重复购买

---

### 任务 4：新建 buy_success.php 结果页

**文件**: `nft/nft/buy_success.php`（新建）

**内容**:
- [x] 根据 `result` 参数区分 completed/pending
- [x] completed: 显示 ✅ 购买成功，展示 NFT 摘要，链接"查看我的收藏"
- [x] pending: 显示 ⏳ 意向已提交，链接"返回市场"
- [x] 简洁居中布局

**依赖**: 任务 3

**验收**: 两种结果正确展示

---

### 任务 5：验证

**内容**:
- [x] PHP lint 无报错
- [x] 售卖卡片链接正确
- [x] buy.php GET 正常展示
- [x] buy.php POST 正常处理

**依赖**: 任务 1-4

**验收**: 全流程可走通
