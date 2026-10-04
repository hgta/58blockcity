<?php
/**
 * 小帮语义检索：知识块全量重建 CLI
 * change: help-semantic-rag (task 3.1 / 3.3)
 *
 * 用法:
 *   php tools/rebuild-help-chunks.php            # 全量重建（articles + faq + glossary，幂等）
 *   php tools/rebuild-help-chunks.php --stats    # 只看现有块统计，不调嵌入 API
 *   php tools/rebuild-help-chunks.php --force    # 忽略 content_hash 全部重嵌（换嵌入模型后用）
 *
 * 前提:
 *   - 已执行 init/migration-help-semantic-rag.sql（help_chunks 表 + ai_providers.purpose 列）
 *   - 后台已配置并启用「嵌入」用途渠道（purpose=embedding，测试通过）
 *
 * 增量规则：content_hash 未变的块保留原向量不重嵌；
 * --force 时先清空全部块再重建（等价换模型）。
 */

if (is_file(__DIR__ . '/../config/database.php')) require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/SecureCrypto.php';
require_once __DIR__ . '/../classes/HelpChunker.php';
require_once __DIR__ . '/../classes/EmbeddingProvider.php';
require_once __DIR__ . '/../classes/HelpChunkSync.php';

// ---- DB 配置兜底（与 tools/crawl-city-profiles.php 同款）----
if (!isset($pdo)) {
    $h = getenv('DB_HOST') ?: 'localhost';
    $n = getenv('DB_NAME');
    $u = getenv('DB_USER');
    $p = getenv('DB_PASS') ?: '';
    if (!$n || !$u) {
        fwrite(STDERR, "缺少数据库配置：请配置 config/database.php 或设置 DB_* 环境变量。\n");
        exit(1);
    }
    $pdo = new PDO("mysql:host={$h};dbname={$n};charset=utf8mb4", $u, $p, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
    ]);
}

$statsMode = in_array('--stats', $argv, true);
$force     = in_array('--force', $argv, true);

// 嵌入 API 调用期间连接会长时间空闲，拉长会话超时（避免 "MySQL server has gone away"）
try { $pdo->exec("SET SESSION wait_timeout=28800, interactive_timeout=28800"); } catch (Exception $ex) {}

// 断连重连闭包（与后台控制台共用实现）
$reconnect = function () use (&$pdo) {
    $pdo = call_user_func(HelpChunkSync::makeReconnect());
    return $pdo;
};

// ---- 统计模式：不调 API，只看现状 ----
if ($statsMode) {
    $rows = $pdo->query(
        "SELECT source_type, COUNT(*) AS chunks, SUM(embedding IS NOT NULL) AS embedded,
                MAX(dim) AS dim, MAX(updated_at) AS last_at
         FROM help_chunks GROUP BY source_type"
    )->fetchAll();
    if (!$rows) {
        echo "help_chunks 为空——尚未回填。\n";
    } else {
        printf("%-10s %8s %10s %8s  %s\n", 'source', 'chunks', 'embedded', 'dim', 'last_updated');
        foreach ($rows as $r) {
            printf("%-10s %8d %10d %8d  %s\n", $r['source_type'], $r['chunks'], (int)$r['embedded'], (int)$r['dim'], $r['last_at']);
        }
    }
    // 渠道状态恒输出：首次运行时最需要看这一行
    $emb = EmbeddingProvider::pick($pdo);
    echo $emb
        ? "嵌入渠道: [{$emb->name()}] {$emb->model()} @ " . $emb->endpointUrl() . "\n"
        : "嵌入渠道: 未配置（purpose='embedding' 且启用的渠道不存在）——请到后台「AI 渠道配置」新增，用途选「嵌入」\n";
    exit(0);
}

// ---- 嵌入渠道 ----
$emb = EmbeddingProvider::pick($pdo);
if (!$emb) {
    fwrite(STDERR, "没有可用的嵌入渠道：请到后台「AI 渠道配置」新增一个，用途选「嵌入」，测试通过后再运行本脚本。\n");
    exit(1);
}
echo "嵌入渠道: [{$emb->name()}] 模型 {$emb->model()}\n端点: " . $emb->endpointUrl() . "\n\n";

// ---- --force：清空重建 ----
if ($force) {
    $n = $pdo->exec("DELETE FROM help_chunks");
    echo "--force：已清空 {$n} 个现有块，全部重嵌。\n\n";
}

// ---- 全量重建 ----
$t0 = microtime(true);
$stats = HelpChunkSync::rebuildAll($pdo, $emb, function ($m) { echo "  {$m}\n"; }, $reconnect);
$sec = round(microtime(true) - $t0, 1);

echo "\n===== 完成（{$sec}s）=====\n";
printf("知识源: %d 个 | 总块数: %d | 本次新嵌: %d | 保留(未变): %d | 清理: %d | 失败: %d\n",
    $stats['sources'], $stats['chunks'], $stats['embedded'], $stats['kept'], $stats['dropped'], $stats['failed']);
if ($stats['failed'] > 0) {
    echo "有失败源：修复后重跑本脚本即可（幂等，已成功部分不会重嵌）。\n";
    exit(1);
}
exit(0);
