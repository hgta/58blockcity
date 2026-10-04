-- ============================================================
-- 小帮语义检索（混合检索 + 搜索兜底）数据迁移
-- change: help-semantic-rag (task 2.1)
-- 依赖: migration-help-center-ai.sql（help_articles/ai_providers/system_settings）
-- ============================================================

-- ------------------------------------------------------------
-- 知识块表：articles/faq/glossary 切块后的向量载体
-- embedding 存 float32 小端序列化 BLOB（PHP unpack/pack 读写）
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `help_chunks` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `source_type` ENUM('article','faq','glossary') NOT NULL COMMENT '知识源类型',
  `source_id` INT(11) NOT NULL COMMENT '源表主键',
  `chunk_no` SMALLINT(6) NOT NULL DEFAULT 0 COMMENT '源内块序号',
  `title` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '文章标题（+小节标题）',
  `chunk_text` TEXT NOT NULL COMMENT '块文本（前置【标题】上下文头）',
  `content_hash` CHAR(64) NOT NULL COMMENT '块文本 SHA-256，增量重嵌比对用',
  `embedding` BLOB NULL COMMENT 'float32 小端向量',
  `dim` SMALLINT(6) NOT NULL DEFAULT 0 COMMENT '向量维度',
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_src` (`source_type`,`source_id`,`chunk_no`),
  KEY `idx_hash` (`content_hash`),
  FULLTEXT KEY `ft_chunk` (`title`,`chunk_text`) WITH PARSER ngram
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='小帮语义检索知识块';

-- ------------------------------------------------------------
-- ai_providers 增加用途维度：聊天渠道参与路由切换，嵌入渠道仅供检索层使用
-- ------------------------------------------------------------
ALTER TABLE `ai_providers`
  ADD COLUMN `purpose` ENUM('chat','embedding') NOT NULL DEFAULT 'chat' COMMENT '渠道用途' AFTER `model`;

-- ------------------------------------------------------------
-- 全局设置（默认关闭，回滚=改回 0）
-- ai_semantic_min_score 为向量余弦相似度阈值（0~1）：
--   融合后 top 候选的 cosine 低于该值 → 判定未命中 → 走搜索兜底
-- ------------------------------------------------------------
INSERT INTO `system_settings` (`setting_key`,`setting_value`) VALUES
('ai_semantic_rag_enabled',     '0'),
('ai_semantic_min_score',       '0.45'),
('ai_search_fallback_enabled',  '1'),
('ai_search_model',             'doubao-seed-2-1-pro-260628')
ON DUPLICATE KEY UPDATE `setting_key` = VALUES(`setting_key`);
