## 1. NFT 类：解析与竞态安全认领（classes/NFT.php）

- [x] 1.1 新增 `parseNftCodes(string $input): array`：正则 `[A-Za-z]{2}\d{2}` 提取、大写归一化、去重保序、截断 50 并返回被忽略数量
- [x] 1.2 新增 `claimNftSafe(int $nftId, int $userId, int $cityId, string $blockId): array`：事务 + `SELECT ... FOR UPDATE` 行锁 + 占用复查，复用区块自动创建链路，返回 ok/跳过/失败
- [ ] 1.3 EXPLAIN 验证行锁走 `nft_city_user` 现有 `nft_id`/`city_id` 索引
- [x] 1.4 `claim_detail.php` 单认领 POST 改用 `claimNftSafe()`（行为不变，仅加防护）

## 2. 批量 service（classes/NFT.php）

- [x] 2.1 新增 `batchClaimPreview(int $userId, int $cityId, array $codes): array`：逐条状态（可认领/已被认领含占用者与区块/不存在/歧义），只读不写
- [x] 2.2 新增 `batchClaim(int $userId, int $cityId, string $blockId, array $codes): array`：先确保区块存在一次，再逐条独立事务调用 `claimNftSafe()`，返回逐条明细 + 汇总（成功/跳过/失败计数）
- [x] 2.3 每日总量防刷：按 `nft_city_user` 当日 `created_at` 计数，≥2000 拒绝执行并提示次日再试（预览不受限）

## 3. API（nft/api/）

- [x] 3.1 新增 `nft_batch_claim_preview.php`：POST codes+city_id，checkLogin，调 `batchClaimPreview`，输出 JSON
- [x] 3.2 新增 `nft_batch_claim_submit.php`：POST codes+city_id+block_id，checkLogin，防刷检查，调 `batchClaim`，输出 JSON
- [x] 3.3 两个 API 服务端二次解析/截断编号（不信任前端），非法参数返回明确错误

## 4. 批量认领页（nft/claim_batch.php）

- [x] 4.1 页面骨架：城市下拉（复用 getAllCities）、区块输入 + `get_user_blocks.php` datalist、编号粘贴 textarea、解析预览/确认认领按钮
- [x] 4.2 预览渲染：逐条 ✅可认领 / ⚠️已被认领（含占用者）/ ❌不存在 / ⏭重复，超上限提示
- [x] 4.3 提交与结果渲染：成功/跳过/失败明细 + 汇总，成功项提供跳转收藏页链接
- [x] 4.4 样式沿用 nft 站现有卡片风格，移动端可用

## 5. 入口与收尾

- [x] 5.1 `claim_list.php` 加"批量认领"入口按钮
- [x] 5.2 php -l / read_lints 全部改动文件通过
- [ ] 5.3 线上冒烟：粘贴 `ML24 使用 出售 FX24...` 场景预览 → 执行 → 收藏页可见；并发双人认领同编号仅一人成功
