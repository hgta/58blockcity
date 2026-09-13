## Purpose

保证站点内容通过百度主动推送接口真实、可观测地提交给百度，并使站点地图的归属符合 sitemap 协议，使百度能够有效发现并收录站内页面。

## ADDED Requirements

### Requirement: 推送凭据必须真实有效

百度主动推送的配置 SHALL 包含非占位符的真实 token，且 `baidu_site` MUST 与百度搜索资源平台已验证的站点一致。

#### Scenario: 占位符 token 不触发推送
- **WHEN** `config/seo.php` 的 `baidu_token` 仍为占位符
- **THEN** 推送逻辑 MUST 静默跳过，MUST NOT 发起 HTTP 请求

#### Scenario: 真实 token 触发推送
- **WHEN** `baidu_token` 为真实值且 `auto_push_enabled` 为真
- **THEN** 推送逻辑 SHALL 向百度接口发起请求

### Requirement: 推送入口唯一且守卫一致

内容发布触发的推送 SHALL 经由单一业务入口，该入口 MUST 统一校验 `auto_push_enabled` 与 token 有效性，MUST NOT 存在绕过守卫的直接调用路径。

#### Scenario: 各内容类型推送行为一致
- **WHEN** 发布商品、店铺、互访圈、NFT、城市、社区帖等任一内容
- **THEN** 其推送均经由同一业务入口，守卫判断逻辑相同

#### Scenario: 关闭自动推送
- **WHEN** `auto_push_enabled` 为假
- **THEN** 内容发布时 MUST NOT 发起任何百度推送请求

### Requirement: 推送结果必须可观测

每次推送 SHALL 记录结果日志，包含百度返回的执行状态与诊断信息，使配置错误与配额问题可被及时发现。

#### Scenario: 推送成功记录
- **WHEN** 推送请求成功
- **THEN** 日志 SHALL 包含百度返回的成功计数与剩余配额

#### Scenario: 推送失败记录
- **WHEN** 推送因 token 无效、站点不匹配或配额耗尽而失败
- **THEN** 日志 SHALL 包含百度返回的错误标识与消息，MUST NOT 静默丢失

#### Scenario: 网络层失败记录
- **WHEN** HTTP 请求超时或连接失败
- **THEN** 日志 SHALL 记录网络层错误信息

### Requirement: sitemap 归属同域

站点的 sitemap SHALL 仅包含与其自身同域的 URL，MUST NOT 包含其他子域的 URL。

#### Scenario: 主站 sitemap 不含跨域 URL
- **WHEN** 请求 `https://www.58.tl/sitemap.xml`
- **THEN** 结果中全部 `<loc>` MUST 以 `https://www.58.tl/` 开头

#### Scenario: sitemap 输出有效
- **WHEN** 请求 `https://www.58.tl/sitemap.xml`
- **THEN** 返回内容为结构合法的 XML `<urlset>`

### Requirement: 不产生无效的外部 ping

站点 MUST NOT 向不存在的接口发起 sitemap ping 请求。

#### Scenario: 访问 sitemap 不产生无效外呼
- **WHEN** 请求 sitemap
- **THEN** MUST NOT 向百度发起 sitemap ping（百度无此接口）
