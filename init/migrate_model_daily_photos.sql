-- 模特日常照片字段（如已存在则忽略错误）
ALTER TABLE models ADD COLUMN daily_photos TEXT NULL COMMENT '日常照片JSON数组';
