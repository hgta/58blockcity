# 模特 / 作者自助申请与后台审核录入

## 问题陈述

模特库（`mall/model/list.php`）与作者库（`mall/author/list.php`）目前只有展示与关注功能，**没有任何用户自助加入的入口**：

1. 想当模特的用户只能线下联系管理员，无法自助提交个人资料与照片。
2. 想与平台合作（提供原创图案作品）的作者同样没有提交通道。
3. 管理员没有集中的申请列表，无法批量查看申请人资料、按联系方式联系、记录处理进度。
4. 录入模特/作者时（`mall/admin/models.php` / `authors.php`）需要手工重新填写用户已提交过的信息。

## 目标

建立「用户自助申请 → 后台审核联系 → 一键预填录入」的完整闭环：

- 模特库页新增「我要当模特」按钮 → 进入模特申请页（需登录），填写基本信息 + 联系方式 + 上传多张照片。
- 作者库页新增「我是作者，我要合作」按钮 → 进入作者合作申请页（需登录），填写作者资料 + 联系方式 + 上传多张作品照片。
- 同一用户同类型**不能重复申请**（待处理/已联系/已通过状态下禁止；被驳回后可重新申请）。
- 用户在个人中心可查看自己的申请列表与状态（待处理 / 已联系 / 已通过 / 已驳回，含驳回原因）。
- 后台新增「模特申请 / 作者合作申请」管理页：查看详情（照片大图、联系方式）、标记已联系、驳回（必填原因）、**一键预填录入**为正式模特/作者。
- 申请照片直接上传至正式目录 `assets/uploads/models|authors/YYYYMM/`，录入时路径直接复用，无需迁移。

## 范围

### In Scope

| 模块 | 内容 |
|------|------|
| 数据库 | 新增 `applications` 表（单表 + `type` 区分模特/作者）；提供迁移脚本并同步 `init/db-init.sql` |
| 业务层 | 新增 `classes/Application.php`：申请 CRUD、重复校验、状态流转、按用户/类型查询 |
| 前台申请 | `mall/apply/model.php`（模特申请）、`mall/apply/author.php`（作者申请）、`mall/apply/my.php`（我的申请） |
| 前台入口 | `mall/model/list.php` 与 `mall/author/list.php` 顶部入口按钮；个人中心 `mall/user/dashboard.php` 侧边栏「我的申请」 |
| 后台审核 | `mall/admin/applications.php`（列表，tab 切换类型）、`mall/admin/application-view.php`（详情 + 状态流转 + 一键预填） |
| 后台预填 | `mall/admin/models.php` / `authors.php` 支持 `apply_id` 参数自动填充表单 |
| 照片上传 | GD 压缩复用后台现有模式，直落正式目录 |

### Out of Scope

- 其他子站（`bct` / `block` / `nft` / `hufang`）不做申请入口。
- 不做申请的全自动审核（始终由管理员人工确认后录入）。
- 不做申请数据与 `users` 表资料的自动同步（如自动创建 user 关联）。
- 不做短信/邮件通知（申请状态变化仅站内展示，管理员通过联系方式线下沟通）。

## 影响范围

- **新增文件**：
  - `init/migrate-applications.sql`（建表，幂等）
  - `classes/Application.php`
  - `mall/apply/model.php`、`mall/apply/author.php`、`mall/apply/my.php`
  - `mall/admin/applications.php`、`mall/admin/application-view.php`
- **修改文件**：
  - `init/db-init.sql`（追加 `applications` 建表语句）
  - `mall/model/list.php`、`mall/author/list.php`（入口按钮）
  - `mall/user/dashboard.php`（侧边栏菜单）
  - `mall/admin/models.php`、`mall/admin/authors.php`（`apply_id` 预填支持）
- **数据库变更**：新增 `applications` 表

## 成功标准

1. 未登录用户点击「我要当模特 / 我是作者，我要合作」跳转登录页，登录后回到申请页。
2. 登录用户可提交模特/作者申请，照片成功上传至正式目录并可在后台查看。
3. 同类型存在「待处理/已联系/已通过」申请时，再次提交被拒绝并提示；被驳回后可重新提交。
4. 个人中心「我的申请」正确展示每次申请的类型、状态、时间与驳回原因。
5. 后台可分别查看模特申请与作者合作申请，支持查看大图、联系方式、标记已联系、驳回（必填原因）。
6. 「一键预填」从申请详情进入 models/authors 新建表单时自动填充对应字段与照片，管理员保存后生成正式记录并回写 `model_id`/`author_id`，申请状态自动置为「已通过」。
7. 全部输入输出做 `htmlspecialchars` 防 XSS，上传做类型/大小白名单校验，SQL 全部走 PDO 预处理。

## 参考

- `init/migrate-add-author.sql` — `authors` 表结构（字段风格）
- `init/db-init.sql` — `models` 表结构（第 1436 行起）
- `init/migrate_model_daily_photos.sql` — `models.daily_photos` 字段
- `mall/admin/models.php` — 模特录入表单字段 + `uploadModelAvatar()` GD 上传模式
- `mall/admin/authors.php` — 作者录入表单字段（含 `style`/`bio`/`author_works`）
- `mall/user/dashboard.php` — 个人中心侧边栏结构
- `includes/auth.php` — 登录校验（`$_SESSION['user_id']`、`$_SESSION['role']==='admin'`）
