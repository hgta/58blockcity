<?php
require_once '../../config/database.php';
require_once '../includes/auth.php';
require_once '../../classes/Circle.php';

checkLogin();

$userId = $_SESSION['user_id'];
$circle = new Circle($pdo);
$userCircles = $circle->getUserCircles($userId);

// 生成专属链接
$shareLink = "https://hufangquan.com/user/circles.php?user_id=".$userId;

// 使用二维码生成库
require_once '../lib/phpqrcode/qrlib.php';
$qrCodePath = '../temp/qrcodes/'.md5($shareLink).'.png';
QRcode::png($shareLink, $qrCodePath);

// 使用GD库生成海报
header('Content-Type: image/png');
$poster = imagecreatetruecolor(800, 1200);

// 背景色
$bgColor = imagecolorallocate($poster, 255, 255, 255);
imagefill($poster, 0, 0, $bgColor);

// 添加品牌LOGO
$logo = imagecreatefrompng('../assets/images/logo.png');
imagecopy($poster, $logo, 300, 50, 0, 0, 200, 200);

// 添加标题
$textColor = imagecolorallocate($poster, 51, 51, 51);
imagettftext($poster, 24, 0, 50, 300, $textColor, '../assets/fonts/msyh.ttf', '我的互访圈列表');

// 添加互访圈信息
$yPosition = 350;
foreach(array_slice($userCircles, 0, 5) as $circle) {
    imagettftext($poster, 16, 0, 50, $yPosition, $textColor, '../assets/fonts/msyh.ttf', '• '.$circle['name']);
    $yPosition += 30;
    imagettftext($poster, 14, 0, 70, $yPosition, imagecolorallocate($poster, 102, 102, 102), '../assets/fonts/msyh.ttf', $circle['city'].' | '.$circle['block_count'].'区块');
    $yPosition += 40;
}

// 添加二维码
$qrCode = imagecreatefrompng($qrCodePath);
imagecopy($poster, $qrCode, 250, 900, 0, 0, 300, 300);

// 添加底部文字
imagettftext($poster, 14, 0, 200, 1150, $textColor, '../assets/fonts/msyh.ttf', '扫码查看我的完整互访圈');

// 输出图像
imagepng($poster);
imagedestroy($poster);
unlink($qrCodePath); // 删除临时二维码文件
?>
