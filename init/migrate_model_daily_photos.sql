-- 模特日常照片字段
ALTER TABLE models ADD COLUMN IF NOT EXISTS daily_photos TEXT NULL COMMENT '日常照片JSON数组';
