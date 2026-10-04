<?php
/**
 * 检索标注台：为真实提问标注「期望命中的知识条目」，供评测复跑
 * change: help-content-admin (task 3.1 ~ 3.4)
 *
 * expected 编码与 tools/eval-retrieval.php 一致：
 *   a10 = 文章#10 / g4 = 术语#4 / f7 = FAQ#7 / 0 = 知识库无对应内容
 */

require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../classes/EmbeddingProvider.php';
require_once '../classes/HelpChunker.php';
require_once '../classes/HelpChunkSync.php';
require_once '../classes/HelpRetrieval.php';

checkAdmin();

function h($s) { return htmlspecialchars((string)$s); }

$actionMsg = '';
$probe = null;

// ---------- 操作 ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        if ($_POST['action'] === 'annotate') {
            $logId = (int)($_POST['log_id'] ?? 0);
            $question = trim((string)($_POST['question'] ?? ''));
            $expected = trim((string)($_POST['expected'] ?? ''));
            if ($logId > 0 && $question !== '') {
                $pdo->prepare(
                    "INSERT INTO help_eval_samples (log_id, question, expected, annotated_by)
                     VALUES (?,?,?,?)
                     ON DUPLICATE KEY UPDATE question=VALUES(question), expected=VALUES(expected), annotated_by=VALUES(annotated_by)"
                )->execute([$logId, mb_substr($question, 0, 500), $expected, $_SESSION['user_id'] ?? null]);
                $actionMsg = '<div class="admin-alert admin-alert-success">已保存标注（#' . $logId . ' → ' . ($expected === '' ? '(清除标注)' : h($expected)) . '）</div>';
            }
        } elseif ($_POST['action'] === 'probe') {
            $q = trim((string)($_POST['question'] ?? ''));
            if ($q !== '') {
                $threshold = (float)($pdo->query("SELECT setting_value FROM system_settings WHERE setting_key='ai_semantic_min_score'")->fetchColumn() ?: 0.45);
                $topN = (int)($pdo->query("SELECT setting_value FROM system_settings WHERE setting_key='ai_rag_topn'")->fetchColumn() ?: 3);
                $emb = EmbeddingProvider::pick($pdo);
                $ret = new HelpRetrieval($pdo, $emb);
                $probe = [
                    'q' => $q,
                    'r' => $ret->search($q, $topN, $threshold),
                    'legacy' => HelpRetrieval::legacySearch($pdo, $q, $topN),
                    'threshold' => $threshold,
                ];
            }
        } elseif ($_POST['action'] === 'export') {
            $rows = $pdo->query("SELECT log_id, question, expected FROM help_eval_samples WHERE expected <> '' ORDER BY id")->fetchAll();
            if (!$rows) {
                $actionMsg = '<div class="admin-alert admin-alert-warning">还没有任何标注，无法导出</div>';
            } else {
                $csv = "log_id,question,matched,expected_article_id,expected_title,candidates\n";
                foreach ($rows as $r) {
                    $csv .= $r['log_id'] . ',"' . str_replace('"', '""', $r['question']) . '",,' . $r['expected'] . ",,\n";
                }
                $dir = __DIR__ . '/../data';
                if (!is_dir($dir)) @mkdir($dir, 0755, true);
                file_put_contents($dir . '/eval-retrieval-sample.csv', $csv);
                $actionMsg = '<div class="admin-alert admin-alert-success">已导出 ' . count($rows)
                    . ' 条到 data/eval-retrieval-sample.csv —— 运行 php tools/eval-retrieval.php --eval=data/eval-retrieval-sample.csv</div>';
            }
        } elseif ($_POST['action'] === 'clear') {
            $pdo->exec("DELETE FROM help_eval_samples");
            $actionMsg = '<div class="admin-alert admin-alert-success">标注已清空（问题样本本身保留）</div>';
        }
    } catch (Exception $ex) {
        $actionMsg = '<div class="admin-alert admin-alert-error">操作失败：' . h($ex->getMessage()) . '</div>';
    }
}

// ---------- 筛选 ----------
$fltStatus    = (string)($_GET['status'] ?? '');
$fltAnnotated = (string)($_GET['annot'] ?? '');
$where = [];
if (in_array($fltStatus, ['ok', 'unmatched', 'error'], true)) $where[] = "l.status = " . $pdo->quote($fltStatus);
if ($fltAnnotated === 'yes') $where[] = "s.id IS NOT NULL AND s.expected <> ''";
if ($fltAnnotated === 'no')  $where[] = "(s.id IS NULL OR s.expected = '')";
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$logs = $pdo->query(
    "SELECT l.id, l.question, l.status, l.matched, l.created_at, s.expected
     FROM ai_chat_logs l
     LEFT JOIN help_eval_samples s ON s.log_id = l.id
     $whereSql
     ORDER BY l.id DESC LIMIT 100"
)->fetchAll();

$cntAnnotated = (int)$pdo->query("SELECT COUNT(*) FROM help_eval_samples WHERE expected <> ''")->fetchColumn();
$cntTotal = (int)$pdo->query("SELECT COUNT(*) FROM help_eval_samples")->fetchColumn();

$arts  = $pdo->query("SELECT id, title FROM help_articles WHERE status='published' ORDER BY title LIMIT 300")->fetchAll();
$faqs  = $pdo->query("SELECT id, question FROM help_faq WHERE status='published' ORDER BY id LIMIT 300")->fetchAll();
$gloss = $pdo->query("SELECT id, term FROM help_glossary ORDER BY pinyin, id LIMIT 300")->fetchAll();

$admin_site_config = ['site' => 'main', 'page_title' => '检索标注台'];
require_once '../shared/admin/admin-header.php';
?>

<?= $actionMsg ?>

<div class="admin-card">
    <div class="admin-card-header">
        <span class="admin-card-title"><i class="fas fa-tags"></i> 标注进度：<?= $cntAnnotated ?> / <?= $cntTotal ?> 条已标注</span>
        <span>
            <form method="POST" style="display:inline">
                <input type="hidden" name="action" value="export">
                <button class="admin-btn admin-btn-primary admin-btn-sm">导出 CSV</button>
            </form>
            <form method="POST" style="display:inline" onsubmit="return confirm('确认清空全部标注？')">
                <input type="hidden" name="action" value="clear">
                <button class="admin-btn admin-btn-danger admin-btn-sm">清空标注</button>
            </form>
        </span>
    </div>
    <div class="admin-card-body" style="padding:0;">
        <div style="padding:12px 16px;display:flex;gap:8px;align-items:center;font-size:13px;">
            <span>筛选：</span>
            <a href="help-eval.php" class="admin-btn admin-btn-secondary admin-btn-sm">全部</a>
            <a href="help-eval.php?annot=no" class="admin-btn admin-btn-secondary admin-btn-sm">未标注</a>
            <a href="help-eval.php?annot=yes" class="admin-btn admin-btn-secondary admin-btn-sm">已标注</a>
            <a href="help-eval.php?status=unmatched" class="admin-btn admin-btn-secondary admin-btn-sm">仅未命中提问</a>
            <a href="help-eval.php?status=unmatched&annot=no" class="admin-btn admin-btn-secondary admin-btn-sm">未命中且未标注</a>
            <span style="color:#64748b;">（默认显示最近 100 条）</span>
        </div>
        <table class="admin-data-table">
            <thead><tr><th style="width:60px;">ID</th><th>用户提问</th><th style="width:90px;">日志状态</th><th style="width:260px;">期望命中的条目</th><th style="width:150px;">操作</th></tr></thead>
            <tbody>
            <?php foreach ($logs as $l): ?>
                <tr>
                    <td style="color:#94a3b8;">#<?= (int)$l['id'] ?></td>
                    <td>
                        <div><?= h(mb_substr($l['question'], 0, 80)) ?></div>
                        <div style="font-size:11px;color:#64748b;"><?= h($l['created_at']) ?></div>
                    </td>
                    <td>
                        <span style="font-size:11px;padding:2px 8px;border-radius:999px;background:<?= $l['status'] === 'unmatched' ? '#78350f' : '#134e4a' ?>;color:<?= $l['status'] === 'unmatched' ? '#fcd34d' : '#5eead4' ?>">
                            <?= h($l['status']) ?>
                        </span>
                    </td>
                    <td>
                        <form method="POST" style="display:flex;gap:6px;">
                            <input type="hidden" name="action" value="annotate">
                            <input type="hidden" name="log_id" value="<?= (int)$l['id'] ?>">
                            <input type="hidden" name="question" value="<?= h($l['question']) ?>">
                            <select name="expected" style="flex:1;padding:6px 8px;background:#0f172a;border:1px solid #334155;border-radius:6px;color:#f1f5f9;font-size:12px;">
                                <option value="">— 未标注 —</option>
                                <option value="0" <?= $l['expected'] === '0' ? 'selected' : '' ?>>0 · 知识库无对应内容</option>
                                <optgroup label="文章">
                                    <?php foreach ($arts as $a): $v = 'a' . $a['id']; ?>
                                    <option value="<?= $v ?>" <?= $l['expected'] === $v ? 'selected' : '' ?>><?= h(mb_substr($a['title'], 0, 24)) ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                                <optgroup label="FAQ">
                                    <?php foreach ($faqs as $f): $v = 'f' . $f['id']; ?>
                                    <option value="<?= $v ?>" <?= $l['expected'] === $v ? 'selected' : '' ?>><?= h(mb_substr($f['question'], 0, 24)) ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                                <optgroup label="术语">
                                    <?php foreach ($gloss as $g): $v = 'g' . $g['id']; ?>
                                    <option value="<?= $v ?>" <?= $l['expected'] === $v ? 'selected' : '' ?>><?= h(mb_substr($g['term'], 0, 24)) ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                            </select>
                            <button class="admin-btn admin-btn-primary admin-btn-sm">保存</button>
                        </form>
                    </td>
                    <td>
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="action" value="probe">
                            <input type="hidden" name="question" value="<?= h($l['question']) ?>">
                            <button class="admin-btn admin-btn-secondary admin-btn-sm">试检索</button>
                        </form>
                        <a href="help-semantic.php?q=<?= urlencode($l['question']) ?>" class="admin-btn admin-btn-secondary admin-btn-sm">试验台</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$logs): ?>
                <tr><td colspan="5" style="text-align:center;color:#64748b;padding:24px;">没有符合条件的问题样本</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($probe): ?>
<div class="admin-card">
    <div class="admin-card-header"><span class="admin-card-title"><i class="fas fa-flask"></i> 试检索：<?= h(mb_substr($probe['q'], 0, 60)) ?></span></div>
    <div class="admin-card-body">
        <div style="font-size:13px;color:#94a3b8;margin-bottom:8px;">
            模式：<?= h($probe['r']['mode']) ?>　阈值：<?= h((string)$probe['threshold']) ?>
            命中判定：<b style="color:<?= $probe['r']['matched'] ? '#5eead4' : '#fbbf24' ?>"><?= $probe['r']['matched'] ? '命中' : '未命中' ?></b>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
            <div>
                <div style="font-size:13px;margin-bottom:6px;"><b>新：混合检索</b></div>
                <?php if ($probe['r']['chunks']): ?>
                <table class="admin-data-table">
                    <thead><tr><th>块标题</th><th>cosine</th></tr></thead>
                    <tbody>
                    <?php foreach ($probe['r']['chunks'] as $c): ?>
                        <tr>
                            <td>
                                <div style="font-size:12px;"><?= h(mb_substr($c['title'], 0, 30)) ?></div>
                                <div style="font-size:11px;color:#64748b;"><?= h(mb_substr($c['text'], 0, 50)) ?>…</div>
                            </td>
                            <td style="font-size:12px;"><?= h((string)($c['cosine'] ?? '-')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div style="font-size:12px;color:#94a3b8;">（无结果）</div>
                <?php endif; ?>
            </div>
            <div>
                <div style="font-size:13px;margin-bottom:6px;"><b>旧：ngram 检索</b></div>
                <?php if ($probe['legacy']): ?>
                <table class="admin-data-table">
                    <thead><tr><th>文章</th><th>score</th></tr></thead>
                    <tbody>
                    <?php foreach ($probe['legacy'] as $a): ?>
                        <tr>
                            <td style="font-size:12px;"><?= h(mb_substr($a['title'], 0, 30)) ?></td>
                            <td style="font-size:12px;color:#94a3b8;"><?= h((string)round((float)$a['score'], 3)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div style="font-size:12px;color:#94a3b8;">（无结果）</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once '../shared/admin/admin-footer.php'; ?>
