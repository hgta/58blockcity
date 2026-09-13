# 设计：一级域名作为主收录目标

## Context

`bct` 与 `v(hufang)` 两组业务各有三个可访问域名（子域 + 一级 `.com` + 一级 `.cn`），内容完全相同。代码侧 canonical 与 URL 生成器全部硬编码子域，与用户「一级域名为主」的目标相反。

用户约束（已确认）：

| 项 | 决定 |
|----|------|
| 服务器 | 境外 |
| ICP 备案 | 不备案（接受收录周期变长） |
| 品牌定位 | 58 生态的**子品牌** |
| 主收录目标 | 一级域名（`.com`） |
| 认证域 | `renqizhi.com` 与 `hufangquan.com`（均需验证） |
| 收录现状 | 各域名仅收录首页，内页零收录 |

## Goals / Non-Goals

**Goals**

- 一级域名成为 canonical 目标与 URL 生成的基准。
- `.cn` 收敛，消除重复内容。
- 保留与 `www.58.tl` 的生态关联（符合子品牌定位）。
- 认证 `renqizhi.com` 后，站内信号与认证目标一致。

**Non-Goals**

- 不处理其余 7 个子域（走既有子域策略）。
- 不解决未备案导致的结构性收录劣势。
- 不做外链建设（属运营范畴，非代码变更）。
- 不追求短期收录提升（canonical 变更有波动期）。

## Decisions

### D1：canonical 按请求 host 动态决定，区分「主域」与「收口域」

**决策**：引入域名角色概念：

```
  对 bct 业务：
    renqizhi.com      → 主域（canonical 指向自己）
    renqizhi.cn       → 别名域（301 到主域，不发 canonical）
    bct.58.tl         → 收口域（canonical 指向 renqizhi.com）
```

canonical 决策表：

| 请求 host | canonical 指向 | 说明 |
|-----------|---------------|------|
| `renqizhi.com` | 自身 | 主域，正常收录 |
| `renqizhi.cn` | — | nginx 301 到 `.com`，不产生页面 |
| `bct.58.tl` | `renqizhi.com` | 权重收口 |

**理由**：canonical 是百度/Google 都认的强信号，把子域权重导向主域是标准做法。

**为什么不把 `bct.58.tl` 直接 301**：用户定位是「58 生态子品牌」，`bct.58.tl` 是生态内的重要入口（主站 `index.php` 有导航指向它）。301 会破坏生态导航体验。canonical 收口既能传递权重，又保留入口。

**替代方案**：canonical 动态等于当前 host — 被否决，会造成三域名各自收录、互相竞争（权重切三份）。

### D2：`.cn` 无条件 301 到 `.com`

**决策**：nginx 层对 `renqizhi.cn` / `hufangquan.cn` 做 301 到对应 `.com`。

**理由**：
- `.cn` 与 `.com` 内容 100% 相同，是纯重复副本。
- 保留两个 = 自我竞争，权重对半。
- `.cn` 的常见价值（国内备案、国内加速）在本场景不存在（境外服务器、不备案）。
- 301 是权重合并的标准手段。

**注意**：DNS 需保留 `.cn` 解析，否则 301 无法生效。

**替代方案**：`.cn` 独立运营 — 被否决，无差异化内容支撑，只会分散权重。

### D3：主域映射集中配置，避免散落硬编码

**决策**：在配置层定义域名映射表，代码统一读取：

```php
// config/seo.php
'primary_domains' => [
    'bct'    => ['primary' => 'renqizhi.com',   'aliases' => ['renqizhi.cn'],  'subdomain' => 'bct.58.tl'],
    'hufang' => ['primary' => 'hufangquan.com', 'aliases' => ['hufangquan.cn'], 'subdomain' => 'v.58.tl'],
],
```

`SeoHelper` 提供解析方法：给定当前 host，返回其「canonical 目标 host」。

**理由**：当前域名硬编码分散在 `header.php` / `sitemap.php` / `robots.txt` / `llms.txt` / `SeoHelper::circleUrl()` 至少 6 处。集中配置后，未来调整只改一处。

**替代方案**：直接在各文件把 `bct.58.tl` 替换为 `renqizhi.com` — 被否决，治标不治本，且子域仍需作为收口域存在，不是简单替换。

### D4：保留生态关联，不切断与 58.tl 的关系

**决策**：
- `Organization` 的 `isRelatedTo` 保持不变（声明与 BlockCity.vip 生态的关系）。
- 主站 `index.php` 的生态导航**仍指向子域**（`bct.58.tl` / `v.58.tl`），因为那是生态内部入口。
- 一级域名的 `llms.txt` 保留指向 `www.58.tl` 主实体的引用。

**理由**：用户明确「58 生态的子品牌」。若切断关联，等于承认是独立站点，与定位矛盾，且会浪费已有的 GEO 实体聚合成果。

**权衡**：保留生态关联意味着百度可能仍视其为 58.tl 体系的一部分（站群判定的残余影响）。用户已接受该定位，故按此执行。

### D5：主域名复用 `www.58.tl` 的 Organization 实体

**决策**：**复用同一 `ORG_ENTITY_ID`**（`https://www.58.tl/#organization`），通过 `url` 字段体现当前站点。

**理由**：
- 定位是「子品牌」→ 同一实体的不同站点，应共用一个 `@id`。
- 若用独立 `@id`，会与 `geo-content-coverage` 的聚合策略冲突。
- 生成式引擎能通过 `url` + `isRelatedTo` 理解「这是主实体的一个站点」。

**替代方案**：一级域名用独立 Organization 实体 — 保留意见，若未来 `renqizhi.com` 要做独立品牌，再单独变更处理。

### D6：sitemap / robots 跟随主域

**决策**：`bct/` 与 `hufang/` 下的 sitemap / robots / llms.txt 全部改用一级域名。

**理由**：sitemap 内的 URL 域名决定百度抓取哪个域名。若 sitemap 仍指向子域，百度会抓子域，与「一级域名为主」矛盾。

**注意**：`hufang/sitemap.php` 原为 `v.58.tl` 的 rewrite 指向它（因 v 与 www 共用 root）。新增 `hufangquan.com` vhost 后，需确认其 `/sitemap.xml` 的映射路径。

## Risks / Trade-offs

| 风险 | 影响 | 缓解 |
|------|------|------|
| canonical 变更引起排名波动 | 短期收录/排名可能下降 | 预期内；2~4 周后观察，不因短期波动回滚 |
| 未备案 + 境外 | 收录周期 2~6 个月 | 用户已接受；不承诺短期效果 |
| 保留生态关联 | 站群判定残余影响 | 用户定位决定；若未来要独立再评估 |
| 多域名并存 | 维护复杂（3 域名 × 2 业务） | D3 集中配置缓解 |
| `bct.58.tl` 已收录首页 | canonical 改动可能导致其首页掉出 | 可接受（目标是一级域名收录） |
| 服务器 nginx 需人工同步 | 配置未同步则 301 不生效 | 产出落地清单，验证 `curl -I` |

## Migration Plan

1. 代码改动：配置映射 + canonical 动态化 + sitemap/robots/llms 域名切换。
2. 服务器 nginx：新增一级域名 vhost，配置 `.cn` 301。
3. 百度平台验证 `renqizhi.com` → 获取 token → 填入配置 → `enabled = true`。
4. 命令行验证推送（`php site.php https://renqizhi.com/`），确认 `success`。
5. 观察 2~4 周，记录收录变化（对比变更前基线：各域名仅收录首页）。

**回滚**：域名映射集中配置（D3），回滚只需恢复配置；canonical 逻辑保留（可按映射开关）。

## Open Questions

- `hufang` 的 sitemap 在 `hufangquan.com` vhost 下的访问路径如何配置？（原为 `v.58.tl` rewrite 到 `/hufang/sitemap.php`）
- 主站 `index.php` 的生态导航该指向子域还是改为一级域名？（当前倾向保留子域，符合生态定位）
- `bct.58.tl` 的 canonical 收口后，是否还需要它自己的 sitemap？（倾向保留，但 sitemap 内 URL 用主域）

> 已确认：`hufangquan.com` 与 `renqizhi.com` 均需在百度平台验证并获取各自 token。
