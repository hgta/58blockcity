## Context

- 现有单认领流程位于 `nft/nft/claim_detail.php`：选城市 → 前端 AJAX 查占用（`/api/nft_claim_status.php`）→ 输入区块 → POST 写 `nft_city_user`。区块不存在时自动创建（`Block::determineZoneByBlockId` / `calculateBasePrice` / `createBlock`）。
- 数据模型：`nft_city_user(nft_id, city_id, user_id, block_id, is_current)`，占用判定 = 同 nft+city 存在 `is_current=1` 行；`nft_avatars.code` 为编号（如 `ML24`）。
- **已知竞态**：`NFT::claimNft()` 只查"同用户已认领"，不做服务端占用检查（占用检查仅在前端 AJAX），并发认领同一 NFT+城市可能产生两行 `is_current=1`。
- 用户场景：从其他平台把持有的编号抄过来（混乱文本 `ML24 使用 出售 FX24 ...`），希望一次性批量认领到同一城市+区块。
- 无本地数据库可用（`config/database.php` 不入仓），所有数据校验依赖线上。

## Goals / Non-Goals

**Goals:**
- 一次批量认领到统一的城市+区块，最大 50 个编号/批
- 解析、dry-run 预校验、正式执行三段共用同一 service，预览即所得
- 修复认领竞态（服务端占用检查 + 行锁），单认领入口一并受益
- 防刷：登录必选、单批上限、每日总量上限
- 结果明细报告（成功/跳过/失败逐条列出）

**Non-Goals:**
- 截图 OCR 识别（二期，云 OCR 方案；本 change 仅保证解析层与输入源解耦）
- 逐编号指定不同城市/区块（交互复杂度不值得）
- 新增数据库表/索引变更（防刷计数先不做独立表）

## Decisions

### D1. 城市+区块统一，不逐个指定
批量 = 一个城市 + 一个区块 + 一批编号。复用现有组件（城市下拉、`get_user_blocks.php` datalist、区块自动创建链路）。替代方案"每编号独立配置城市/区块"交互复杂度爆炸，与"把一个区块挂满头像"的真实诉求不符。

### D2. 编号解析用服务端正则，噪音词自然过滤
`/[A-Za-z]{2}\d{2}/` 全局匹配 → 大写归一化 → 去重保序 → 编码查 `nft_avatars.code`。"使用/出售/在用"等按钮文字与编号格式不匹配，天然被过滤。解析放服务端（`NFT::parseNftCodes()`）而非前端，保证 dry-run 与执行口径一致、前端伪造参数无效。
- 兼容残留风险：编号格式未来若扩展（如 3 位数字 `ML240`），正则需同步调整 → 正则单独封装为方法并加注释。

### D3. dry-run 与执行共用 service，逐条独立事务
`batchClaimPreview()`（只读校验）与 `batchClaim()`（写库）共享逐条校验逻辑。执行时每条编号独立事务：

```sql
BEGIN;
  SELECT id FROM nft_city_user
   WHERE nft_id=? AND city_id=? AND is_current=1
   FOR UPDATE;            -- 行锁防并发
  -- 仍空闲 → 写认领；被占 → 标记跳过
COMMIT;
```

替代方案"一个大事务全成全败"被否：8 个编号里 1 个被占时用户期望其余 7 个照常成功。行锁依赖现有索引（`nft_id`/`city_id` 已有 KEY），无需 DDL。

### D4. 竞态修复抽为 `claimNftSafe()`
新增方法封装 事务+行锁+占用复查，`claim_detail.php` 单认领 POST 改用它；`claimNft()` 保留原实现供兼容（或内部复用）。修复无需 DDL：MySQL 无 partial unique，用锁而非唯一约束。

### D5. 防刷：三层限制
1. `checkLogin()` 必选（复用现有 auth）
2. 单批 `<= 50` 个编号（服务端截断，超出部分标记忽略）
3. 同用户每日批量认领编号总数 `<= 2000`：一期用轻量方案——按 `nft_city_user` 当日 `created_at` 该用户批量插入计数（无需新表），达到上限拒绝并提示次日再试
   - 权衡：用 `nft_city_user` 当日计数无法区分"批量/单个"来源，但作为粗粒度防刷足够；若误伤再引入专用计数表（留待二期）

### D6. 前端两步式交互（无框架，延续现有 jQuery 风格）
粘贴 → 「解析预览」AJAX 调 preview API 渲染状态清单（✅/⚠️/❌/⏭ 图标+说明）→ 「确认认领」提交编号数组+城市+区块 → 渲染结果明细。与 `claim_detail.php` 现有 AJAX 风格一致，不引入新依赖。

### D7. 二期 OCR 预留
输入层（文本框 + 图片上传按钮位）与解析层解耦：解析入口只接受字符串。二期接入云 OCR（百度/腾讯）后，服务端把图片 OCR 文本喂给同一 `parseNftCodes()`，前端无需改动流程。

## Risks / Trade-offs

- [dry-run 与执行之间状态漂移（他人抢先认领）] → 执行时逐条二次校验 + 行锁，漂移项自动降级为"跳过"，绝不覆盖他人认领
- [FOR UPDATE 行锁在无合适索引时退化为表锁] → `nft_city_user` 已有 `nft_id`/`city_id` 索引；实现时 EXPLAIN 验证
- [`nft_avatars.code` 无唯一约束/大小写不一致导致查不到] → 查询统一 `UPPER(code) = :code`；若线上存在重复 code，预览会逐条列出候选，执行时跳过歧义编号
- [每日上限用 nft_city_user 计数会误伤高频单认领用户] → 阈值 2000 足够宽松；观察线上后再调
- [批量并发触发竞态外的其他副作用（如区块自动创建并发重复）] → `createBlock` 已有按 city+number 查重的调用链；批量执行前先确保区块存在一次，避免逐条重复创建

## Migration Plan

- 纯新增文件 + `NFT.php` 追加方法 + `claim_detail.php` 改调用 `claimNftSafe`，无 DDL、无配置变更
- 部署：git pull 即生效；回滚：revert 提交即可
- 上线后观察点：批量接口耗时（50 条 × 行锁事务）、每日上限触发情况

## Open Questions

- OCR 二期选型（百度 vs 腾讯）与费用额度，待接入时再定
- 是否需要把批量认领行为写入"认领播报"（`getRecentClaims` 已有的展示位），默认沿用现有逻辑无需改动
