# Proposal: contract-contact-info

## Why

拍卖落槌后，买卖双方进入契约期需要直接联系：人民币契约买家要向卖家要收款方式（微信/支付宝），人气值契约买家要卖家的人气值账号，商品契约卖家还需要买家的收货信息。但现状是：

1. **契约页没有任何联系方式**。`contract.php` 只展示双方用户名，`users.phone` 字段存在却从未在契约场景暴露。
2. **「联系卖家」按钮是死胡同**。契约页（`bid/user/contract.php` 238 行）跳转 `/user/messages.php`——那是通知中心，只能看系统通知、发不出消息；且文案对买卖双方都写死「联系卖家」，卖家视角应是「联系买家」。
3. **站内私信系统已存在但未接线**。`/messages/?with=<userId>` 支持双向对话（`assets/shared/messages-core.php` + `Message` 类），bid 站入口缺失。
4. **收款/发货全靠私下猜**。付款指引写着「请直接联系卖家协商转账方式」，但卖家没地方留转账方式——环路的起点就是断的。

站内先例：block 出售市场已在 `block_listings` 上实现 `contact_phone` / `contact_wechat`（发布时卖家自填、买家页明文展示），模式已被验证。

## What Changes

- `auctions` 表新增 4 列契约联系方式：`seller_contact_phone` / `seller_contact_wechat` / `buyer_contact_phone` / `buyer_contact_wechat`
- 契约页新增「联系方式」卡片：对方联系方式展示（明文 + 复制按钮），自己的联系方式 inline 编辑保存
- 联系方式**仅契约当事人可见**（复用现有 `isBuyer/isSeller` 服务端守卫），落槌前不暴露
- 「联系卖家」文案按视角修复为「联系买家 / 联系卖家」，链接改为私信直连 `/messages/?with=<对方ID>`
- 联系方式未填写时显示引导提示（卖家侧强调：不填买家无法付款）
- 服务端校验：手机号格式（可空）、微信长度上限（可空）、输出转义

## Impact

- **Affected specs**: 新增 capability `auction-contract-communication`
- **Affected code**:
  - `init/migration-contract-contact.sql`（新增迁移）
  - `classes/Auction.php`（新增 `updateContractContact()`；`getContract` 带出新字段）
  - `bid/user/contract.php`（联系方式卡片 + inline 表单 + 文案/链接修复）
- **Explicitly out of scope**: 商品契约收货信息（姓名/电话/地址采集）、商城订单状态回写、卖家主动确认收款——均为后续 change
