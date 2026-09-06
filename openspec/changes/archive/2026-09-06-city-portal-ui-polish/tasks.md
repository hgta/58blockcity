# 城市门户 UI 打磨 — 任务清单

## Phase 1: 数据层微调（classes/CityPortal.php）

### 1.1 blocks() 增加分区统计
- [x] `blocks($cityId)` 内追加 `SELECT zone, COUNT(*) total, SUM(status IN ('sold','reserved')) opened FROM blocks WHERE city_id = ? GROUP BY zone`
- [x] 结果以 `zone_stats`（`['A' => ['total'=>n,'opened'=>m], ...]`）并入返回结构；SQL 失败归一为空数组（guard 内）

### 1.2 circles() 取数 6→8
- [x] `getCirclesByCity($cityName, 6, '')` → `getCirclesByCity($cityName, 8, '')`
- [x] 确认 items 透传 `block_count`（已存在，仅核对）

---

## Phase 2: 渲染模板（includes/city-portal-render.php）

### 2.1 头部导航与首页统一
- [x] `city.php`：输出前 `session_start()`；ctx 增加 `logged_in`
- [x] render.php：header 导航替换为首页同款 6 子站（区块交易/BCT交易/NFT头像/人气商城/互访圈/拍卖）
- [x] 登录态按钮：已登录 → 个人中心(橙底)+退出；未登录 → 登录(橙底)+注册（与 index.php 一致）
- [x] logo 包 `<a href="/index.php">` 可回首页

### 2.2 Hero 统计区加人气值
- [x] statgrid 顺序：全国排名 / 现有居民 / 开启区块数 / 人气值 / 基金余额
- [x] 人气值取 `$city['popularity']`，`cp_bignum()` 格式化

### 2.3 区块街景空态 → 9 区概览
- [x] 空态文案改为「该城还没有被命名认领的区块，去区块地图抢注你的地盘 →」
- [x] 空态渲染 `cp-zones` 9 区概览网格：区号 + 现实区名（city_profiles.districts 映射，缺省仅区号）+ 已开/总块数 + 深链
- [x] 有命名区块时：保留 6 卡；底部 `cp-mapstrip` mini 区条升级显示已开块数（`A·12` 形式，数据取 zone_stats）

### 2.4 互访圈卡片增强
- [x] 卡片 meta：`圈主 {username} · {category} · {block_count} 个区块`（block_count=0 时省略该段）

### 2.5 页脚与首页统一
- [x] footer 整段替换为首页 5 栏结构：关于/快速链接/帮助支持/关注我们(二维码)/联系我们 + 版权行
- [x] grid 与响应式用新类 `cp-footer-grid`（city-portal.css 承载），移除旧 `.footer-column` 依赖

---

## Phase 3: 样式（assets/css/city-portal.css）

### 3.1 Hero 紧凑化（尺寸表见 design.md §6）
- [x] `.cp-hero` padding 18px 26px / gap 18px
- [x] `.cp-hero-avatar img` 80px / border 2px
- [x] `.cp-city-name` 24px；`.cp-slogan` 13px mb 10px
- [x] `.cp-statgrid` 5 列 `minmax(0,1fr)` / mb 10px / gap 8px；移动端 2 列且第 5 卡 span 2
- [x] `.cp-stat` padding 7px 10px；`.cp-stat-label` 11px；`.cp-stat-value` 17px
- [x] `.cp-enter-btn` padding 8px 20px
- [x] 移动端断点：hero padding 16px、avatar 64px

### 3.2 新增样式
- [x] `.cp-zones`：`repeat(auto-fill, minmax(110px, 1fr))` 网格 + `.cp-zone` 小卡（区号/区名/已开块数，整卡链接，hover 橙描边）
- [x] `.cp-footer-grid`：5 列 grid（2fr 1fr 1fr 1.2fr 1fr），≤768px 2 列、≤480px 1 列；深色底 `#1a1a2e`（与首页 footer 一致）

---

## Phase 4: 验证与发布

- [x] 本地 lint（render.php / CityPortal.php / city.php）
- [x] 桌面 + 移动端走查：长沙（空态街景代表）、北京（有命名区块代表）、互访圈≥8 的城市
- [x] 核对项：header/footer 与首页逐项一致；hero 5 指标且高度较改前降 ≥30%；互访圈 2×4 无缺角；9 区概览高度 ≤ 原 6 卡街景区
- [x] 空 city_profiles / 空圈子 / 空 blocks 等空态组合页面无 PHP 错误
- [x] 提交推送；服务器 `git pull` + `rm -f city/cache/*.html`（或 `php city/build-static.php all`）后抽查线上页面
