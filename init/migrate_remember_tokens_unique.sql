-- 生产环境迁移：为 remember_tokens 添加 user_id 唯一约束
-- 执行前注意：如果有同一 user_id 的多条记录，需要先清理

-- 1. 删除每个 user_id 的旧记录，只保留最新一条
DELETE t1 FROM remember_tokens t1
INNER JOIN remember_tokens t2 
WHERE t1.user_id = t2.user_id AND t1.id < t2.id;

-- 2. 添加唯一约束
ALTER TABLE `remember_tokens` ADD UNIQUE KEY `uk_user_id` (`user_id`);
