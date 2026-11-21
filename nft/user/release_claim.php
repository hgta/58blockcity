<?php
require_once '../../config/database.php';
require_once '../../classes/NFT.php';
require_once '../includes/auth.php';

checkLogin();

// 仅接受 POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: collection.php');
    exit;
}

$userId = (int)$_SESSION['user_id'];
$nftId = intval($_POST['nft_id'] ?? 0);
$cityId = intval($_POST['city_id'] ?? 0);

if ($nftId <= 0 || $cityId <= 0) {
    $_SESSION['error'] = '无效的请求参数';
    header('Location: collection.php');
    exit;
}

$nft = new NFT($pdo);

// 仅允许移除"已售出"的认领（存在 pending/completed 交易且无在售记录）
$soldMap = $nft->getSoldClaimMap($userId);
if (!isset($soldMap[$nftId . '-' . $cityId])) {
    $_SESSION['error'] = '该头像未处于已售出状态，无法移除认领';
    header('Location: collection.php');
    exit;
}

$result = $nft->releaseClaim($nftId, $cityId, $userId);
if ($result['success']) {
    $_SESSION['message'] = '认领已移除，真正的主人现在可以重新认领该头像';
} else {
    $_SESSION['error'] = $result['message'];
}

header('Location: collection.php');
exit;
