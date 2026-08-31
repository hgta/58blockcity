-- ============================================================
-- unify-user-avatar：users.avatar 字段归一化（幂等，可重复执行）
-- 目标：字段只保留 `uploads/avatars/<file>` 一种相对值（或 default.jpg 占位）
-- 执行时机：代码上线后、用户访问前（或与代码同批发布，先 SQL 后代码）
-- 依赖：先执行 init/migrate-avatars-files.sh 完成物理文件复制
-- ============================================================

-- ① 空值 / 占位 → default.jpg（裸值，由 User::avatarUrl() 兜底为默认图）
UPDATE users SET avatar = 'default.jpg'
 WHERE avatar IS NULL OR avatar = '' OR avatar = 'default';

-- ② 历史 mall 格式：assets/uploads/avatars/xxx → uploads/avatars/xxx
UPDATE users SET avatar = CONCAT('uploads/avatars/', SUBSTRING_INDEX(avatar, 'assets/uploads/avatars/', -1))
 WHERE avatar LIKE 'assets/uploads/avatars/%';

-- ③ 已带根相对前缀：/assets/images/uploads/avatars/xxx → uploads/avatars/xxx
UPDATE users SET avatar = REPLACE(avatar, '/assets/images/uploads/avatars/', 'uploads/avatars/')
 WHERE avatar LIKE '/assets/images/uploads/avatars/%';

-- ④ 无前导斜杠脏值：assets/images/uploads/avatars/xxx → uploads/avatars/xxx
UPDATE users SET avatar = REPLACE(avatar, 'assets/images/uploads/avatars/', 'uploads/avatars/')
 WHERE avatar LIKE 'assets/images/uploads/avatars/%';

-- ⑤ 根相对裸名：/assets/images/xxx（xxx 非 uploads/ 目录）→ uploads/avatars/xxx
UPDATE users SET avatar = CONCAT('uploads/avatars/', SUBSTRING(avatar, LENGTH('/assets/images/') + 1))
 WHERE avatar LIKE '/assets/images/%'
   AND avatar NOT LIKE '/assets/images/uploads/%';

-- ⑥ 无前导斜杠：assets/images/xxx（xxx 非 uploads/ 目录）→ uploads/avatars/xxx
UPDATE users SET avatar = CONCAT('uploads/avatars/', SUBSTRING(avatar, LENGTH('assets/images/') + 1))
 WHERE avatar LIKE 'assets/images/%'
   AND avatar NOT LIKE 'assets/images/uploads/%';

-- ⑦ 裸文件名（历史主站早期真实头像）→ 加 uploads/avatars/ 前缀
--    排除默认占位值（保持裸值语义，由 avatarUrl() 兜底默认图）
UPDATE users SET avatar = CONCAT('uploads/avatars/', avatar)
 WHERE avatar NOT LIKE '%/%'
   AND avatar NOT IN ('default.jpg', 'default', 'default-avatar.jpg', 'default-avatar.png', 'default.png');

-- ============================================================
-- 核对：迁移后应只剩 uploads/avatars/ 或 default.jpg（绝对 URL 由 avatarUrl 原样兜底，可留）
-- SELECT avatar, COUNT(*) FROM users GROUP BY avatar ORDER BY COUNT(*) DESC LIMIT 30;
-- ============================================================
