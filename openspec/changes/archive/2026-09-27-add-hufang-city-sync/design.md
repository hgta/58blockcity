# 城市数据一键同步 — 技术设计

## Context

见 proposal.md。现状关键事实（已实测验证，2026-09-06）：

- blockcity.vip H5 前端为 uni-app 构建的 SPA，所有 `/api/*` 请求经统一拦截器注入签名头；**匿名（未登录）即可访问排行接口**
- 真实请求配方（浏览器抓包 + 用抓包值复验签名公式 PASS + node 端到端 200 OK）：

```
POST https://www.blockcity.vip/api/area/rankList?areaId=0     # 参数在 query，body 为空
请求头：
  content-type : application/json;charset=utf-8
  platform     : H5
  Authorization: false            # 字面量字符串，非空
  ak           : <毫秒时间戳><6位随机 0-9A-Z>     # 客户端自造，无需注册
  u            : 0                # 匿名 userId
  a            : <8位随机 0-9A-Z>
  t            : <毫秒时间戳>
  n            : <ak><t><8位随机 0-9A-Z>
  s            : 大写MD5("blockcity_blockcity" + t + n + "Blockcity153#abc#123")
  Referer      : https://www.blockcity.vip/pages/block/area
```

- 返回结构：`{"msg":"操作成功","code":200,"data":{"list":[{id,name,regions,ranking,num,letter,areaNo,userNum,proposalNum}, ...]}}`，共 200 条（北京 ranking=1/userNum=3993/num=4970，丽江 ranking=200）
- 字段映射：`ranking`→`cities.rank`、`userNum`→`cities.resident_count`、`num`→`cities.activated_blocks`、`name`→匹配键
- 旧 `block/admin/sync-cities.php` token 方案失败根因：参数放 body 而非 query、`Authorization` 空（应为 `false`）、缺 `platform: H5`、缺 `u: 0`、content-type 错误 —— 五项叠加导致一律 500「系统繁忙」
- 统一后台体系：各子站 admin 页共用 `shared/admin/admin-header.php` + `admin-menu-config.php`；auth 统一在根 `includes/auth.php`（session 域 `.58.tl` 全子站互通，`checkAdmin()` 校验）
- 项目惯例：业务类放 `classes/`（如 `City.php`、`CityPortal.php`），页面直接 `require_once` 引入

## Goals / Non-Goals

**Goals:**

- 管理员在 v.58.tl 城市列表页一键完成同步，单次页面请求内返回结果
- 同步核心逻辑单点维护（一个类），互访圈一键按钮与区块子站同步页共用
- 接口异常时零写入、可读的错误提示；未匹配城市可见
- 零外部依赖：纯 PHP curl + md5，无需 token / composer 包 / 浏览器

**Non-Goals:**

- 不做定时自动同步（cron 可后续基于同一类追加，不纳入本次）
- 不更新 `popularity`、基金字段（接口不提供）
- 不做城市自动新增（榜单城市若库中不存在仅列入未匹配名单）
- 不改前台任何展示逻辑

## Decisions

### D1: 同步核心抽为 `classes/CitySyncer.php`

**选择**：独立类 `CitySyncer`，静态方法为主，页面直接 require 后调用。

```php
CitySyncer::fetchRankList(): array            // 远程请求+解析，失败抛 RuntimeException（含原因）
CitySyncer::apply(array $list, PDO $pdo): array
//  → ['fetched'=>200, 'updated'=>198, 'unchanged'=>x, 'missed'=>['中国数藏',...]]
```

**理由**：签名配方、请求姿势、字段映射是「一处维护」的关键 —— 接口盐值或头格式变更时只改此文件；两端 UI（hufang 一键按钮 / block 同步页）各自薄壳化。
**替代方案**：① 复制整页到 hufang（双份维护，弃）；② 只修 block 页 + hufang 加链接跳转（用户明确要在 v.58.tl 完成，弃）；③ 抽到 `shared/admin/`（那是 UI 组件层，业务类惯例在 `classes/`，弃）。

### D2: 请求姿势严格复刻抓包配方

- `curl POST`，参数只放 query（`?areaId=0`），body 为空字符串
- 全部 8 个业务头逐字面设置：`Authorization: false`、`u: 0`、`platform: H5` 等为实测必需项，缺一即 500
- `s` 用 `strtoupper(md5(...))`
- `CURLOPT_TIMEOUT 30`、`Referer` 固定为榜单页 URL
- 容错判定：HTTP 200 且 JSON `code == 200` 且 `data.list` 为非空数组，否则抛异常（不区分具体错误码，提示统一为「接口返回异常，签名配方可能需更新」）

### D3: 匹配与更新策略

- 两级匹配：先 `name` 精确；失败后去后缀归一化（`市`，必要时扩 `县/区` 不做——榜单均为城市名，仅 `市` 够用）再匹配；仍失败进 `missed`
- 更新语句：`UPDATE cities SET rank=?, resident_count=?, activated_blocks=?, updated_at=NOW() WHERE name=?`（归一化匹配时 WHERE 用库中真实名，需先查 id→name 映射；实现上预载 `cities` 全表 name 清单做匹配，200 城规模无性能问题）
- 单事务包裹全部 UPDATE；`rowCount>0` 计入 updated，`=0` 计入 unchanged
- 不新增城市、不删除城市、不动其他字段

### D4: hufang 一键按钮 = 本页 POST 动作

`hufang/admin/cities.php` 顶部操作区加「同步数据」按钮，`POST cities.php`（hidden `action=sync`）；页面头部处理动作后经 PRG（POST-Redirect-GET）用 session flash 显示结果，避免刷新重复提交。按钮请求期间用原生 `confirm()` 提示「将拉取 blockcity.vip 最新榜单并更新，继续？」即可，不加 loading 动画（单请求秒级完成）。
**替代方案**：独立 `sync-cities.php` 页（多一次跳转，用户要的是"一键"，弃；block 端因有统计/最近更新展示继续保留独立页）。

### D5: block 端同步页薄壳化

- 移除：token 表单、`save_token`/`auto_sync` 中 token 相关逻辑、`settings` 读写、`bc_load_token` 等函数
- 保留：统计卡片、手动 JSON 通道（共用 `CitySyncer::apply()`）、最近更新表
- 「立即同步」改为直接调 `CitySyncer::fetchRankList() + apply()`
- 风险低：该页此前从未测通，无既有可用行为需要兼容

### D6: 菜单入口

`admin-menu-config.php` 的 hufang 菜单暂**不加**「同步数据」项 —— 一键按钮已覆盖入口需求，菜单保持精简；后续若需要再加（一行配置的事）。

## Risks / Trade-offs

- [接口签名配方变更（盐值/头/路径调整）] → 配方集中在 `CitySyncer` 单文件；错误提示引导排查；手动 JSON 通道兜底永远可用
- [接口增加频率限制或封禁来源 IP] → 同步为管理员低频手动操作（非爬虫节奏）；失败提示明确；必要时再评估加缓存间隔
- [榜单含非地名条目（中国数藏/中国书画）] → 设计上进 `missed` 名单展示，不视为错误
- [PHP curl 超时导致 MySQL 连接断开（旧页已知问题）] → 沿用旧页的「写库前 `SELECT 1` 探活、失败重连」模式，封装进 `apply()` 前的连接保障（与旧 `bc_ensure_pdo` 同思路）
- [去「市」归一化误伤] → 仅在精确匹配失败后兜底使用，不改变精确命中路径行为

## Migration Plan

1. 部署：`git pull` 即生效，无 SQL 迁移、无新表新列（`settings.blockcity_api_token` 旧键残留无害，可后续手工清理）
2. 验证顺序：block 端同步页点「立即同步」确认 200/200 → v.58.tl 城市列表页一键同步确认结果摘要 → 手动 JSON 通道粘贴样例确认兜底可用
3. 回滚：还原两个 admin 页面与删除 `CitySyncer.php` 即可，无数据回滚需求（字段更新可再次同步覆盖）

## Open Questions

（无 —— 接口配方、字段映射、交互形态均已实测或由用户确认）
