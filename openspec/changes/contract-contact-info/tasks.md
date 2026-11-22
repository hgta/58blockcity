# Tasks: contract-contact-info

## 1. 数据层

- [x] 1.1 新增 `init/migration-contract-contact.sql`：幂等新增 `auctions.seller_contact_phone` / `seller_contact_wechat` / `buyer_contact_phone` / `buyer_contact_wechat`（varchar 可空，写法对齐 `migrate-auction-contract.sql`）
- [x] 1.2 `Auction::updateContractContact($auctionId, $userId, $data)`：校验当事人身份（buyer/seller 由库中 `seller_id`/`current_bidder_id` 判定）、手机号格式（正则 `^[0-9+\-\s]{5,20}$`）、微信长度 1–64，按角色更新对应两列

## 2. 契约页

- [x] 2.1 `bid/user/contract.php`：POST 处理 `save_contact`（CSRF + 调 `updateContractContact`）
- [x] 2.2 新增「联系方式」卡片：对方电话/微信展示 + 复制按钮（`htmlspecialchars` + `addslashes` 双转义）；自己一侧 inline 编辑表单
- [x] 2.3 空值引导：自己未填 → 表单 + 分币种提示（人民币契约卖家侧强调收款方式）；对方未填 → 「对方未留联系方式」
- [x] 2.4 可见性：仅在 `ac_contract_stage()` 非 NULL 时渲染整张卡片（落槌前不渲染）
- [x] 2.5 文案与链接修复：「联系卖家」按 `$isSeller` 分支显示「联系买家」，href 改为 `https://www.58.tl/messages/?with=<对方userId>`（替换原 `/user/messages.php` 死链）

## 3. 通知文案

- [x] 3.1 落槌通知（买家/卖家两条）文案追加「可在契约页填写联系方式保持沟通」，不改变通知结构与类型

## 4. 验证与回归

- [x] 4.1 新增静态断言测试：`updateContractContact` 的角色判定/校验正则/更新列；contract.php 卡片渲染分支（当事人/非当事人/空值/落槌前）与私信链接拼接
- [x] 4.2 `php -l` 全量通过；既有测试回归（AuctionEditGradeTest / MallProductAuctionTest）
- [x] 4.3 部署清单确认：迁移 SQL 先行，`classes/Auction.php`、`bid/user/contract.php` 随后
