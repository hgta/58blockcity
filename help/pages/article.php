<?php
/**
 * 文章详情页（richtext / steps 双模式渲染 + 反馈 + 相关推荐）
 * change: help-center-ai-assistant (task 2.3)
 */

$slug = isset($_GET['slug']) ? preg_replace('/[^a-z0-9\-]/', '', (string)$_GET['slug']) : '';

$stmt = $pdo->prepare(
    "SELECT a.*, c.name AS cat_name, c.slug AS cat_slug, c.icon AS cat_icon
     FROM help_articles a JOIN help_categories c ON c.id = a.category_id
     WHERE a.slug = :slug"
);
$stmt->execute([':slug' => $slug]);
$a = $stmt->fetch();

if (!$a) {
    http_response_code(404);
    require __DIR__ . '/../_layout.php';
    help_header(['title' => '内容不存在']);
    echo '<div class="hc-empty"><i class="fa-solid fa-file-circle-xmark"></i><div>文章不存在</div><a class="btn" href="' . e(help_url()) . '">返回帮助中心首页</a></div>';
    help_footer();
    exit;
}

// 下架/草稿：明确提示并引导
if ($a['status'] !== 'published') {
    http_response_code(410);
    require __DIR__ . '/../_layout.php';
    help_header(['title' => '内容已下架']);
    echo '<div class="hc-empty"><i class="fa-solid fa-eye-slash"></i><div>该内容已下架</div>'
       . '<a class="btn" href="' . e(category_url($a['cat_slug'])) . '">返回分类：' . e($a['cat_name']) . '</a></div>';
    help_footer();
    exit;
}

// 浏览计数（简单防刷：会话内不重复）
$viewKey = 'help_v_' . $a['id'];
if (empty($_SESSION[$viewKey])) {
    $_SESSION[$viewKey] = 1;
    $pdo->prepare("UPDATE help_articles SET view_count = view_count + 1 WHERE id = :id")->execute([':id' => $a['id']]);
    $a['view_count']++;
}

// 当前访客是否已反馈
$fbStmt = $pdo->prepare("SELECT helpful FROM help_article_feedback WHERE article_id = :aid AND visitor_hash = :vh");
$fbStmt->execute([':aid' => $a['id'], ':vh' => help_visitor_hash()]);
$myFeedback = $fbStmt->fetchColumn(); // false | 0 | 1

// 相关文章（同分类其他文章）
$relStmt = $pdo->prepare(
    "SELECT id, title, slug FROM help_articles
     WHERE category_id = :cid AND status = 'published' AND id != :id
     ORDER BY is_pinned DESC, view_count DESC LIMIT 5"
);
$relStmt->execute([':cid' => $a['category_id'], ':id' => $a['id']]);
$related = $relStmt->fetchAll();

// 关联FAQ（引用本文的）
$faqStmt = $pdo->prepare(
    "SELECT question, answer FROM help_faq WHERE related_article_id = :aid AND status = 'published' LIMIT 3"
);
$faqStmt->execute([':aid' => $a['id']]);
$faqs = $faqStmt->fetchAll();

// 步骤解析
$steps = [];
if ($a['content_type'] === 'steps') {
    $decoded = json_decode($a['content_steps'], true);
    if (is_array($decoded)) $steps = $decoded;
}

require __DIR__ . '/../_layout.php';
help_header([
    'title' => $a['title'],
    'description' => $a['summary'] ?: help_plain_summary($a['content_richtext']),
]);
?>

<div class="hc-breadcrumb">
  <a href="<?= e(help_url()) ?>">帮助中心</a> /
  <a href="<?= e(category_url($a['cat_slug'])) ?>"><?= e($a['cat_name']) ?></a> /
  <?= e(mb_substr($a['title'], 0, 24)) ?>
</div>

<div class="hc-layout">
  <aside class="hc-side">
    <div class="hc-side-card">
      <h4><i class="fa-solid fa-list"></i> 本文目录</h4>
      <?php if ($steps): ?>
        <?php foreach ($steps as $i => $s): ?>
        <a href="#step-<?= $i + 1 ?>"><i class="fa-solid fa-hashtag"></i><?= e(mb_substr($s['title'] ?? ('第' . ($i + 1) . '步'), 0, 16)) ?></a>
        <?php endforeach; ?>
      <?php else: ?>
        <a href="#article-body"><i class="fa-solid fa-file-lines"></i>正文内容</a>
      <?php endif; ?>
    </div>
    <?php if ($related): ?>
    <div class="hc-side-card">
      <h4><i class="fa-solid fa-link"></i> 相关文章</h4>
      <?php foreach ($related as $r): ?>
      <a href="<?= e(article_url($r['slug'])) ?>"><i class="fa-solid fa-angle-right"></i><?= e(mb_substr($r['title'], 0, 18)) ?></a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </aside>

  <main>
    <article class="hc-article">
      <h1><?= e($a['title']) ?></h1>
      <div class="meta">
        <a href="<?= e(category_url($a['cat_slug'])) ?>"><i class="fa-solid fa-<?= e($a['cat_icon']) ?>"></i> <?= e($a['cat_name']) ?></a>
        <span><i class="fa-regular fa-clock"></i> 更新于 <?= e(date('Y-m-d', strtotime($a['updated_at']))) ?></span>
        <span><i class="fa-regular fa-eye"></i> <?= (int)$a['view_count'] ?> 次浏览</span>
        <?php if ($a['is_ai_generated']): ?><span style="color:#9ca3af"><i class="fa-solid fa-wand-magic-sparkles"></i> 内容经人工审核</span><?php endif; ?>
      </div>

      <?php if ($a['summary']): ?>
        <div style="background:#fff7f0;border-radius:10px;padding:12px 16px;font-size:14px;color:var(--muted);margin-bottom:16px">
          <i class="fa-solid fa-lightbulb" style="color:var(--brand)"></i> <?= e($a['summary']) ?>
        </div>
      <?php endif; ?>

      <div class="body" id="article-body">
        <?php if ($a['content_type'] === 'steps' && $steps): ?>
          <div class="hc-steps">
            <?php foreach ($steps as $i => $s): ?>
            <div class="hc-step" id="step-<?= $i + 1 ?>">
              <h3><?= e($s['title'] ?? ('第 ' . ($i + 1) . ' 步')) ?></h3>
              <p><?= e($s['text'] ?? '') ?></p>
              <?php if (!empty($s['image'])): ?>
                <img src="<?= e($s['image']) ?>" alt="<?= e($s['title'] ?? '步骤截图') ?>" loading="lazy">
              <?php endif; ?>
            </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <?= $a['content_richtext'] /* 已入库富文本（后台/迁移脚本产出） */ ?>
        <?php endif; ?>
      </div>

      <?php if ($faqs): ?>
      <div style="margin-top:24px">
        <div class="hc-section-title" style="margin-top:0"><i class="fa-solid fa-comments"></i> 相关常见问题</div>
        <?php foreach ($faqs as $f): ?>
        <div class="hc-faq-item">
          <div class="hc-faq-q"><i class="fa-solid fa-circle-question"></i><?= e($f['question']) ?><i class="fa-solid fa-chevron-down"></i></div>
          <div class="hc-faq-a"><?= nl2br(e($f['answer'])) ?></div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <div class="hc-feedback" id="helpFeedback" data-article="<?= (int)$a['id'] ?>">
        <span><i class="fa-solid fa-circle-check" style="color:var(--brand)"></i> 这篇内容对你有帮助吗？</span>
        <?php if ($myFeedback === false): ?>
          <button type="button" data-helpful="1"><i class="fa-regular fa-thumbs-up"></i> 有帮助</button>
          <button type="button" data-helpful="0"><i class="fa-regular fa-thumbs-down"></i> 没帮助</button>
        <?php else: ?>
          <button type="button" class="done" disabled>已反馈<?= $myFeedback ? '：有帮助' : '：没帮助' ?>，感谢！</button>
        <?php endif; ?>
        <span id="fbMsg" style="font-size:12px;color:var(--muted)"></span>
      </div>
    </article>

    <?php if ($related): ?>
    <div class="hc-section-title"><i class="fa-solid fa-book-open"></i> 继续阅读</div>
    <div class="hc-list">
      <?php foreach ($related as $r): ?>
      <a class="hc-list-item" href="<?= e(article_url($r['slug'])) ?>">
        <i class="fa-solid fa-file-lines" style="color:#c0c4cc"></i>
        <div class="t"><?= e($r['title']) ?></div>
        <span class="m">→</span>
      </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </main>
</div>

<script>
(function () {
  var box = document.getElementById('helpFeedback');
  if (!box) return;
  box.addEventListener('click', function (ev) {
    var btn = ev.target.closest('button[data-helpful]');
    if (!btn) return;
    var xhr = new XMLHttpRequest();
    xhr.open('POST', '<?= e(help_url('api/feedback.php')) ?>', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onreadystatechange = function () {
      if (xhr.readyState !== 4) return;
      var msg = document.getElementById('fbMsg');
      try {
        var res = JSON.parse(xhr.responseText);
        if (res.ok) {
          box.querySelectorAll('button[data-helpful]').forEach(function (b) { b.remove(); });
          var done = document.createElement('button');
          done.className = 'done'; done.disabled = true;
          done.textContent = '已记录，感谢反馈！';
          box.insertBefore(done, msg);
        } else {
          msg.textContent = res.msg || '提交失败，请稍后再试';
        }
      } catch (e) { msg.textContent = '网络异常，请稍后再试'; }
    };
    xhr.send('article_id=' + box.dataset.article + '&helpful=' + btn.dataset.helpful);
  });
})();
</script>

<?php help_footer(); ?>
