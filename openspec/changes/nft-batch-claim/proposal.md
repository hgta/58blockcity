## Why

用户在微信小程序等其他平台持有大量 NFT 头像编号（如 ML24、FX24），想在本平台快速认领到自己的区块下。当前 `claim_detail.php` 只支持单个认领：每次都要选城市、查状态、输区块、提交，8 个编号要点 8 次，体验繁琐。用户希望直接粘贴一段编号文本（甚至截图）批量完成认领。

## What Changes

- 新增批量认领页 `nft/claim_batch.php`：统一选择一个城市 + 一个区块，粘贴含编号的文本（如 `ML24 使用 出售 FX24 使用 出售...`），正则提取编号（`[A-Za-z]{2}\d{2}`，忽略大小写、去重保序），批量预校验并认领
- 新增解析/预校验/执行三段式流程：
  - dry-run 预览 API（不落库）：逐条返回 可认领 / 已被他人认领 / 编号不存在 / 重复输入
  - 用户确认后正式执行：逐条独立事务，返回 N 成功 / M 跳过 / K 失败明细
- 新增 `NFT::claimNftSafe()`（事务 + `SELECT ... FOR UPDATE` 行锁 + 服务端占用复查），修复现有认领竞态（两人同时认领同一 NFT+城市可能插出两行 `is_current=1`）；单个认领入口同步受益
- 防刷：登录必选、单批 ≤ 50 个编号、同用户每日批量认领编号总量上限（2000）
- 图片 OCR 识别（截图提取编号）列为二期，不在本 change 范围；但解析层与输入层解耦，二期接入时无需重写

## Capabilities

### New Capabilities
- `nft-batch-claim`: 批量认领的输入解析、预校验（dry-run）、批量执行、结果明细报告与防刷限制

### Modified Capabilities
<!-- 现有 spec 中无 nft 认领相关 capability（openspec/specs 仅 bct/city-data-sync/popularity），竞态修复作为 nft-batch-claim capability 的一部分描述 -->

## Impact

- **新增**：`nft/claim_batch.php`（批量认领页）、`nft/api/nft_batch_claim_preview.php`（dry-run）、`nft/api/nft_batch_claim_submit.php`（执行）
- **修改**：`classes/NFT.php`（新增 `parseNftCodes()`、`batchClaimPreview()`、`batchClaim()`、`claimNftSafe()`；`claim_detail.php` 的单认领入口改用 `claimNftSafe`）、`nft/nft/claim_list.php`（批量认领入口按钮）
- **数据库**：零结构变更（编号查 `nft_avatars.code`，占用查 `nft_city_user`，现有索引足够；防刷计数先落 error_log + 每请求上限，后续再议独立计数表）
- **依赖**：无新增外部依赖（二期 OCR 才引入云服务 SDK）
