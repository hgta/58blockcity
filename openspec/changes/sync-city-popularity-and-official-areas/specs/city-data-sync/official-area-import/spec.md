## Purpose

让后台能够把 blockcity.vip 官方维护的完整区域列表同步到本地 `cities` 表，自动补齐本地缺失的城市，并更新已有城市与官方区域的映射关系（`official_area_id`、排名、居民数、开启区块等）。

## ADDED Requirements

### Requirement: 系统拉取官方全部区域列表

后台 SHALL 通过匿名签名请求 `POST /api/area/list` 获取官方全部区域。接口返回的每条记录 SHALL 包含 `id`（areaId）、`name`、开发排名 `ranking`、开启区块数 `num`、居民数 `userNum`、区号 `areaNo` 等字段。

#### Scenario: 成功拉取全部区域

- **WHEN** 管理员触发同步
- **THEN** 系统成功解析官方区域列表，得到每个官方区域的 areaId 与名称映射
- **THEN** 映射结果缓存在本地，供后续单条/批量人气值同步复用

#### Scenario: 官方区域列表接口超时

- **WHEN** `/api/area/list` 请求超时或返回非成功码
- **THEN** 系统提示「全量区域列表获取失败，可稍后重试」
- **THEN** 本次同步不写入任何新城市，也不修改现有人气值数据

### Requirement: 系统根据区域名匹配本地城市

系统 SHALL 优先使用 `official_area_id` 匹配本地城市；其次使用城市名精确匹配；再次使用去「市」后缀归一化匹配。匹配规则 SHALL 与既有 `CitySyncer::normName()` 保持一致，确保「北京/北京市」等写法能够互相对应。

#### Scenario: 精确匹配已有城市

- **WHEN** 官方区域名为「北京」且本地存在同名城市
- **THEN** 该城市被识别为同一城市，只更新映射与统计字段

#### Scenario: 归一化匹配带后缀的城市名

- **WHEN** 官方区域名为「北京市」而本地为「北京」（或反之）
- **THEN** 去「市」后成功匹配，不重复插入

### Requirement: 系统插入本地缺失的官方区域

对于官方列表中存在但本地 `cities` 表中没有的城市，系统 SHALL 自动插入一条新记录，字段默认值如下：`name` = 官方名称；`official_area_id` = 官方 areaId；`rank` = `ranking`；`resident_count` = `userNum`；`activated_blocks` = `num`；`area_code` = `areaNo`；`pinyin` = 空字符串；`is_hot` = 0；`status` = `active`；`popularity` 及其相关字段初始为 0，待后续人气值同步写入。

#### Scenario: 新增一个官方区域

- **WHEN** 官方列表出现「中国数藏」而本地无此名称
- **THEN** 系统向 `cities` 表插入该记录，并标记 `official_area_id`
- **THEN** 页面同步结果中显示「新增 N 个城市」

#### Scenario: 非城市条目也被插入

- **WHEN** 官方列表包含「中国数藏」这类非地名条目
- **THEN** 系统仍按规则插入，管理员后续可手动编辑或删除

### Requirement: 系统更新已有城市的官方映射与基础统计

对于已匹配的城市，系统 SHALL 更新 `official_area_id`、`rank`、`resident_count`、`activated_blocks`、`area_code` 等字段为官方最新值。`popularity` 相关字段在此步骤中不动，由单独的人气值同步接口写入。

#### Scenario: 已有城市排名变化

- **WHEN** 官方「杭州」的 `ranking` 从 2 变为 3
- **THEN** 本地 `cities.rank` 同步更新为 3
- **THEN** `cities.popularity` 保持原值，等人气值同步再更新
