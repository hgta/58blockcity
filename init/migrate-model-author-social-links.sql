-- 模特与作者社媒主页字段迁移
-- change: model-author-social-links
-- 说明：
--   1) 名字列 7 个：抖音/快手/B站/红果/腾讯视频/爱奇艺/优酷（该平台上的账号名/演员名）
--      微博/小红书复用现有 weibo / xiaohongshu 列，不新增
--   2) 链接列 9 个：link_<platform>，对应平台主页完整 URL
--   3) 全部可空，存量数据不受影响；models 与 authors 结构完全对称

ALTER TABLE `models`
  ADD COLUMN `douyin` varchar(100) DEFAULT NULL COMMENT '抖音名' AFTER `xiaohongshu`,
  ADD COLUMN `kuaishou` varchar(100) DEFAULT NULL COMMENT '快手名' AFTER `douyin`,
  ADD COLUMN `bilibili` varchar(100) DEFAULT NULL COMMENT 'B站名' AFTER `kuaishou`,
  ADD COLUMN `hongguo` varchar(100) DEFAULT NULL COMMENT '红果演员名' AFTER `bilibili`,
  ADD COLUMN `tencent_video` varchar(100) DEFAULT NULL COMMENT '腾讯视频名' AFTER `hongguo`,
  ADD COLUMN `iqiyi` varchar(100) DEFAULT NULL COMMENT '爱奇艺名' AFTER `tencent_video`,
  ADD COLUMN `youku` varchar(100) DEFAULT NULL COMMENT '优酷名' AFTER `iqiyi`,
  ADD COLUMN `link_weibo` varchar(500) DEFAULT NULL COMMENT '微博主页链接' AFTER `youku`,
  ADD COLUMN `link_xiaohongshu` varchar(500) DEFAULT NULL COMMENT '小红书主页链接' AFTER `link_weibo`,
  ADD COLUMN `link_douyin` varchar(500) DEFAULT NULL COMMENT '抖音主页链接' AFTER `link_xiaohongshu`,
  ADD COLUMN `link_kuaishou` varchar(500) DEFAULT NULL COMMENT '快手主页链接' AFTER `link_douyin`,
  ADD COLUMN `link_bilibili` varchar(500) DEFAULT NULL COMMENT 'B站主页链接' AFTER `link_kuaishou`,
  ADD COLUMN `link_hongguo` varchar(500) DEFAULT NULL COMMENT '红果主页链接' AFTER `link_bilibili`,
  ADD COLUMN `link_tencent_video` varchar(500) DEFAULT NULL COMMENT '腾讯视频主页链接' AFTER `link_hongguo`,
  ADD COLUMN `link_iqiyi` varchar(500) DEFAULT NULL COMMENT '爱奇艺主页链接' AFTER `link_tencent_video`,
  ADD COLUMN `link_youku` varchar(500) DEFAULT NULL COMMENT '优酷主页链接' AFTER `link_iqiyi`;

ALTER TABLE `authors`
  ADD COLUMN `douyin` varchar(100) DEFAULT NULL COMMENT '抖音名' AFTER `xiaohongshu`,
  ADD COLUMN `kuaishou` varchar(100) DEFAULT NULL COMMENT '快手名' AFTER `douyin`,
  ADD COLUMN `bilibili` varchar(100) DEFAULT NULL COMMENT 'B站名' AFTER `kuaishou`,
  ADD COLUMN `hongguo` varchar(100) DEFAULT NULL COMMENT '红果演员名' AFTER `bilibili`,
  ADD COLUMN `tencent_video` varchar(100) DEFAULT NULL COMMENT '腾讯视频名' AFTER `hongguo`,
  ADD COLUMN `iqiyi` varchar(100) DEFAULT NULL COMMENT '爱奇艺名' AFTER `tencent_video`,
  ADD COLUMN `youku` varchar(100) DEFAULT NULL COMMENT '优酷名' AFTER `iqiyi`,
  ADD COLUMN `link_weibo` varchar(500) DEFAULT NULL COMMENT '微博主页链接' AFTER `youku`,
  ADD COLUMN `link_xiaohongshu` varchar(500) DEFAULT NULL COMMENT '小红书主页链接' AFTER `link_weibo`,
  ADD COLUMN `link_douyin` varchar(500) DEFAULT NULL COMMENT '抖音主页链接' AFTER `link_xiaohongshu`,
  ADD COLUMN `link_kuaishou` varchar(500) DEFAULT NULL COMMENT '快手主页链接' AFTER `link_douyin`,
  ADD COLUMN `link_bilibili` varchar(500) DEFAULT NULL COMMENT 'B站主页链接' AFTER `link_kuaishou`,
  ADD COLUMN `link_hongguo` varchar(500) DEFAULT NULL COMMENT '红果主页链接' AFTER `link_bilibili`,
  ADD COLUMN `link_tencent_video` varchar(500) DEFAULT NULL COMMENT '腾讯视频主页链接' AFTER `link_hongguo`,
  ADD COLUMN `link_iqiyi` varchar(500) DEFAULT NULL COMMENT '爱奇艺主页链接' AFTER `link_tencent_video`,
  ADD COLUMN `link_youku` varchar(500) DEFAULT NULL COMMENT '优酷主页链接' AFTER `link_iqiyi`;
