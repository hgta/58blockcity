# 设计文档

## 总体思路

「提交 → 待处理 → 已联系 → 已通过/已驳回」状态闭环，模特与作者共用一张 `applications` 表（`type` 区分），差异字段合并存放：

- 共性字段（昵称/性别/城市/星座/联系方式/照片）与 `models`/`authors` 表同名同义，保证一键预填时字段直映。
- 模特特有：`age`/`height`/`weight`/`measurements`/`hobbies`；作者特有：`style`/`bio`。
- 照片上传阶段即落正式目录，录入时只读路径、不做文件迁移。
- 后台「录入为模特/作者」用 `apply_id` 预填表单，管理员确认后创建正式记录并回写关联，申请自动置为「已通过」。

## 数据层

### applications 表（新增）

```sql
CREATE TABLE IF NOT EXISTS `applications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type` enum('model','author') NOT NULL COMMENT '申请类型',
  `user_id` int(11) NOT NULL COMMENT '申请用户',
  `status` enum('pending','contacted','approved','rejected') DEFAULT 'pending' COMMENT '待处理/已联系/已通过/已驳回',
  `nickname` varchar(100) NOT NULL COMMENT '昵称/艺名',
  `gender` enum('男','女','保密') DEFAULT '保密',
  `age` tinyint(3) UNSIGNED DEFAULT NULL,
  `height` decimal(5,1) DEFAULT NULL,
  `weight` decimal(4,1) DEFAULT NULL,
  `measurements` varchar(50) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `zodiac` varchar(20) DEFAULT NULL,
  `hobbies` text,
  `style` varchar(50) DEFAULT NULL,
  `bio` text,
  `phone` varchar(20) DEFAULT NULL COMMENT '联系电话',
  `qq` varchar(20) DEFAULT NULL,
  `weixin` varchar(100) DEFAULT NULL,
  `weibo` varchar(200) DEFAULT NULL,
  `xiaohongshu` varchar(200) DEFAULT NULL,
  `photos` text COMMENT '照片/作品图 JSON 数组（已落正式目录）',
  `admin_remark` text COMMENT '后台备注',
  `reject_reason` varchar(255) DEFAULT NULL COMMENT '驳回原因（展示给用户）',
  `model_id` int(11) DEFAULT NULL COMMENT '录入后关联 model id',
  `author_id` int(11) DEFAULT NULL COMMENT '录入后关联 author id',
  `reviewed_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_type_status` (`type`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 迁移脚本（新增）

`init/migrate-applications.sql`：上述建表语句 + `CREATE TABLE IF NOT EXISTS` 幂等保护。
同步在 `init/db-init.sql` 追加相同建表语句（新增库直接建全）。

### 状态机

```
提交 ──► pending(待处理) ──► contacted(已联系) ──► approved(已通过)   ← 录入成功，关联 model_id/author_id
             │                    │
             └────────────────────┴──► rejected(已驳回)              ← 必填 reject_reason
```

- 状态流转仅后台可操作：`pending → contacted`（标记已联系）、`pending/contacted → rejected`（必填原因）、`pending/contacted → approved`（通过录入）。
- `approved` 时回写 `model_id` 或 `author_id` 与 `reviewed_at`。

### 重复申请规则

同一 `user_id` + 同一 `type`，若存在 `status IN ('pending','contacted','approved')` 的记录则拒绝新申请，页面提示「已有进行中的申请」；`rejected` 允许重新提交。应用层校验（提交时查询），不做 DB 唯一约束。

## 业务层（classes/Application.php）

方法清单：

| 方法 | 说明 |
|------|------|
| `create($type, $userId, $data, $photos)` | 落库，status=pending；重复申请则抛异常/返回错误 |
| `hasActive($type, $userId)` | 是否存在非 rejected 的申请（重复校验） |
| `getMyApplications($userId)` | 我的申请列表（倒序） |
| `getById($id)` | 详情（后台用） |
| `getList($type, $status, $page, $perPage)` | 后台列表 + 分页 + 状态筛选 |
| `getStats($type)` | 各状态数量（后台 tab 角标） |
| `updateStatus($id, $status, $extra)` | 状态流转：contacted / rejected(必填 reason) / approved(回写 model_id|author_id + reviewed_at) |
| `countByUser($type, $userId)` | 个人中心角标（可选） |

所有 SQL 使用 PDO 预处理；`photos` 存取用 `json_encode`/`json_decode`。

## 前台

### 申请页（mall/apply/model.php、author.php）

统一骨架：
1. 登录校验：`!isset($_SESSION['user_id'])` → `header('Location: ../auth/login.php?redirect=...')`（沿用现有跳转风格）。
2. 重复申请校验：`hasActive()` 为真 → 页面顶部提示「您已提交过模特/作者申请，请耐心等待审核」，不渲染表单。
3. 表单字段（POST 提交）：
   - 模特：`nickname* gender age height weight measurements city zodiac hobbies` + `phone qq weixin weibo xiaohongshu` + 多图 `photos[]`
   - 作者：`nickname* gender city zodiac style bio` + `phone qq weixin weibo xiaohongshu` + 多图 `works[]`
4. 提交处理：
   - `trim()` 收集字段；昵称必填、其余选填。
   - 多图上传（复用 `mall/admin/models.php` 的 GD 模式）：
     - 第一张图裁切为 400×400 头像存 `assets/uploads/models|authors/YYYYMM/`（作者侧若仅作品则并入作品数组）。
     - 全部原图 `move_uploaded_file` 存同目录，返回相对路径（如 `assets/uploads/models/202608/a1b2c3.jpg`）。
     - 类型白名单 `image/jpeg|jpg|png|gif|webp`，单文件 ≤5MB，文件名 `uniqid()` + 扩展名。
   - 照片路径数组 `json_encode` 存入 `photos`。
   - 成功后跳转 `my.php`（或回申请页提示成功）。

### 我的申请（mall/apply/my.php）

- 登录校验同上；按 `user_id` 拉取 `getMyApplications()`。
- 列表卡片：类型图标（模特/作者）、昵称、提交时间、状态徽章、照片缩略（最多 3 张）、驳回原因（rejected 时红字展示）。
- 状态徽章配色：待处理灰、已联系蓝、已通过绿、已驳回红。

### 入口按钮

- `mall/model/list.php`：头部工具栏/筛选区右侧新增「我要当模特」按钮（`<i class="fas fa-user-plus"></i>`），点击 `mall/apply/model.php`；未登录自动跳登录。
- `mall/author/list.php`：同样位置新增「我是作者，我要合作」按钮（`<i class="fas fa-handshake"></i>`），点击 `mall/apply/author.php`。
- `mall/user/dashboard.php` 侧边栏「我的关注」下方新增「我的申请」（`<i class="fas fa-file-signature"></i>` → `apply/my.php`）。

## 后台

### 列表页（mall/admin/applications.php）

- 权限校验：`$_SESSION['role'] !== 'admin'` → 跳登录/拒绝（沿用现有后台校验）。
- 顶部 tab：全部 / 模特申请 / 作者合作申请（`type` 筛选，可选 `status` 筛选）。
- 表格列：ID、类型、昵称、联系方式（手机/QQ/微信，最多展示前 2 项）、照片缩略、状态徽章、提交时间、操作。
- 操作列：「查看」→ `application-view.php?id=`。
- 分页 + 状态角标统计（`getStats`）。

### 详情页（mall/admin/application-view.php）

- 展示：基本信息全字段、联系方式块（手机/QQ/微信/微博/小红书，可复制）、照片墙（大图点击放大，`<a target="_blank">`）、提交用户（`user_id` → `users.username`）、提交时间。
- 状态流转操作（按当前状态渲染）：
  - `pending/contacted`：「标记已联系」（POST → `updateStatus('contacted')`）
  - `pending/contacted`：「驳回」——内联表单，**必填原因** → `updateStatus('rejected', ['reject_reason'=>...])`
  - `pending/contacted`：「录入为模特/作者」→ `location.href='models.php?apply_id='.$id` 或 `authors.php?apply_id='.$id`
  - 已通过：显示关联的 model/author 链接与 `reviewed_at`
- `admin_remark` 文本域：后台备注（可编辑保存）。

### 一键预填（mall/admin/models.php、authors.php）

- 入口处解析 `$_GET['apply_id']`：读取申请记录（校验 `type` 匹配当前页）。
- 若存在：用申请数据初始化 `$formData`，字段映射如下，照片列表直接预填：

**模特（apply → models）**

| 申请字段 | models 字段 |
|----------|-------------|
| nickname | nickname |
| gender | gender |
| age | age |
| height | height |
| weight | weight |
| measurements | measurements |
| city | city |
| zodiac | zodiac |
| hobbies | hobbies |
| qq/weixin/weibo/xiaohongshu | 同名 |
| photos[0] | avatar |
| photos 全部 | daily_photos |

**作者（apply → authors）**

| 申请字段 | authors 字段 |
|----------|-------------|
| nickname | nickname |
| gender | gender |
| city | city |
| zodiac | zodiac |
| style | style |
| bio | bio |
| qq/weixin/weibo/xiaohongshu | 同名 |
| photos[0] | avatar |
| photos 全部 | author_works |

- 提交成功创建正式记录后：回写 `applications.model_id`/`author_id` + `status='approved'` + `reviewed_at`（在 models.php/authors.php 的创建成功分支按 `apply_id` 执行 `updateStatus`）。
- 页面顶部提示条：「来自 {{昵称}} 的申请预填，请核对后保存」。

## 安全与健壮性

| 项 | 处理 |
|----|------|
| 登录 | 前台申请/我的申请：`$_SESSION['user_id']`；后台：`$_SESSION['role']==='admin'` |
| XSS | 所有输出 `htmlspecialchars()`（含照片 URL、昵称、备注、驳回原因） |
| SQL | 全部 PDO 预处理绑定 |
| 上传 | 类型白名单 + ≤5MB + 文件名 `uniqid()` 随机化 + 目录按月分桶 |
| 重复申请 | 提交时 `hasActive()` 应用层拦截 |
| 驳回必填 | 后台「驳回」未填原因则拒绝并提示 |
| 越权 | 详情/状态流转校验 `apply_id` 归属与 `type` 匹配 |
| 历史数据 | 新表，无兼容问题；照片冗余文件由管理员手动清理（不在本次范围） |
