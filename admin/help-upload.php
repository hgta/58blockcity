<?php
/**
 * 帮助中心图片上传接口（仅限管理员）
 * change: help-center-ai-assistant (task 5.3)
 * POST multipart: image=<file>
 * 返回 JSON: {ok, url, msg}
 * 限制: jpg/png/gif/webp，单文件 ≤ 3MB
 * 落盘: <项目根>/help/uploads/help/YYYYMM/
 *       必须落在 help 子站文档根内 —— help.58.tl 的 root 是 <项目根>/help，
 *       存到 <项目根>/uploads/ 会返回一个在帮助中心 404 的地址
 */

require_once '../config/database.php';
require_once '../includes/auth.php';

// 用 API 版鉴权：checkAdmin() 在会话失效时 302 到登录页，fetch 自动跟随重定向后
// 拿到 HTML，前端 r.json() 抛异常 → 页面显示"网络异常"，真因被吞掉。
checkAdminApi();

header('Content-Type: application/json; charset=utf-8');

function up_out($ok, $url = '', $msg = '') {
    echo json_encode(['ok' => $ok, 'url' => $url, 'msg' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

/** '8M' / '1G' / '512K' → 字节数 */
function up_bytes($v) {
    $v = trim((string)$v);
    if ($v === '') return 0;
    $unit = strtolower(substr($v, -1));
    $n = (float)$v;
    if ($unit === 'g') $n *= 1024 * 1024 * 1024;
    elseif ($unit === 'm') $n *= 1024 * 1024;
    elseif ($unit === 'k') $n *= 1024;
    return (int)$n;
}

/** 帮助中心基址（图片用绝对地址：子站与主站文档根不同，相对路径必有一边 404） */
function up_help_base() {
    global $pdo;
    $cfg = '';
    try {
        $st = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'help_site_url'");
        $st->execute();
        $v = $st->fetchColumn();
        if ($v !== false && $v !== null) $cfg = (string)$v;
    } catch (Exception $ex) { /* 设置表不可用时用默认 */ }
    if ($cfg !== '') return rtrim($cfg, '/');
    return 'https://help.58.tl';
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') up_out(false, '', '方法不允许');

$appMax     = 3 * 1024 * 1024;
$postMax    = up_bytes(ini_get('post_max_size'));
$uploadMax  = up_bytes(ini_get('upload_max_filesize'));
$contentLen = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);

// ① 先判请求体：超过 post_max_size 时 PHP 会直接清空 $_FILES，
//    只回"未收到文件"会把人带偏（真因是服务器限制，不是浏览器没传）
if ($postMax > 0 && $contentLen > $postMax) {
    up_out(false, '', sprintf(
        '图片约 %.1fMB，超过服务器 post_max_size 限制（%s）。请调大 php.ini 的 post_max_size 与 upload_max_filesize，'
        . '并同步 nginx client_max_body_size（默认仅 1MB）',
        $contentLen / 1048576,
        ini_get('post_max_size')
    ));
}

if (empty($_FILES['image'])) {
    $hint = ($uploadMax > 0 && $contentLen > $uploadMax)
        ? sprintf('（图片约 %.1fMB，超过 upload_max_filesize %s）', $contentLen / 1048576, ini_get('upload_max_filesize'))
        : '';
    up_out(false, '', '未收到文件，请重试' . $hint);
}

$f = $_FILES['image'];

// ② 错误码先于 tmp_name 判断：超限时 error 有值但 tmp_name 为空
if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    $errMap = [
        UPLOAD_ERR_INI_SIZE   => '文件超过服务器 upload_max_filesize（' . ini_get('upload_max_filesize') . '）',
        UPLOAD_ERR_FORM_SIZE  => '文件超过表单 MAX_FILE_SIZE 限制',
        UPLOAD_ERR_PARTIAL    => '文件只上传了一部分，请重试',
        UPLOAD_ERR_NO_FILE    => '未选择文件',
        UPLOAD_ERR_NO_TMP_DIR => '服务器缺少临时目录',
        UPLOAD_ERR_CANT_WRITE => '服务器写入临时文件失败',
        UPLOAD_ERR_EXTENSION  => '上传被某个 PHP 扩展中断',
    ];
    up_out(false, '', $errMap[$f['error']] ?? ('上传出错（code=' . $f['error'] . '）'));
}

if (!is_uploaded_file($f['tmp_name'] ?? '')) {
    up_out(false, '', '临时文件校验失败，请重试');
}

if ($f['size'] <= 0) {
    up_out(false, '', '文件为空');
}
if ($f['size'] > $appMax) {
    up_out(false, '', sprintf('图片 %.1fMB 超过 3MB 限制，请压缩后再传', $f['size'] / 1048576));
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
// 落在 help 子站文档根内（help.58.tl root = <项目根>/help）
$dir = __DIR__ . '/../help/uploads/help/' . date('Ym');
if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
    up_out(false, '', '无法创建存储目录（请检查 help/uploads 写权限）');
}

$name = date('Ymd_His') . '_' . substr(bin2hex(random_bytes(6)), 0, 8) . '.' . $ext;
$dest = $dir . '/' . $name;

if (!move_uploaded_file($f['tmp_name'], $dest)) {
    up_out(false, '', '保存文件失败（请检查目录写权限）');
}
@chmod($dest, 0644);

up_out(true, up_help_base() . '/uploads/help/' . date('Ym') . '/' . $name);
