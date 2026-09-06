# 城市门户 UI 打磨 — 技术设计

## 总体原则

- 门户向首页对齐，**首页为视觉基准**（品牌橙 #ff6b00 主题已上线）。
- 渲染层改动集中在 `includes/city-portal-render.php` 单文件模板；新样式全部追加到 `assets/css/city-portal.css`，不触碰 `city/city.css` 公共类。
- 数据层仅两处小改：`CityPortal::blocks()` 附带分区统计、`circles()` limit 调整。

---

## 1. 头部导航统一

**现状**：门户 header（render.php）为「返回首页/NFT交易/互访圈/TOP200城市/我的区块」，与首页 6 子站导航 + 登录态按钮不同。

**方案**：

- `city.php`：`session_start()` 移到文件最前（输出前），构造 ctx 时增加 `logged_in => isset($_SESSION['user_id'])`。
- `city-portal-render.php` `city_portal_render(array $ctx)` 读取 `$ctx['logged_in']`，header 输出与 `index.php` 完全相同的结构：
  - 导航：区块交易 / BCT交易 / NFT头像 / 人气商城 / 互访圈 / 拍卖（`nav-button`，同 href）
  - 登录态：已登录 → `个人中心`（橙底白字）+ `退出`（`auth/logout.php`）；未登录 → `登录`（橙底白字）+ `注册`
  - 保留门户页 `logo`（`logo-img` + `logo-text`），logo 整体包 `<a href="/index.php">` 保证可回首页
- 登录按钮 inline 色用 `#ff6b00`（与首页一致）；`nav-button` hover 底色随 `city-portal.css` 追加 `.user-actions .nav-button` 微调（若 city.css 已有等效样式则复用）。

**注意**：`session_start()` 必须在任何输出之前（缓存 readfile 分支之前执行即可，session 开销可忽略）。

## 2. Hero 统计区增加人气值

**数据**：`cities.popularity int` 已存在，`city.php` 取出的 `$city` 行已含该列，无需新查询。

**方案**：

- `cp-statgrid` 由 4 块改 5 块，顺序：全国排名 / 现有居民 / 开启区块数 / **人气值** / 基金余额（人气值位于基金余额左侧，符合需求）。
- 人气值展示：`cp_bignum($city['popularity'])`（复用门户既有大数格式化函数，与 BCT 流通量同风格）。
- CSS：`.cp-statgrid` `repeat(4, minmax(0,1fr))` → `repeat(5, minmax(0,1fr))`；移动端 `repeat(2, ...)` 维持（5 块 2+2+1 可接受，第 5 块跨 2 列 `grid-column: span 2` 修正排版）。

## 3. 区块街景空态 → 9 区概览

**名词解释**（对齐用户提问）：「该城暂无已命名的区块」= `blocks` 表中该 city 没有 `name IS NOT NULL AND name <> ''` 且 `status IN ('sold','reserved')` 的行，即还没有用户买地并自定义命名展示的地块。

**为什么不使用 9 区合并大图**：9 区合并即把 A–H/Z 九个分区的地图块拼成一张城市大图，宽度撑满容器、高度约 600px+，会把 BCT/NFT 等模块全部推出首屏，坪效最差；且仅北上杭深等少数城市有地图素材。

**方案**：空态时渲染「9 区块概览」紧凑网格（新 CSS 类 `cp-zones`）：

- 数据：`CityPortal::blocks()` 在原有命名区块查询之外，追加一条聚合 SQL：
  ```sql
  SELECT zone,
         COUNT(*) AS total,
         SUM(status IN ('sold','reserved')) AS opened
  FROM blocks WHERE city_id = ?
  GROUP BY zone
  ```
  结果并入返回结构 `res['zone_stats'] = ['A' => ['total'=>x,'opened'=>y], ...]`（guard 容错内，失败为空数组）。
- 模板：空态分支不再输出 `cp-empty` 一行，改为：
  - 一行说明文案（保留引导链接，措辞改为「该城区块尚未被命名认领」）：
    `该城还没有被命名认领的区块，<a>去区块地图抢注你的地盘 →</a>`
  - `cp-zones` 网格：`repeat(auto-fill, minmax(110px, 1fr))`，每区一卡：
    ```
    [ A ]
    东城区·  (districts 映射的现实区名，缺省只显示区号)
    已开 12 / 30 块
    ```
    卡片高约 72px，9 卡 3 行（桌面 6-8 列时 2 行），整块高度 ≤ 原 6 卡街景区。
  - 卡片整卡 `<a>` 深链 `https://block.58.tl/city.php?name={pinyin}#zone-{Z}`（锚点进区）。
- 有命名区块的城市：维持现有 6 卡 + 底部 `cp-mapstrip` mini 区条不动（`zone_stats` 数据照取，供 mapstrip 升级显示「已开块数」，如 `A·12`，低成本增强）。

## 4. 页脚统一

**方案**：将 `city-portal-render.php` 的 `<footer>` 整段替换为 `index.php` footer 同款 HTML（5 栏 grid：关于58区块城市 / 快速链接 / 帮助支持 / 关注我们(两张二维码) / 联系我们 + 底部版权行）。

- 直接复制 `index.php` 结构，链接与文案逐项一致；二维码路径 `/images/qr-discount.png`、`/images/qr-customer-service.png` 为根路径，门户页同域可用。
- 样式：首页 footer 为 inline style（深色 `#1a1a2e` 底 + 5 列 grid），同样 inline 复制，避免依赖 city.css 的 `.footer-container`；同时移除旧 footer 引用的 `.footer-column` 结构。
- 响应式：追加 `.footer-grid` 2 列/1 列断点（与首页相同 inline 媒体查询写法不适用于 inline style，改在 `city-portal.css` 追加 `.cp-footer-grid` 类承载 grid 定义与响应式，HTML 用类而非 inline style，与首页视觉一致即可——**视觉对齐优先于标记逐字一致**）。

## 5. 互访圈 8 个 + 显示区块数

**方案**：

- `CityPortal::circles()`：`getCirclesByCity($cityName, 6, '')` → `getCirclesByCity($cityName, 8, '')`（`Circle` 类签名不变，仅传参）。
- 模板卡片 meta 行：`圈主 {username} · {category}` → `圈主 {username} · {category} · {block_count} 个区块`（`block_count` 已在 `getCirclesByCity` 返回中，CityPortal 也已透传，仅渲染未用）。
- `block_count` 为 0 时不显示该段（避免「0 个区块」噪音）。
- 网格：`.cp-cgrid` `minmax(240px,1fr)` 保持（1200px 容器 4 列 × 2 行 = 8 恰满）；移动端 `minmax(240px)` 自然降为 1-2 列无需改。若某城不足 8 个圈子，按实际数渲染（不造空卡）。

## 6. Hero 紧凑化

**现状尺寸**：padding 28px 30px / avatar 104px / 城市名 30px / slogan mb 14px / statgrid mb 16px gap 10px / stat padding 10px 12px value 20px。

**目标尺寸**（`city-portal.css` 修订，桌面端整体高度约从 ~300px 降至 ~180px）：

| 项 | 现值 | 新值 |
|----|------|------|
| `.cp-hero` padding | 28px 30px | 18px 26px |
| `.cp-hero` gap | 24px | 18px |
| `.cp-hero-avatar img` | 104px, border 3px | 80px, border 2px |
| `.cp-city-name` | 30px | 24px, mb 2px→0 |
| `.cp-slogan` | 14px mb 14px | 13px mb 10px |
| `.cp-statgrid` | mb 16px gap 10px | mb 10px gap 8px |
| `.cp-stat` | padding 10px 12px | padding 7px 10px |
| `.cp-stat-label` | 12px | 11px |
| `.cp-stat-value` | 20px | 17px |
| `.cp-enter-btn` | padding 10px 22px | padding 8px 20px |

- 移动端（≤768px）同步：hero padding 20px→16px，avatar 80→64px，statgrid 2 列（第 5 卡 span 2）。
- 布局不变（头像左、主体右），仅尺寸压缩，5 块统计在压缩后仍单行放下（value 17px × 5 列 minmax(0,1fr) 安全）。

---

## 缓存与发布

1. 模板/CSS 均变更 → `city/cache/*.html` 全部过期：TTL 600s 内仍会吐旧页，发布后手动 `rm -f city/cache/*.html`（或跑 `php city/build-static.php all` 主动重建）。
2. 服务器发布顺序：git pull → 清缓存 → 抽查 2-3 个城市（长沙=空态街景代表、北京=有命名区块代表、任一互访圈多的城市）。
3. 回滚：git revert 单 commit 即可，缓存重建自动恢复。

## 风险与对策

| 风险 | 对策 |
|------|------|
| `session_start()` 与缓存 readfile 顺序（headers already sent） | session_start 置于任何输出前；readfile 前不影响 |
| 5 列 statgrid 在窄桌面（~900px）挤压换行溢出 | `minmax(0,1fr)` + value `word-break:break-all`（现有），1200 容器下 5×~200px 安全 |
| 9 区统计 SQL 在大城（blocks 行多）慢 | city_id 走索引（`city_zone_block` 复合索引前缀），单城 blocks 量级 ≤ 数千行，GROUP BY 无风险 |
| 互访圈不足 8 个的城市 | 按实际渲染，不填充假卡；grid auto-fill 自适应 |
| 首页后续再改版导致再度不一致 | 本轮把「页眉/页脚以首页为基准」写入 tasks 备注；长期可抽 shared 组件（超出本 change 范围，proposal 已声明 Out of Scope） |
