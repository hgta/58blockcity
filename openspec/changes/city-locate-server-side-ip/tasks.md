# Tasks: city-locate-server-side-ip

## 1. 服务端定位接口

- [x] 1.1 新增 `config/amap-sample.php`，定义 `AMAP_WEB_KEY`（空值占位，真实文件 `config/amap.php` 不入库）
- [x] 1.2 新增主站根下 `api/city_geo.php`：只接受 GET，输出 JSON
- [x] 1.3 接入会话缓存：`$_SESSION` 命中直接返回，避免同一次访问翻页重复查询
- [x] 1.4 接入 IP 文件缓存（24h，键为 IP 哈希；目录 `city/cache/geo`），缓存目录不可写时静默退化
- [x] 1.5 调用高德 `v3/ip`，严格校验 `status === '1' && city 非空`；`city` 为空数组/空串均视为失败
- [x] 1.6 区号获取：先查城市→citycode 文件缓存（30 天），未命中再调 `geocode/geo` 并回写
- [x] 1.7 返回统一结构 `{ok, city, citycode, adcode, from}`，`from` 取值 `session|cache|amap`；失败返回 `ok:false` + `reason`
- [x] 1.8 CORS：仅对 `*.58.tl` 来源回写 `Access-Control-Allow-Origin`，并加 `Vary: Origin`（已验证 evil.com 不回写）
- [x] 1.9 接口层加轻量频控（同 IP 每分钟 30 次），防止单点刷接口
- [x] 1.10 未配置 `config/amap.php` 时临时回退旧 key 并 `error_log` 提醒，避免部署后全站「未知城市」

## 2. 前端改造（三份 `getCityInfo`）

- [x] 2.1 `city/city.js`：删除 `navigator.geolocation` 分支，改为 `fetch('https://www.58.tl/api/city_geo.php')`
- [x] 2.2 `assets/js/main.js`：同步改造（其 `getCityInfo` 定义重写；`initCityLocation` 改为委托 `getCityInfo()`）
- [x] 2.3 `hufang/assets/js/main.js`：同步改造（DOMContentLoaded 内调用）
- [x] 2.4 三处统一：接口返回 `ok` 时渲染城市名 + 两处链接；`ok:false` 时显示「未知城市」，**不触发任何定位授权**
- [x] 2.5 三处统一：无 `#userCity` 元素时静默返回，不请求接口
- [x] 2.6 新增 localStorage 短期缓存（7 天，含时间戳），命中则首帧直接渲染、跳过请求
- [x] 2.7 静态页 `58hufang.html`、`hufang/luoluo.html`、`rankings/rankings.html`、`rankings/gdp-total.html` 引用方式不变，仅版本号递增
- [x] 2.8 删除两处「随机城市 mock」兜底（`getCityFromCoords`），此前会显示错误城市

## 3. 引入点与缓存版本

- [x] 3.1 `shared/footer.php` 中 `city/city.js` 版本号 `20261002 → 20261023`（跨子站共享引入点）
- [x] 3.2 `index.php`、`top200city.php`、`all-cities.php`、`includes/city-portal-render.php`、`58hufang.html`、`hufang/luoluo.html`、`rankings/*.html` 版本号同步
- [x] 3.3 `shared/header.php`、`block/includes/header.php` 的 `assets/js/main.js` 版本号同步
- [x] 3.4 全库确认再无 `getCurrentPosition` / `watchPosition` / `navigator.geolocation` 残留

## 4. 验证

- [x] 4.3 定位失败路径（本地出口 IP 无法定位）：返回 `ok:false/lookup_failed`，无 JS 报错、无弹窗
- [x] 4.4 缓存复用验证：同一 IP 首次 `from=amap`、再次 `from=cache`，外部调用次数不随 PV 增长
- [x] 4.6 缓存目录写入正常（`city/cache/geo`），目录不可写时自动退化为仅会话缓存
- [ ] 4.1 线上验证：新开无痕窗口访问主站与 bct 子站，确认不再出现定位授权弹窗
- [ ] 4.2 线上验证：欢迎条正确显示所在城市，两个链接均为 `blockcity.biz/{区号}?iclc`
- [ ] 4.5 线上验证：从 bct.58.tl 跨站调用接口，确认 CORS 生效
