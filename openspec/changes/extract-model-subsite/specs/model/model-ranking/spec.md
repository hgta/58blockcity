## Purpose

为子站提供模特排行榜，以多维榜单制造竞争感与荣誉感，并作为内容再发现入口。

## ADDED Requirements

### Requirement: 多维榜单

排行榜页 SHALL 至少提供四个维度：粉丝数、点赞数、参演短剧数、作品数。

#### Scenario: 切换维度

- **WHEN** 用户选择某一维度
- **THEN** 榜单按该维度由高到低排列
- **AND** 当前选中维度可辨识

#### Scenario: 排序正确性

- **WHEN** 任一维度榜单渲染
- **THEN** 相邻名次的排序值满足由高到低
- **AND** 排序值相等的模特稳定排列

### Requirement: 名次与荣誉展示

排行榜 SHALL 对前三名做差异化展示；荣誉色 SHALL 仅用于名次语义。

#### Scenario: 前三名突出

- **WHEN** 榜单存在至少三位模特
- **THEN** 前三名以区别于其余名次的方式展示

#### Scenario: 榜单不足三人

- **WHEN** 榜单不足三位模特
- **THEN** 按实际数量展示，不出现空名次占位

### Requirement: 榜单项内容

每个榜单项 SHALL 展示模特头像、昵称、当前维度的数值，并提供进入其个人页的入口。

#### Scenario: 点击进入个人页

- **WHEN** 用户点击任一榜单项
- **THEN** 进入该模特的个人页

### Requirement: 空数据与边界

排行榜在数据稀疏 SHALL 保持可用，SHALL NOT 出现破损布局。

#### Scenario: 站内无模特

- **WHEN** 站内没有任何活跃模特
- **THEN** 展示空状态提示

#### Scenario: 部分维度无数据

- **WHEN** 某一维度的值全为 0
- **THEN** 该维度仍可展示，不出现报错或空白页

### Requirement: 榜单页 SEO

排行榜页 SHALL 输出 canonical 与列表类结构化数据。

#### Scenario: 列表结构化数据

- **WHEN** 搜索引擎抓取排行榜页
- **THEN** 页面包含列表类结构化数据
