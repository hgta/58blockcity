<?php
/**
 * 语义检索控制台：知识块状态 / 一键重建 / 开关阈值 / 检索试验台
 * change: help-content-admin (task 2.1 ~ 2.4)
 *
 * 说明：
 * - 全量重建在请求内同步执行（22 块量级约 40s），带锁防并发；内容量很大时建议仍用 CLI
 * - 试验台用于调 ai_semantic_min_score：可直接看到命中块的 cosine 与命中判定
 */

require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../classes/SecureCrypto.php';
require_once '../classes/EmbeddingProvider.php';
require_once '../classes/HelpChunker.php';
require_once '../classes/HelpChunkSync.php';
require_once '../classes/HelpRetrieval.php';

checkAdmin();

// ---------- 设置读写 ----------
$settingsKeys = [
    'ai_semantic_rag_enabled'    => ['label' => '混合检索开关', 'hint' => '关闭 = 小帮回落到原 ngram 检索路径（回滚手段）'],
    'ai_semantic_min_score'      => ['label' => '命中阈值（cosine）', 'hint' => '融合 top1 的余弦相似度低于此值 → 判定未命中 → 走联网搜索兜底'],
    'ai_search_fallback_enabled' => ['label' => '联网搜索兜底', 'hint' => '未命中时调用方舟 Responses API + web_search，回答标注「非官方」'],
    'ai_search_model'            => ['label' => '联网搜索模型', 'hint' => '方舟 Responses API 的归纳模型，如 doubao-seed-2-1-pro-260628'],
    'ai_chat_first_byte_timeout' => ['label' => '前台首字节超时（秒）', 'hint' => '流式调用时渠道在该时间内一个字都没吐出即判失败并切换下一个；0=关闭该保护。实测 ark-code-latest 思考约 92s，设 8 可避免用户干等'],
];

function readSettings(PDO $pdo)
{
    $out = [];
    try {
        foreach ($pdo->query("SELECT setting_key, setting_value FROM system_settings") as $r) {
            $out[$r['setting_key']] = $r['setting_value'];
        }
    } catch (Exception $ex) {}
    return $out;
}

$settings = readSettings($pdo);
$defaults = [
    'ai_semantic_rag_enabled' => '0',
    'ai_semantic_min_score' => '0.45',
    'ai_search_fallback_enabled' => '1',
    'ai_search_model' => '',
];

$actionMsg = '';
$rebuildLog = [];
$testResult = null;

// ---------- 操作 ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        if ($_POST['action'] === 'save_settings') {
            $up = $pdo->prepare(
                "INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
            );
            foreach ($settingsKeys as $k => $meta) {
                if ($k === 'ai_semantic_rag_enabled' || $k === 'ai_search_fallback_enabled') {
                    $v = isset($_POST[$k]) ? '1' : '0';
                } else {
                    $v = trim((string)($_POST[$k] ?? ''));
                    if ($k === 'ai_semantic_min_score') {
                        $v = (string)max(0, min(1, (float)$v));
                    } elseif ($k === 'ai_chat_first_byte_timeout') {
                        $v = (string)max(0, min(60, (int)$v));
                    }
                }
                if ($v === '' && $k === 'ai_search_model') continue; // 留空不改
                $up->execute([$k, $v]);
                $settings[$k] = $v;
            }
            $actionMsg = '<div class="admin-alert admin-alert-success">设置已保存，立即生效</div>';
        } elseif ($_POST['action'] === 'rebuild') {
            // ---- 并发锁（10 分钟）----
            $lockKey = 'help_rebuild_locked_at';
            $lockedAt = (int)($settings[$lockKey] ?? 0);
            if ($lockedAt > 0 && time() - $lockedAt < 600) {
                $actionMsg = '<div class="admin-alert admin-alert-warning">重建进行中（或上次异常退出），请稍后再试</div>';
            } else {
                $up = $pdo->prepare(
                    "INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?)
                     ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
                );
                $up->execute([$lockKey, (string)time()]);
                $emb = EmbeddingProvider::pick($pdo);
                if (!$emb) {
                    $up->execute([$lockKey, '0']);
                    $actionMsg = '<div class="admin-alert admin-alert-error">没有可用的嵌入渠道——先到「AI渠道配置」新增用途为「嵌入」的渠道</div>';
                } else {
                    @set_time_limit(300);
                    $t0 = microtime(true);
                    $stats = HelpChunkSync::rebuildAll(
                        $pdo, $emb,
                        function ($m) use (&$rebuildLog) { $rebuildLog[] = $m; },
                        HelpChunkSync::makeReconnect()
                    );
                    $sec = round(microtime(true) - $t0, 1);
                    $up->execute([$lockKey, '0']);
                    $actionMsg = '<div class="admin-alert admin-alert-' . ($stats['failed'] > 0 ? 'warning' : 'success') . '">'
                        . '重建完成（' . $sec . 's）：知识源 ' . $stats['sources'] . ' 个，总块数 ' . $stats['chunks']
                        . '，新嵌 ' . $stats['embedded'] . '，保留 ' . $stats['kept']
                        . '，清理 ' . $stats['dropped'] . '，失败 ' . $stats['failed'] . '</div>';
                    HelpRetrieval::resetCache();
                }
            }
        } elseif ($_POST['action'] === 'rebuild_source') {
            $type = in_array($_POST['source_type'] ?? '', ['article', 'faq', 'glossary'], true) ? $_POST['source_type'] : '';
            $sid = (int)($_POST['source_id'] ?? 0);
            $emb = EmbeddingProvider::pick($pdo);
            if ($type && $sid > 0 && $emb) {
                @set_time_limit(120);
                if ($type === 'article') $s = HelpChunkSync::syncArticle($pdo, $emb, $sid);
                elseif ($type === 'faq') $s = HelpChunkSync::syncFaq($pdo, $emb, $sid);
                else $s = HelpChunkSync::syncGlossary($pdo, $emb, $sid);
                HelpRetrieval::resetCache();
                $actionMsg = '<div class="admin-alert admin-alert-success">'
                    . h($type) . ' #' . $sid . ' 同步完成：' . $s['total'] . ' 块（新嵌 ' . $s['embedded'] . '）</div>';
            }
        } elseif ($_POST['action'] === 'test') {
            $q = trim((string)($_POST['q'] ?? ''));
            if ($q !== '') {
                $topN = max(1, min(5, (int)($settings['ai_rag_topn'] ?? 3)));
                $threshold = (float)($settings['ai_semantic_min_score'] ?? 0.45);
                $emb = EmbeddingProvider::pick($pdo);
                $ret = new HelpRetrieval($pdo, $emb);
                $r = $ret->search($q, $topN, $threshold);
                $legacy = HelpRetrieval::legacySearch($pdo, $q, $topN);
                $testResult = ['q' => $q, 'r' => $r, 'legacy' => $legacy, 'threshold' => $threshold, 'emb_ok' => (bool)$emb];
            }
        }
    } catch (Exception $ex) {
        $actionMsg = '<div class="admin-alert admin-alert-error">操作失败：' . h($ex->getMessage()) . '</div>';
        try {
            $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('help_rebuild_locked_at', '0')
                           ON DUPLICATE KEY UPDATE setting_value = '0'")->execute();
        } catch (Exception $ex2) {}
    }
}

function h($s) { return htmlspecialchars((string)$s); }

// 标注台跳转过来时（?q=...）直接跑一次检索，省得再手工输入
if ($testResult === null && isset($_GET['q']) && trim((string)$_GET['q']) !== '') {
    $q = trim((string)$_GET['q']);
    $topN = max(1, min(5, (int)($settings['ai_rag_topn'] ?? 3)));
    $threshold = (float)($settings['ai_semantic_min_score'] ?? 0.45);
    $embTest = EmbeddingProvider::pick($pdo);
    $ret = new HelpRetrieval($pdo, $embTest);
    $testResult = [
        'q' => $q,
        'r' => $ret->search($q, $topN, $threshold),
        'legacy' => HelpRetrieval::legacySearch($pdo, $q, $topN),
        'threshold' => $threshold,
        'emb_ok' => (bool)$embTest,
    ];
}

// ---------- 块状态 ----------
$chunkStats = [];
try {
    $chunkStats = $pdo->query(
        "SELECT source_type, COUNT(*) AS chunks, SUM(embedding IS NOT NULL) AS embedded,
                MAX(dim) AS dim, MAX(updated_at) AS last_at
         FROM help_chunks GROUP BY source_type"
    )->fetchAll();
} catch (Exception $ex) {}

// 已发布但未入库的源
$missing = [];
try {
    $missing = array_merge(
        $pdo->query("SELECT 'article' AS t, a.id, a.title AS name FROM help_articles a
                     WHERE a.status='published' AND NOT EXISTS (SELECT 1 FROM help_chunks c WHERE c.source_type='article' AND c.source_id=a.id)
                     ORDER BY a.id LIMIT 20")->fetchAll(),
        $pdo->query("SELECT 'faq' AS t, f.id, f.question AS name FROM help_faq f
                     WHERE f.status='published' AND NOT EXISTS (SELECT 1 FROM help_chunks c WHERE c.source_type='faq' AND c.source_id=f.id)
                     ORDER BY f.id LIMIT 20")->fetchAll(),
        $pdo->query("SELECT 'glossary' AS t, g.id, g.term AS name FROM help_glossary g
                     WHERE NOT EXISTS (SELECT 1 FROM help_chunks c WHERE c.source_type='glossary' AND c.source_id=g.id)
                     ORDER BY g.id LIMIT 20")->fetchAll()
    );
} catch (Exception $ex) {}

$emb = EmbeddingProvider::pick($pdo);
$enabled = ($settings['ai_semantic_rag_enabled'] ?? '0') === '1';

$admin_site_config = ['site' => 'main', 'page_title' => '语义检索控制台'];
require_once '../shared/admin/admin-header.php';
?>

<?= $actionMsg ?>

<div class="admin-card">
    <div class="admin-card-header">
        <span class="admin-card-title"><i class="fas fa-satellite-dish"></i> 运行状态</span>
        <form method="POST" style="display:inline" onsubmit="return confirm('确认全量重建所有知识块？会调用嵌入 API（量级极小），耗时约数十秒。')">
            <input type="hidden" name="action" value="rebuild">
            <button class="admin-btn admin-btn-primary admin-btn-sm">全量重建知识块</button>
        </form>
    </div>
    <div class="admin-card-body">
        <div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:12px;">
            <span style="padding:4px 12px;border-radius:999px;font-size:12px;background:<?= $enabled ? '#134e4a' : '#3f3f46' ?>;color:<?= $enabled ? '#5eead4' : '#d4d4d8' ?>">
                混合检索：<?= $enabled ? '已开启' : '已关闭（走原 ngram 路径）' ?>
            </span>
            <span style="padding:4px 12px;border-radius:999px;font-size:12px;background:<?= $emb ? '#134e4a' : '#7f1d1d' ?>;color:<?= $emb ? '#5eead4' : '#fecaca' ?>">
                嵌入渠道：<?= $emb ? h($emb->name()) . ' / ' . h($emb->model()) : '未配置' ?>
            </span>
            <?php if ($emb): ?>
            <span style="font-size:11px;color:#94a3b8;font-family:monospace;"><?= h($emb->endpointUrl()) ?></span>
            <?php endif; ?>
        </div>

        <?php if ($chunkStats): ?>
        <table class="admin-data-table">
            <thead><tr><th>来源</th><th>块数</th><th>已嵌入</th><th>维度</th><th>最后更新</th></tr></thead>
            <tbody>
                <?php foreach ($chunkStats as $r): ?>
                <tr>
                    <td><b><?= h($r['source_type']) ?></b></td>
                    <td><?= (int)$r['chunks'] ?></td>
                    <td><?= (int)$r['embedded'] ?></td>
                    <td><?= (int)$r['dim'] ?></td>
                    <td style="font-size:12px;color:#94a3b8;"><?= h($r['last_at']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div style="color:#94a3b8;font-size:13px;">help_chunks 为空——点右上角「全量重建知识块」做首次回填。</div>
        <?php endif; ?>

        <?php if ($missing): ?>
        <div style="margin-top:14px;">
            <div style="font-size:13px;color:#fbbf24;margin-bottom:6px;">
                <i class="fas fa-triangle-exclamation"></i> 已发布但未生成知识块（<?= count($missing) ?>）——通常是保存前未配嵌入渠道，可单条补建：
            </div>
            <table class="admin-data-table">
                <thead><tr><th>来源</th><th>ID</th><th>标题/问题</th><th>操作</th></tr></thead>
                <tbody>
                <?php foreach ($missing as $m): ?>
                    <tr>
                        <td><?= h($m['t']) ?></td>
                        <td>#<?= (int)$m['id'] ?></td>
                        <td><?= h(mb_substr($m['name'], 0, 40)) ?></td>
                        <td>
                            <form method="POST" style="display:inline">
                                <input type="hidden" name="action" value="rebuild_source">
                                <input type="hidden" name="source_type" value="<?= h($m['t']) ?>">
                                <input type="hidden" name="source_id" value="<?= (int)$m['id'] ?>">
                                <button class="admin-btn admin-btn-secondary admin-btn-sm">补建</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <?php if ($rebuildLog): ?>
        <details style="margin-top:12px;" open>
            <summary style="font-size:12px;color:#94a3b8;cursor:pointer;">重建明细（<?= count($rebuildLog) ?> 行）</summary>
            <pre style="max-height:240px;overflow:auto;font-size:11px;color:#cbd5e1;background:#0f172a;padding:10px;border-radius:6px;"><?= h(implode("\n", $rebuildLog)) ?></pre>
        </details>
        <?php endif; ?>
    </div>
</div>

<div class="admin-card">
    <div class="admin-card-header"><span class="admin-card-title"><i class="fas fa-sliders"></i> 开关与阈值</span></div>
    <div class="admin-card-body">
        <form method="POST">
            <input type="hidden" name="action" value="save_settings">
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:14px;">
                <div>
                    <label style="font-size:13px;"><input type="checkbox" name="ai_semantic_rag_enabled" <?= $enabled ? 'checked' : '' ?>> <b>启用混合检索</b></label>
                    <div style="font-size:12px;color:#64748b;margin-top:2px;"><?= h($settingsKeys['ai_semantic_rag_enabled']['hint']) ?></div>
                </div>
                <div>
                    <label style="display:block;font-size:13px;margin-bottom:4px;"><b>命中阈值 ai_semantic_min_score</b></label>
                    <input name="ai_semantic_min_score" value="<?= h($settings['ai_semantic_min_score'] ?? $defaults['ai_semantic_min_score']) ?>" style="width:100%;padding:8px 12px;background:#0f172a;border:1px solid #334155;border-radius:6px;color:#f1f5f9;">
                    <div style="font-size:12px;color:#64748b;margin-top:2px;"><?= h($settingsKeys['ai_semantic_min_score']['hint']) ?></div>
                </div>
                <div>
                    <label style="font-size:13px;"><input type="checkbox" name="ai_search_fallback_enabled" <?= ($settings['ai_search_fallback_enabled'] ?? '1') === '1' ? 'checked' : '' ?>> <b>联网搜索兜底</b></label>
                    <div style="font-size:12px;color:#64748b;margin-top:2px;"><?= h($settingsKeys['ai_search_fallback_enabled']['hint']) ?></div>
                </div>
                <div>
                    <label style="display:block;font-size:13px;margin-bottom:4px;"><b>前台首字节超时（秒）</b></label>
                    <input name="ai_chat_first_byte_timeout" value="<?= h($settings['ai_chat_first_byte_timeout'] ?? '8') ?>" style="width:100%;padding:8px 12px;background:#0f172a;border:1px solid #334155;border-radius:6px;color:#f1f5f9;">
                    <div style="font-size:12px;color:#64748b;margin-top:2px;"><?= h($settingsKeys['ai_chat_first_byte_timeout']['hint']) ?></div>
                </div>
                <div>
                    <label style="display:block;font-size:13px;margin-bottom:4px;"><b>联网搜索模型</b></label>
                    <input name="ai_search_model" value="<?= h($settings['ai_search_model'] ?? '') ?>" placeholder="留空=不改" style="width:100%;padding:8px 12px;background:#0f172a;border:1px solid #334155;border-radius:6px;color:#f1f5f9;font-family:monospace;font-size:12px;">
                    <div style="font-size:12px;color:#64748b;margin-top:2px;"><?= h($settingsKeys['ai_search_model']['hint']) ?></div>
                </div>
            </div>
            <div style="margin-top:14px;"><button type="submit" class="admin-btn admin-btn-primary">保存设置</button></div>
        </form>
    </div>
</div>

<div class="admin-card">
    <div class="admin-card-header"><span class="admin-card-title"><i class="fas fa-flask"></i> 检索试验台（新旧对照，用于调阈值）</span></div>
    <div class="admin-card-body">
        <form method="POST" style="display:flex;gap:8px;margin-bottom:12px;">
            <input type="hidden" name="action" value="test">
            <input name="q" value="<?= h($testResult['q'] ?? '') ?>" placeholder="输入用户可能问的问题，如：认领区块要钱吗" style="flex:1;padding:8px 12px;background:#0f172a;border:1px solid #334155;border-radius:6px;color:#f1f5f9;">
            <button class="admin-btn admin-btn-primary">检索</button>
        </form>

        <?php if ($testResult): ?>
        <div style="font-size:13px;color:#94a3b8;margin-bottom:8px;">
            问题：<b style="color:#e2e8f0;"><?= h($testResult['q']) ?></b>
            模式：<?= h($testResult['r']['mode']) ?>　阈值：<?= h((string)$testResult['threshold']) ?>
            命中判定：<b style="color:<?= $testResult['r']['matched'] ? '#5eead4' : '#fbbf24' ?>"><?= $testResult['r']['matched'] ? '命中' : '未命中' ?></b>
            <?php if (!$testResult['r']['matched'] && ($settings['ai_search_fallback_enabled'] ?? '1') === '1'): ?>
            → 线上将走联网搜索兜底（回答标注「非官方」）
            <?php endif; ?>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
            <div>
                <div style="font-size:13px;margin-bottom:6px;"><b>新：混合检索</b></div>
                <?php if ($testResult['r']['chunks']): ?>
                <table class="admin-data-table">
                    <thead><tr><th>标题</th><th>cosine</th><th>RRF</th></tr></thead>
                    <tbody>
                    <?php foreach ($testResult['r']['chunks'] as $c): ?>
                        <tr>
                            <td>
                                <div style="font-size:12px;"><?= h(mb_substr($c['title'], 0, 30)) ?></div>
                                <div style="font-size:11px;color:#64748b;"><?= h(mb_substr($c['text'], 0, 60)) ?>…</div>
                            </td>
                            <td style="font-size:12px;"><?= h((string)($c['cosine'] ?? '-')) ?></td>
                            <td style="font-size:12px;color:#94a3b8;"><?= h((string)$c['score']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div style="font-size:12px;color:#94a3b8;">（无结果）</div>
                <?php endif; ?>
            </div>
            <div>
                <div style="font-size:13px;margin-bottom:6px;"><b>旧：ngram 检索（对照）</b></div>
                <?php if ($testResult['legacy']): ?>
                <table class="admin-data-table">
                    <thead><tr><th>标题</th><th>score</th></tr></thead>
                    <tbody>
                    <?php foreach ($testResult['legacy'] as $a): ?>
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
        <?php if (!$testResult['emb_ok']): ?>
        <div style="margin-top:8px;font-size:12px;color:#fca5a5;">嵌入渠道不可用，本次为纯 ngram 降级结果</div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../shared/admin/admin-footer.php'; ?>
