# 设计：help 子站接入百度主动推送

## 前提事实

| 事实 | 证据 |
|---|---|
| `admin/help-articles.php` 无任何推送调用 | 检索 `SeoHelper` / `pushContentUrl` / `push` 均零命中 |
| 其他内容类型已接入 | `hufang/circles/create.php:62`、`mall/shop/products.php`、`nft/nft/sell.php`、`classes/Post.php` |
| 推送按 URL host 选 token，取不到则静默跳过 | `classes/SeoHelper.php:528-553`（`pushContentUrl`）、`:379-419`（`resolvePushCredentials`） |
| `sites` 映射无 `help.58.tl` | `config/seo-sample.php:17-33`（10 子域 + 2 一级域） |
| `site.php` 默认入口含 301 死链 | `site.php:27` → `https://www.58.tl/help/help.html` |
| curl 超时 20s，同步执行 | `classes/SeoHelper.php:468` `CURLOPT_TIMEOUT => 20` |
| 推送链路前置变更已完成 | `fix-baidu-push-pipeline`（`isComplete: true`） |
| 各子站接入变更未覆盖 help | `per-subsite-baidu-indexing` 覆盖建站时 9 子域 |
| 文章下架返回 410 | `help/pages/article.php:28` |

---

## D1：推送目标为 `help.58.tl`（硬依赖批次 A）

**决策**：推送 URL 一律用 `https://help.58.tl/article/{slug}` 形式，**绝不推送 `www.58.tl/help/…`**。

**理由**：A 已确定 `help.58.tl` 为权威域、`www.58.tl/help/*` 全路径 301。推送非规范地址会被 301 跳走，等同于浪费配额且给百度错误信号。

**顺序**：A 上线前，本变更的代码可以写，但**推送结果应为空**（未配置 token 时静默跳过）。tasks 里把"A 已上线"列为 0.1 前置。

---

## D2：触发时机 —— 只在「状态跃迁到 published」时推

**决策**：

| 操作 | 是否推送 | 理由 |
|---|---|---|
| 新建且 status=published | ✅ 推 | 新链接，正是推送接口的目标场景 |
| 编辑：非 published → published | ✅ 推 | 首次可访问，等同新链接 |
| 编辑：已是 published，仅改正文 | ❌ 不推 | 百度普通推送接口面向**新链接**；重复推同一 URL 只消耗配额。更新靠 sitemap 的 `lastmod`（A 已补） |
| 下架：published → 其他 | ❌ 不推（走死链） | 见 D5 |
| FAQ / 术语新增 | ✅ 重推聚合页 | FAQ/术语无独立 URL，新增内容体现在 `/faq`、`/glossary` 上 |

**判据实现**：保存前先读旧记录的 `status`，与提交值比较，识别是否发生"跃迁到 published"。

---

## D3：节流 —— 同一 URL 冷却期内不重复推

**决策**：新增 `help_push_log` 表（`url` 主键、`pushed_at`、`action`），推送前查询上次推送时间，距今小于冷却期（默认 24 小时，可配）则跳过。

**理由**：百度按站点分配每日推送配额，帮助文章总量不大但后台可能被频繁保存（自动摘要、slug 生成都触发保存）。没有节流会在短时间内烧掉配额。

**为什么不用 `system_settings` 或文件**：URL 级别的记录需要按条查询，表是最直接的；且后续死链清单也能复用同一张表。

**字段设计**：

```sql
CREATE TABLE help_push_log (
  url       VARCHAR(255) NOT NULL,
  action    VARCHAR(16)  NOT NULL DEFAULT 'push',  -- push | dead
  pushed_at DATETIME     NOT NULL,
  PRIMARY KEY (url),
  KEY idx_pushed (pushed_at)
);
```

---

## D4：失败隔离 —— 推送 MUST NOT 影响后台保存

**决策**：在 help 侧封装 `help_push_url($url)`（放 `help/_init.php`），内部做：节流检查 → `try/catch` 包住 `SeoHelper::pushContentUrl()` → 任何异常只记日志，不向上抛。

**理由**：`baiduPush()` 是同步 curl，超时 20s（`:468`）。若不加隔离：

- 百度接口慢或不可达 → 后台点"保存"要卡 20 秒；
- curl 抛异常 → 直接中断保存流程，文章内容可能已入库但提示失败，产生数据一致性困惑。

**注意**：`SeoHelper::pushContentUrl()` 本身在未配置 token 时是静默 return（不抛异常），所以"未配置"场景天然安全；隔离主要针对网络层与 DB 层异常。

**不做异步**：本期保持同步 + 隔离。若实测发现 20s 卡顿明显，后续再改队列/异步（记录为遗留项）。

---

## D5：死链处理 —— 生成清单，平台侧提交（可选，P2）

**决策**：已下架（status 非 published）与 slug 已变更的旧 URL 写入 `help_push_log`（`action='dead'`），由新增的 `help/deadlinks.php` 输出纯文本清单（每行一个 URL），在百度搜索资源平台的死链提交入口登记该文件地址。

**理由**：百度的死链提交是**平台侧提交死链文件**（txt 格式，每行一条 URL），不是普通推送接口能代劳的。因此代码侧只需维护清单并暴露一个可访问的地址，提交动作在平台完成一次即可。

**slug 变更的特殊处理**：改 slug 会产生"旧 URL 已 404/410、新 URL 未推送"的状态。保存时应：旧 URL 入死链清单 + 新 URL 触发推送。slug 字段在后台可编辑（`admin/help-articles.php`），需在保存逻辑里比对新旧 slug。

**成本提示**：该项涉及保存逻辑改造，与 C 批次同改 `admin/help-articles.php`。建议 C 完成后再接，或合并实施。

---

## D6：配置结构 —— 只加一项，不改结构

**决策**：`config/seo-sample.php` 的 `sites` 增加：

```php
'help.58.tl' => ['token' => '', 'enabled' => false],
```

**理由**：`per-subsite-baidu-indexing` 已把配置结构设计成「每子域一组 token/site/enabled」，help 只是缺一项，不需要改结构。`resolvePushCredentials()` 会自动识别。

**部署侧**（非代码）：复制生成的 `config/seo.php` 中填入验证后的 token 并置 `enabled=true`。该文件已在 `.gitignore`，不入库。

---

## D7：`site.php` 批量工具修正

**决策**：默认入口列表移除 `https://www.58.tl/help/help.html`（已 301），补入 `https://help.58.tl/`；`site.php` 已支持命令行传入任意 URL，批量补推 help 历史文章可直接：

```
php site.php https://help.58.tl/article/a https://help.58.tl/article/b ...
```

**理由**：默认列表是"入口页"性质的示例，含死链会误导后续使用；help 作为第 11 个已接入子域应有代表入口。

**不做**：不新增"从 sitemap 自动拉取全量 URL 推送"的能力——批量补推用命令行传参即可满足，避免扩大范围。

---

## 风险与权衡

| 项 | 选择 | 放弃的 |
|---|---|---|
| 触发时机 | 仅状态跃迁到 published | 每次保存都推（烧配额） |
| 节流 | `help_push_log` 表 + 24h 冷却 | 无节流 / 用 settings 存时间戳 |
| 失败处理 | 同步 + try/catch 隔离 | 改异步队列（成本高，本期不做） |
| 死链 | 生成 txt 清单 + 平台提交 | 无处理（旧 URL 长期滞留索引） |
| 配置 | sites 增一项 | 改配置结构 |

**遗留**

- 推送异步化（若实测 20s 卡顿明显）。
- 推送结果目前在 `error_log` 里，后台无可见性——若需要"推送成功/失败"的后台反馈，另开任务（与 `help-content-admin` 任务 4.3「同步失败的可见化提示」同类诉求）。
