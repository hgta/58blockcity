## Context

- `hufang/circles/create.php` 当前将 `block_count` 作为普通数字输入框，由用户手动填写。
- `classes/Block.php` 已有 `getUserBlocksByCity($userId, $cityId)` 等方法，但缺少一个专门用于计数的轻量方法。
- `blocks` 表的 `owner_id`、`city_id`、`status` 字段已能准确表达"某用户在某城市已认领的区块"。
- 合并块 `merged_blocks` 在认领时已将组内每个单块写入 `blocks` 表并标记为 `sold`，因此直接统计 `blocks` 表即可，无需额外累加 `merged_blocks`。

参见 `proposal.md` 了解本次变更的动机。

## Goals / Non-Goals

**Goals:**
- 用户选择城市后，"拥有区块总数"自动、准确地回填。
- 创建流程对前端异常有后端兜底，保证功能可用。
- 不引入新的数据库表或字段，复用现有 `blocks` 表。

**Non-Goals:**
- 不改变互访圈其它字段或创建后业务逻辑。
- 不处理 block 子站数据缺失或历史数据异常（仅按当前 `blocks` 表状态统计）。
- 不修改现有圈子历史数据的 `block_count`。

## Decisions

### 1. 统计口径：仅统计 `blocks` 表中 `status='sold'` 的区块

- **选择**：`countUserBlocksByCity` 直接 `COUNT(*) FROM blocks WHERE owner_id=? AND city_id=? AND status='sold'`。
- **理由**：合并块在创建时已经把每个单块写入 `blocks` 表，重复统计 `merged_blocks` 会重复计数。
- **替代方案**：同时查询 `merged_blocks` 并按组内块数累加。未采用，因为会重复计算已写入 `blocks` 的单块。

### 2. 前端交互：城市选择后 AJAX 回填 + 输入框只读

- **选择**：监听 `city` 输入框的 `change` 事件，发送 AJAX 请求到 `hufang/circles/ajax-block-count.php`，返回后写入 `block_count` 并设为只读。
- **理由**：实时反馈，用户体验好；只读可防止用户手动修改导致与 block 子站不一致。
- **替代方案**：仅在表单提交时后端计算。未采用，因为用户希望在选择城市时就能看到数量。

### 3. 后端兜底：提交时若 `block_count` 未传或无效，自动重新计算

- **选择**：`create.php` 提交阶段校验，如果 `block_count` 为空或小于 0，先根据城市名查找 `city_id` 再调用新方法计算。
- **理由**：防止前端失败时创建出错，保证健壮性。

## Risks / Trade-offs

- **风险**：用户可能通过浏览器开发者工具修改只读输入框的值，提交时绕过前端。
  - **缓解**：提交时后端会按城市重新计算并覆盖，确保写入数据库的值来自 block 子站真实数据。
- **风险**：城市名与用户输入不完全匹配（如带"市"后缀）。
  - **缓解**：复用现有 `City` 类的城市查询逻辑，同时支持精确匹配与去"市"后缀匹配。
- **风险**：`blocks` 表数据量大时城市级计数查询较慢。
  - **缓解**：`(owner_id, city_id, status)` 已能命中索引；如后续出现性能问题，可在此字段组合上加复合索引。
