## Context

见 `proposal.md` 的 Why。理解本设计需要以下现状约束：

- `bct_orders.expires_at datetime NULL COMMENT '过期时间'` 字段与 `idx_expires` 索引均已存在（`init/db-init.sql`），无需新增字段。
- `BCTOrder::createOrder($userId, $city, $type, $amount, $tradeType, $contactInfo, $userPrice, $durationDays = 0, $mediatorId = null)` 已含 `$durationDays` 参数并已实现写入逻辑：`$durationDays > 0` 时按天数计算，否则写入 `NULL`。但**全项目无任何调用方传入该参数**，因此现有订单 `expires_at` 恒为 `NULL`。
- **无任何代码读取 `expires_at`**：不存在过期判定或取消逻辑。
- `bct_orders.status` 当前为 `enum('pending','processing','completed','canceled')`，需扩展 `expired`。
- `amount` 字段语义是「剩余可成交数量」：撮合成交后原子递减，归零则置 `completed`（见 `updateOrderAfterTrade()`）。
- `classes/Auction.php` 已实现同类「惰性状态推进」：`tick()` → `activateStarted()` / `settleExpired()`，用户访问时顺带推进，无 cron 依赖。
- `bct/cron/auto_match.php` 已实现定时任务的 `flock` 防重入保护，可作为新 cron 的范式。
- **时区前车之鉴**：`Auction.php:33-39` 记录了一个真实问题——时间字段由 PHP 写入、状态推进却用 MySQL `NOW()` 比较，两者时区不一致时产生状态错位，其解法是显式 `SET time_zone = date('P')`。

## Goals / Non-Goals

**Goals:**

- 让用户能表达挂单的有效期意图，默认 30 天。
- 超期挂单自动退出待撮合集合，不再污染行情。
- 过期执行既及时（不依赖用户访问）又不遗漏（有兜底途径）。
- 区分「用户主动取消」与「系统过期取消」。
- 避免重蹈 `Auction.php` 的时区口径不一致问题。

**Non-Goals:**

- 不做"临期提醒"（邮件/站内信通知用户即将过期）。
- 不做"续期 / 延期"功能（过期后重新发布即可）。
- 不引入新的数据表；复用 `expires_at` 与 `status`。
- 不改变撮合逻辑对价格与数量的既有判定规则。
- 不对存量 `expires_at IS NULL` 的历史订单做自动过期（见 Risks）。

## Decisions

### 1. 有效期选项用枚举映射到过期时间，而非各填天数

前端提供固定 6 个选项，后端维护「选项 → 过期时间」的映射，而非让用户自由输入天数。

- **理由**：选项集中在一处便于统一语义（尤其"3个月"与"长期"这两种特殊取值），也避免前端传任意天数绕过校验。
- **备选**：用户自由填 1-365 天。实现更灵活，但"3个月"与"长期"无法自然表达，且需要考虑上下限校验。
- **取舍**：牺牲灵活性换取语义明确。若将来需要自定义天数，可在映射基础上扩展。

### 2.「3个月」按自然月，不按 90 天

使用 `strtotime('+3 months')` 而非 `+90 days`。

- **理由**：用户说"3个月"的语义是日历上的 3 个月，`+3 months` 才是正确解读。90 天在跨不同长度的月份时会产生数天偏差。
- **备选**：固定 `+90 days`。实现更简单且长度恒定，但语义不准确。
- **一致性**：PHP 的 `strtotime('+N months')` 对月末有溢出处理（如 1月31日 +1月 → 3月3日），这是 PHP 既有行为，接受而不额外修补。

### 3.「长期」用 `expires_at = NULL` 表示

- **理由**：`createOrder()` 现有实现已在 `$durationDays <= 0` 时写入 `NULL`；`classes/Task.php:136` 也是以 `empty($expire_at)` 判断不过期。沿用同一约定可保持代码库语义统一。
- **备选**：写入一个极大值（如 2099 年）。查询更统一（无需 `IS NULL` 分支），但语义上误导，且"长期"订单会被索引当作普通远期订单处理。
- **代价**：所有过期判定查询都需写成 `expires_at IS NOT NULL AND expires_at < ?`，多一个条件。

### 4. 过期判定统一用 PHP 时间，不在 SQL 里用 `NOW()`

过期判定条件由 PHP 传入时间参数（`date('Y-m-d H:i:s')`），SQL 里用 `expires_at < ?`。

- **理由**：`expires_at` 由 PHP 的 `date()` 写入，若用 MySQL `NOW()` 比较就会复现 `Auction.php` 已记录过的时区错位问题。让写入与比较都在 PHP 时区下进行，从根上消除该风险。
- **备选**：在连接初始化时 `SET time_zone = date('P')` 对齐，然后放心用 `NOW()`。这在 `Auction.php` 中已被采用且有效，但要求所有入口（页面、cron、API）都执行该语句，遗漏一处就会出现不一致。
- **取舍**：传参方式牺牲一点 SQL 简洁性，换取不依赖"每个入口都记得设置时区"这一隐式约定。

### 5. 惰性推进 + 定时兜底，两者都做

借鉴 `Auction::tick()` 的惰性模式，同时新增 cron 任务。

- **理由**：单一惰性在"用户不访问就不推进"上有盲区，会让订单在页面上显示为待撮合却实际已过期；单一 cron 则依赖运维正确配置，漏跑就完全失效。两者互补：惰性保证用户看到的始终正确，cron 保证长期无人访问的订单也被清理。
- **备选**：仅惰性（零运维，但状态陈旧）、仅 cron（及时，但需运维）。
- **幂等性要求**：两条路径可能同时触发，因此过期处理必须**只更新符合条件且状态仍为 `pending`/`processing` 的行**，自然幂等。

### 6. 过期覆盖 `pending` 与 `processing`，不碰 `completed` / `canceled`

- **理由**：`processing` 表示已部分成交、仍有剩余量在挂。若只处理 `pending`，这类订单的剩余数量会永久挂起，与"不能长期挂着"的诉求直接冲突。
- **安全边界**：`completed`（已全部成交）必须排除，否则会把成交记录标记为过期，破坏账目语义；`canceled` 排除以保持用户意图。
- **实现要点**：用 `status IN ('pending','processing')` 而非 `status <> 'completed'`，显式列出而非排除，避免将来新增状态时被意外纳入。

### 7. 新增 `expired` 状态而非复用 `canceled`

- **理由**：用户需要在订单列表区分"我主动取消的"与"系统过期的"。复用 `canceled` 会让用户看到"已取消"却想不起自己取消过，尤其过期是系统行为、用户无感知。同时后台统计需要把两者分开计数。
- **备选**：复用 `canceled` 并加一个 `cancel_reason` 字段。改动小，但需要额外的字段与展示逻辑，且两个枚举值的语义分离更直观。
- **代价**：需 `ALTER TABLE` 扩展 enum，并同步更新用户页、后台页的状态映射与筛选列表。

### 8. 批量模式整批共用有效期

- **理由**：与 `bct/batch-order-publish` 中方向、交易方式"整批统一"的模型一致。批量行数多，逐行选有效期会显著增加输入负担且收益很低。
- **实现**：批量提交时把有效期随方向、交易方式一起传入，由后端对每一行应用同一时长。

## Risks / Trade-offs

- [Risk] 存量订单 `expires_at` 全为 `NULL`，上线后不会被过期，问题依旧存在 → Mitigation: 明确不在本次范围内自动处理存量；如需清理存量，应另行制定一次性策略（例如为超过 N 天未成交的订单补设过期时间），并在实施前与业务确认。
- [Risk] `status` enum 变更在数据量大时可能锁表 → Mitigation: 在低峰期执行；若表数据量大，先评估行数再决定是否用 `ALGORITHM=INPLACE`。
- [Risk] 惰性推进在页面请求中执行写操作，可能拖慢订单列表响应 → Mitigation: 惰性部分限定作用域（用户订单页只处理该用户的订单），并对单次处理数量设上限；全站范围的清理交给 cron。
- [Risk] 惰性与 cron 并发触发同一批订单 → Mitigation: 处理语句自带 `status IN ('pending','processing')` 条件，重复执行结果一致；cron 另有 `flock` 防重入。
- [Risk] 用户选择「1天」后可能对"为何订单消失了"感到困惑 → Mitigation: 在有效期选项附近以文字说明"到期后订单将自动取消"；订单列表提供「已过期」筛选让用户能查到。
- [Risk] `+3 months` 在月末溢出（1月31日 → 3月3日）可能不符用户直觉 → Mitigation: 接受 PHP 既有行为；选项文案统一写"3个月"，不承诺精确到某日。
- [Risk] 过期后剩余数量作废，若用户认为其仍持有相应 BCT 会产生疑问 → Mitigation: 明确挂单只是意向表达、发布时不冻结余额（与现有"发布不校验余额"的设计一致），因此过期作废不涉及资产变动。
