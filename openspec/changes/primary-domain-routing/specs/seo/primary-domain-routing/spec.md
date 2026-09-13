## Purpose

保证在多个域名指向同一份内容的情况下，站点能明确声明唯一的主收录目标，使搜索引擎的权重集中于既定主域，避免多域名互相竞争。

## ADDED Requirements

### Requirement: 主域与别名域的明确划分

每个业务 SHALL 明确定义一个主域、零到多个别名域，以及可选的收口子域。

#### Scenario: 配置中定义域名角色
- **WHEN** 检查域名配置
- **THEN** 每个涉及多域名的业务 SHALL 声明其主域与别名域

#### Scenario: 未配置的业务不受影响
- **WHEN** 某业务未定义主域映射
- **THEN** 其行为 MUST 与变更前一致，MUST NOT 发生意外的 canonical 变更

### Requirement: canonical 指向主域

页面输出的 canonical SHALL 指向该业务的主域，MUST NOT 指向别名域。

#### Scenario: 主域访问时指向自身
- **WHEN** 通过主域访问页面
- **THEN** canonical MUST 为该主域下的对应 URL

#### Scenario: 子域访问时收敛到主域
- **WHEN** 通过收口子域访问页面
- **THEN** canonical MUST 指向主域的对应 URL，MUST NOT 指向子域自身

#### Scenario: canonical 与当前页面路径一致
- **WHEN** 检查任一页面的 canonical
- **THEN** 其路径部分 MUST 与当前请求路径一致，MUST NOT 一律指向首页

### Requirement: 别名域收敛

别名域 SHALL 通过重定向收敛到主域，MUST NOT 与主域同时提供可索引的相同内容。

#### Scenario: 别名域重定向
- **WHEN** 请求别名域下的任意 URL
- **THEN** 响应 MUST 为 301 重定向到主域的对应 URL

#### Scenario: 别名域不输出 canonical
- **WHEN** 请求别名域
- **THEN** 在重定向生效的前提下 MUST NOT 返回携带 canonical 的页面内容

### Requirement: URL 生成以主域为基准

站点生成的对外 URL（sitemap、站点说明文件、分享链接、结构化数据中的 URL）SHALL 使用主域。

#### Scenario: sitemap 使用主域
- **WHEN** 请求主域的 sitemap
- **THEN** 全部 `<loc>` MUST 以该业务的主域开头

#### Scenario: 站点说明文件使用主域
- **WHEN** 检查站点说明文件中的入口链接
- **THEN** 链接 MUST 指向主域

#### Scenario: 蜘蛛指引声明主域 sitemap
- **WHEN** 检查爬虫指引文件
- **THEN** 其声明的 sitemap 地址 MUST 指向主域

### Requirement: 保留生态关联声明

主域页面 SHALL 保留与其所属更大生态实体的关联声明，MUST NOT 因切换主域而切断该关联。

#### Scenario: 关联声明保留
- **WHEN** 检查主域页面的结构化数据
- **THEN** MUST 包含与其所属生态实体的关联关系声明

#### Scenario: 站点说明文件保留归属
- **WHEN** 检查主域的站点说明文件
- **THEN** MUST 包含指向所属生态主实体的引用
