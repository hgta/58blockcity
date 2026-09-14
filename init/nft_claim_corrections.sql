-- --------------------------------------------------------
--
-- 认领订正审计表
-- 记录后台对 NFT 认领归属的每一次订正操作，用于追溯与回退定位
--
-- 幂等：可重复执行
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `nft_claim_corrections` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '主键',
  `claim_id` int(11) NOT NULL COMMENT '原认领记录ID(nft_city_user.id)',
  `new_claim_id` int(11) DEFAULT NULL COMMENT '订正后新增的认领记录ID',
  `nft_id` int(11) NOT NULL COMMENT 'NFT ID',
  `city_id` int(11) NOT NULL COMMENT '城市ID',
  `from_user_id` int(11) NOT NULL COMMENT '原归属用户ID',
  `to_user_id` int(11) NOT NULL COMMENT '新归属用户ID',
  `admin_id` int(11) DEFAULT NULL COMMENT '操作管理员ID',
  `reason` varchar(255) NOT NULL COMMENT '订正原因',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_claim_id` (`claim_id`),
  KEY `idx_nft_id` (`nft_id`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='NFT认领订正审计表';
