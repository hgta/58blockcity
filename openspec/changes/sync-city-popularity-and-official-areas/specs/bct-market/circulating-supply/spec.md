## Purpose

让 BCT 市场与行情页展示的「流通量」从过去直接使用 `cities.popularity` 或 `city_bct.circulating_supply`，改为使用官方口径下的真实可流通量，即 `已产生人气值 - 已消耗人气值`。

## ADDED Requirements

### Requirement: BCT 市场的流通量等于 points - consume

`classes/CityBCT.php` 中所有用于计算市值、流通量的查询 SHALL 将流通量计算为 `cities.popularity - cities.popularity_consume`，并确保结果不小于 0。当 `cities.popularity_consume` 为空或城市记录不存在时，SHALL 回退到既有 `city_bct.circulating_supply`。

#### Scenario: 正常展示真实流通量

- **WHEN** 某城市 `popularity = 17730198` 且 `popularity_consume = 348389`
- **THEN** BCT 行情页显示该城市流通量为 `17730198 - 348389 = 17381809`

#### Scenario: 消耗大于等于已产生时兜底为 0

- **WHEN** 某城市 `popularity - popularity_consume` 计算结果小于 0
- **THEN** 该城市流通量按 0 展示，不允许出现负数

#### Scenario: 未同步过的人气值字段为空

- **WHEN** 某城市尚未同步，`cities.popularity_consume` 为空
- **THEN** BCT 市场回退使用 `city_bct.circulating_supply`，保证既有数据不受影响

### Requirement: 城市详情页（City Portal）同步使用真实流通量

`includes/city-portal-render.php` 与 `classes/CityPortal.php` 中展示「流通量」的字段 SHALL 沿用 `CityBCT::getCityBCT()` 返回的 `circulating_supply`，即统一由 `CityBCT` 计算真实流通量，不在城市门户里单独写死公式。

#### Scenario: 城市详情页展示流通量

- **WHEN** 用户访问某城市详情页
- **THEN** 页面调用 `CityBCT::getCityBCT()` 获取真实流通量
- **THEN** 显示结果与 BCT 行情页保持一致
