# 互访圈后台城市数据一键同步（匿名接口版）

## Why

城市排名/居民数/开启区块数目前只能依赖 block 子站后台的 `sync-cities.php` 同步，但该页面的 token 方案从未测通，cities 表数据长期滞后于 blockcity.vip 最新榜单；互访圈（v.58.tl）后台则完全没有同步入口。经真实浏览器抓包逆向，已确诊旧方案失败根因（请求姿势错误：参数应在 query 而非 body、缺 `platform: H5` 头、`Authorization` 应为字面量 `false` 等），并端到端验证了**匿名签名请求**即可拿到完整 200 城榜单 —— 无需登录、无需 token，纯 PHP curl 一次请求完成。

## What Changes

- 新增共享类 `classes/CitySyncer.php`：以已验证的匿名签名配方请求 `POST /api/area/rankList?areaId=0`，解析 200 城榜单并更新 `cities` 表的 `rank` / `resident_count` / `activated_blocks`（附带 `areaNo` 区号可选用）
- `hufang/admin/cities.php` 顶部操作区新增「同步数据」按钮：POST 本页执行同步，完成后 flash 显示「解析 N 城 / 更新 M 城 / 未匹配名单」
- 修复 `block/admin/sync-cities.php`：切换到 `CitySyncer` 匿名通道，**移除 token 配置表单**（不再需要），保留手动粘贴 JSON 兜底通道与统计/最近更新展示
- 城市名匹配增强：精确匹配失败时按去「市」后缀归一化兜底（如「北京市」→「北京」），未匹配城市名在结果中列出（如榜单中的「中国数藏」「中国书画」等非地名条目）
- 同步行为约束：单事务批量 UPDATE；curl 超时/接口非 200 时报错不写库；接口数据与库中一致时不产生无效写入

## Capabilities

### New Capabilities
- `city-data-sync`: 管理后台城市数据同步能力 —— 匿名签名请求 blockcity.vip 排行接口、解析与城市名匹配、批量更新 cities 表、一键触发与结果反馈、手动 JSON 兜底的完整行为要求

### Modified Capabilities

（无 —— `openspec/specs/` 下暂无既有能力规格）

## Impact

- **代码**：新增 `classes/CitySyncer.php`；修改 `hufang/admin/cities.php`、`block/admin/sync-cities.php`；`shared/admin/admin-menu-config.php` hufang 菜单增加「同步数据」入口（可选，一键按钮已够用则不加菜单）
- **外部依赖**：blockcity.vip `/api/area/rankList` 接口（签名盐值/头格式若变更需同步维护 CitySyncer，已集中单点）
- **数据库**：`cities` 表 rank/resident_count/activated_blocks/updated_at 字段更新；`settings` 表 `blockcity_api_token` 键废弃不再读写
- **不受影响**：前台各子站展示逻辑、city.php 门户、既有管理页其他功能
