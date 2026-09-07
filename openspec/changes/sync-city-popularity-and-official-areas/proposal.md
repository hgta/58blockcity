## Why

`v.58.tl/admin/cities.php`（项目对应 `hufang/admin/cities.php`）目前只能从 blockcity.vip 同步城市排名、居民数、开启区块，缺少人气值同步能力。官方 H5 页面 `https://www.blockcity.vip/pages/block/pointsTips` 只是展示壳，真正的人气值数据来自 `pointsArea/getBalance` 接口，且需要先拿到城市的 `areaId`。管理员需要后台既能批量同步，也能逐个城市同步，并把官方人气值明细（points、consume、balance 等）持久化到本地，同时让 BCT 市场的流通量反映 `points - consume` 的真实可流通量。

## What Changes

- 在 `hufang/admin/cities.php` 增加「同步人气值」入口：
  - 表格每行新增「同步」按钮，可针对单个城市拉取并更新。
  - 顶部新增「批量同步官方区域 + 人气值」按钮，采用 AJAX 分块轮询，避免 421 次外部请求导致 PHP 超时或触发官方限流。
- 扩展 `classes/CitySyncer.php`（或新增辅助类）新增能力：
  - 调用 `/api/area/list` 拉取全部官方区域，本地不存在时自动插入 `cities` 表。
  - 调用 `/api/pointsArea/getBalance?areaId=<id>` 拉取人气值明细。
- 数据库 `cities` 表新增字段：
  - `official_area_id`
  - `popularity_consume`
  - `popularity_balance`
  - `popularity_balance2`
  - `popularity_points_id`
- 同步时同步更新 `city_bct.circulating_supply = points - consume`，让 BCT 市场、行情页展示真实可流通量。
- 提供新的 SQL 迁移文件 `init/migration-city-popularity-fields.sql`。

## Capabilities

### New Capabilities

- `city-data-sync/popularity`: 从 blockcity.vip 拉取单个/批量城市人气值明细并持久化，支持 AJAX 分块轮询，失败重试与进度反馈。
- `city-data-sync/official-area-import`: 拉取官方全部区域列表，更新已有城市映射，并在本地不存在时自动插入新区域。
- `bct-market/circulating-supply`: BCT 行情与市场的流通量改为使用 `cities.popularity - cities.popularity_consume`（真实可流通量）。

### Modified Capabilities

- 无现有全局 spec 需要修改；`bct-market/circulating-supply` 为新定义能力，但会改变 `CityBCT` 取值逻辑。

## Impact

- 受影响文件：
  - `classes/CitySyncer.php`
  - `hufang/admin/cities.php`
  - `classes/CityBCT.php`
  - `init/migration-city-popularity-fields.sql`
  - 新增 `hufang/admin/sync-popularity-api.php`（AJAX 处理端点）
- 依赖：外部 blockcity.vip 匿名签名接口，沿用既有 `CitySyncer` 签名配方。
- 风险：官方接口可能限流/超时，实现中需含重试、退避、分块与失败隔离。
