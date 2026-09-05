# 设计文档 — 统一城市门户页

## 总体思路

```
请求 www.58.tl/city/{pinyin}.html  (nginx rewrite → city.php?pinyin=)
        │
        ▼
  1. 命中缓存 city/cache/{pinyin}.html（未过期）→ 直接输出
        │ 未命中
        ▼
  2. 加载：cities 行(元宇宙4指标/区号) + city_profiles 行(真实资料,可空)
        │
        ▼
  3. 装配：CityPortal::assemble(cityRow) → 六个模块数据集(每模块含 items + count，允许空态)
        │
        ▼
  4. 渲染统一模板 → 输出 + 写 city/cache/{pinyin}.html（TTL 控制）
```

关键设计决策：

- **单一渲染入口**：不再区分「富静态页 / 兜底页」，一套模板 + 模块级空态。静态 `city/*.html` 全部停用并移出 Git。
- **缓存优先**：门户页聚合查询多（6 子站），首访实时组装后落盘静态缓存，二次访问零 DB 查询；更新节奏由 `city/build-static.php` 控制。
- **城市键统一换算**：库内两种城市维度字段（int `city_id` 与 string 城市名），由 `CityKey` 单一职责换算与清洗，业务模块不各自处理。
- **数据资产策略：不沿用旧文案，一次性统一采集新资料**。旧富静态页人工文案已多年未更新（用户判定过时），不做迁移；改为离线采集器从公开结构化源（默认 Wikidata SPARQL 取 面积/人口/GDP，中文维基摘要取简介）抓取全量城市 → JSON → 同步入库 `city_profiles`。口径「结构优先、缺失留空、年份标注」，采集产出入库后长期复用。

## 数据层

### city_profiles 表（新增）

与 `cities` 1:1（`city_id` 唯一）。字段以「真实世界城市资料」为主，可直接承载 beijing.html 现有展示项：

```sql
CREATE TABLE IF NOT EXISTS `city_profiles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `city_id` int(11) NOT NULL COMMENT '关联 cities.id',
  `admin_area` varchar(50) DEFAULT NULL COMMENT '行政面积',
  `population` varchar(50) DEFAULT NULL COMMENT '常住人口',
  `gdp` varchar(50) DEFAULT NULL COMMENT 'GDP(含年份前缀，如 2023年 40269亿元)',
  `gdp_per_capita` varchar(50) DEFAULT NULL COMMENT '人均GDP',
  `urbanization_rate` varchar(20) DEFAULT NULL COMMENT '城镇化率',
  `universities` varchar(30) DEFAULT NULL COMMENT '高校数量',
  `feature_tags` varchar(255) DEFAULT NULL COMMENT '特色标签(JSON数组: 国家首都,历史文化名城)',
  `slogan` varchar(120) DEFAULT NULL COMMENT '城市口号(副标题)',
  `position` text COMMENT '城市定位(特色亮点-定位)',
  `landmarks` text COMMENT '地标(特色亮点-地标)',
  `food` text COMMENT '特色美食(特色亮点-美食)',
  `potential` text COMMENT '发展潜力(特色亮点-发展潜力)',
  `districts` text COMMENT '区块-现实行政区映射 JSON:[{zone:"A",area:"东城区块",note:"核心城区"}]',
  `intro` text COMMENT '详细介绍 JSON:[{h:"地理位置与历史沿革",p:"..."},...]',
  `data_year` varchar(10) DEFAULT NULL COMMENT '统计数据年份(用于标注,缺省留空)',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1=已完善,0=待补充',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_city` (`city_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

- 迁移脚本：`init/migrate-city-profiles.sql`（幂等 `CREATE TABLE IF NOT EXISTS`）；同步追加至 `init/db-init.sql`。
- 仅 `city_id` 关联，城市名变更不影响资料（`cities.name` 是唯一事实源）。

### 城市键换算（classes/CityKey.php 新增）

解决 `city_id`(int) 与 城市名(string) 双键并存：

| 方法 | 说明 |
|------|------|
| `byPinyin($pinyin)` / `byId($id)` | 查 `cities` 行（City 已有，薄封装） |
| `normalizeName($raw)` | 清洗城市名：去 `市/省/自治区/特别行政区` 等后缀、全半角、首尾空格 → 返回标准短名（如 `北京市→北京`、`北京　→北京`） |
| `idsByNameLookup()` | 一次性 `SELECT id,name,pinyin FROM cities` 构建 `短名→id` 映射（进程内缓存） |
| `cityNameToId($raw)` | normalize + 查映射，返回 `city_id`（找不到返回 null） |
| `idToCityName($id)` | 返回标准短名（模块做 string 过滤用） |

用途：`block/nft_sales` 侧用 `city_id` 直查；`bct/club/hufang/mall/users` 侧用 `cityNameToId` 换算出 id 后过滤或直接按城市名查询。空态判断统一 `null === $id`。

## 业务层（classes/CityPortal.php 新增）

### assemble 结构

```php
class CityPortal {
    // 输入 cities 行；输出聚合好的模块数组（各模块独立容错）
    public function assemble(array $city): array {
        $cityId   = (int)$city['id'];
        $cityName = CityKey::idToCityName($cityId);   // 标准短名
        return [
            'meta'  => [...],                 // hero 指标已在渲染层直接用 $city
            'blocks'    => $this->blocks($cityId),       // 区块街景
            'bct'       => $this->bct($cityName),        // BCT 行情
            'nft'       => $this->nft($cityId),          // NFT 热卖
            'club'      => $this->club($cityName),       // 同城动态
            'circles'   => $this->circles($cityName),    // 互访圈
            'mall'      => $this->mall($cityName),       // 城市好店(模特/作者)
        ];
    }
}
```

每个 `getter` 返回 `['ok'=>bool,'count'=>int,'items'=>[],'msg'=>'']`：任何异常（表缺失/查询失败/无数据）都归一为空态而非抛错。

### 各模块数据来源（复用现有查询 / 少量新写）

| 模块 | 城市键 | 数据来源 | 展示项与排序 | 降级空态 |
|------|--------|----------|--------------|----------|
| 🏘 区块街景 | city_id | `blocks` WHERE `city_id=? AND name IS NOT NULL AND name<>''`（命名区块），按 `updated_at DESC LIMIT 6`；附 `zone/block_number/display_type/display_image/display_text` | 编号+区号+命名(皮肤字/图缩略)+状态 | 「暂无命名区块」→ 深链 block 认领 |
| 💰 BCT 行情 | 城市名 | `city_bct`（`CityBCT::getCityBCT($cityName)`） | 现价/流通量/总供给/基础价 | 「暂无行情」 |
| 🖼 NFT 热卖 | city_id | `nft_sales` JOIN nft 相关（挂售 active）；复用 NFT 类城市查询 | 缩略图+价格；按价格/时间 | 「该城暂无挂售」→ 深链 nft |
| 💬 同城动态 | 城市名 | `posts`（`Post::getFeed` 传 city）按 `created_at DESC LIMIT 5` | 标题/正文截断/话题/时间 | 「暂无同城动态」→ 深链 club |
| 🔄 互访圈 | 城市名 | `circles` active（`Circle::getCirclesByCity`）；圈子数/访问量可用 `city_rankings` 视图 | 圈子卡片：名称/人数/访问 | 「暂无圈子」→ 深链 hufang |
| 🛍 城市好店 | 城市名 | `models`/`authors` WHERE `city=? AND status='active'`（各取 3） | 模特/作者卡（头像+昵称） | 「该城暂无好店」→ 深链 mall |

> 注：`shops/products` 无城市字段，本版 mall 模块只聚合 `models`/`authors`（含真实城市），不强行映射店铺/商品；如需可后续加列，属 Out of Scope。

### 深链映射（渲染层集中配置，一处维护）

```php
// 渲染层常量/数组：模块 → 子站深链
'blocks'  => "https://block.58.tl/city.php?name={$pinyin}",     // 区块站同城页(block 子站自带 city.php)
'bct'     => "https://bct.58.tl/market.php",                    // 行情市场
'nft'     => "https://nft.58.tl/",
'club'    => "https://club.58.tl/index.php?city={$cityName}",
'circles' => "https://v.58.tl/?city={$cityName}",               // 互访圈(与 club 参数风格保持一致，若无效则首页)
'mall'    => "https://mall.58.tl/model/list.php?city={$cityName}",
```

## 前台（city.php 统一模板重写）

以**现有模式 B 骨架**为母版（head/SEO/schema/favicon/51la/统计/面包屑/header/city-location-bar/footer/优惠悬浮已完备），新增正文区域。结构：

```
head(SEO: 复用 SeoHelper 模式; description 在资料存在时注入人口/区块/GDP 语义)
├─ 城市 header：avatar(资料 avatar 或首字SVG) + h1 + slogan + 4 指标卡 + 「进入XX区块城市」
├─ 【城市资料卡】(city_profiles 存在才渲染，未完善显示引导)
│    指标网格(面积/人口/GDP/人均/城镇化/高校，值缺省自动隐藏该项)
│    特色亮点折叠卡(定位/地标/美食/发展潜力)
├─ 【🏘 区块街景】最新命名区块卡组 + 「进入区块地图」
├─ 【💰 BCT行情】现价/流通/24h 等迷你卡
├─ 【🖼 NFT热卖】横向缩略图条
├─ 【💬 同城动态】最新帖子列表
├─ 【🔄 互访圈】热门圈子卡片
├─ 【🛍 城市好店】模特/作者混合卡
├─ 【详细介绍】(city_profiles.intro 长文, 可选段)
└─ footer / 优惠悬浮 / city.js
```

要点：

- 每个模块 section 头带「更多 →」深链；模块空态给统一提示样式 `.portal-empty`。
- 地图大图不强求：将 `districts` JSON 渲染成 9 区 mini 卡（A~Z 区名 + 现实区域名，若 `districts` 缺省则只显示 A~Z 区名静态文本），不再依赖 `map/*.jpg`。
- 新样式独立成 `assets/css/city-portal.css`，页面 head 引用；尽量与 `city/city.css` 不冲突（新模板不使用旧静态模板类，复用/微调视觉变量如品牌橙 `#ff6b00`）。
- 因含真实城市文字，SEO meta：`title` 带城市；`description` 动态拼接资料；`keywords` 扩充（城市+区块城市/元宇宙/区号）。资料缺失城市退化为现有默认句式。

## 缓存与重生成

### 缓存策略

- 输出完整 HTML 写 `city/cache/{pinyin}.html`（运行时生成目录）。
- 有效时间：默认 TTL（如 30 分钟）以内直接回；无缓存/过期 → 实时组装并覆盖写。
- 写入原子性：`file_put_contents(..., LOCK_EX)` + 临时文件 rename，避免半截文件。
- 目录白名单：仅接受 `^[a-z]+$` pinyin，路径用 `basename` 防穿越。

### CLI / cron（city/build-static.php 新增）

```bash
php city/build-static.php all            # 全量重生成(200城)
php city/build-static.php beijing        # 单城
php city/build-static.php hot            # 仅 is_hot=1
```

- 复用 `CityPortal::assemble` + 模板渲染，生成后落 cache 目录。
- 适合挂 cron（如每日一次），与现有 `sync-cities.php` 风格一致。
- 全量耗时约 N×单城；异常城市跳过不中断。

## 资料采集与补充

### 采集原则

旧 `city/*.html` 人工文案不迁移（无更新通道、可能过时）。全量城市真实资料由**离线采集器一次/低频抓取**，运行节奏由人控制，不做随请求实时爬取。

### tools/crawl-city-profiles.php（离线采集，产出 JSON）

- 输入：`cities` 表全部城市（`pinyin + name`）；逐个抓取。
- 主源 Wikidata SPARQL（`https://query.wikidata.org/sparql`，免费无 key）：批量查城市实体结构化数据，映射到 `city_profiles` 字段：
  - 面积 `area` ← P2046；人口 `population` ← P1082；GDP ← 经济属性（P2131 为 GDP 增长率、P2132 为 GDP 总额、P2218 为人口、P2219 为 GDP——按实际可用性取）；`admin_area` 即面积、`data_year` 取引用的统计年份 qualifier（无则留空）。
- 备选/补强源（按配置启用）：中文维基百科 REST `/page/summary/{title}` 取 `description` + `extract` 前段 → 拼入 `intro` 首段；`position/slogan` 等无法可靠自动化字段留空待 admin。
- 容错与节奏：
  - 每城请求间隔（默认 1s）+ 随机抖动；单请求超时（默认 20s）；失败重试 1 次后记入失败清单继续，不中断全量。
  - 支持断点续跑：按 `data/city-profiles/{pinyin}.json` 是否存在跳过（`--force` 覆盖）。
  - SPARQL 批量优于逐条：先一次性查出全部城市 Q 与属性，再按缺项补单查。
- 输出：`data/city-profiles/{pinyin}.json`（含 `city_id/name/data_year` 与各资料字段；值为 string 统一口径）；结尾打印 成功数/空结果清单/失败清单。

### tools/sync-city-profiles.php（JSON → DB）

- 读 `data/city-profiles/*.json`，按 `pinyin`/`city_id` upsert 进 `city_profiles`（`ON DUPLICATE KEY UPDATE`）；不存在的 pinyin 记录跳过。
- 批量（预处理循环）执行；`status` 按「至少含 面积/人口/GDP 之一」自动置 1，否则 0。

### 后续资料充实通道

1. `city_profiles` 建好 + 采集工具就绪 → 在有网环境执行一次全量采集并入库；结果入库后页面即展示。
2. admin 可视化编辑（可选后续 change），便于人工校对采集数据（数值年份、富文案字段补全）。
3. 采集源可配置化（Wikidata/维基百科/未来政府公开 CSV），新源以同样 JSON 形状接入即可，不需改动渲染层。

## 静态文件停用与风险

- `city/*.html` 停用：从 `city.php` 移除「模式 A 读静态文件」；Git 删除 67 个 html（保留 `city.css`/`city.js`/`city/cache/`）。
- 风险点排查：
  - `shared/header.php:288` 硬编码 `city/beijing.html`（城市定位条默认链接）→ 顺带改为指向 `city.php` 或按用户城市动态（本期先改为去掉 `.html` 直链或用 `City::getCityByPinyin` 兜底）。
  - 搜索引擎已收录 URL 不变（rewrite 路径不变），页面 200 内容升级，SEO 无损。
  - 其它 `*.html` 引用（`city.js`/链接）扫描一遍，确认无遗留。

## 安全与健壮性

| 项 | 处理 |
|----|------|
| XSS | 模块内容、资料、介绍等所有输出 `htmlspecialchars`（注意 JSON 内文本也转义） |
| SQL | 全部 PDO 预处理绑定；聚合查询失败降级为空态，不抛 500 |
| 城市名 | 一律经 `CityKey::normalizeName` 清洗后入查询参数；异常城市名不注入 SQL |
| 文件 | pinyin 白名单 `^[a-z]+$`；cache 写入限 `city/cache/`，rename+锁防半写 |
| 空库 | `city_profiles` 全空 → 页面只渲染 4 指标 + 六模块空态/真实模块，仍完整输出 |
| 并发 | 首访并发写缓存用 `LOCK_EX`+rename，极端下两次写可接受 |
