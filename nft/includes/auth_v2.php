<?php
/**
 * 用户认证相关辅助函数
 */

// 启动会话
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * 检查用户是否已登录
 * 如果未登录，则重定向到登录页面
 */
function checkLogin() {
    if (!isLoggedIn()) {
        // 保存当前URL以便登录后跳转
        $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
        header('Location: ../auth/login.php');
        exit;
    }
}

/**
 * 检查用户是否已登录
 * @return bool 是否已登录
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * 检查用户是否有权限访问管理后台
 * 这里只是一个示例，实际应根据用户角色判断
 */
function checkAdmin() {
    checkLogin();
    
    // 示例：假设用户ID为1的是管理员
    // 实际应用中应该有更完善的权限系统
    if ($_SESSION['user_id'] != 1) {
        header('Location: ../');
        exit;
    }
}

function checkAdminLogin() {
    if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
        header("Location: /admin/login.php");
        exit();
    }
}

/**
 * 生成CSRF令牌
 * @return string CSRF令牌
 */
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * 验证CSRF令牌
 * @param string $token 待验证的令牌
 * @return bool 是否有效
 */
function verifyCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * 验证表单提交的CSRF令牌
 */
function validateCsrfToken() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? '';
        if (!verifyCsrfToken($token)) {
            die('CSRF令牌验证失败');
        }
    }
}

/**
 * 设置闪存消息（一次性消息，显示后自动清除）
 * @param string $type 消息类型（success, error, warning, info）
 * @param string $message 消息内容
 */
function setFlashMessage($type, $message) {
    $_SESSION['flash_messages'][$type][] = $message;
}

/**
 * 获取并清除闪存消息
 * @param string $type 消息类型
 * @return array 消息数组
 */
function getFlashMessages($type) {
    $messages = $_SESSION['flash_messages'][$type] ?? [];
    unset($_SESSION['flash_messages'][$type]);
    return $messages;
}

/**
 * 显示闪存消息
 */
function displayFlashMessages() {
    $types = ['success', 'error', 'warning', 'info'];
    foreach ($types as $type) {
        $messages = getFlashMessages($type);
        foreach ($messages as $message) {
            echo '<div class="alert alert-'.$type.'">'.htmlspecialchars($message).'</div>';
        }
    }
}