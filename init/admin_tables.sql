-- ============================================
-- 统一后台管理系统数据库迁移
-- 执行方式: 在 MySQL 中 source 此文件
-- ============================================

-- 管理员角色表
CREATE TABLE IF NOT EXISTS admin_roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role_key VARCHAR(32) NOT NULL UNIQUE COMMENT '角色标识: super_admin, admin, moderator',
    role_name VARCHAR(64) NOT NULL COMMENT '显示名称',
    permissions JSON NOT NULL COMMENT '权限列表JSON',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='管理员角色表';

-- 初始化默认角色
INSERT INTO admin_roles (role_key, role_name, permissions) VALUES
('super_admin', '超级管理员', '["*"]'),
('admin', '管理员', '["read","write","delete"]'),
('moderator', '审核员', '["read","review"]')
ON DUPLICATE KEY UPDATE role_name = VALUES(role_name), permissions = VALUES(permissions);

-- 管理员站点分配表
CREATE TABLE IF NOT EXISTS admin_assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL COMMENT '用户ID',
    role_key VARCHAR(32) NOT NULL DEFAULT 'admin' COMMENT '角色标识',
    site_scope VARCHAR(32) DEFAULT 'all' COMMENT '站点范围: all / block / mall / nft / hufang / bct',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_role (user_id, role_key, site_scope),
    KEY idx_user_id (user_id),
    KEY idx_site_scope (site_scope)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='管理员站点分配表';

-- 管理员操作日志表
CREATE TABLE IF NOT EXISTS admin_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL COMMENT '操作者用户ID',
    action VARCHAR(64) NOT NULL COMMENT '操作类型',
    target VARCHAR(255) DEFAULT '' COMMENT '操作对象',
    detail TEXT DEFAULT NULL COMMENT '详情',
    ip_address VARCHAR(45) DEFAULT '' COMMENT 'IP地址',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    KEY idx_user_id (user_id),
    KEY idx_action (action),
    KEY idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='管理员操作日志表';
