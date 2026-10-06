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
             ORDER BY f.sort_order, f.id DESC"
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
    'canonical' => help_canonical_url('faq'),
    // FAQPage 只送当前激活 tab 的问答（change: help-structured-data D3）；空分类时 schema 返回空串
    'jsonld' => $items ? SeoHelper::faqPageSchema($items) : '',
    'breadcrumb' => [
        ['name' => '帮助中心', 'url' => help_canonical_url()],
        ['name' => '常见问题', 'url' => ''],
    ],
]);
?>

<div class="hc-breadcrumb"><a href="<?= e(help_url()) ?>">帮助中心</a> / 常见问题</div>

<?php help_pagehead('常见问题', $cats ? '按分类浏览，点开查看答案' : ''); ?>

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
  <?php help_faq_item(
      $f['question'],
      $f['answer'],
      $f['art_slug'] ? article_url($f['art_slug']) : '',
      '查看详细教程：' . $f['art_title']
  ); ?>
<?php endforeach; ?>
<?php endif; ?>
<?php endif; ?>

<div style="margin-top:22px">
  <?= help_ai_cta('cta', '没找到你的问题？问 AI 助手，或留言给我们') ?>
</div>

<?php help_footer(); ?>
