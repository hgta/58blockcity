# Proposal: city-locate-server-side-ip

## Why

访问 58 各子站时浏览器频繁弹出「获取您的位置」授权框，打扰每一位新访客。弹窗并非主定位路径产生：主路径是**高德 IP 定位**（`restapi.amap.com/v3/ip`，无需授权、不弹窗），`navigator.geolocation.getCurrentPosition()` 只在 IP 定位失败时被降级调用。

但 IP 定位正在持续失败，原因有两层：

1. **配额被吃光**：每次 PV 连打两次高德接口（`v3/ip` + `geocode/geo`），免费 key 的日配额很低。超限后高德仍返回 HTTP 200，但 `status≠1`、`city` 为 `undefined`，于是 `ipData.city.replace('市','')` 抛 TypeError，静默滑进 `.catch()` → 触发定位弹窗。**异常被当成"定位失败"处理**，这是弹窗的直接导火索。
2. **key 裸奔在公开 JS 里**：`key=c7ebabae...` 明文写在 `city/city.js`、`assets/js/main.js`、`hufang/assets/js/main.js` 三处，F12 即可见，未绑域名白名单时会被盗刷，进一步耗尽配额。

问题的本质是：**同一个"城市级"定位结果，每次 PV 都各自去查一次外部 API，且失败后用一个会打扰用户的兜底**。城市级精度下，浏览器定位相比 IP 定位没有实质收益，为这点收益让每位访客弹授权框不划算。

## What Changes

- **新增服务端 IP 定位接口**：把高德调用从前端移到 PHP，按「会话 + 按 IP 文件缓存」复用结果，每个访客每天最多查一次外部 API（而不是每 PV 两次），并把 key 从前端移除
- **前端彻底去除定位弹窗**：三份 `getCityInfo()` 改为调用该服务端接口；删除全部 `navigator.geolocation` 降级分支；IP 定位失败时显示「未知城市」并保持默认的 blockcity 链接，不再弹窗
- **城市→电话区号缓存**：区号属于长期稳定映射，服务端按城市缓存（长 TTL），基本消除第二次高德调用
- **补齐异常防御**：服务端严格校验高德返回 `status==='1'`，不再把 `undefined` 当字符串处理

## Capabilities

### New Capabilities
- `city/geo-server-side-locate`: 服务端 IP 定位能力——统一入口接口、访客级结果复用、外部 API 配额保护、失败时优雅降级为"未知城市"（绝不触发浏览器定位授权）

### Modified Capabilities
- `city/header-welcome-bar`（若已存在 spec 则更新）：欢迎条城市文本与链接的取值来源由「前端直连高德」改为「服务端接口」

## Impact

- **前端**：`city/city.js`、`assets/js/main.js`、`hufang/assets/js/main.js` 三份 `getCityInfo()` 改造（同主干逻辑，三处需同步）；删除全部 `navigator.geolocation` 调用（`city.js:23`、`assets/js/main.js:380`、`hufang/assets/js/main.js:380` 及已注释的 `initCityLocation` 相关分支）
- **后端**：新增服务端定位接口（主站根下，供各子站跨站调用）+ IP/区号缓存；新增 `config/amap-sample.php` 存放 key 常量
- **调用方**：`shared/footer.php`（共享引入点）、`index.php`、`top200city.php`、`all-cities.php`、`58hufang.html`、`hufang/luoluo.html`、`rankings/rankings.html`、`rankings/gdp-total.html`、`hufang/assets/js/main.js` 的调用点保持原有触发方式，仅函数内部实现改变
- **数据影响**：无数据库改动；新增文件缓存目录（需可写）
- **不做的事**：不做 GPS/基站级精确定位、不做"手动选择城市"交互（留作后续）、不改动欢迎条 HTML 结构与文案、不引入 Redis/Memcached（用文件缓存即可）
