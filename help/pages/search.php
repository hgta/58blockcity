<?php
/**
 * 站内搜索（FULLTEXT ngram 优先，LIKE 兜底，摘要高亮）
 * change: help-center-ai-assistant (task 2.4)
 */

$q = trim((string)($_GET['q'] ?? ''));
$kw = mb_substr($q, 0, 60);
$arts = [];

if ($kw !== '') {
    // 1) FULLTEXT ngram
    try {
        $stmt = $pdo->prepare(
            "SELECT id, title, slug, summary, content_richtext, content_steps,
                    MATCH(title, summary, content_richtext) AGAINST(:kw1 IN NATURAL LANGUAGE MODE) AS score
             FROM help_articles
             WHERE status = 'published' AND MATCH(title, summary, content_richtext) AGAINST(:kw2 IN NATURAL LANGUAGE MODE)
             ORDER BY score DESC, view_count DESC LIMIT 20"
        );
        $stmt->execute([':kw1' => $kw, ':kw2' => $kw]);
        $arts = $stmt->fetchAll();
    } catch (Exception $ex) { $arts = []; }

    // 2) LIKE 兜底（短词/分词边界场景）
    if (!$arts) {
        $like = '%' . $kw . '%';
        $stmt = $pdo->prepare(
            "SELECT id, title, slug, summary, content_richtext, content_steps, 0 AS score
             FROM help_articles
             WHERE status = 'published' AND (title LIKE :l1 OR summary LIKE :l2 OR content_richtext LIKE :l3)
             ORDER BY is_pinned DESC, view_count DESC LIMIT 20"
        );
        $stmt->execute([':l1' => $like, ':l2' => $like, ':l3' => $like]);
        $arts = $stmt->fetchAll();
    }

    // 3) FAQ 补充结果
    $like = '%' . $kw . '%';
    $stmt = $pdo->prepare(
        "SELECT id, question, answer, related_article_id FROM help_faq
         WHERE status = 'published' AND (question LIKE :l1 OR answer LIKE :l2)
         ORDER BY sort_order LIMIT 5"
    );
    $stmt->execute([':l1' => $like, ':l2' => $like]);
    $faqs = $stmt->fetchAll();
}

/** 摘要生成：命中处前后截取并高亮 */
function hl_summary($q, $row) {
    $src = '';
    if (!empty($row['summary'])) {
        $src = $row['summary'];
    } else {
        $plain = trim(preg_replace('/\s+/u', ' ', strip_tags((string)$row['content_richtext'])));
        $pos = mb_strpos($plain, $q);
        $src = $pos === false ? mb_substr($plain, 0, 120) : mb_substr($plain, max(0, $pos - 30), 130);
    }
    return htmlspecialchars(mb_substr($src, 0, 140), ENT_QUOTES, 'UTF-8');
}

require __DIR__ . '/../_layout.php';
help_header([
    'title' => $kw !== '' ? ('搜索：' . $kw) : '搜索',
    'active' => '',
]);
?>

<div class="hc-breadcrumb"><a href="<?= e(help_url()) ?>">帮助中心</a> / 搜索</div>

<div class="hc-hero" style="padding:28px 24px">
  <form class="hc-hero-search" action="<?= e(help_url('search')) ?>" method="get">
    <input type="text" name="q" placeholder="输入关键词，如：认领、BCT、提现…" value="<?= e($kw) ?>">
    <button type="submit">搜索</button>
  </form>
</div>

<?php if ($kw === ''): ?>
  <div class="hc-empty"><i class="fa-solid fa-keyboard"></i><div>输入关键词开始搜索帮助内容</div></div>

<?php elseif (!$arts && empty($faqs)): ?>
  <div class="hc-empty">
    <i class="fa-solid fa-magnifying-glass-minus"></i>
    <div>没有找到与"<?= e($kw) ?>"相关的内容</div>
    <a class="btn" href="<?= e(help_url('ask') . '?q=' . urlencode($kw)) ?>"><i class="fa-solid fa-robot"></i> 去问问 AI 助手</a>
    <div style="margin-top:10px"><a href="<?= e(help_url()) ?>">← 返回浏览分类</a></div>
  </div>

<?php else: ?>
  <?php if ($arts): ?>
  <div class="hc-section-title"><i class="fa-solid fa-file-lines"></i> 相关教程（<?= count($arts) ?>）</div>
  <div class="hc-articles">
    <?php foreach ($arts as $a): ?>
    <a class="hc-art-card" href="<?= e(article_url($a['slug'])) ?>">
      <h3><?= e($a['title']) ?></h3>
      <p><?= hl_summary($kw, $a) ?></p>
    </a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php if (!empty($faqs)): ?>
  <div class="hc-section-title"><i class="fa-solid fa-comments"></i> 相关常见问题（<?= count($faqs) ?>）</div>
  <?php foreach ($faqs as $f): ?>
  <div class="hc-faq-item">
    <div class="hc-faq-q"><i class="fa-solid fa-circle-question"></i><?= e($f['question']) ?><i class="fa-solid fa-chevron-down"></i></div>
    <div class="hc-faq-a">
      <?= nl2br(e(mb_substr($f['answer'], 0, 200))) ?>
      <?php if ($f['related_article_id']): ?>
        <br><a href="#art-<?= (int)$f['related_article_id'] ?>">查看详细教程 ↓</a>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
  <?php endif; ?>

  <div class="hc-list" style="margin-top:20px">
    <a class="hc-list-item" href="<?= e(help_url('ask') . '?q=' . urlencode($kw)) ?>">
      <i class="fa-solid fa-robot" style="color:var(--brand)"></i>
      <div class="t">以上内容没解决？把"<?= e($kw) ?>"丢给 AI 助手试试</div>
      <span class="m" style="color:var(--brand);font-weight:600">去提问 →</span>
    </a>
  </div>
<?php endif; ?>

<?php help_footer(); ?>
