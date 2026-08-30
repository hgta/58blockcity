# 模特 / 作者自助申请与后台审核录入 — 任务清单

## Phase 1: 数据库

### 1.1 迁移脚本
- [x] 新增 `init/migrate-applications.sql`
  - `CREATE TABLE IF NOT EXISTS applications`（单表 + `type` 区分）
  - 字段：type / user_id / status / nickname / gender / age / height / weight / measurements / city / zodiac / hobbies / style / bio / phone / qq / weixin / weibo / xiaohongshu / photos / admin_remark / reject_reason / model_id / author_id / reviewed_at / created_at
  - 索引：`idx_user`、`idx_type_status`
  - 带中文 COMMENT

### 1.2 同步建表语句
- [x] `init/db-init.sql` 追加 `applications` 建表语句（与迁移脚本一致）

---

## Phase 2: 业务层

### 2.1 新增 classes/Application.php
- [x] 构造：接收 `$pdo`
- [x] `create($type, $userId, $data, $photos)`：写入 status=pending，重复申请拦截
- [x] `hasActive($type, $userId)`：存在非 rejected 申请返回 true
- [x] `getMyApplications($userId)`：按 user_id 倒序列表（含 status/reject_reason/photos）
- [x] `getById($id)`：详情
- [x] `getList($type, $status, $page, $perPage)`：后台列表 + 分页
- [x] `getStats($type)`：各状态数量
- [x] `updateStatus($id, $status, $extra)`：contacted / rejected(必填 reason) / approved(回写 model_id|author_id + reviewed_at)
- [x] photos 用 `json_encode`/`json_decode` 存取；全部 PDO 预处理

---

## Phase 3: 前台申请页

### 3.1 模特申请（mall/apply/model.php）
- [x] 登录校验：未登录跳 `mall/auth/login.php`
- [x] 重复申请校验：`hasActive('model', $userId)` 为真 → 提示并隐藏表单
- [x] 表单：nickname* / gender / age / height / weight / measurements / city / zodiac / hobbies + phone / qq / weixin / weibo / xiaohongshu + 多图 `photos[]`
- [x] 多图上传（GD 复用后台模式）：首图裁切 400×400 头像，其余原图直存 `assets/uploads/models/YYYYMM/`
- [x] 类型白名单 + ≤5MB + `uniqid()` 文件名
- [x] 提交成功落库 photos JSON，跳转 my.php

### 3.2 作者申请（mall/apply/author.php）
- [x] 登录校验 + 重复申请校验（`hasActive('author', ...)`）
- [x] 表单：nickname* / gender / city / zodiac / style / bio + phone / qq / weixin / weibo / xiaohongshu + 多图 `works[]`
- [x] 多图上传直存 `assets/uploads/authors/YYYYMM/`（首图作头像）
- [x] 提交成功落库，跳转 my.php

### 3.3 我的申请（mall/apply/my.php）
- [x] 登录校验，`getMyApplications($userId)` 列表
- [x] 卡片：类型图标、昵称、时间、状态徽章（灰/蓝/绿/红）、照片缩略（≤3）、驳回原因红字展示

---

## Phase 4: 前台入口

### 4.1 模特库入口
- [x] `mall/model/list.php` 头部新增「我要当模特」按钮 → `mall/apply/model.php`

### 4.2 作者库入口
- [x] `mall/author/list.php` 头部新增「我是作者，我要合作」按钮 → `mall/apply/author.php`

### 4.3 个人中心入口
- [x] `mall/user/dashboard.php` 侧边栏新增「我的申请」→ `mall/apply/my.php`

---

## Phase 5: 后台审核

### 5.1 列表页（mall/admin/applications.php）
- [x] 权限校验 `$_SESSION['role']==='admin'`
- [x] tab：全部 / 模特申请 / 作者合作申请（type + status 筛选）
- [x] 表格：ID、类型、昵称、联系方式（手机/QQ/微信）、照片缩略、状态徽章、时间、操作
- [x] 分页 + 各状态角标（getStats）

### 5.2 详情页（mall/admin/application-view.php）
- [x] 全字段展示 + 联系方式块 + 照片墙（新窗口大图）+ 提交用户 + 时间
- [x] 「标记已联系」→ status=contacted
- [x] 「驳回」→ 必填 reject_reason → status=rejected
- [x] 「录入为模特/作者」→ 跳 `models.php?apply_id=` / `authors.php?apply_id=`
- [x] 已通过：显示关联 model/author 链接 + reviewed_at
- [x] admin_remark 备注编辑保存

### 5.3 一键预填（mall/admin/models.php）
- [x] 解析 `$_GET['apply_id']`，type 校验为 model
- [x] 申请数据预填 `$formData`（映射：nickname/gender/age/height/weight/measurements/city/zodiac/hobbies/qq/weixin/weibo/xiaohongshu）
- [x] photos[0] 预填 avatar、全量预填 daily_photos
- [x] 创建成功分支回写：applications.model_id + status=approved + reviewed_at
- [x] 顶部提示条：「来自 {{昵称}} 的申请预填，请核对后保存」

### 5.4 一键预填（mall/admin/authors.php）
- [x] 解析 `$_GET['apply_id']`，type 校验为 author
- [x] 申请数据预填 `$formData`（映射：nickname/gender/city/zodiac/style/bio/qq/weixin/weibo/xiaohongshu）
- [x] photos[0] 预填 avatar、全量预填 author_works
- [x] 创建成功分支回写：applications.author_id + status=approved + reviewed_at
- [x] 顶部提示条同上

---

## Phase 6: 验证（需部署后手动验证）

### 6.1 申请流程
- [ ] 未登录点击入口按钮 → 跳登录，登录后回到申请页
- [ ] 登录后提交模特申请（含多图）→ 成功，照片出现在 `assets/uploads/models/YYYYMM/`
- [ ] 提交作者合作申请（含作品图）→ 成功，照片出现在 `assets/uploads/authors/YYYYMM/`
- [ ] 有进行中申请时再次提交被拦截并提示
- [ ] 被驳回后重新提交成功

### 6.2 我的申请
- [ ] 个人中心「我的申请」正确显示类型/状态/时间/照片缩略
- [ ] 驳回后能看到驳回原因（红字）

### 6.3 后台审核
- [ ] 后台 tab 正确区分模特申请 / 作者合作申请
- [ ] 详情页照片墙可新窗口查看大图，联系方式完整
- [ ] 标记已联系 → 状态变蓝
- [ ] 驳回必填原因，缺省被拒绝

### 6.4 一键预填
- [ ] 模特申请 → 「录入为模特」→ models.php 表单自动填充 + 照片预填，保存后生成模特且申请状态置为已通过并关联
- [ ] 作者申请 → 「录入为作者」→ authors.php 同理
- [ ] 预填后手动改错字段保存，正式记录以管理员最终提交为准

### 6.5 安全
- [ ] 未登录访问 apply/* 被重定向
- [ ] 非 admin 访问 admin/applications.php 被拒绝
- [ ] 提交非法文件类型/超 5MB 被拒绝
