## Purpose

保证全局品牌实体在站点的全部对外子域中被一致地输出，使生成式引擎能够正确聚合与归因品牌信息，不因跨子域而将同一实体识别为多个。

## ADDED Requirements

### Requirement: 全局实体在全部对外子域输出

每个对外公开的子域页面 SHALL 输出全局品牌实体的结构化数据，其标识 MUST 与其他子域一致。

#### Scenario: 子域页面包含实体锚点
- **WHEN** 抓取任一对外子域（如 `mall.58.tl`）的页面
- **THEN** 源码中的结构化数据 MUST 包含标识为全局实体标识的 `Organization`

#### Scenario: 子域间实体标识一致
- **WHEN** 对比两个不同子域的实体结构化数据
- **THEN** 二者的实体标识 MUST 完全相同

### Requirement: 实体描述单一可信来源

品牌实体的描述性字段 SHALL 由单一来源定义，其他位置的同字段 MUST 与该来源保持一致，MUST NOT 出现相互矛盾的描述。

#### Scenario: 静态页与权威来源一致
- **WHEN** 比对静态页内联的实体描述与权威来源的对应字段
- **THEN** 二者的子域清单、名称、标识等字段 MUST 一致

#### Scenario: 架构变化后描述同步
- **WHEN** 站点子域数量或构成发生变化
- **THEN** 实体描述中的子域清单 SHALL 被同步更新

### Requirement: 关联平台关系声明正确

实体对第三方平台的关系 SHALL 使用弱关系字段表达，`sameAs` MUST 仅包含实体的自有平台主页，MUST NOT 包含第三方站点。

#### Scenario: 第三方平台不进 sameAs
- **WHEN** 检查实体的 `sameAs` 字段
- **THEN** MUST NOT 包含第三方独立平台的 URL

#### Scenario: 无自有入口时不声明
- **WHEN** 站点没有可引用的自有平台主页
- **THEN** `sameAs` MUST 留空或省略，MUST NOT 以非自有 URL 填充
