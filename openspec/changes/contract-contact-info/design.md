# Design: contract-contact-info

## Context

契约页是买卖双方在落槌后的唯一协作入口。现状双方只能看到对方用户名；联系方式缺失导致「成交后无法联系」的硬断点。block 出售市场已有 `contact_phone/contact_wechat` 的成熟先例（`classes/BlockListing.php`、`block/block/buy.php` 展示侧），本设计直接沿用其字段模型，把可见性从「全站公开」收紧为「仅契约当事人」。

## Goals / Non-Goals

**Goals**
- 成交后双方在契约页互见联系方式（电话/微信），支持复制与私信直连
- 双方均可随时补充/修改自己的联系方式（inline，不跳页）
- 未填写时有明确引导，卖家侧强引导（付款依赖卖家联系方式）

**Non-Goals**
- 不做收货地址/收货人采集（商品契约发货信息，后续 change）
- 不做联系方式脱敏/遮掩（双方是交易对手，block 市场先例为明文，此语境明文合理）
- 不做站内信系统改造（`/messages/?with=` 已可用，仅接线）

## Decisions

### D1 — 字段放 `auctions` 表，结构化 4 列（用户已确认方案 A）

`seller_contact_phone` / `seller_contact_wechat` / `buyer_contact_phone` / `buyer_contact_wechat`，全部 `varchar` 可空。

- 放 `auctions` 而非 `users`：契约语义（这单交易的联系方式）+ 最小可见面（只有对手方）。放 `users` 则暴露面是全站资料页。
- 结构化而非自由文本：可做格式校验、展示端「电话/微信」标签语义清晰、与 block 先例一致。
- 手机号存原样字符串（前端 `type="tel"` 引导，服务端宽松校验：仅允许数字/`+`/`-`/空格，长度 5–20）。微信不做格式校验（微信号规则复杂，宽松为上），仅限长度 1–64 与可见字符。

### D2 — 可见性：落槌 + 当事人双条件

- 展示时机 = 契约成立（`status='sold'` 且 `payment_status` 非 NULL，即 `ac_contract_stage()` 非 NULL 的同一判定）。落槌前字段即使已填也不渲染。
- 服务端守卫：`contract.php` 现有 `isBuyer/isSeller` 重定向已保证非当事人进不来，无需新增接口层校验；但 `updateContractContact()` 内部仍需校验「操作者是 buyer 或 seller 且契约存在」，防止未来新增调用点绕过。

### D3 — 编辑入口：契约页 inline 表单，各改各的

一方一个表单，只提交自己的两列（`contact_role` 由服务端从 session 判定，不接受前端传入）。更新后对方下次打开契约页即见。不推送通知（避免打扰；对方打开契约页自然看到）。

### D4 — 私信直连接线

「联系买家/联系卖家」按钮 href 改为 `https://www.58.tl/messages/?with=<对方userId>`（私信站为 www 域，与 bid 站同域 cookie，`includes/session.php` 统一会话）。用户名旁不再需要额外入口——按钮就在联系方式卡片里，卡片本身就是「联系对方」的聚合区。

### D5 — 空值引导分层

- 自己未填：卡片显示编辑表单 + 提示「填写后对方才能联系你」（卖家侧在人民币契约下追加「买家需要你的收款方式才能付款」）。
- 对方未填：显示「对方未留联系方式」，私信按钮始终可用（兜底路径永远存在）。

### D6 — 输出安全

电话/微信输出一律 `htmlspecialchars`；复制按钮走 `addslashes` 转义后的 data 属性（沿用商城 `copyOrderText` 的既有模式）。

## Risks / Trade-offs

- **「复制」按钮的 XSS 面**：属性注入走 `addslashes` + `htmlspecialchars` 双转义，与商城一致。
- **卖家长期不填**：付款指引区文案在联系方式为空时替换为「等待卖家提供联系方式」，配合现有落槌通知文案追加一句提醒（不改通知结构，只改文本）。
- **迁移风险低**：4 列均可空 NULL，无默认值回填，存量契约不受影响。

## Migration Plan

`init/migration-contract-contact.sql`：幂等 `ALTER TABLE ... ADD COLUMN`（信息模式判断，与 `migrate-auction-contract.sql` 同款写法）。部署时先跑迁移再发代码。

## Open Questions

- 落槌瞬间的系统通知是否追加「请双方填写联系方式」——倾向追加，待实施时定文案。
