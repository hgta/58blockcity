# 任务：统一全站用户头像

## Phase 1 — 核心方法与上传端

- [x] 1.1 `classes/User.php` 新增静态方法 `avatarUrl($avatar)`（见 design.md §2：默认图 / 绝对URL / 根相对 / assets→uploads 归一 / 裸名→uploads/avatars / 主站前缀）
- [x] 1.2 `bct/user/profile.php`：上传目录改 `dirname(__DIR__, 2) . '/assets/images/uploads/avatars/'` + mkdir 守卫；删除旧头像路径同步改
- [x] 1.3 `hufang/user/profile.php`：同上改造（与 bct 几乎同构）
- [x] 1.4 `mall/user/profile.php`：上传目录改 `dirname(__DIR__, 2) . '/assets/images/uploads/avatars/'` + mkdir 守卫；存值去掉 `assets/` 前缀，改为 `uploads/avatars/<file>`

## Phase 2 — 展示端全站替换

- [x] 2.1 `assets/shared/messages-core.php`：全局函数 `avatarUrl()` 改为委托 `User::avatarUrl()`
- [x] 2.2 全站 PHP `<img src>` 用户头像展示点统一替换为 `User::avatarUrl()` + `onerror` 兜底默认图，逐站核对：
  - [x] bct：user/profile.php（上传 + 2 处展示）
  - [x] club：index.php、post.php、includes/sidebar.php、user/dashboard.php、search.php
  - [x] block：user/dashboard.php（首字头像 vw-owner-avatar / 城市头像非 img 不动）
  - [x] nft：includes/sidebar.php、user/dashboard.php、ranking/index.php、ranking/user.php（NFT base_image 不动）
  - [x] bid：user/dashboard.php
  - [x] hufang：circles/*、rankings 相关、user/*、admin 相关、includes/functions.php、index_v1.php
  - [x] mall：user/*（profile/dashboard/security/shops/following）、shop/orders.php、shop/manage.php（buyer_avatar）、index/model/author 的 user_avatar 兜底（模特/作者专属头像不动）
  - [x] 其他全站 grep 命中的用户头像 `<img>`（7 种旧拼接方式归零）
- [x] 2.3 前端 JS 头像（assets/js/message-modal.js 等）：预置 `window.AVATAR_BASE` / `window.DEFAULT_AVATAR` 全局常量（shared/header.php 输出），JS 实现与 `avatarUrl()` 相同判断逻辑 + onerror 兜底

## Phase 3 — 迁移与收尾

- [x] 3.1 新建 `init/migrate-avatar.sql`：幂等 SQL 归一化存量（空值→default.jpg、`assets/uploads/avatars/`→`uploads/avatars/`、`/assets/images/` 脏值归一、裸名→`uploads/avatars/` 前缀，排除 default.jpg）
- [x] 3.2 `init/migrate-avatars-files.sh`：一次性文件迁移脚本（cp -n 复制 bct/hufang/mall 三处存量头像到主仓库 `assets/images/uploads/avatars/`，幂等）
- [x] 3.3 全站 lint 检查 + grep 复查无残留旧拼接（`../assets/images/` . avatar、`https://58.tl/assets/images/` . avatar 等 7 种写法归零）
- [x] 3.4 核对不影响商品图/模特/NFT/作者头像（normalizeImageUrl 商品逻辑保留）

## Phase 4 — 上线验证（部署后执行）

- [ ] 4.1 执行 `init/migrate-avatars-files.sh` + `init/migrate-avatar.sql`（幂等）后，bct / hufang / mall 旧头像在全部子站正常显示
- [ ] 4.2 任一子站换头像 → 全站同步更新（文件落主仓库目录）
- [ ] 4.3 无头像用户全站显示统一默认图，无 404 无破图
- [ ] 4.4 站内信弹窗（JS 渲染）头像一致
- [ ] 4.5 迁移脚本重复执行结果不变（幂等）
