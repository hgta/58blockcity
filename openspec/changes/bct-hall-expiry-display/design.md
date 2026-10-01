# Design: bct-hall-expiry-display

## Context

挂单发布时按有效期选项解析出 `expires_at`（`BCTOrder::resolveExpiresAt`，默认 30 天，「长期」为 NULL）。过期链路现状：

```
发布 (expires_at = now+30d)
  │
  ├─ 大厅/个人中心加载 → 惰性 expireOverdueOrders(500)
  │    pending/processing 且无活跃 claim 且到期 → expired，退出列表 ✓
  │
  └─ 有活跃 claim（交易中）→ 保护跳过，永不过期
       └─ 展示盲区：expires_at 已过仍显示"交易中" ← 本次补齐
```

「已过期不显示」的需求由现有惰性过期 + 查询过滤（`status IN ('pending','processing')`）已满足，本次不重复造轮子。

## Goals / Non-Goals

**Goals**
- 有效期列传达「还能挂多久」，而不是「哪天过期」
- 交易中顺延的单有明确标识
- 大厅与个人中心口径一致

**Non-Goals**
- 不改变过期/释放状态机（claim-flow 变更刚定稿）
- 不加有效期筛选
- 不做倒计时实时刷新（秒级 JS 倒计时对 30 天粒度无意义）

## Decisions

### D1 剩余时长计算与展示分级（服务端 PHP 计算）

以 `expires_at - now` 差值计算，分级展示：

| 剩余 | 显示 | 样式 |
|---|---|---|
| > 7 天 | `剩 23 天` | 默认灰 |
| 3-7 天 | `剩 5 天` | 黄 |
| 1-3 天 | `剩 2 天` | 橙 |
| < 1 天 | `剩 6 小时` | 红 |
| `expires_at` 为 NULL | `长期` | 灰（现状保留） |
| 已到期（理论上已被惰性过期清掉，防御性兜底） | `已过期` | 红+删除线 |

- 天数按 `floor(diff / 86400)` 向下取整——「剩 1 天」意味着不足两天，最后一天降为按小时展示（`floor(diff/3600)` 小时，0 小时显示 `不足 1 小时`）
- 时间口径沿用 `expires_at` 写入的 PHP 时间，不用 SQL `NOW()`，与既有时区约定一致

### D2 交易中顺延的判定与展示

判定条件：行数据带 `claim_status`（活跃接单）**且** `expires_at` 非空且早于当前时间。显示 `交易中顺延`（灰），并加 `title` 提示"原有效期已过，交易完成后/释放后按过期规则处理"。

数据来源零成本：大厅查询已 LEFT JOIN `bct_order_claims`（claim-flow 变更引入），`claim_status` 字段现成。个人中心「我的挂单」行内已有 `getActiveClaim` 探测（交易中徽标用），复用同一结果。

**语义说明（不改变数据）**：顺延期间 `expires_at` 不改写；接单释放回 pending 后，下一次惰性过期即按原 `expires_at` 处理——所以「顺延」只是冻结，不是续期。`buyer_confirmed` 卡单永顺延属既定设计（防线下已打款被撕单）。

### D3 个人中心「我的挂单」补有效期列

现表无有效期列。在「状态」列后新增，口径与 D1/D2 完全一致。已完成/已取消/已过期的历史单不参与剩余计算（active tab 才有意义；completed tab 显示 `已结束`）。

### D4 输出抽公共函数

剩余时长格式化逻辑大厅与个人中心两处复用，在两页共用的位置放一个 `formatRemainingValidity($expiresAt, $inTrade)` 小函数（放 `bct/includes/` 新文件 `expiry-display.php`，返回 `[text, cssClass]`），避免两处复制分级阈值。

## Risks / Trade-offs

- **时区**：`expires_at` 由 PHP `date()` 写入，服务端渲染时同为 PHP 时间，口径自洽，无风险
- **顺延文案歧义**：用户可能误解为"挂单人续期了"。用固定文案「交易中顺延」+ title 说明缓解
- **不加筛选**：数据量增长后可能需要「即将到期」排序，届时再扩展 `sort` 参数即可，本次预留意识不做实现

## Migration Plan

无数据库变更、无新依赖，直接部署即可。

## Open Questions

无——分级阈值（7/3/1 天）如上线后觉得粒度不合适，改一个函数即可。
