<?php
/**
 * 模特子站 · 登录/注册桥接
 * 统一认证源在 mall.58.tl/auth/，这里仅做带 redirect 的安全跳转，
 * 保证子站上的「关注 / 点赞 / 申请」能完整闭环回到子站。
 */
require_once __DIR__ . '/includes/bootstrap.php';

$redirect = $_GET['redirect'] ?? '';
// 只允许回跳本站（防开放重定向）
$redirect = trim((string)$redirect);
if ($redirect === '' || strpos($redirect, MODEL_BASE_URL) !== 0) {
    $redirect = MODEL_BASE_URL . '/';
}

$action = $_GET['action'] ?? 'login';
$base = ($action === 'register') ? MODEL_REGISTER_URL : MODEL_LOGIN_URL;

header('Location: ' . $base . '?redirect=' . urlencode($redirect));
exit;
