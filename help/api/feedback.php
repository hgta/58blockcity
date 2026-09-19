<?php
/**
 * 文章"有帮助"反馈接口
 * change: help-center-ai-assistant (task 2.3)
 * POST article_id, helpful(0|1)
 */

require_once __DIR__ . '/../_init.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'msg' => '方法不允许']);
    exit;
}

$articleId = (int)($_POST['article_id'] ?? 0);
$helpful   = ($_POST['helpful'] ?? '') === '1' ? 1 : 0;

if ($articleId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'msg' => '参数错误']);
    exit;
}

try {
    $exists = $pdo->prepare("SELECT id FROM help_articles WHERE id = :id AND status = 'published'");
    $exists->execute([':id' => $articleId]);
    if (!$exists->fetchColumn()) {
        echo json_encode(['ok' => false, 'msg' => '文章不存在']);
        exit;
    }

    $vh = help_visitor_hash();
    $ins = $pdo->prepare(
        "INSERT INTO help_article_feedback (article_id, helpful, visitor_hash) VALUES (:aid, :h, :vh)
         ON DUPLICATE KEY UPDATE helpful = VALUES(helpful)"
    );
    $ins->execute([':aid' => $articleId, ':h' => $helpful, ':vh' => $vh]);

    // 重算计数（避免 upsert 双向变化的脏计数）
    $pdo->prepare("UPDATE help_articles SET helpful_count = (SELECT COUNT(*) FROM help_article_feedback WHERE article_id = :aid AND helpful = 1) WHERE id = :aid2")
        ->execute([':aid' => $articleId, ':aid2' => $articleId]);

    echo json_encode(['ok' => true]);
} catch (Exception $ex) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => '服务异常']);
}
