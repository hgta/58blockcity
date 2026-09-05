# 统一城市门户页 — 任务清单

## Phase 1: 数据库与城市键

### 1.1 city_profiles 表
- [x] 新增 `init/migrate-city-profiles.sql`（`CREATE TABLE IF NOT EXISTS` 幂等）
  - 字段：city_id(uk) / admin_area / population / gdp / gdp_per_capita / urbanization_rate / universities / feature_tags / slogan / position / landmarks / food / potential / districts / intro / data_year / status / created_at / updated_at
- [x] `init/db-init.sql` 追加相同建表语句（新库全建）

### 1.2 CityKey 换算工具类
- [x] 新增 `classes/CityKey.php`
  - [x] `byPinyin` / `byId`（薄封装 City）
  - [x] `normalizeName`：去 `市/省/自治区/特别行政区` 后缀、全半角、trim
  - [x] `idsByNameLookup`：构建 短名→city_id 映射（进程内静态缓存）
  - [x] `cityNameToId` / `idToCityName`

---

## Phase 2: 聚合业务层 CityPortal

### 2.1 新增 `classes/CityPortal.php`
- [x] `assemble($city)`：返回六个模块数组（blocks/bct/nft/club/circles/mall），每模块 `{ok,count,items,msg}` 独立容错
- [x] `blocks($cityId)`：`blocks` 有命名（name 非空）按 `updated_at DESC LIMIT 6`；含 zone/block_number/display_text/display_image/status
- [x] `bct($cityName)`：`CityBCT::getCityBCT` → 现价/流通量/总供给/base_price；表缺/无记录 → 空态
- [x] `nft($cityId)`：按 city_id 查挂售（active）NFT，取缩略图/价格；无 → 空态
- [x] `club($cityName)`：`Post::getFeed` 传 city，最新 5 条（标题/正文截断/type/topic/时间）
- [x] `circles($cityName)`：`Circle::getCirclesByCity` 取圈子；数量/访问量用 `city_rankings` 视图（若有）
- [x] `mall($cityName)`：`models` / `authors` 各取 active 3 条（头像+昵称）
- [x] 所有方法 try/catch → 归一空态；全部 PDO 预处理

---

## Phase 3: 统一模板渲染（city.php 重写）

### 3.1 city.php 主流程改造
- [x] 保留路由/校验/`cities` 行加载/SEO 元数据骨架
- [x] 移除「模式 A：读 `city/{pinyin}.html` + 正则替换」分支
- [x] 新增缓存逻辑：命中 `city/cache/{pinyin}.html`（TTL 内）直接输出
- [x] 未命中 → `CityPortal::assemble` → 渲染模板 → 输出并写缓存（`LOCK_EX`+临时文件 rename）

### 3.2 页面模板（includes/city-portal-render.php）
- [x] 在现有模式 B 骨架上实现新正文结构（含各 section + 更多→深链 + `.cp-empty` 空态样式）
- [x] 城市资料卡：有 `city_profiles` 才渲染；指标缺省自动隐藏；未完善城市显示引导占位
- [x] 🏘 区块街景卡组、💰 BCT 行情迷你卡、🖼 NFT 缩略条、💬 同城动态列表、🔄 互访圈圈子卡、🛍 城市好店卡
- [x] 详细介绍区：`intro` JSON 段落渲染（可选）
- [x] 区块 9 区 mini 卡：有 `districts` 用现实区名，无则仅 A~Z 区名占位（不依赖 map/*.jpg）
- [x] 深链映射集中配置常量（block/bct/nft/club/v/mall 见 design，渲染层 `$L` 一处维护）

### 3.3 样式与脚本
- [x] 新增 `assets/css/city-portal.css`（复用品牌视觉变量，覆盖新增模块与空态样式）
- [x] head 引用新 css（现有 SEO/favicon/统计脚本保留，另引用 `city/city.css`）
- [x] 检查 `city/city.css`/`city/city.js` 是否仍需引用，按需调整（继续引用，`getCityInfo` 交互保留）

---

## Phase 4: 资料采集与缓存工具

### 4.1 tools/crawl-city-profiles.php（离线采集器）
- [x] 读 `cities` 全量（pinyin + name）为采集目标
- [x] Wikidata SPARQL 批量查城市实体：面积 P2046 / 人口 P1082 / GDP P2132 等 → 映射字段
- [x] 中文维基摘要补简介（`--no-wiki` 可关）；`data_year` 取 statement `P585` 年份 qualifier
- [x] 请求限速/超时/重试 1 次/失败清单；断点续跑（已有 JSON 跳过，`--force` 覆盖）
- [x] 产出 `data/city-profiles/{pinyin}.json`；结尾打印成功/空/失败统计

### 4.2 tools/sync-city-profiles.php（JSON → city_profiles）
- [x] 遍历 JSON upsert by `city_id`（ON DUPLICATE KEY UPDATE）
- [x] `status` 自动判定（含面积/人口/GDP 任一 → 1）；空 city_id/pinyin 记录跳过

### 4.3 city/build-static.php
- [x] 支持 `all` / `hot` / 单拼音三种模式
- [x] 复用渲染逻辑，循环落 `city/cache/{pinyin}.html`；单城异常不中断
- [x] `hot` 用 `cities.is_hot=1`；文档注明建议 cron 每日执行

---

## Phase 5: 静态 HTML 停用与引用清理

### 5.1 停用静态文件
- [x] Git 删除 `city/*.html`（保留 `city.css`/`city.js`，保留 `city/build-static.php`；`city/cache/` 与 `data/city-profiles/` 已加入 .gitignore）
- [x] 确认 nginx rewrite 仍将 `/city/*.html` → `city.php`（URL 不变，见 `docs/nginx-rewrite.conf:53/216`）

### 5.2 引用清理
- [x] 全仓扫描 `city/{pinyin}.html` 硬编码引用并修正（`shared/header.php:288` 城市定位条默认链接 → `top200city.php`）
- [x] 修正后不再有指向静态文件本身的链接（`.html` URL 由 rewrite 承载的站点内链属预期可保留）

---

## Phase 6: 验证（需部署 + 数据后手动验证）

### 6.1 富城市样例（北京）
- [ ] `/city/beijing.html` 200，展示 4 指标 + 资料卡 + 6 模块
- [ ] 区块街景/行情/NFT/club/圈子/好店内容与各子站真实数据一致
- [ ] 详情长文与特色文案来自 `city_profiles`（采集同步后不缺失）

### 6.2 空态城市样例
- [ ] 选一个无数据城市（DB 有、无静态页、无模块内容）→ 页面 200 不报错，模块显示空态，资料卡隐藏/引导占位

### 6.3 缓存与重生成
- [ ] 首次访问生成 `city/cache/{pinyin}.html`；二次访问零查询直接输出
- [ ] `php city/build-static.php beijing` 强制重生成生效
- [ ] `all` / `hot` 模式跑通且异常城市不中断

### 6.4 静态停用回归
- [ ] 删除 html 后随机抽 5 个原 URL（含北京/普通/冷门）均 200 且非兜底形态
- [ ] 全站无 404、控制台无资源 404（css/js/favicon）

### 6.5 资料采集质量
- [ ] `php tools/crawl-city-profiles.php`（有网环境）→ `php tools/sync-city-profiles.php` 后北京行含正确数值与 `data_year`，资料卡展示正常
- [ ] 失败/空结果清单可读，缺资料城市清单可作 admin 校对依据

### 6.6 安全
- [ ] 非法 pinyin / 目录穿越请求被拦截（404）
- [ ] 含注入字符的城市名不出错
- [ ] cache 目录文件无法被 web 直接当 PHP 执行（.html 仅静态输出）
