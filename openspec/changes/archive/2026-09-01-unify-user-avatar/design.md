# 设计：统一全站用户头像

## 1. 统一后的规范（目标态）

| 维度 | 规范 |
|---|---|
| 物理存储 | 一律 `主仓库/assets/images/uploads/avatars/`（对应线上 `58.tl/assets/images/uploads/avatars/`） |
| users.avatar 字段 | 一律存 `uploads/avatars/<file>`（相对路径，无前导斜杠） |
| 展示 URL | 一律由 `User::avatarUrl($avatar)` 生成，形如 `https://58.tl/assets/images/uploads/avatars/xxx.jpg` |
| 默认图 | 空头像/解析失败 → `https://58.tl/assets/images/default.jpg`（主站 assets/images/ 下，全站共用） |
| 兜底 | 所有 `<img>` 加 `onerror` 回退默认图 |

## 2. 核心：`User::avatarUrl()` 静态方法

```php
/**
 * 统一用户头像 URL（全站单一事实来源）
 * 兼容历史三种存量格式 + 未来新格式
 * @param string $avatar users.avatar 原始值（或 null/空）
 * @return string 完整头像 URL
 */
public static function avatarUrl($avatar) {
    $default = 'https://58.tl/assets/images/default.jpg';
    $base    = 'https://58.tl/assets/images/';
    $avatar  = trim((string)$avatar);

    // ① 空 / default 占位 → 默认图
    if ($avatar === '' || $avatar === 'default.jpg' || $avatar === 'default') {
        return $default;
    }
    // ② 绝对 URL（http/https/protocol-relative）→ 原样
    if (preg_match('#^(https?:)?//#i', $avatar)) {
        return $avatar;
    }
    // ③ 根相对路径（/ 开头）→ 原样（如已拼好的 /assets/images/...）
    if ($avatar[0] === '/') {
        return $avatar;
    }
    // ④ 历史 mall 格式：assets/uploads/avatars/xxx → uploads/avatars/xxx
    if (strpos($avatar, 'assets/uploads/avatars/') === 0) {
        $avatar = 'uploads/avatars/' . substr($avatar, strlen('assets/uploads/avatars/'));
    }
    // ⑤ 裸文件名（历史主站早期格式）→ 归一到 uploads/avatars/ 目录
    if (strpos($avatar, '/') === false) {
        $avatar = 'uploads/avatars/' . $avatar;
    }
    // ⑥ 现在只剩 uploads/avatars/xxx 这一种 → 主站前缀
    return $base . ltrim($avatar, '/');
}
```

要点：
- 纯静态方法，不依赖实例/PDO，任何子站 `User::avatarUrl(...)` 直接可调。
- 裸文件名统一映射到 `uploads/avatars/`：与主站早期存量物理位置（`assets/images/uploads/avatars/`）一致。
- 无法识别的历史脏值靠 `onerror` 兜底默认图，不抛错、不白屏。
- 此方法**幂等**：同一值重复调用结果不变，可安全用于替换后再次渲染。

## 3. 上传端改造（3 个 profile.php）

统一策略：**上传物理目录指向主仓库 `assets/images/uploads/avatars/`（绝对路径），存值统一 `uploads/avatars/<file>`**。

### 3.1 bct/user/profile.php / hufang/user/profile.php（几乎同构）

现状：`$uploadDir = '../assets/images/uploads/avatars/'`（相对各自子站）+ GD 缩放 200x200 + `updateUser()`。

改造：
```php
// 绝对路径指向主仓库根（bct/ 或 hufang/ 上溯 2 层：user → bct/hufang → 仓库根）
$uploadDir = dirname(__DIR__, 2) . '/assets/images/uploads/avatars/';
// 确保目录存在
if (!is_dir($uploadDir)) { @mkdir($uploadDir, 0755, true); }
// 存值保持 'uploads/avatars/' . $filename（不变，与统一规范一致）
// 删除旧头像：unlink(dirname(__DIR__, 2) . '/assets/images/' . $userData['avatar'])
```

> `bct/`、`hufang/` 无独立域名，线上真实路径即仓库根下的子目录，`dirname(__DIR__, 2)` 即仓库根。若线上部署结构与本地仓库一致（nginx root 已确认）则此写法可用；如线上不一致，改为从 `config/` 注入头像目录常量。

### 3.2 mall/user/profile.php（格式最特殊）

现状：`$uploadDir = __DIR__ . '/../assets/uploads/avatars/'` + 存 `assets/uploads/avatars/<file>`（无 GD 压缩）。

改造：
```php
// 注意层级：mall/user/profile.php 上溯 2 层到仓库根
$uploadDir = dirname(__DIR__, 2) . '/assets/images/uploads/avatars/';
if (!is_dir($uploadDir)) { @mkdir($uploadDir, 0755, true); }
// 存值改为 'uploads/avatars/' . $fileName（去掉 assets/ 前缀，对齐统一规范）
```
- 可选：复用 GD 压缩（与 bct/hufang 一致），非必须，第一版不做。

### 3.3 写入约束

`users.avatar` 只允许出现 `uploads/avatars/<file>` 这一种值。`updateUser()`/`updateAvatar()` 本身无需改动（只存字符串），实施时可在两个方法内加一行格式校验（可选防御）。

## 4. 展示端改造（全站 30+ 处）

### 4.1 PHP 展示点

对所有 `<img src="...avatar...">` 处：
- 删除各站自拼前缀（`../assets/images/`、`../../assets/images/`、`https://58.tl/assets/images/`、`../` 等）
- 改为 `src="<?= htmlspecialchars(User::avatarUrl($avatar)) ?>"`（`$avatar` 为 DB 原始值）
- 追加 `onerror="this.onerror=null;this.src='https://58.tl/assets/images/default.jpg'"`

已知展示点（实施时以全站 grep 为准，见 tasks 第 2.2 节）：
- bct：user/profile.php、user/dashboard.php
- club：index.php、post.php、includes/sidebar.php、user/dashboard.php
- block：includes/sidebar.php 及用户相关
- nft：includes/sidebar.php、user/dashboard.php、ranking 相关
- bid：user/dashboard.php 等
- hufang：circles/*、rankings 相关、user/*、admin 相关
- mall：user/*、shop/orders.php（买家头像 `normalizeImageUrl($o['buyer_avatar'])`）、shop/manage.php（`../../ . $o['buyer_avatar']`）
- assets/shared/messages-core.php：全局函数 `avatarUrl()` 委托 `User::avatarUrl()`
- 主站根目录：无用户头像展示（city.php 是 SVG 城市头像，不动）

> 商品图片/模特头像/作者头像/NFT 头像**不在此次范围内**，只动"用户头像"。

### 4.2 前端 JS（assets/js/message-modal.js 等）

JS 无法调 PHP 静态方法。方案：
- 页面输出时预置全局 JS 常量（由 shared/header.php 或相关 PHP 模板输出一次）：
  ```html
  <script>window.AVATAR_BASE='https://58.tl/assets/images/';window.DEFAULT_AVATAR='https://58.tl/assets/images/default.jpg';</script>
  ```
- JS 内实现与 `User::avatarUrl()` 相同的判断逻辑（含 onerror 兜底）。

### 4.3 messages-core.php 委托

```php
function avatarUrl($avatar) {
    return User::avatarUrl($avatar); // User 类已被全站统一 require
}
```

## 5. 存量迁移方案（一次性运维，幂等可重复执行）

### 5.1 物理文件复制（服务器上执行一次）

```bash
# 主仓库头像目录（按线上实际路径替换）
MAIN=/var/www/58blockcity/assets/images/uploads/avatars
mkdir -p "$MAIN"

# bct / hufang：子站内 assets/images/uploads/avatars/
for d in bct hufang; do
    if [ -d "$d/assets/images/uploads/avatars" ]; then
        cp -n "$d/assets/images/uploads/avatars/"* "$MAIN/" 2>/dev/null
    fi
done

# mall：assets/uploads/avatars/
if [ -d "mall/assets/uploads/avatars" ]; then
    cp -n "mall/assets/uploads/avatars/"* "$MAIN/" 2>/dev/null
fi
```

> `cp -n`（no-clobber）保证幂等：重复执行不覆盖已有同名文件。跨目录重名冲突以先复制者为准（概率极低，文件名含 userId+time 后缀）。

### 5.2 SQL 字段归一化（init/migrate-avatar.sql，幂等）

```sql
-- ① 空值 / 占位 → default.jpg（裸值，由 avatarUrl 兜底为默认图）
UPDATE users SET avatar = 'default.jpg'
 WHERE avatar IS NULL OR avatar = '' OR avatar = 'default';

-- ② 历史 mall 格式：assets/uploads/avatars/xxx → uploads/avatars/xxx
UPDATE users SET avatar = CONCAT('uploads/avatars/', SUBSTRING_INDEX(avatar, 'assets/uploads/avatars/', -1))
 WHERE avatar LIKE 'assets/uploads/avatars/%';

-- ③ 已带 /assets/images/ 前缀的脏值 → uploads/avatars/xxx
UPDATE users SET avatar = REPLACE(avatar, '/assets/images/uploads/avatars/', 'uploads/avatars/')
 WHERE avatar LIKE '/assets/images/uploads/avatars/%';

-- ④ 裸文件名（历史主站早期真实头像）→ 加 uploads/avatars/ 前缀
--    注意排除 default.jpg（默认图语义保留裸值）
UPDATE users SET avatar = CONCAT('uploads/avatars/', avatar)
 WHERE avatar NOT LIKE '%/%' AND avatar <> 'default.jpg' AND avatar <> '';
```

> 全量 SQL 需包在存储过程 + `IF NOT EXISTS` 守卫里（参照 `init/migrate-club.sql` 惯例）或直接标为一次性脚本。执行时机：**代码上线后、用户访问前**（或与代码同批发布，先 SQL 后代码）。

### 5.3 旧目录清理（可选）

迁移完成后，旧目录（`bct/assets/images/uploads/avatars/`、`hufang/...`、`mall/assets/uploads/avatars/`）保留一段时间作备份，确认线上无 404 后手动清理（不进代码库）。

## 6. 风险与对策

| 风险 | 对策 |
|---|---|
| 线上部署路径与本地仓库结构不一致 | 上传目录改为 `config/` 常量注入，`dirname(__DIR__)` 仅作默认值 |
| 历史脏值无法识别 | `onerror` 全站兜底默认图，绝不白屏 |
| 跨目录文件名冲突（迁移时） | `cp -n` 不覆盖 + 文件名含 userId+time 天然唯一 |
| JS 展示点遗漏 | 实施时 grep 双保险：`avatar` + `img src` 全站扫描 |
| 替换后遗漏 onerror 的旧拼接 | tasks 中逐站核对清单 + 上线后抽查各子站头像页 |
| 影响其他图片（商品/模特/NFT） | 只替换"用户头像"上下文，`normalizeImageUrl` 商品逻辑保留不动 |

## 7. 验收标准

1. bct / hufang / mall 任一子站换头像 → 全站所有子站该用户头像正常显示。
2. 历史裸名、`assets/uploads/avatars/`、`uploads/avatars/` 三种存量值在全部子站均正常出图。
3. 无头像用户在全站显示统一默认图（无 404、无破图）。
4. 主站早期头像（`assets/images/uploads/avatars/` 裸名）显示正常。
5. 移动端/站内信弹窗（JS 渲染）头像一致。
6. 迁移脚本幂等：重复执行结果不变。
