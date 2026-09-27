# 城市数据同步能力规格

## Purpose

让各子站管理后台能够以一键方式从 blockcity.vip 官方排行榜匿名拉取最新城市排名、居民数、开启区块数并更新到 cities 表，无需登录态或 token，为城市运营与前台展示提供及时数据。

## ADDED Requirements

### Requirement: 管理员一键触发同步
已登录管理员 SHALL 能在互访圈后台城市列表页（v.58.tl/admin/cities.php）通过「同步数据」按钮一键触发同步，同步 SHALL 在单次页面请求内完成（一次远程请求 + 批量入库），并在返回页面显示结果摘要。

#### Scenario: 一键同步成功
- **WHEN** 管理员在城市列表页点击「同步数据」按钮
- **THEN** 系统拉取 blockcity.vip 排行榜单并更新 cities 表，页面顶部显示「解析 N 个城市 / 成功更新 M 个」及未匹配城市名单（若有）

#### Scenario: 非管理员触发被拒
- **WHEN** 未登录或非管理员角色的请求尝试触发同步动作
- **THEN** 请求被后台权限校验拦截并重定向到登录页，不执行任何远程请求或数据库写入

### Requirement: 匿名签名拉取榜单
同步 SHALL 通过匿名签名请求 blockcity.vip 排行接口获取城市榜单，MUST NOT 依赖任何用户登录 token、cookie 或浏览器环境。请求 SHALL 携带与官方 H5 前端一致的签名头（ak / a / t / n / s / u / platform / Authorization），签名摘要使用大写 MD5。

#### Scenario: 匿名请求获取 200 城榜单
- **WHEN** 同步执行时向 `POST /api/area/rankList?areaId=0` 发送带签名头的空 body 请求
- **THEN** 接口返回 code=200 与约 200 个城市条目，每条含 城市名 / 排名(ranking) / 居民数(userNum) / 开启区块数(num)

#### Scenario: 接口异常时安全失败
- **WHEN** 远程接口返回非成功码、HTTP 非 200、超时（默认 30 秒内）或返回体无法解析为预期结构
- **THEN** 同步终止并显示失败原因提示，cities 表不发生任何写入

### Requirement: 城市数据匹配与更新
同步 SHALL 按城市名匹配 cities 表记录并更新 `rank`、`resident_count`、`activated_blocks` 三个字段及 `updated_at`；匹配 SHALL 先精确匹配，失败后按去「市」后缀归一化匹配。榜单中无法匹配的条目（如非地名条目）SHALL 在结果中列出且 MUST NOT 中断整体同步。人气值（popularity）、基金等字段 MUST NOT 被本同步修改。

#### Scenario: 精确匹配更新
- **WHEN** 榜单条目城市名「北京」与 cities 表 name 精确一致
- **THEN** 该城市的排名、居民数、开启区块数更新为榜单值

#### Scenario: 带后缀名称归一化匹配
- **WHEN** 榜单条目城市名为「北京市」而 cities 表中为「北京」（或反之）
- **THEN** 归一化后成功匹配并更新

#### Scenario: 未匹配条目不中断
- **WHEN** 榜单含 cities 表中不存在的条目（如「中国数藏」）
- **THEN** 该条目计入「未匹配」名单并在结果中展示，其余城市正常更新

#### Scenario: 掉榜城市排名清零
- **WHEN** cities 表中某城市带有旧排名（1-200）但不在本次官方榜单中
- **THEN** 同步在同一事务内将该城市排名重置为 0（其余字段不动，计数计入 demoted），避免旧排名与新榜单撞号错位

#### Scenario: 管理员手动排名不受清零影响
- **WHEN** cities 表中某城市排名大于 200（管理员手动维护的 200+ / 300+ 名次）且不在本次官方榜单中
- **THEN** 该城市排名保持不变，不计入 demoted；清零操作范围严格限定 `rank BETWEEN 1 AND 200`（官方榜语义区间）

#### Scenario: 数据一致性写入
- **WHEN** 榜单值与库中当前值完全相同
- **THEN** 该城市不计入「成功更新」计数（或计入但另有说明），事务整体成功

### Requirement: 同步结果反馈
同步完成后系统 SHALL 向管理员展示结构化结果：拉取总数、成功更新数、未匹配城市名单、以及（若失败）明确的原因提示（如「接口返回异常 / 签名可能过期需检查算法」）。

#### Scenario: 结果摘要展示
- **WHEN** 一次同步完成
- **THEN** 页面显示「解析 200 个城市，更新 198 个，未匹配：中国数藏、中国书画」样式的摘要

### Requirement: 手动 JSON 兜底通道
区块交易子站同步页（block.58.tl/admin/sync-cities.php）SHALL 保留手动粘贴 JSON 更新通道，格式与榜单条目字段兼容（name / ranking / userNum / num 及常见别名）；自动通道失败时管理员可用该通道完成更新。

#### Scenario: 手动粘贴更新
- **WHEN** 管理员在手动通道粘贴合法城市 JSON 数组并提交
- **THEN** 系统按同一匹配规则更新对应城市并显示更新计数

#### Scenario: 非法 JSON 提示
- **WHEN** 粘贴内容无法解析为 JSON 数组
- **THEN** 显示「JSON 格式解析失败」，不执行任何写入

### Requirement: 区块子站同步页切换匿名通道
block.58.tl/admin/sync-cities.php 的自动同步 SHALL 改用与互访圈一致的匿名签名通道，原 token 配置表单与 token 存储 SHALL 移除（不再读写 `settings` 表 `blockcity_api_token`），页面统计与最近更新展示 SHALL 保留。

#### Scenario: 旧 token 入口退役
- **WHEN** 管理员访问修复后的同步页
- **THEN** 页面不再出现 token 输入框，「立即同步」按钮走匿名通道且结果反馈一致

#### Scenario: 移除后无残留依赖
- **WHEN** 匿名同步执行
- **THEN** 全程不读取也不写入 `settings` 表的 token 键
