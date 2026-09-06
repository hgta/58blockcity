# 互访圈城市数据一键同步 — 任务清单

## 1. 同步核心类 `classes/CitySyncer.php`

- [x] 1.1 实现 `fetchRankList()`：按 design.md D2 配方（POST + query 参数 areaId=0 + 8 个业务头 + 大写 MD5 签名 + 30s 超时）curl 请求 `/api/area/rankList`，code=200 且 `data.list` 非空数组时返回城市数组，否则抛 RuntimeException；本地 `php -r`（或服务器 CLI）直接调用验证返回 200 城
- [x] 1.2 实现 `apply(array $list, PDO $pdo)`：预载 cities 表 name 映射 → 精确匹配 + 去「市」后缀归一化兜底 → 单事务批量 UPDATE rank/resident_count/activated_blocks/updated_at → 返回 `['fetched','updated','unchanged','missed'=>[]]`；写库前 `SELECT 1` 探活重连（防 curl 耗时后连接失效）
- [x] 1.3 实现 `applyManualJson(string $json, PDO $pdo)`：解析 JSON 数组（兼容 name/ranking/userNum/num 及 rank/resident_count/activated_blocks 别名），复用同一匹配更新逻辑；非法 JSON 返回明确错误

## 2. 互访圈一键入口（hufang/admin/cities.php）

- [x] 2.1 页面头部处理 `POST action=sync`：`checkAdmin()` 后调用 `CitySyncer::fetchRankList()` + `apply()`，结果写入 session flash，PRG 重定向回本页；异常时 flash 错误信息且零写入
- [x] 2.2 顶部操作区新增「同步数据」按钮（confirm 确认后提交）；flash 渲染区展示「解析 N / 更新 M / 未匹配名单」摘要；与既有 success/error alert 样式一致
- [x] 2.3 浏览器走查：未登录访问被重定向；登录后点击按钮 3 秒内返回结果摘要；重复点击无重复提交（PRG 生效）

## 3. 区块子站同步页修复（block/admin/sync-cities.php）

- [x] 3.1 移除 token 相关全部代码：token 表单、save_token/auto_sync 分支、bc_load_token/bc_save_token/bc_clear_token、settings 表读写
- [x] 3.2 「立即同步」改为调用 `CitySyncer::fetchRankList() + apply()`，结果提示与 hufang 端一致；移除本地 bc_fetch/bc_sign_headers/bc_extract_list/bc_pick_city（逻辑已并入 CitySyncer）
- [x] 3.3 手动 JSON 通道切换到 `CitySyncer::applyManualJson()`；统计卡片（城市总数/已设排名）与「最近更新」表保留，Token 状态卡改为「上次同步结果」或移除
- [x] 3.4 浏览器走查 block 端：一键同步成功显示 200/200 摘要；手动粘贴样例 JSON 更新成功；粘贴非法 JSON 提示解析失败且零写入

## 4. 端到端验证与发布

- [x] 4.1 数据核对：同步后 cities 表前 5 名 rank/resident_count/activated_blocks 与 blockcity.vip/pages/block/area 页面显示一致（北京 1/3993/4970 基准）；「中国数藏」「中国书画」出现在未匹配名单且不报错
- [x] 4.2 边界验证：断网/接口超时模拟（临时改错域名）→ 显示失败原因且 cities 表无写入；数据无变化时二次同步 updated=0/unchanged 计数正确
- [x] 4.3 lint 全部改动文件（CitySyncer.php / 两个 admin 页面）无错误；提交推送；服务器 `git pull` 后按 design.md「验证顺序」走查三个入口
