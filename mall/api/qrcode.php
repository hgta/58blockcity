<?php
/**
 * 商品/页面分享二维码生成端点
 *
 * 用法：/mall/api/qrcode.php?url=https://...&size=200
 * 直接输出 PNG 二维码图片。
 *
 * 复用 hufang/lib/phpqrcode 库，避免重复引入。
 * 仅允许 http(s) URL，防止被滥用为开放代理。
 */
require_once __DIR__ . '/../../hufang/lib/phpqrcode/qrlib.php';

header('Content-Type: image/png');
header('Cache-Control: public, max-age=86400'); // 24h 缓存，URL 不变可复用

$url = isset($_GET['url']) ? trim((string)$_GET['url']) : '';
$size = isset($_GET['size']) ? max(80, min(800, (int)$_GET['size'])) : 200;

// 安全校验：仅允许 http(s) 链接，且长度合理
if ($url === '' || strlen($url) > 2048 || !preg_match('#^https?://#i', $url)) {
    // 非法参数：返回 1x1 透明像素
    $img = imagecreate(1, 1);
    imagecolorallocatealpha($img, 0, 0, 0, 127);
    imagepng($img);
    imagedestroy($img);
    exit;
}

// 容错级别 L（7%）：分享场景对环境复杂度要求不高，最小尺寸
$errorCorrectionLevel = 'M';
$matrixPointSize = max(2, (int)round($size / 50)); // 100→2, 200→4, 400→8

QRcode::png($url, false, $errorCorrectionLevel, $matrixPointSize, 2);