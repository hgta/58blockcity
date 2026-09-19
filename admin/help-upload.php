<?php
/**
 * 帮助中心图片上传接口（仅限管理员）
 * change: help-center-ai-assistant (task 5.3)
 * POST multipart: image=<file>
 * 返回 JSON: {ok, url, msg}
 * 限制: jpg/png/gif/webp，单文件 ≤ 3MB，按日期目录落盘 uploads/help/
 */

require_once '../config/database.php';
require_once '../includes/auth.php';

checkAdmin();

header('Content-Type: application/json; charset=utf-8');

function up_out($ok, $url = '', $msg = '') {
    echo json_encode(['ok' => $ok, 'url' => $url, 'msg' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') up_out(false, '', '方法不允许');

if (empty($_FILES['image']) || !is_uploaded_file($_FILES['image']['tmp_name'] ?? '')) {
    up_out(false, '', '未收到文件');
}

$f = $_FILES['image'];
if ($f['error'] !== UPLOAD_ERR_OK) {
    up_out(false, '', '上传出错（code=' . $f['error'] . '）');
}

$maxBytes = 3 * 1024 * 1024;
if ($f['size'] <= 0 || $f['size'] > $maxBytes) {
    up_out(false, '', '文件过大，限制 3MB 以内');
}

// 格式校验（MIME + 扩展名双重）
$allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
$mime = '';
if (function_exists('finfo_open')) {
    $fi = finfo_open(FILEINFO_MIME_TYPE);
    $mime = (string)finfo_file($fi, $f['tmp_name']);
    finfo_close($fi);
} else {
    $mime = (string)($f['type'] ?? '');
}
if (!isset($allowed[$mime])) {
    up_out(false, '', '仅支持 JPG/PNG/GIF/WEBP 图片');
}

$ext = $allowed[$mime];
$dir = __DIR__ . '/../uploads/help/' . date('Ym');
if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
    up_out(false, '', '无法创建存储目录');
}

$name = date('Ymd_His') . '_' . substr(bin2hex(random_bytes(6)), 0, 8) . '.' . $ext;
$dest = $dir . '/' . $name;

if (!move_uploaded_file($f['tmp_name'], $dest)) {
    up_out(false, '', '保存文件失败');
}
@chmod($dest, 0644);

up_out(true, '/uploads/help/' . date('Ym') . '/' . $name);
