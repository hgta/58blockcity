## Purpose

让管理员能够从 blockcity.vip 拉取并持久化每个城市的实时人气值明细，支持单个城市和批量城市两种同步模式，同时把外部接口的限流与超时风险控制在可接受范围内。

## ADDED Requirements

### Requirement: 管理员可以同步单个城市的人气值

后台 SHALL 提供单个城市人气值同步入口。管理员选择某城市后，系统 SHALL 先通过该城市的 `official_area_id` 或官方区域名查找 areaId，再调用 `pointsArea/getBalance` 接口，并将返回的 `points`、`consume`、`balance`、`balance2`、`pointsId` 持久化到 `cities` 表。

#### Scenario: 同步已存在的单个城市

- **WHEN** 管理员点击某城市行的「同步人气值」按钮
- **THEN** 系统拉取该城市官方 `areaId` 对应的 `getBalance` 数据，并更新 `cities.popularity`、`popularity_consume`、`popularity_balance`、`popularity_balance2`、`popularity_points_id` 字段
- **THEN** 页面刷新后显示最新人气值与更新时间

#### Scenario: 单个城市同步失败

- **WHEN** 官方接口返回 500/超时或城市未匹配到 areaId
- **THEN** 系统显示失败原因，且该城市原有数据不被修改

### Requirement: 管理员可以批量同步全部官方城市的人气值

后台 SHALL 提供批量同步入口。由于全量同步需要对约 400+ 个城市逐一调用 `getBalance`，系统 SHALL 采用前端 AJAX 分块轮询方式，每次只处理少量城市，并实时返回进度与结果摘要。

#### Scenario: 批量同步成功

- **WHEN** 管理员点击「批量同步全部官方城市人气值」
- **THEN** 系统先拉取官方区域列表并缓存 name→areaId 映射
- **THEN** 前端按每批固定数量（如 20~30 个城市）轮询后端，直到全部处理完毕
- **THEN** 页面显示更新成功数、失败数、未匹配数与失败清单

#### Scenario: 批量同步中途接口限流

- **WHEN** 某一批次中某个 `getBalance` 请求超时或被限流
- **THEN** 该城市单独重试 1~2 次并计入失败，其它城市继续处理
- **THEN** 失败城市不会阻塞整批同步

### Requirement: 系统把全部人气值余额字段记录到数据库

同步 SHALL 把官方 `getBalance` 接口返回的 `points`、`consume`、`pointsId`、`balance`、`balance2` 全部写入数据库。`cities.popularity` 字段保持语义为「当前已产生人气值」，即 `points`。

#### Scenario: 写入全部余额字段

- **WHEN** 官方返回 `{"points":17730198,"consume":348389,"pointsId":81222353,"balance":3269739,"balance2":2099970}`
- **THEN** 数据库对应城市记录的这五个字段分别写入上述数值

### Requirement: 同步过程中每个城市失败互不影响

批量同步 SHALL 对每个城市使用独立的 try-catch，任一城市的接口异常、数据解析异常或数据库异常 SHALL 只影响该城市，其它城市正常更新。

#### Scenario: 部分城市 getBalance 返回异常

- **WHEN** 批量同步中 3 个城市的 `getBalance` 返回 500
- **THEN** 这 3 个城市计入失败清单，剩余城市正常完成更新
