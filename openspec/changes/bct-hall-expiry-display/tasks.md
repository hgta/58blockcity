# Tasks: bct-hall-expiry-display

## 1. 公共格式化

- [x] 1.1 新建 `bct/includes/expiry-display.php`：`formatRemainingValidity($expiresAt, $inTrade)` 返回 `[text, cssClass]`，按 D1 分级（>7d 灰 / 3-7d 黄 / 1-3d 橙 / <1d 红按小时 / NULL=长期 / ≤0=已过期 / inTrade+过期=交易中顺延）

## 2. 挂单大厅

- [x] 2.1 `bct/orders.php` 有效期列改调 `formatRemainingValidity($o['expires_at'], !empty($o['claim_status']))`，交易中顺延加 `title` 悬停说明
- [x] 2.2 表头「有效期」改名「剩余有效期」，并补充分级样式 CSS（与现有 hall 表格风格一致）
- [x] 2.3 验证过期挂单不出现在列表（现行为回归，无代码改动预期）

## 3. 个人中心

- [x] 3.1 `bct/user/dashboard.php` 我的挂单表新增「有效期」列：active tab 调 `formatRemainingValidity`（复用行内已有的 `getActiveClaim` 探测结果传 inTrade）；completed tab 显示「已结束」
- [x] 3.2 同步补充表格 CSS（与大厅同口径样式）

## 4. 验证

- [x] 4.1 分级文案与样式核对：`tests/expiry-display-sample.php` 抽样脚本覆盖全部 8 分支（29天/5天/2天/10小时/30分钟/长期/已过期/交易中顺延），输出符合 spec
- [ ] 4.2 手测交易中顺延：线上接单一笔到期单，大厅显示「交易中顺延」；释放后下次加载退出列表（逻辑分支已由抽样脚本覆盖，页面链路待部署验证）
- [ ] 4.3 手测个人中心：同口径核对 buy/sell/completed 三个 tab（待部署验证）

> 4.2/4.3 为运行时页面手测，部署后到 https://bct.58.tl/orders.php 与个人中心核对即可。无数据库迁移，代码可直接部署。
