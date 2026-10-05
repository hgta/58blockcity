<?php
/**
 * 术语表页（按拼音首字母分组）
 * change: help-center-ai-assistant (task 2.4)
 */

$terms = $pdo->query(
    "SELECT g.term, g.pinyin, g.definition, a.slug AS art_slug, a.title AS art_title
     FROM help_glossary g
     LEFT JOIN help_articles a ON a.id = g.related_article_id AND a.status = 'published'
     ORDER BY g.sort_order, g.pinyin, g.id
     LIMIT 500"
)->fetchAll();

// 按拼音首字母分组（无拼音的归入 #）
$groups = [];
foreach ($terms as $t) {
    $letter = strtoupper(mb_substr(preg_replace('/[^a-zA-Z]/u', '', $t['pinyin']), 0, 1));
    if ($letter === '') $letter = '#';
    $groups[$letter][] = $t;
}
ksort($groups);

require __DIR__ . '/../_layout.php';
help_header([
    'title' => '术语表',
    'description' => '58区块城市平台术语表：区块、BCT人气值、NFT、互访圈、拍卖等名词解释，按拼音字母排序。',
    'active' => 'glossary',
]);
?>

<div class="hc-breadcrumb"><a href="<?= e(help_url()) ?>">帮助中心</a> / 术语表</div>

<?php help_pagehead('术语表', $terms ? '按拼音首字母排列，共 ' . count($terms) . ' 条' : ''); ?>

<?php if (!$terms): ?>
<div class="hc-empty">
  <i class="fa-solid fa-book"></i>
  <div>术语表正在整理中</div>
  <a class="btn" href="<?= e(help_url('ask')) ?>"><i class="fa-solid fa-robot"></i> 先问问 AI 助手</a>
</div>
<?php else: ?>

<div class="hc-faq-cat" id="hcTermNav">
  <?php foreach (array_keys($groups) as $letter): ?>
  <a href="#letter-<?= e($letter) ?>"><?= e($letter) ?></a>
  <?php endforeach; ?>
</div>

<?php foreach ($groups as $letter => $items): ?>
<div class="hc-term-group">
  <h3 id="letter-<?= e($letter) ?>"><?= e($letter) ?></h3>
  <?php foreach ($items as $t): ?>
  <div class="hc-term-item">
    <div class="term"><?= e($t['term']) ?></div>
    <div class="def"><?= nl2br(e($t['definition'])) ?>
      <?php if ($t['art_slug']): ?>
        <a href="<?= e(article_url($t['art_slug'])) ?>"><i class="fa-solid fa-book-open"></i> <?= e($t['art_title']) ?></a>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endforeach; ?>
<?php endif; ?>

<div style="margin-top:22px">
  <?= help_ai_cta('cta', '没找到想查的术语？问 AI 助手，或留言给我们') ?>
</div>

<?php help_footer(); ?>
