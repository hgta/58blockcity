## Context

背景见 proposal.md。58 生态各子站（bid/club/bct/mall/hufang…）共享同一套基础：统一 `users` 表 + `.58.tl` cookie 登录态、`shared/header.php`/`shared/footer.php` 渲染站点头尾、`config/seo.php` subdomains 登记子域名、`shared/admin/admin-menu-config.php` 登记后台站点与菜单、`config/database.php` 提供 PDO。人气值账本 `user_city_popularity(user_id, city, popularity)` 是"用户×城市"维度，`classes/UserPopularity.php` 已提供事务性 `transferPopularity()` 双人同城划转（余额不足即回滚）。互访圈 hufang 有上传截图 + 压缩的先例（`hufang/includes/functions.php:compressImage`）。鉴权、CSRF、闪存、通知、站内信均已具备（`includes/auth.php`、`classes/Notification.php`、`classes/Message.php`）。

## Goals / Non-Goals

**Goals:**
- 用与 bid 同等的工程量，新增 `task/` 子站（task.58.tl），行为完全对齐 spec（plaza + skills）。
- 人气值结算直接复用 `UserPopularity` 同一账本与划转 API，不引入新账户/托管表。
- 现金任务零站内资金动作，仅状态推进与线下说明。
- 新增功能可通过既有后台框架管理（类别、任务、认领争议仲裁）。

**Non-Goals:**
- 不建立 escrow/冻结/托管资金模型（spec 明确"不托管，验收后直接划转"）。
- 不在本站内实现"打卡/做市长"玩法本体，任务仅承载描述与凭证。
- 不做 L2 的互访 `visits` 记录打通（v2 候选）。
- 不做第三方支付网关接入、不做提现。

## Decisions

### D1：新子站结构采用 bid 样板

`task/` 目录结构仿 `bid/`：

```
task/
  index.php           任务广场（列表/筛选/分页）
  create.php          发布任务（雇主）
  view.php            任务详情（含领取按钮、各认领动态）
  my.php              我的发布 / 我的承接（tab）
  user/
    dashboard.php     个人中心（任务站内：我的发布/承接汇总）
    skills.php        技能卡编辑（求职侧）
    claims.php        我的承接列表
  auth/               登录/注册/登出代理 → require ../../auth/login.php 等
  includes/
    header.php        site_config + require ../../shared/header.php
    footer.php        require ../../shared/footer.php
    auth.php          require ../../includes/auth.php
  admin/              后台：dashboard / categories / tasks / claims / disputes
  uploads/task_proofs/  凭证图片（gitignore）
  assets/             本站少量样式（必要时），主体用 shared assets
```

页面共同模板引用共享层（参照 `bid/includes/header.php` 模式）。伪静态如需要再补 `.htaccess`，首期沿用 `?id=` 动态查询（bid 同款）。

### D2：数据库模型（`init/migrate-task.sql`）

所有新表统一 `CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci`（与 `cities`、既有较新表对齐；避免与一般表比较时重蹈 collation 混用报错，若未来 JOIN 老 general_ci 表则加 `COLLATE` 显式对齐，参照此前 1267 修复经验）。

```sql
-- 任务类别（后台可维护，发布/广场/技能卡共用启用集合）
task_categories(
  id INT PK AI, name VARCHAR(50), sort_order INT DEFAULT 0,
  status ENUM('active','inactive') DEFAULT 'active', created_at DATETIME
)
-- 种子：代互访、代打卡、代做市长、其他（可按需扩展）

-- 任务
tasks(
  id INT PK AI,
  employer_id INT NOT NULL,            -- users.id
  category_id INT NOT NULL,            -- task_categories.id
  title VARCHAR(120), description TEXT,
  city VARCHAR(50) NULL,               -- 关联城市名（人气值任务必填，用于结算账本定位）
  target_type ENUM('block','circle') NULL, -- L1 导流对象类型（区块/互访圈）
  target_id VARCHAR(64) NULL,          -- 对象标识（块号或圈子ID）
  reward_type ENUM('popularity','cash') NOT NULL,
  reward_amount INT NOT NULL,          -- 人气值为整数；现金单位为分（存储统一无浮点）
  quota INT NOT NULL DEFAULT 1,        -- 名额，1=单人
  claimed_count INT NOT NULL DEFAULT 0, -- 冗余计数（事务内递增）
  review_days INT NOT NULL DEFAULT 3,  -- 交付后雇主验收期限(天)
  expire_at DATETIME,                  -- 失效时间（到期不再接受新领取）
  status ENUM('open','closed') DEFAULT 'open', -- closed=雇主手动关闭/到期后台惰性关闭
  created_at DATETIME, updated_at DATETIME,
  KEY(employer_id), KEY(category_id), KEY(city), KEY(status, expire_at)
)

-- 认领（每个名额一条）
task_claims(
  id INT PK AI,
  task_id INT NOT NULL,
  worker_id INT NOT NULL,
  status ENUM('accepted','submitted','rejected','settling','completed','cancelled','disputed') DEFAULT 'accepted',
    -- accepted=已领取待交付; submitted=已交待验收; rejected=被驳回可补交;
    -- settling=验收通过待结算; completed=已结算; cancelled=取消; disputed=争议中
  proof_text TEXT NULL,
  proof_image VARCHAR(255) NULL,       -- uploads/task_proofs/xxx.jpg
  employer_note VARCHAR(255) NULL,     -- 验收意见/驳回原因
  review_due_at DATETIME NULL,         -- 交付后 deadline = 交件时间 + review_days
  claimed_at DATETIME, submitted_at DATETIME NULL,
  reviewed_at DATETIME NULL, settled_at DATETIME NULL,
  UNIQUE(task_id, worker_id),          -- 一人一份
  KEY(worker_id), KEY(status)
)

-- 技能卡
task_skills(
  id INT PK AI, user_id INT NOT NULL,
  description VARCHAR(500), ref_price VARCHAR(50) NULL,  -- 参考价自由文本（现金/人气值皆可）
  status ENUM('active','inactive') DEFAULT 'active',
  created_at DATETIME, updated_at DATETIME,
  UNIQUE(user_id)
)
task_skill_categories(skill_id INT, category_id INT, PRIMARY KEY(skill_id, category_id))

-- 评价
task_reviews(
  id INT PK AI, task_id INT, claim_id INT,
  from_user_id INT, to_user_id INT,
  rating TINYINT(1..5), content VARCHAR(500), created_at DATETIME,
  UNIQUE(claim_id, from_user_id)       -- 一方仅一次
)

-- 争议（admin 仲裁）
task_disputes(
  id INT PK AI, claim_id INT UNIQUE,
  initiator_id INT, reason VARCHAR(500),
  status ENUM('open','resolved') DEFAULT 'open',
  resolution ENUM('settle','cancel') NULL,
  admin_id INT NULL, admin_note VARCHAR(500) NULL,
  created_at DATETIME, resolved_at DATETIME NULL
)
```

资金口径：
- 人气值任务的 `reward_amount` 为整数人气值；结算时按任务 `city` 做 `UserPopularity::transferPopularity(employer_id, worker_id, city, reward_amount)`。
- 现金任务金额**不入库为金额数字**（避免误解为站内账），`reward_amount` 仅对 popularity 使用；现金任务详情页只展示发布者填写的"赏金说明"文本（放 description 或单独 `cash_desc`）。→ 简化：统一用 `reward_amount` 存数值 + `reward_type` 区分单位（人气值整数 / 现金"分"），展示层格式化。避免两套字段。
- 说明里实际可见，现金金额以"分"存 integer，展示转元，不产生任何资金变动。

### D3：结算状态机（关键路径）

```
领取 accepted ──提交凭证──▶ submitted ──雇主通过──▶ settling ──划转成功──▶ completed
      │                        │                       │
      │ 超时/雇主关任务(cancel) │ 雇主驳回               └─余额不足─▶ 停在 settling，
      ▼                        ▼                           通知雇主补足后重试划转
  cancelled                rejected ──接单补交──▶ submitted（review_due 重置）
      ▲                        │
      └──仲裁取消(dispute)      └──任一方发起争议──▶ disputed ──admin裁决──▶ completed / cancelled
```

- 满额：`claimed_count >= quota` 后不可再领取（广场显示"已满"）；名额释放仅当某认领在 `accepted/submitted` 被取消且任务仍 open 时 `claimed_count-1` 并发通知候选——**首期取消场景仅限：雇主关闭任务前对未完成认领整体取消**，不实现"有人放弃我顶上"（保留名额，见 Open Questions）。为避免表设计复杂，首期不自动补名额。

实际简化说明：为控制首期范围，"取消/仲裁"只作用于认领状态与任务状态，不影响任务 `claimed_count` 回补（名额槽位不做自动再分配）。

### D4：人气值结算与余额校验

人气值认领在 `settling` 时调用 `UserPopularity::transferPopularity`：
- 成功 → 认领 `completed`，写 `task_reviews` 触发窗口，并给双方通知。
- 失败（雇主该城余额不足）→ 认领**保持 settling**，通知雇主"补足后在我的承接/详情页重试结算"。这里不新建"平台账户"，不扣任何中间人。

现金认领：`settling → completed` 无需划转，仅状态推进；页面标注"线下完成，平台不托管"。

### D5：技能卡与"找承接人"

`task/skills.php`（编辑，登录）与公开的"找承接人"列表（可按类别筛）。复用 `User::avatarUrl()` 渲染头像。联系承接人落地到既有站内信入口：任务站详情/技能卡展示对应用户主页链接（`../user/profile` 各站有个人中心跳转方式），首期通过"查看主页"转既有私信路径，不在任务站复制一套站内信 UI。→ 需要确认具体对接 URL，见 Open Questions。

### D6：凭证上传

复用 hufang 同款做法：`uploads/task_proofs/`，校验 `image/jpeg|png|gif`、`move_uploaded_file` + `compressImage`。`compressImage` 目前仅存在于 `hufang/includes/functions.php`，任务站 includes 代理需把它升级为共享：将 `compressImage` 上移到 `includes/functions.php`（根共享层），hufang 处改引共享版本或保留副本并统一——**决定：把 `compressImage` 复制进根 `includes/functions.php`（函数已有 `function_exists` 守卫模式则直接加同名函数即可共存）**，任务站 require 根 functions。

实际上需先检查根 `includes/functions.php` 是否已被页面 require。若站点普遍未 require 根 functions，则任务站 includes/header.php 自行 `require_once __DIR__.'/../../includes/functions.php'`。

### D7：后台管理

在 `shared/admin/admin-menu-config.php` 增加：

```php
$ADMIN_SITES['task'] = ['name'=>'任务广场','url'=>'https://task.58.tl/admin/dashboard.php','icon'=>'fa-tasks'];
$ADMIN_MENUS['task'] = [
   ['icon'=>'fa-home','text'=>'任务看板','url'=>'dashboard.php'],
   ['icon'=>'fa-tags','text'=>'类别管理','url'=>'categories.php'],
   ['icon'=>'fa-tasks','text'=>'任务列表','url'=>'tasks.php'],
   ['icon'=>'fa-hand-paper','text'=>'认领管理','url'=>'claims.php'],
   ['icon'=>'fa-gavel','text'=>'争议仲裁','url'=>'disputes.php'],
];
```

`task/admin/*` 复用 `shared/admin/admin-header.php`/`admin-auth.php` 体系。仲裁页支持对 `disputed` 认领裁决 settle/cancel，联动认领/任务/名额与通知。

### D8：站点入口登记（4 处）

1. `config/seo.php` `subdomains` 加 `'task' => 'https://task.58.tl'`。
2. 根 `index.php`：顶部导航按钮 + eco-grid 卡片 + 页脚快速链接，加"任务广场"。
3. `shared/footer.php` cross-site-links + 快速链接，加任务广场卡片。
4. `shared/admin/admin-menu-config.php`（见 D7）。
5. （补充）robots/sitemap 由 seo.php subdomains 自动覆盖，无需手改。

### D9：权限模型

- 发布/领取/提交凭证/验收/评价/技能卡：登录用户且认领归属正确（`claim.worker_id == uid`、`task.employer_id == uid`）。
- 非本人任务不能验收/评价；自己任务不能领取。
- admin：仅在 `task/admin/*` 内用 `checkAdmin()` 鉴权，可查看全量、裁决争议。
- 页面数据访问用 PDO 预处理 + CSRF 校验（复用 `generateCsrfToken`/`verifyCsrfToken`）。

## Risks / Trade-offs

- [不托管导致雇主跑单/验收后不划转] → spec 已选"验收后直接转"，靠实名用户体系 + 双向评价 + 可仲裁的争议通道抑制；人气值划转在事务内，余额不足会明示并重试，不会半途。
- [现金线下交易无平台保障] → UI 与规则明确"平台不托管、线下完成、风险自担"，并在任务详情显著提示；平台可提供交易备注/凭证上传留痕。
- [claimed_count 冗余计数与状态不一致风险] → 领取/取消在事务内递增递减；取消不自动回补名额槽位，简化首期逻辑。
- [collation 混用] → 新表统一 unicode_ci；若与新表比较的既有列是 general_ci，按 `cities.name` 关联处显式 `COLLATE`（沿用此前修复经验）。
- [compressImage 共享化动到 hufang] → 采用"根 includes 增加 + 带 function_exists 守卫"，不动 hufang 调用点，风险低。
- [技能卡联系闭环弱（首期跳既有主页/私信）] → 避免在任务站重建站内信，控制范围；v2 可做站内即时对话聚合。

## Migration Plan

1. 合入 `init/migrate-task.sql`（幂等：`CREATE TABLE IF NOT EXISTS` + 种子类别 `INSERT IGNORE`），在 phpMyAdmin 或 CLI 执行一次。
2. 新增 `task/` 目录全套页面、`classes/Task*.php`、`task/admin/*`。
3. 上线登记：`config/seo.php`、根 `index.php`、`shared/footer.php`、`shared/admin/admin-menu-config.php`。
4. DNS/站点配置将 task.58.tl 指向 `task/`（部署环节）。
5. 回滚：删除登记条目 + 保留页面文件即可下线；表可保留（无破坏性字段变更，不需 drop）。全程不影响既有表数据。

## Open Questions

- 承接人详情/私信具体 URL：任务站跳既有个人主页/站内信的确切入口需在实现时对 58.tl 侧核对（不同子站个人中心路径存在差异），不影响 spec/表结构。
- "有人放弃认领是否自动放给候补/回到广场"：首期确认不自动回补名额槽位（Open 状态保持但名额已占满即显示已满），是否接受见 apply 时确认。
- 现金金额入库存"分"或仅文本说明：实现时定（倾向存整数分 + 展示格式化，避免展示浮点），不影响 spec。
