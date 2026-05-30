<?php
require_once '../../config/database.php';
require_once '../../classes/NFT.php';

header('Content-Type: application/json');

try {
    $nftId = (int)($_GET['nft_id'] ?? 0);
    $cityId = (int)($_GET['city_id'] ?? 0);
    
    if ($nftId <= 0 || $cityId <= 0) {
        throw new Exception('参数无效');
    }
    
    $nft = new NFT($pdo);
    $status = $nft->getClaimStatus($nftId, $cityId);
    
    if (isset($status['error'])) {
        throw new Exception($status['error']);
    }
    
    echo json_encode([
    'success' => true,
    'claimed' => $status['claimed'],
    'username' => $status['username'] ?? '',
    'city_name' => $status['city_name'] ?? '',
    'block_id' => $status['block_id'] ?? ''  // 确保转为整数
	]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

?>