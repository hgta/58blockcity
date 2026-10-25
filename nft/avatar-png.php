<?php
/**
 * NFT 头像 SVG → PNG 转换端点
 *
 * 用途：微信 / 百度等抓取器不认 SVG，拍品分享卡需要位图。
 * 本站头像全是 .svg（nft/avatar/ 下 2000+ 个），无法直接作 og:image，
 * 本端点按需转换并缓存，输出可直接用于 og:image 的绝对 URL。
 *
 * 用法：
 *   /avatar-png.php?f=63546dda002cf212.svg&s=300   → 300×300 PNG
 *   /avatar-png.php?probe=1                        → JSON 输出服务器可用转换器（排查用）
 *
 * 转换器优先级：Imagick → rsvg-convert → convert(ImageMagick CLI) → inkscape
 *              → cairosvg(python) → 纯 PHP GD 光栅化器(SvgRaster)
 * 前 5 个依赖服务器环境，最后一个纯 PHP 实现，无需任何扩展。
 */

declare(strict_types=1);

$AVATAR_DIR = __DIR__ . '/avatar/';
$CACHE_DIR  = __DIR__ . '/avatar-cache/';
$FALLBACK   = 'https://www.58.tl/assets/images/og-bid.jpg';

// ---- 探测模式 ----
if (isset($_GET['probe'])) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'imagick_extension' => extension_loaded('imagick'),
        'gd_extension'      => function_exists('imagecreatetruecolor'),
        'cli' => [
            'rsvg-convert' => trim(shell_exec('command -v rsvg-convert 2>/dev/null') ?? ''),
            'convert'      => trim(shell_exec('command -v convert 2>/dev/null') ?? ''),
            'inkscape'     => trim(shell_exec('command -v inkscape 2>/dev/null') ?? ''),
            'cairosvg'     => trim(shell_exec('command -v cairosvg 2>/dev/null') ?? ''),
        ],
        'cache_dir' => [
            'path'      => $CACHE_DIR,
            'exists'    => is_dir($CACHE_DIR),
            'writable'  => is_dir($CACHE_DIR) ? is_writable($CACHE_DIR) : is_writable(dirname($CACHE_DIR)),
        ],
        'svg_count' => count(glob($AVATAR_DIR . '*.svg') ?: []),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

// ---- 参数校验 ----
$file = basename((string)($_GET['f'] ?? ''));
$size = intval($_GET['s'] ?? 300);
$size = max(64, min(1024, $size));

// 白名单：仅允许 字母数字 . _ - ，且必须以 .svg 结尾（防目录穿越 / 任意文件读取）
if ($file === '' || !preg_match('/^[A-Za-z0-9._-]+\.svg$/', $file)) {
    header('Location: ' . $FALLBACK, true, 302);
    exit;
}

$svgPath = realpath($AVATAR_DIR . $file);
if ($svgPath === false || strpos($svgPath, realpath($AVATAR_DIR)) !== 0 || !is_file($svgPath)) {
    header('Location: ' . $FALLBACK, true, 302);
    exit;
}

// ---- 缓存命中 ----
$cacheKey = md5($file) . '-' . $size . '.png';
$cacheFile = $CACHE_DIR . $cacheKey;
if (is_file($cacheFile) && filemtime($cacheFile) >= filemtime($svgPath)) {
    header('Content-Type: image/png');
    header('Cache-Control: public, max-age=31536000, immutable');
    header('X-Avatar-Png: cache');
    header('Content-Length: ' . filesize($cacheFile));
    readfile($cacheFile);
    exit;
}

$svg = file_get_contents($svgPath);
if ($svg === false || $svg === '') {
    header('Location: ' . $FALLBACK, true, 302);
    exit;
}

$png   = false;
$via   = '';

// ---- 1) Imagick ----
if ($png === false && extension_loaded('imagick')) {
    try {
        $im = new Imagick();
        $im->setBackgroundColor(new ImagickPixel('transparent'));
        $im->readImageBlob($svg);
        $im->setImageFormat('png');
        $im->thumbnailImage($size, $size, true);
        $png = $im->getImageBlob();
        $im->clear();
        $via = 'imagick';
    } catch (Throwable $e) {
        error_log('avatar-png imagick failed: ' . $e->getMessage());
    }
}

// ---- 2) 命令行转换器 ----
if ($png === false) {
    $tmpOut = tempnam(sys_get_temp_dir(), 'avpng') . '.png';
    $candidates = [
        'rsvg-convert' => 'rsvg-convert -w %2$d -h %2$d %1$s -o %3$s',
        'convert'      => 'convert -background none -resize %2$dx%2$d %1$s %3$s',
        'inkscape'     => 'inkscape -w %2$d -h %2$d %1$s -o %3$s',
        'cairosvg'     => 'cairosvg -W %2$d -H %2$d %1$s -o %3$s',
    ];
    foreach ($candidates as $name => $tpl) {
        $bin = trim(shell_exec('command -v ' . escapeshellarg($name) . ' 2>/dev/null') ?? '');
        if ($bin === '') { continue; }
        $cmd = sprintf($tpl, escapeshellarg($svgPath), $size, escapeshellarg($tmpOut)) . ' 2>&1';
        @shell_exec($cmd);
        if (is_file($tmpOut) && filesize($tmpOut) > 0) {
            $data = file_get_contents($tmpOut);
            if ($data !== false && substr($data, 0, 8) === "\x89PNG\x0d\x0a\x1a\x0a") {
                $png = $data;
                $via = $name;
                break;
            }
        }
        @unlink($tmpOut);
    }
    @unlink($tmpOut);
}

// ---- 3) 纯 PHP GD 光栅化器（无需任何扩展）----
if ($png === false) {
    require_once __DIR__ . '/includes/SvgRaster.php';
    $png = SvgRaster::toPng($svg, $size, null);
    $via = 'gd-raster';
    // 保险：全透明说明什么都没画出来（渲染失败），按失败处理，避免分享出空白图
    if (self_isBlank($png)) {
        error_log('avatar-png: gd-raster produced blank image for ' . $file);
        $png = false;
    }
}

if ($png === false || $png === '') {
    header('Location: ' . $FALLBACK, true, 302);
    exit;
}

/** 采样检测 PNG 是否整张透明（无内容） */
function self_isBlank($png) {
    if ($png === false || $png === '') { return true; }
    $im = @imagecreatefromstring($png);
    if ($im === false) { return true; }
    $w = imagesx($im); $h = imagesy($im);
    $blank = true;
    for ($i = 0; $i < 400 && $blank; $i++) {
        $x = (int)round(($i % 20) * ($w - 1) / 19);
        $y = (int)round(floor($i / 20) * ($h - 1) / 19);
        $c = @imagecolorat($im, $x, $y);
        // 取 alpha（32 位真彩色：0-127 透明通道在高 7 位之外，用 imagecolorsforindex 更稳）
        $rgba = @imagecolorsforindex($im, $c);
        if (!isset($rgba['alpha']) || $rgba['alpha'] < 127) { $blank = false; }
    }
    imagedestroy($im);
    return $blank;
}

// ---- 写缓存（失败不影响输出）----
if (!is_dir($CACHE_DIR)) { @mkdir($CACHE_DIR, 0775, true); }
if (is_dir($CACHE_DIR) && is_writable($CACHE_DIR)) {
    @file_put_contents($cacheFile, $png);
}

header('Content-Type: image/png');
header('Cache-Control: public, max-age=604800');
header('X-Avatar-Png: ' . $via);
header('Content-Length: ' . strlen($png));
echo $png;
exit;
