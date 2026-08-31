# 提案：统一全站用户头像（Single Source of Truth）

## 背景

58 生态多子站（block / nft / mall / bct / club / hufang / bid / v）共享同一 MySQL 数据库（`users.avatar` 一个字段），但**文件系统分散 + 各站代码拼接各写各的**，导致头像"有的行有的不行"。

### 部署拓扑（nginx 已确认）

```
58.tl / v.58.tl      → root = 主仓库根 (58blockcity/)
mall/nft/block/bid/club → root = 各子站目录
bct/ hufang/         → 无独立域名，走主站 / v.58.tl 下的路径
```

### 核心问题：三处不统一

**① 写入格式不统一（users.avatar 字段值）**

| 上传入口 | 物理落盘 | users.avatar 存的值 |
|---|---|---|
| 主站早期 | `assets/images/uploads/avatars/` | 裸名 `avatar_1_*.jpg` |
| `bct/user/profile.php` | `bct/assets/images/uploads/avatars/` | `uploads/avatars/xxx` |
| `hufang/user/profile.php` | `hufang/assets/images/uploads/avatars/` | `uploads/avatars/xxx` |
| `mall/user/profile.php` | `mall/assets/uploads/avatars/` | `assets/uploads/avatars/xxx` |

**② 展示拼接不统一（至少 7 种写法）**

```
/assets/images/ . $avatar           club、message-modal.js、hufang/circles
../assets/images/ . $avatar         block、nft sidebar、hufang
../../assets/images/ . $avatar      hufang rankings/admin
https://58.tl/assets/images/ . $avatar   bct、club、bid、nft dashboard
../ . $avatar                       mall user 系列
normalizeImageUrl()                 mall shop orders（买家头像）
avatarUrl() strpos 判断             assets/shared/messages-core.php
```

**③ 默认图位置不统一**：`default.jpg` 只存在于主站 `assets/images/`，子站裸拼 `/assets/images/default.jpg` 直接 404。

### 为什么"有的行有的不行"

```
bct 用户换头像 → 文件落 bct/assets/images/uploads/avatars/
               → 但展示拼 https://58.tl/assets/images/uploads/avatars/xxx
               → 主仓库 assets/ 下没有该文件 → 404 ❌（bct 自己页面也显示不了）

主站早期头像 → 文件在 assets/images/uploads/avatars/，DB 存裸名
             → 展示拼 assets/images/裸名 → 404 ❌

mall 上传 → 存 assets/uploads/avatars/xxx → mall 自己页面 ../ 相对 ✓
          → 跨站（主站/其他子站）全 404 ❌
```

## 目标

1. **统一物理存储**：头像一律落主仓库 `assets/images/uploads/avatars/`。
2. **统一字段格式**：`users.avatar` 一律存 `uploads/avatars/<file>`。
3. **统一解析**：`User::avatarUrl()` 静态方法，全站所有展示点统一调用，任何站都能正确出图。
4. **统一默认图**：空头像/加载失败统一回退主站 `https://58.tl/assets/images/default.jpg`。
5. **存量迁移**：旧文件复制 + SQL 归一化 + onerror 兜底，存量用户头像一次性修好。

## 关键决策（已与用户确认）

| # | 决策项 | 结论 |
|---|--------|------|
| 1 | 统一域名 | 主站 `https://58.tl/assets/images/...`（主仓库 assets 已有存量目录，CDN 友好） |
| 2 | 解析函数位置 | `classes/User.php` 加静态方法 `User::avatarUrl()` |
| 3 | 存量迁移 | 出迁移方案（物理文件复制 + SQL 归一化 + onerror 兜底） |

## 设计要点（详见 design.md）

- `User::avatarUrl($avatar)` 静态方法：空 → 默认图；`http(s)://`/`/` 开头 → 原样；`assets/uploads/avatars/` → 归一化为 `uploads/avatars/`；`uploads/avatars/` → 主站前缀；裸文件名 → 主站 `uploads/avatars/` 前缀。
- 3 个 profile.php 上传目录改为主仓库绝对路径，存值统一 `uploads/avatars/xxx`（去掉 mall 的 `assets/` 前缀）。
- 全站展示点 `<img src>` 统一换 `User::avatarUrl()`，并加 `onerror` 兜底默认图。
- `messages-core.php` 全局 `avatarUrl()` 委托 `User::avatarUrl()`，`message-modal.js` 前端同步判断逻辑。
- 迁移：一次性 shell 复制 + 幂等 SQL（裸名 → `uploads/avatars/` 前缀、`assets/uploads/avatars/` → `uploads/avatars/`、空值 → `default.jpg`）。

## 非目标（本次不做）

- 头像上传改 CDN / 对象存储（后续单独 change）。
- 头像裁切、多尺寸缩略图。
- NFT 头像、模特头像、作者头像等其他图片（除非拼接方式影响用户头像）。

## 受影响文件

### 修改
- `classes/User.php` — 新增静态方法 `avatarUrl()`
- `bct/user/profile.php` — 上传目录指向主仓库 + 存值格式
- `hufang/user/profile.php` — 上传目录指向主仓库 + 存值格式
- `mall/user/profile.php` — 上传目录指向主仓库 + 存值格式
- 全站用户头像 `<img>` 展示点（bct / block / nft / mall / club / hufang / bid / messages-core / message-modal.js 等 30+ 处）
- `assets/shared/messages-core.php` — 委托 `User::avatarUrl()`

### 新建
- `init/migrate-avatar.sql` — 存量头像字段归一化（幂等）
- `docs/` 或 `init/` 下一次性文件迁移脚本说明

### 不动
- `config/`、`sitemap.php`、nginx 配置（头像走主站静态路径，无需新增 rewrite）
