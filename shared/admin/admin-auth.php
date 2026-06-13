<?php
/**
 * 统一后台权限检查
 *
 * 前置条件: 已 require 当前子站的 includes/auth.php 并完成 checkLogin()
 * 用法: checkAdminAccess($site);
 */

/**
 * 检查当前用户是否有权限访问指定站点的管理后台
 * @param string $site 站点标识 (main/block/mall/nft/hufang/bct/all)
 */
function checkAdminAccess($site = 'all') {
    global $pdo;

    // 先确保已登录
    if (!function_exists('isLoggedIn') || !isLoggedIn()) {
        header('Location: ../auth/login.php');
        exit;
    }

    $userId = $_SESSION['user_id'] ?? 0;

    // 兼容模式: user_id == 1 或 users.role == 'admin' 视为超级管理员
    if ($userId == 1 || ($_SESSION['role'] ?? '') === 'admin') {
        return true;
    }

    // 新模式: 检查 admin_assignments 表 (如果表存在)
    if (isset($pdo)) {
        try {
            $stmt = $pdo->prepare("
                SELECT 1 FROM admin_assignments
                WHERE user_id = ? AND (site_scope = 'all' OR site_scope = ?)
                LIMIT 1
            ");
            $stmt->execute([$userId, $site]);
            if ($stmt->fetch()) {
                return true;
            }
        } catch (PDOException $e) {
            // 表可能不存在，回退到兼容模式
            error_log("admin_assignments 检查失败: " . $e->getMessage());
        }
    }

    // 无权限: 返回 403
    http_response_code(403);
    echo '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="UTF-8"><title>403 无权限</title></head><body style="background:#0f172a;color:#f1f5f9;font-family:sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;"><div style="text-align:center;"><h1 style="font-size:64px;margin-bottom:8px;color:#ff6b00;">403</h1><p style="font-size:18px;color:#94a3b8;margin-bottom:24px;">您没有权限访问此管理后台</p><a href="../" style="display:inline-block;padding:10px 24px;background:linear-gradient(135deg,#ff6b00,#ff9500);color:#fff;border-radius:8px;text-decoration:none;font-weight:600;">返回首页</a></div></body></html>';
    exit;
}

/**
 * 判断当前用户是否为超级管理员
 */
function isSuperAdmin() {
    $userId = $_SESSION['user_id'] ?? 0;
    $role = $_SESSION['role'] ?? '';
    return $userId == 1 || $role === 'admin';
}

/**
 * 获取当前管理员信息
 */
function getCurrentAdminUser() {
    return [
        'id' => $_SESSION['user_id'] ?? 0,
        'username' => $_SESSION['username'] ?? '',
        'email' => $_SESSION['email'] ?? '',
        'role' => $_SESSION['role'] ?? '',
        'is_super' => isSuperAdmin(),
    ];
}

/**
 * 记录管理员操作日志
 * @param string $action 操作类型
 * @param string $target 操作对象
 * @param string $detail 详情
 */
function logAdminAction($action, $target = '', $detail = '') {
    global $pdo;
    if (!isset($pdo)) return;

    try {
        // 先检查表是否存在
        $stmt = $pdo->query("SHOW TABLES LIKE 'admin_logs'");
        if ($stmt->rowCount() == 0) return;

        $stmt = $pdo->prepare("
            INSERT INTO admin_logs (user_id, action, target, detail, ip_address, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $_SESSION['user_id'] ?? 0,
            $action,
            $target,
            $detail,
            $_SERVER['REMOTE_ADDR'] ?? ''
        ]);
    } catch (PDOException $e) {
        error_log("记录管理员日志失败: " . $e->getMessage());
    }
}
