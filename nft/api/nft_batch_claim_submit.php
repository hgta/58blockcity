<?php
/**
 * 批量认领执行（逐条独立事务）
 * POST: input(string 含编号文本) + city_id + block_id
 * 返回: 逐条明细 + 汇总（成功/跳过/失败）
 */
require_once '../../config/database.php';
require_once '../../classes/NFT.php';
require_once '../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

try {
    checkLogin();

    $userId  = (int)$_SESSION['user_id'];
    $cityId  = (int)($_POST['city_id'] ?? 0);
    $blockId = trim((string)($_POST['block_id'] ?? ''));
    $input   = (string)($_POST['input'] ?? '');

    if ($cityId <= 0) {
        throw new Exception('请选择城市');
    }
    if ($blockId === '') {
        throw new Exception('请输入区块ID');
    }
    if (trim($input) === '') {
        throw new Exception('请输入包含编号的文本');
    }

    $nft = new NFT($pdo);

    // 服务端二次解析，不信任前端提交的编号列表
    $parsed = $nft->parseNftCodes($input);
    if (empty($parsed['codes'])) {
        throw new Exception('未解析到有效编号（格式：两字母+两数字，如 ML24）');
    }

    $result = $nft->batchClaim($userId, $cityId, $blockId, $parsed['codes']);
    if (isset($result['error'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => $result['error']]);
        exit;
    }

    echo json_encode([
        'success'  => true,
        'codes'    => $parsed['codes'],
        'ignored'  => $parsed['ignored'],
        'items'    => $result['items'],
        'summary'  => $result['summary'],
        'block_id' => $result['block_id'],
        'city_id'  => $cityId,
    ]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
