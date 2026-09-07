## Context

项目已通过 `classes/CitySyncer.php` 实现 blockcity.vip 城市排行榜（`ranking/num/userNum`）的匿名签名同步，并在 `hufang/admin/cities.php` 提供了一键同步按钮。本次需要在同一后台增加人气值同步能力，并扩展为「官方区域导入 + 人气值同步 + BCT 流通量真实化」三件事。官方接口为 blockcity.vip H5 后端，签名配方与既有 `CitySyncer` 一致。

关键已知事实：
- `POST /api/area/list` 返回全部 421 个官方区域，含 `id/name/ranking/num/userNum/areaNo`。
- `POST /api/pointsArea/getBalance?areaId=<id>` 返回 `points/consume/balance/balance2/pointsId`。
- `points` 即「当前已产生人气值」，对应现有 `cities.popularity`。
- `points - consume` 才是真实可流通量。
- 测试环境中连续高频请求会触发官方限流/超时，批量同步必须分块、限速、可重试。

## Goals / Non-Goals

**Goals:**
- 在 `hufang/admin/cities.php` 提供单个城市与批量城市的人气值同步入口。
- 自动拉取官方全部区域，本地缺失时插入 `cities` 表，已有城市更新映射与基础统计。
- 把 `points/consume/pointsId/balance/balance2` 全部写入 `cities` 表。
- 让 BCT 市场的流通量展示改为 `points - consume`。
- 为新增字段提供可回滚的 SQL 迁移。

**Non-Goals:**
- 不新增定时 cron，所有同步仍由管理员手动触发（后续可基于同一 CLI 追加）。
- 不修改 BCT 交易撮合、订单、支付逻辑；只改「流通量」展示口径。
- 不为城市自动生成 pinyin（官方接口不提供），新插入城市 pinyin 为空，由管理员后续维护。
- 不自动为新增城市创建 `city_bct` 记录；仅更新已有 `city_bct` 的 `circulating_supply`。

## Decisions

### D1: 复用并扩展 `classes/CitySyncer.php` 作为同步核心

**选择**：在 `CitySyncer` 中新增：
- `fetchAreaList(PDO $pdo)`：拉取 `/api/area/list`，同时完成 upsert 并返回 name→areaId 映射。
- `fetchAreaBalance(int $areaId)`：拉取 `/api/pointsArea/getBalance?areaId=xx` 并返回解析后的数组。
- `applyPopularity(array $areaIds, PDO $pdo, callable $progress = null)`：批量更新人气值字段与 `city_bct.circulating_supply`。

**理由**：签名配方、请求头、错误处理与既有 `CitySyncer` 完全一致，集中维护可降低官方接口变更时的修改面。替代方案是新建 `PopularitySyncer.php`（增加一个文件，但签名逻辑会重复或需要继承），最终选择扩展既有类。

### D2: 后台 AJAX 分块轮询 + Session 队列

**选择**：批量同步拆成三个阶段：
1. `action=prepare`：拉取官方区域列表，完成 `cities` 的 upsert，并把待同步 areaId 列表写入 `$_SESSION['pop_sync_queue']`。
2. `action=chunk`：每次从队列头部取 25 个 areaId，顺序调用 `getBalance`，更新 DB，返回进度。
3. 前端用 JS 轮询 `chunk`，直到队列清空，展示结果摘要。

**理由**：
- 421 次外部调用若在同一 PHP 请求内完成会超时（30s），分块后每段请求可控制在 5~10 秒内。
- 顺序调用 + 100~200ms 间隔可降低官方限流概率；并发测试曾触发大面积超时。
- Session 队列避免把 400+ areaId 在前后端来回传输。

替代方案：
- 使用 `curl_multi` 并发：速度更快但测试显示容易触发限流，弃用。
- 后台 CLI 脚本 + 进度文件：更稳健，但项目当前无消息队列/守护进程，部署成本高，弃用。

### D3: 新字段统一落到 `cities` 表

**选择**：新增字段：
- `official_area_id` INT DEFAULT NULL
- `popularity_consume` INT DEFAULT 0
- `popularity_balance` INT DEFAULT 0
- `popularity_balance2` INT DEFAULT 0
- `popularity_points_id` BIGINT DEFAULT 0

`cities.popularity` 保持为「当前已产生人气值」（即 `points`），与现有语义兼容。

**理由**：人气值是城市属性，和 `resident_count`、`activated_blocks` 同类，放在 `cities` 表最自然。若单独建表会增加 JOIN 复杂度和读取成本。

### D4: BCT 流通量计算改为 `cities.popularity - cities.popularity_consume`

**选择**：修改 `classes/CityBCT.php` 中的三个查询，将 `circulating_supply` 计算为：

```sql
GREATEST(COALESCE(c.popularity, cb.circulating_supply) - COALESCE(c.popularity_consume, 0), cb.circulating_supply)
```

**理由**：
- 让 BCT 行情页、市值、排序等全部自动使用真实可流通量。
- 当 `popularity_consume` 为空时回退到既有 `cb.circulating_supply`，保证老数据不受影响。
- `GREATEST(..., 0)` 防止异常负值。

替代方案：直接更新 `city_bct.circulating_supply = points - consume`。该方案简单，但会丢失「points/consume 明细」且需要每次同步都写 city_bct，最终选择改 SQL 公式更透明。

### D5: 官方区域 upsert 使用 name 精确 + 去「市」归一化匹配

**选择**：复用 `CitySyncer::normName()` 的去「市」逻辑，先精确匹配，失败后归一化匹配；仍未匹配则 INSERT。

**理由**：与既有城市同步的匹配策略保持一致，降低维护成本。不新增复杂的行政区划别名库。

### D6: 新增字段通过迁移 SQL 部署

**选择**：新增 `init/migration-city-popularity-fields.sql`，包含 `ALTER TABLE cities ADD COLUMN ...` 与 `ALTER TABLE city_bct MODIFY/ADD`（如需要）。部署时由 DBA/运维手动执行一次。

**理由**：项目 SQL 文件均存放在 `init/` 目录，符合既有习惯；在线 `ALTER` 对 `cities` 表（几百行）影响很小。

## Risks / Trade-offs

- [官方接口限流或变更] → 配方集中在 `CitySyncer`；实现中加入重试/退避；批量分块降低请求密度。
- [`/api/area/list` 不稳定] → prepare 阶段设置 60s 超时与重试；若仍失败则提示用户稍后重试，并提供「仅同步已有 official_area_id 的城市」的降级入口。
- [新插入城市 pinyin 为空] → 城市详情伪静态 `/city/<pinyin>.html` 对新增城市不可用；管理员可在编辑页补全 pinyin。新增城市默认 `status=active` 但不会自动加入 BCT（见 Non-Goals）。
- [points/consume 数值动态变化] → 同步结果页显示「数据为官方实时值，可能存在秒级波动」。
- [并发 AJAX 轮询导致 Session 写冲突] → `chunk` 接口只读取并截断队列头部，不写入其它 Session 字段；每次请求结束立即 `session_write_close()`。

## Migration Plan

1. 执行 `init/migration-city-popularity-fields.sql` 添加字段。
2. 部署代码后，管理员访问 `v.58.tl/admin/cities.php` 点击「同步官方区域 + 人气值」。
3. 首次 `prepare` 会拉取官方 421 城并插入缺失城市；后续 `chunk` 轮询更新人气值。
4. 回滚：删除新增字段，还原 `classes/CitySyncer.php`、`classes/CityBCT.php`、`hufang/admin/cities.php`，删除新增 API 文件。数据回滚需手动恢复 `cities.popularity` 等字段的旧值或从备份恢复。

## Open Questions

- 是否需要把新插入城市自动加入 `city_bct` 表？当前 Non-Goals 选择不加入，若运营希望新增城市直接进入 BCT 市场，可后续再单独变更。
