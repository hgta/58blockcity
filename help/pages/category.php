<?php
/**
 * 分类列表页
 * change: help-center-ai-assistant (task 2.3)
 */

$slug = isset($_GET['slug']) ? preg_replace('/[^a-z0-9\-]/', '', (string)$_GET['slug']) : '';

$cat = null;
foreach ($pdo->query("SELECT * FROM help_categories WHERE is_visible = 1 ORDER BY sort_order, id") as $c) {
    if ($c['slug'] === $slug) { $cat = $c; break; }
}
$allCats = $pdo->query("SELECT id, name, slug FROM help_categories WHERE is_visible = 1 ORDER BY sort_order, id")->fetchAll();

$page  = max(1, (int)($_GET['page'] ?? 1));
$per   = 20;

if (!$cat) {
    http_response_code(404);
    require __DIR__ . '/../_layout.php';
    help_header(['title' => '分类不存在', 'active' => '']);
    echo '<div class="hc-empty"><i class="fa-solid fa-folder-minus"></i><div>分类不存在或已下线</div><a class="btn" href="' . e(help_url()) . '">返回帮助中心首页</a></div>';
    help_footer();
    exit;
}

$total = (int)$pdo->query("SELECT COUNT(*) FROM help_articles WHERE category_id = " . (int)$cat['id'] . " AND status = 'published'")->fetchColumn();
$pages = max(1, (int)ceil($total / $per));
$page  = min($page, $pages);
$arts = $pdo->prepare(
    "SELECT id, title, slug, summary, is_pinned, is_ai_generated, view_count, updated_at
     FROM help_articles WHERE category_id = :cid AND status = 'published'
     ORDER BY is_pinned DESC, updated_at DESC LIMIT :off, :per"
);
$arts->bindValue(':cid', (int)$cat['id'], PDO::PARAM_INT);
$arts->bindValue(':off', ($page - 1) * $per, PDO::PARAM_INT);
$arts->bindValue(':per', $per, PDO::PARAM_INT);
$arts->execute();
$arts = $arts->fetchAll();

require __DIR__ . '/../_layout.php';
help_header([
    'title' => $cat['name'],
    'description' => $cat['description'] ?: ($cat['name'] . '相关教程与常见问题 - 58区块城市帮助中心'),
    'active' => 'cat-' . $cat['slug'],
]);
?>

<div class="hc-breadcrumb"><a href="<?= e(help_url()) ?>">帮助中心</a> / <?= e($cat['name']) ?></div>

<div class="hc-layout">
  <aside class="hc-side">
    <div class="hc-side-card">
      <h4><i class="fa-solid fa-folder-tree"></i> 全部分类</h4>
      <?php foreach ($allCats as $c): ?>
      <a href="<?= e(category_url($c['slug'])) ?>" class="<?= $c['id'] == $cat['id'] ? 'on' : '' ?>">
        <i class="fa-solid fa-angle-right"></i><?= e($c['name']) ?>
      </a>
      <?php endforeach; ?>
    </div>
    <div class="hc-side-card">
      <h4><i class="fa-solid fa-robot"></i> AI 助手</h4>
      <a href="<?= e(help_url('ask')) ?>"><i class="fa-solid fa-comment-dots" style="color:var(--brand)"></i>没找到？问 AI</a>
      <a href="<?= e(help_url('faq')) ?>"><i class="fa-solid fa-list-check"></i>常见问题</a>
    </div>
  </aside>

  <main>
    <div class="hc-section-title"><i class="fa-solid fa-<?= e($cat['icon']) ?>"></i> <?= e($cat['name']) ?>
      <span style="font-size:13px;font-weight:400;color:var(--muted)"><?= $total ?> 篇内容</span>
    </div>

    <?php if (!$arts): ?>
    <div class="hc-empty">
      <i class="fa-solid fa-file-circle-question"></i>
      <div>该分类下暂无内容，正在补充中</div>
      <a class="btn" href="<?= e(help_url('ask')) ?>"><i class="fa-solid fa-robot"></i> 先问问 AI 助手</a>
      <div style="margin-top:10px"><a href="<?= e(help_url()) ?>">← 返回首页</a></div>
    </div>
    <?php else: ?>
    <div class="hc-articles">
      <?php foreach ($arts as $a): ?>
      <a class="hc-art-card" href="<?= e(article_url($a['slug'])) ?>">
        <h3>
          <?= $a['is_pinned'] ? '<i class="fa-solid fa-thumbtack" style="color:var(--brand)" title="置顶"></i> ' : '' ?>
          <?= e($a['title']) ?>
        </h3>
        <p><?= e($a['summary']) ?></p>
        <div class="meta">
          <span><i class="fa-regular fa-eye"></i> <?= (int)$a['view_count'] ?></span>
          <span>更新于 <?= e(date('Y-m-d', strtotime($a['updated_at']))) ?></span>
        </div>
      </a>
      <?php endforeach; ?>
    </div>

    <?php if ($pages > 1): ?>
    <div class="hc-pager">
      <?php if ($page > 1): ?><a href="<?= e(category_url($cat['slug']) . '?page=' . ($page - 1)) ?>">«</a><?php endif; ?>
      <?php for ($i = 1; $i <= $pages; $i++): ?>
        <?php if ($i == $page): ?><span class="cur"><?= $i ?></span>
        <?php else: ?><a href="<?= e(category_url($cat['slug']) . '?page=' . $i) ?>"><?= $i ?></a><?php endif; ?>
      <?php endfor; ?>
      <?php if ($page < $pages): ?><a href="<?= e(category_url($cat['slug']) . '?page=' . ($page + 1)) ?>">»</a><?php endif; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
  </main>
</div>

<?php help_footer(); ?>
