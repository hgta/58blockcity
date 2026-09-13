## Purpose

使各子域能够作为独立站点被百度验证、推送与收录，每个子域拥有与其域名匹配的推送凭据、站点地图与爬虫指引。

## ADDED Requirements

### Requirement: 推送凭据按子域独立配置

推送配置 SHALL 支持按子域分别配置 token 与启用状态，MUST 支持在未配置某子域时安全跳过。

#### Scenario: 未配置的子域跳过推送
- **WHEN** 推送一个未在配置中启用 token 的子域 URL
- **THEN** 推送 MUST 被跳过并记录原因，MUST NOT 使用其他子域的 token 发送

#### Scenario: 已配置的子域正常推送
- **WHEN** 推送一个已启用 token 的子域 URL
- **THEN** 推送 SHALL 使用该子域自身的 token 与站点参数

#### Scenario: 推送目标与 URL 归属一致
- **WHEN** 推送任一子域 URL
- **THEN** 请求中的站点参数 MUST 与该 URL 的域名一致

### Requirement: 每个子域提供独立 sitemap

每个对外收录的子域 SHALL 通过本域的 `/sitemap.xml` 提供站点地图，其内容 MUST 仅包含本域 URL。

#### Scenario: 子域 sitemap 可访问
- **WHEN** 请求 `https://mall.58.tl/sitemap.xml`
- **THEN** 返回合法的 XML sitemap，且全部 `<loc>` 以 `https://mall.58.tl/` 开头

#### Scenario: 子域 sitemap 覆盖本域内容
- **WHEN** 请求任一子域 sitemap
- **THEN** 结果包含该子域首页、主要列表页与动态内容详情页

#### Scenario: 子域 sitemap 不含跨域 URL
- **WHEN** 检查任一子域 sitemap
- **THEN** MUST NOT 包含其他子域的 URL

### Requirement: 每个子域提供 robots 指引

每个对外收录的子域 SHALL 提供本域的 `robots.txt`，其中 MUST 声明指向本域 sitemap 的地址，并 SHALL 继承站点的爬虫放行与后台屏蔽策略。

#### Scenario: 子域 robots 声明本域 sitemap
- **WHEN** 请求 `https://mall.58.tl/robots.txt`
- **THEN** 内容包含 `Sitemap: https://mall.58.tl/sitemap.xml`

#### Scenario: 子域 robots 屏蔽私密路径
- **WHEN** 检查任一子域 `robots.txt`
- **THEN** 用户中心、后台、购物车、订单等私密路径被 `Disallow`

### Requirement: 子域可分批启用

子域的推送启用状态 SHALL 可独立控制，使各子域能够逐个验证与上线。

#### Scenario: 单个子域独立开关
- **WHEN** 某子域配置为未启用
- **THEN** 该子域 MUST NOT 发起推送，且不影响其他子域的推送行为

#### Scenario: 子域启用不影响主域
- **WHEN** 启用某子域的推送
- **THEN** 主域 `www.58.tl` 的推送行为 MUST 保持不变
