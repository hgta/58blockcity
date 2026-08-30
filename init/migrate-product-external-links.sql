-- 商品外部售卖渠道：products 表新增 7 个平台链接字段（重复执行会报重复列错误，仅需执行一次）
-- 用于 mall 子站商品详情页展示"更多购买渠道"入口，与站内购买并存
-- 空值 = 未设置，详情页不渲染对应平台入口

ALTER TABLE `products`
  ADD COLUMN `link_xiaohongshu` varchar(500) DEFAULT NULL COMMENT '小红书售卖链接' AFTER `video_url`,
  ADD COLUMN `link_taobao` varchar(500) DEFAULT NULL COMMENT '淘宝售卖链接' AFTER `link_xiaohongshu`,
  ADD COLUMN `link_douyin` varchar(500) DEFAULT NULL COMMENT '抖音售卖链接' AFTER `link_taobao`,
  ADD COLUMN `link_kuaishou` varchar(500) DEFAULT NULL COMMENT '快手售卖链接' AFTER `link_douyin`,
  ADD COLUMN `link_jd` varchar(500) DEFAULT NULL COMMENT '京东售卖链接' AFTER `link_kuaishou`,
  ADD COLUMN `link_pdd` varchar(500) DEFAULT NULL COMMENT '拼多多售卖链接' AFTER `link_jd`,
  ADD COLUMN `link_wechat_shop` varchar(500) DEFAULT NULL COMMENT '微信小店售卖链接' AFTER `link_pdd`;
