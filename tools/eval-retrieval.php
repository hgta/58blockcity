<?php
/**
 * 小帮检索质量离线评测：旧 ngram vs 新混合检索 命中率对比
 * change: help-semantic-rag (task 6.1 / 6.2)
 *
 * 用法（两步走）：
 *   第一步 抽样导出（不调嵌入 API）：
 *     php tools/eval-retrieval.php --sample=50
 *     → 生成 data/eval-retrieval-sample.csv（log_id,question,matched,expected_article_id,expected_title）
 *     人工把 expected_article_id 一列填好（0=知识库无对应文章），标题列辅助人工判断
 *
 *   第二步 跑对比（每题 1 次查询嵌入 API 调用）：
 *     php tools/eval-retrieval.php --eval=data/eval-retrieval-sample.csv [--topn=3]
 *     → 输出两种检索的命中率对比 + 阈值建议（供 ai_semantic_min_score 调参）
 *
 * 命中口径：expected_article_id ∈ 检索返回的文章 id 集合（topN）；
 *           expected=0 的题不计入命中率，单独统计"两路都未误报 matched"的比例。
 */

if (is_file(__DIR__ . '/../config/database.php')) require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/SecureCrypto.php';
require_once __DIR__ . '/../classes/HelpChunker.php';
require_once __DIR__ . '/../classes/EmbeddingProvider.php';
require_once __DIR__ . '/../classes/HelpChunkSync.php';
require_once __DIR__ . '/../classes/HelpRetrieval.php';

if (!isset($pdo)) {
    $h = getenv('DB_HOST') ?: 'localhost';
    $n = getenv('DB_NAME'); $u = getenv('DB_USER'); $p = getenv('DB_PASS') ?: '';
    if (!$n || !$u) { fwrite(STDERR, "缺少数据库配置。\n"); exit(1); }
    $pdo = new PDO("mysql:host={$h};dbname={$n};charset=utf8mb4", $u, $p, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}

// ---------- 参数 ----------
$args = getopt('', ['sample::', 'eval::', 'topn::']);
$topN = max(1, min(5, (int)($args['topn'] ?? 3)));

// ---------- 抽样导出 ----------
if (isset($args['sample'])) {
    $n = max(10, min(500, (int)$args['sample']));
    // unmatched 与 ok 各半，取最近记录
    $rows = $pdo->query(
        "(SELECT id, question, matched FROM ai_chat_logs WHERE status='unmatched' AND question <> '' ORDER BY id DESC LIMIT " . (int)ceil($n / 2) . ")
         UNION ALL
         (SELECT id, question, matched FROM ai_chat_logs WHERE status='ok' AND matched=1 AND question <> '' ORDER BY id DESC LIMIT " . (int)floor($n / 2) . ")"
    )->fetchAll();

    // 预填候选：旧 ngram 检索结果帮助人工标注
    $out = "log_id,question,matched,expected_article_id,expected_title,candidates\n";
    foreach ($rows as $r) {
        $cand = legacySearch($pdo, $r['question'], $topN);
        $candStr = $cand ? implode(' | ', array_map(function ($c) { return "#{$c['id']} {$c['title']}"; }, $cand)) : '(无)';
        $out .= $r['id'] . ',"' . str_replace('"', '""', $r['question']) . '",' . (int)$r['matched'] . ',,,"' . str_replace('"', '""', $candStr) . "\"\n";
    }
    @mkdir(__DIR__ . '/../data', 0755, true);
    $file = __DIR__ . '/../data/eval-retrieval-sample.csv';
    file_put_contents($file, $out);
    echo "已导出 {$rows} 条到 {$file}\n";
    echo "下一步：人工填写 expected_article_id 列（0=无对应文章），然后运行 --eval={$file}\n";
    exit(0);
}

// ---------- 对比评测 ----------
if (isset($args['eval'])) {
    $file = $args['eval'];
    if (!is_file($file)) { fwrite(STDERR, "文件不存在: {$file}\n"); exit(1); }
    $fh = fopen($file, 'r');
    $head = fgetcsv($fh);
    if (!$head || count($head) < 4) { fwrite(STDERR, "CSV 格式不符（需 log_id,question,matched,expected_article_id 列）\n"); exit(1); }

    $emb = EmbeddingProvider::pick($pdo);
    if (!$emb) { fwrite(STDERR, "没有可用的嵌入渠道（后台配置 purpose=embedding 后重试）\n"); exit(1); }
    echo "嵌入渠道: [{$emb->name()}] {$emb->model()}\n";
    $ret = new HelpRetrieval($pdo, $emb);

    $stats = [
        'total' => 0, 'annotated' => 0,
        'legacy_hit' => 0, 'hybrid_hit' => 0,
        'both_miss' => 0, 'hybrid_only' => 0, 'legacy_only' => 0,
        'noarticle_total' => 0, 'noarticle_legacy_matched' => 0, 'noarticle_hybrid_matched' => 0,
    ];
    $thresholds = [0.30, 0.35, 0.40, 0.45, 0.50, 0.55, 0.60];
    $thStats = array_fill_keys($thresholds, ['tp' => 0, 'fp' => 0, 'fn' => 0]);

    while (($row = fgetcsv($fh)) !== false) {
        if (count($row) < 4) continue;
        list($logId, $question, $matched, $expected) = [$row[0], $row[1], $row[2], $row[3]];
        $question = trim((string)$question);
        $expected = (int)$expected;
        if ($question === '') continue;
        $stats['total']++;

        // 旧 ngram：chat.php legacy 路径的等价实现
        $legacy = legacySearch($pdo, $question, $topN);
        $legacyIds = array_map(function ($a) { return (int)$a['id']; }, $legacy);

        // 新混合
        $r = $ret->search($question, $topN, 0.45);
        $hybridIds = [];
        foreach ($r['chunks'] as $c) {
            if ($c['source_type'] === 'article') $hybridIds[] = (int)$c['source_id'];
        }
        $hybridIds = array_values(array_unique($hybridIds));
        $hybridTopCos = 0.0;
        foreach ($r['chunks'] as $c) { if ($c['cosine'] !== null) { $hybridTopCos = max($hybridTopCos, $c['cosine']); } }

        if ($expected > 0) {
            $stats['annotated']++;
            $lh = in_array($expected, $legacyIds, true);
            $hh = in_array($expected, $hybridIds, true);
            if ($lh) $stats['legacy_hit']++;
            if ($hh) $stats['hybrid_hit']++;
            if ($hh && !$lh) $stats['hybrid_only']++;
            if ($lh && !$hh) $stats['legacy_only']++;
            if (!$lh && !$hh) $stats['both_miss']++;

            // 阈值扫描：tp=命中且topcos≥t；fn=命中但topcos<t；fp=未命中但topcos≥t
            foreach ($thresholds as $t) {
                $above = $hybridTopCos >= $t;
                if ($hh && $above) $thStats[$t]['tp']++;
                elseif ($hh && !$above) $thStats[$t]['fn']++;
                elseif (!$hh && $above) $thStats[$t]['fp']++;
            }
        } else {
            // 无对应文章：统计两路误报 matched 的比例（越低越好）
            $stats['noarticle_total']++;
            if ($legacy) $stats['noarticle_legacy_matched']++;
            if ($r['matched']) $stats['noarticle_hybrid_matched']++;
        }
    }
    fclose($fh);

    // ---------- 报告 ----------
    echo "\n===== 命中率对比（top-{$topN}，标注题 {$stats['annotated']} / 总题 {$stats['total']}）=====\n";
    if ($stats['annotated'] > 0) {
        printf("旧 ngram 检索 : %d/%d = %.1f%%\n", $stats['legacy_hit'], $stats['annotated'], 100 * $stats['legacy_hit'] / $stats['annotated']);
        printf("新 混合检索  : %d/%d = %.1f%%\n", $stats['hybrid_hit'], $stats['annotated'], 100 * $stats['hybrid_hit'] / $stats['annotated']);
        printf("  仅新检索命中（纯增益）: %d 题；仅旧检索命中（回归风险）: %d 题；双双未中: %d 题\n",
            $stats['hybrid_only'], $stats['legacy_only'], $stats['both_miss']);
    } else {
        echo "（无标注题——请先填 expected_article_id 列）\n";
    }
    if ($stats['noarticle_total'] > 0) {
        echo "\n「无对应文章」题（误报 matched 比例，越低越好）：\n";
        printf("  旧 ngram: %d/%d = %.1f%%   新混合(含阈值判定): %d/%d = %.1f%%\n",
            $stats['noarticle_legacy_matched'], $stats['noarticle_total'], 100 * $stats['noarticle_legacy_matched'] / $stats['noarticle_total'],
            $stats['noarticle_hybrid_matched'], $stats['noarticle_total'], 100 * $stats['noarticle_hybrid_matched'] / $stats['noarticle_total']);
    }
    if ($stats['annotated'] > 0) {
        echo "\n===== ai_semantic_min_score 阈值建议（F1 = 2TP/(2TP+FP+FN)）=====\n";
        $best = [null, -1];
        foreach ($thresholds as $t) {
            $s = $thStats[$t];
            $f1 = ($s['tp'] + $s['fn'] + $s['fp']) > 0 ? 2 * $s['tp'] / (2 * $s['tp'] + $s['fp'] + $s['fn']) : 0;
            printf("  %.2f  TP=%-3d FP=%-3d FN=%-3d  F1=%.3f\n", $t, $s['tp'], $s['fp'], $s['fn'], $f1);
            if ($f1 > $best[1]) $best = [$t, $f1];
        }
        echo "  → 建议 ai_semantic_min_score = {$best[0]}（F1={$best[1]}，误报更敏感可上调）\n";
    }
    exit(0);
}

fwrite(STDERR, "用法：php tools/eval-retrieval.php --sample=50 | --eval=<csv> [--topn=3]\n");
exit(1);

// ---------- 旧 ngram 检索（chat.php legacy 路径等价实现） ----------
function legacySearch(PDO $pdo, $question, $topN)
{
    try {
        $stmt = $pdo->prepare(
            "SELECT id, title, slug, MATCH(title, summary, content_richtext) AGAINST(? IN NATURAL LANGUAGE MODE) AS score
             FROM help_articles
             WHERE status='published' AND MATCH(title, summary, content_richtext) AGAINST(? IN NATURAL LANGUAGE MODE)
             ORDER BY score DESC LIMIT " . (int)$topN
        );
        $stmt->execute([$question, $question]);
        $arts = $stmt->fetchAll();
        if (!$arts) {
            $like = '%' . $question . '%';
            $stmt = $pdo->prepare(
                "SELECT id, title, slug, 0 AS score FROM help_articles
                 WHERE status='published' AND (title LIKE ? OR summary LIKE ? OR content_richtext LIKE ?)
                 ORDER BY view_count DESC LIMIT " . (int)$topN
            );
            $stmt->execute([$like, $like, $like]);
            $arts = $stmt->fetchAll();
        }
        return $arts;
    } catch (Exception $ex) {
        return [];
    }
}
