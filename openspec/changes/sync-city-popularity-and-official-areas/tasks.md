## 1. 数据库迁移

- [x] 1.1 新增 `init/migration-city-popularity-fields.sql`：为 `cities` 表添加 `official_area_id`、`popularity_consume`、`popularity_balance`、`popularity_balance2`、`popularity_points_id` 字段，并添加 `official_area_id` 唯一索引。验证：执行后 `SHOW COLUMNS FROM cities` 包含这 5 个新字段。
- [ ] 1.2 在本地/测试环境执行迁移 SQL 并确认无报错。

## 2. 扩展 CitySyncer 同步能力

- [x] 2.1 在 `classes/CitySyncer.php` 中新增通用 `signedPost($url, $timeout = 30)` 私有方法，复用现有签名配方，支持指定 URL 与超时。验证：用该方法能成功请求 `/api/area/rankList?areaId=0`。
- [x] 2.2 新增 `fetchAreaList()`：调用 `/api/area/list`，解析并返回 `{id, name, ranking, num, userNum, areaNo, letter}` 数组。验证：返回数量约 421 条，字段完整。
- [x] 2.3 新增 `fetchAreaBalance($areaId)`：调用 `/api/pointsArea/getBalance?areaId=xx`，返回 `points/consume/balance/balance2/pointsId`。验证：北京 areaId=52 返回 `points` 为 1700 万级正整数。
- [x] 2.4 新增 `applyOfficialAreas(array $areas, PDO $pdo)`：对 `cities` 表执行 upsert；不存在则 INSERT，存在则更新 `official_area_id/rank/resident_count/activated_blocks/area_code/updated_at`。验证：同步后 `official_area_id` 列被填满，新增城市出现且 pinyin 为空。
- [x] 2.5 新增 `applyPopularity(array $areaIds, PDO $pdo, $onProgress = null)`：逐个城市拉取 `getBalance`，更新 `cities` 的人气值字段与 `city_bct.circulating_supply = points - consume`（仅更新已有 `city_bct` 行，不自动插入）。验证：更新后 `cities.popularity_consume` 非空且 `city_bct.circulating_supply` 等于 `points - consume`。
- [x] 2.6 在 2.5 中加入单城市重试（最多 2 次）、失败隔离与调用间隔（100~200ms），避免官方限流。验证：模拟超时后该城市仍被重试，其它城市不受影响。

## 3. 新增 AJAX 同步端点

- [x] 3.1 创建 `hufang/admin/sync-popularity-api.php`，支持 `action=prepare`：拉取官方区域列表并写入 `$_SESSION['pop_sync_queue']`（待同步 areaId 数组），返回 `{total, batchSize}`。验证：POST 后 Session 队列非空。
- [x] 3.2 实现 `action=chunk`：每次取出 25 个 areaId 同步，更新 DB 后返回 `{done, total, updated, failed, errors, finished}`。验证：连续调用直到 `finished=true` 能处理完所有城市。
- [x] 3.3 实现 `action=single`：接收 `city_id`，查找/缓存 areaId，拉取并更新单城市人气值，返回 JSON 结果。验证：对已有城市调用后该城市 `popularity` 字段更新。
- [x] 3.4 所有端点加入管理员权限校验（`checkAdmin()`），非管理员返回 403 JSON。验证：未登录访问被重定向或返回 403。

## 4. 改造 hufang/admin/cities.php 页面

- [x] 4.1 在表格「操作」列增加「同步人气值」按钮，调用 `action=single`，成功后刷新当前页并保留搜索/分页参数。验证：点击后页面刷新，对应城市人气值列显示最新值。
- [x] 4.2 在顶部操作区增加「同步官方区域 + 人气值（全部）」按钮，点击后弹出带进度条的模态框，前端轮询 `prepare` + `chunk` 直到完成。验证：模态框中进度条逐步增加，最终显示成功/失败摘要。
- [x] 4.3 在表格上方展示最近一次同步摘要（成功/失败/未匹配数），使用 `$_SESSION` flash 或持久化日志。验证：批量同步完成后页面顶部出现摘要提示。
- [x] 4.4 确保搜索、分页、排序参数在同步后不被丢失。

## 5. 改造 BCT 流通量计算

- [x] 5.1 修改 `classes/CityBCT.php` 中 `getCityBCT()`、`getAllCitiesBCT()`、`getMarketStats()` 三处查询，将 `circulating_supply` 计算为 `GREATEST(COALESCE(c.popularity, cb.circulating_supply) - COALESCE(c.popularity_consume, 0), 0)`。验证：对北京等城市，查询结果 `circulating_supply = popularity - popularity_consume`。
- [x] 5.2 对 `popularity_consume` 为空的城市，回退到既有 `cb.circulating_supply`。验证：未同步城市的市场流通量保持原值。
- [x] 5.3 确认 `includes/city-portal-render.php` 与 `classes/CityPortal.php` 直接读取 `CityBCT` 返回的 `circulating_supply`，无需额外修改即可展示真实流通量。验证：城市详情页流通量与 BCT 行情页一致。

## 6. 测试与验证

- [ ] 6.1 本地手动测试单个城市同步：选择北京，点击「同步人气值」，确认 `cities.popularity/popularity_consume` 与官方返回值一致。
- [ ] 6.2 本地手动测试批量同步：点击「同步官方区域 + 人气值（全部）」，确认新增城市被插入、全部城市人气值字段被写入、进度条正常结束。
- [ ] 6.3 检查 BCT 行情页 `/bct/` 或 `/bct/market.php` 的流通量、市值是否使用 `points - consume`。验证：市值 = (points - consume) × current_price。
- [ ] 6.4 模拟官方接口超时/500，验证失败城市被隔离并出现在失败清单，其它城市正常更新。

## 7. 提交与部署

- [ ] 7.1 运行 `php -l` 或 IDE lint 检查所有改动文件，确保无语法错误。
- [ ] 7.2 在测试环境执行迁移 SQL 并验证。
- [ ] 7.3 `git add` 所有改动，提交 `feat(hufang-admin): 同步 blockcity.vip 官方区域与人气值，BCT 流通量改为 points-consume`，并 `push origin main`。
- [ ] 7.4 线上 `v.58.tl/admin/cities.php` 执行首次「同步官方区域 + 人气值」，观察结果摘要与新增城市。
