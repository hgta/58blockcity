# Design: city-locate-server-side-ip

## 现状（改造前）

```
页面加载
   │
   ▼
getCityInfo()            ← 三份拷贝：city/city.js、assets/js/main.js、hufang/assets/js/main.js
   │
   ▼
高德 v3/ip（前端直连，key 明文）
   │
   ├─ status=1 ────────────▶ 显示城市 + 拼 citycode（再打一次 geocode/geo）
   │
   └─ status≠1 / undefined / 网络失败
            │  city.replace() 抛异常
            ▼
      .catch()
            ▼
      navigator.geolocation.getCurrentPosition()   ← ★ 弹窗
```

三处调用点分布：

| 文件 | 调用方式 |
|---|---|
| `shared/footer.php` | 引入 `https://www.58.tl/city/city.js` 并在 `#userCity` 存在时自动调用 |
| `index.php` / `top200city.php` / `all-cities.php` / `includes/city-portal-render.php` | 引入 `/city/city.js` |
| `58hufang.html` / `hufang/luoluo.html` / `rankings/*.html` | 静态页内联 `getCityInfo()` |
| `hufang/assets/js/main.js` | DOMContentLoaded 内 `getCityInfo()` |
| `assets/js/main.js` | 仅定义、未被调用（`initCityLocation` 已注释禁用） |

## 目标架构

```
页面加载
   │
   ▼
getCityInfo()  →  fetch('/api/city_geo.php')        ← 单一收口
                        │
                        ▼
              ┌──────────────────────┐
              │ 服务端 city_geo.php   │
              ├──────────────────────┤
              │ 1. 会话缓存 $_SESSION│  命中 → 直接返回
              │ 2. IP 文件缓存(24h)  │  命中 → 直接返回（from=cache）
              │ 3. 高德 v3/ip        │  status 严格校验
              │ 4. 区号：城市缓存    │  命中（长 TTL，城市→citycode）
              │    └ 未命中 → geo API│
              │ 5. 失败 → ok:false   │  ★ 绝不返回"需要定位"信号
              └──────────────────────┘
                        │
                        ▼
              {ok:true, city, citycode, from}
                        │
      ┌─────────────────┴─────────────────┐
      ▼                                   ▼
  渲染城市名+链接                    ok:false → 保持「未知城市」
  localStorage 记一份                  + 默认 blockcity 链接
```

## 关键决策

### D1 降级路径直接删除，而不是"延迟触发"

有人会想：把 `geolocation` 改成用户点击"定位"时才调用（懒触发），既保留能力又不自动弹窗。

**不采用**。理由：城市级精度下浏览器定位与 IP 定位结果差异极小，而"让用户点一下才能识别城市"几乎没人会点，等于功能不存在。直接删除换来的是零打扰 + 更少代码。若将来真需要精确位置，应作为独立功能按需引入，而不是塞进欢迎条。

### D2 缓存分两级，各解决不同问题

| 层级 | 键 | TTL | 解决什么 |
|---|---|---|---|
| 会话缓存 | `$_SESSION` | 会话级 | 同一次访问内翻页不重复查（覆盖 90% 重复） |
| IP 文件缓存 | `md5(IP)` | 24h | 同一访客隔天再来不重复查；同 IP 下多用户共享 |
| 区号缓存 | 城市名 | 30 天 | 消除第二次 `geocode/geo` 调用（城市→区号是长期稳定映射） |

配额消耗估算：改造前 **2 次/PV**；改造后约 **1 次/IP/天**（区号命中缓存后），降幅两个数量级。

### D3 严格校验高德返回，异常不再滑进降级

```php
$ok = ($data['status'] ?? '') === '1' && !empty($data['city']);
```

`city` 为空（配额超限、局域网 IP、境外 IP）时一律返回 `ok:false`，前端渲染「未知城市」。**任何失败都不再触达浏览器定位 API**。

### D4 key 移出前端，落到服务端配置

新增 `config/amap-sample.php`：

```php
define('AMAP_WEB_KEY', ''); // 在高德控制台绑定域名白名单
```

真实文件为 `config/amap.php`（沿用现有 `database-sample.php` 的 sample 模式，真实配置不入库）。接口在未配置 key 时直接返回 `ok:false`，不报错、不外呼。

### D5 跨子站调用：CORS 白名单

`shared/footer.php` 在 bct / block / nft 等子站引入的是 `https://www.58.tl/city/city.js`，接口同在主站根下，从 `*.58.tl` fetch 需要 CORS：

- 校验 `Origin` 主机名以 `.58.tl` 结尾（或为 `www.58.tl`）→ 回写 `Access-Control-Allow-Origin` 并 `Vary: Origin`
- 非白名单来源不回写该头（浏览器自然拦截），接口本身不泄漏敏感数据
- 请求为简单 GET，无需预检

### D6 前端兜底：localStorage 短期缓存

接口成功后把 `{city, citycode, ts}` 写入 `localStorage`（TTL 7 天）。下次访问优先读本地，命中则不再请求接口——进一步减少接口调用，也让城市文本**首帧就有值**（不再经历"未知城市 → 城市名"的跳动）。

## 文件级改动

| 文件 | 改动 |
|---|---|
| `config/amap-sample.php` | 新增：AMAP_WEB_KEY 常量 |
| `api/city_geo.php`（主站根下） | 新增：定位接口（会话+文件缓存、区号缓存、status 校验、CORS 白名单） |
| `city/city.js` | `getCityInfo()` 改为 fetch 接口；删除 geolocation 分支；加 localStorage 缓存；无 `#userCity` 元素时静默返回 |
| `assets/js/main.js` | 同上（其 `getCityInfo` 定义保留但改为同实现，或改为委托共享实现） |
| `hufang/assets/js/main.js` | 同上 |
| `includes/city-portal-render.php`、`index.php`、`top200city.php`、`all-cities.php`、`58hufang.html`、`hufang/luoluo.html`、`rankings/*.html` | 引入的 `city.js` 版本号 +1（破缓存） |

## Risks / Trade-offs

- **[IP 定位不准]** 部分网络（企业出口、VPN、移动 NAT）IP 归属地与实际城市不符 → 与改造前完全一致，未变差；将来可用"手动选择城市"兜底
- **[配额仍可能耗尽]** 若盗刷未止（旧 key 已泄露），新 key 应立即在高德控制台绑定域名白名单；且服务端接口本身可加简单频控
- **[文件缓存目录需可写]** 部署环境需保证缓存目录可写；不可写时退化为"只走会话缓存"，功能仍可用
- **[三份 JS 拷贝]** 本次同步改三处是已知隐患；收口为一个公共文件（如 `assets/js/city-locate.js`）是更好的终态，但本次改动面已不小，建议留作后续重构
- **[浏览器已记住的授权]** 已授权过的浏览器不会再弹（Chrome 记住权限）；本次改动后新访客不再被弹，老访客若已授权也不受影响
