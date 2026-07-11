# 重新设计售卖页购买流程

## 背景

上个 change (`redesign-sale-list`) 重写了售卖页布局，但卡片点击跳转到 `view.php`（NFT 详情页），而 `view.php` 没有"立即购买"按钮——它只有认领、出售、求购。同时项目中存在一个 `buy.php` 但它依赖旧的 `nfts` 表结构，完全不可用。**listed → completed 的购买流转完全不存在**。

## 问题

1. 售卖卡片点进去的 `view.php` 没有"立即购买"入口，购买链路断裂
2. `buy.php` 存在但查询的是旧 `nfts` 表（`owner_id`, `is_for_sale`），与当前 `nft_transactions` 数据模型不兼容
3. `getSaleList()` 不返回 `transaction_id` 和 `seller_id`，无法定位具体的挂售记录
4. 没有购买完成的处理逻辑：`nft_transactions` 中 `status='listed'` 的记录无法流转为 `completed`

## 目标

重新设计完整的购买流程，打通"浏览售卖列表 → 确认购买 → 交易处理 → 成功反馈"：

- 售卖卡片点击跳转独立购买确认页 `buy.php`
- `buy.php` 全新重写：展示 NFT 信息+价格+余额，支持人气值Ⓟ自动成交和人民币¥提交意向两种模式
- Ⓟ 人气值交易：自动转账+更新 ownership+完成
- ¥ 人民币交易：创建 pending 记录，等待卖家确认
- 新建购买成功页 `buy_success.php`

## 范围

### 本次包含

- `getSaleList()` SQL 新增 `transaction_id` 和 `seller_id` 字段
- `sale_list.php` 卡片链接改为 `buy.php?tx=`
- `buy.php` 基于 `nft_transactions` 模型完全重写
- 新增 `buy_success.php` 购买结果页
- 购买防呆：登录检查、自买拦截、余额校验、已售拦截

### 本次不包含

- 卖家确认 ¥ 交易的界面
- 支付网关接入
- 交易通知/消息推送
- 退款/取消交易

## 成功指标

- 售卖页卡片点击正确跳转 `buy.php?tx=xxx`
- 人气值购买：余额充足时一键成交，NFT 所有权转移
- 人民币购买：提交后创建 pending 记录
- 已售/余额不足/自买等异常场景正确拦截
