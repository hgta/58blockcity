<?php
/**
 * FAQ 页（按子站/分类 Tab 分组）
 * change: help-center-ai-assistant (task 2.4)
 */

$cats = $pdo->query(
    "SELECT c.id, c.name, c.slug,
            (SELECT COUNT(*) FROM help_faq f WHERE f.category_id = c.id AND f.status = 'published') AS cnt
     FROM help_categories c WHERE c.is_visible = 1
     HAVING cnt > 0
     ORDER BY c.sort_order, c.id"
)->fetchAll();

$active = isset($_GET['cat']) ? (string)$_GET['cat'] : ($cats ? $cats[0]['slug'] : '');
$items = [];
foreach ($cats as $c) {
    if ($c['slug'] === $active) {
        $stmt = $pdo->prepare(
            "SELECT f.question, f.answer, f.related_article_id, a.slug AS art_slug, a.title AS art_title
             FROM help_faq f
             LEFT JOIN help_articles a ON a.id = f.related_article_id AND a.status = 'published'
             WHERE f.category_id = :cid AND f.status = 'published'
             ORDER BY f.sort_order, f.id"
        );
        $stmt->execute([':cid' => $c['id']]);
        $items = $stmt->fetchAll();
        break;
    }
}

require __DIR__ . '/../_layout.php';
help_header([
    'title' => '常见问题 FAQ',
    'description' => '58区块城市各功能常见问题解答：区块交易、BCT、NFT、商城、互访圈、拍卖、账户、支付提现等。',
    'active' => 'faq',
]);
?>

<div class="hc-breadcrumb"><a href="<?= e(help_url()) ?>">帮助中心</a> / 常见问题</div>

<div class="hc-section-title" style="margin-top:0"><i class="fa-solid fa-comments"></i> 常见问题</div>

<?php if (!$cats): ?>
<div class="hc-empty">
  <i class="fa-solid fa-comments"></i>
  <div>FAQ 正在整理中</div>
  <a class="btn" href="<?= e(help_url('ask')) ?>"><i class="fa-solid fa-robot"></i> 先问问 AI 助手</a>
</div>
<?php else: ?>
<div class="hc-faq-cat">
  <?php foreach ($cats as $c): ?>
  <a href="<?= e(help_url('faq') . '?cat=' . urlencode($c['slug'])) ?>" class="<?= $c['slug'] === $active ? 'on' : '' ?>">
    <?= e($c['name']) ?>（<?= (int)$c['cnt'] ?>）
  </a>
  <?php endforeach; ?>
</div>

<?php if (!$items): ?>
<div class="hc-empty"><i class="fa-solid fa-inbox"></i><div>该分类暂无 FAQ</div></div>
<?php else: ?>
<?php foreach ($items as $f): ?>
<div class="hc-faq-item">
  <div class="hc-faq-q"><i class="fa-solid fa-circle-question"></i><?= e($f['question']) ?><i class="fa-solid fa-chevron-down"></i></div>
  <div class="hc-faq-a">
    <?= nl2br(e($f['answer'])) ?>
    <?php if ($f['art_slug']): ?>
      <br><a href="<?= e(article_url($f['art_slug'])) ?>"><i class="fa-solid fa-book-open"></i> 查看详细教程：<?= e($f['art_title']) ?></a>
    <?php endif; ?>
  </div>
</div>
<?php endforeach; ?>
<?php endif; ?>
<?php endif; ?>

<div class="hc-list" style="margin-top:22px">
  <a class="hc-list-item" href="<?= e(help_url('ask')) ?>">
    <i class="fa-solid fa-robot" style="color:var(--brand)"></i>
    <div class="t">没找到你的问题？问 AI 助手，或留言给我们</div>
    <span class="m" style="color:var(--brand);font-weight:600">去提问 →</span>
  </a>
</div>

<?php help_footer(); ?>
