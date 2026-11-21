<?php
/**
 * 批量认领预校验（dry-run，不落库）
 * POST: input(string 含编号的文本)
 * 返回: 解析结果 + 逐条状态（可认领/已被认领/不存在/歧义/已认领）
 */
require_once '../../config/database.php';
require_once '../../classes/NFT.php';
require_once '../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

try {
    checkLogin();

    $userId = (int)$_SESSION['user_id'];
    $cityId = (int)($_POST['city_id'] ?? 0);
    $input  = (string)($_POST['input'] ?? '');

    if ($cityId <= 0) {
        throw new Exception('请选择城市');
    }
    if (trim($input) === '') {
        throw new Exception('请输入包含编号的文本');
    }

    $nft = new NFT($pdo);
    $parsed = $nft->parseNftCodes($input);
    if (empty($parsed['codes'])) {
        throw new Exception('未解析到有效编号（格式：两字母+两数字，如 ML24）');
    }

    $preview = $nft->batchClaimPreview($userId, $cityId, $parsed['codes']);

    echo json_encode([
        'success' => true,
        'codes'   => $parsed['codes'],
        'ignored' => $parsed['ignored'],
        'items'   => $preview['items'],
        'summary' => $preview['summary'],
    ]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
