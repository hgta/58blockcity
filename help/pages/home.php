<?php
/**
 * 帮助中心首页
 * change: help-center-ai-assistant (task 2.2)
 */

// 分类（含文章计数）
$cats = $pdo->query(
    "SELECT c.id, c.name, c.slug, c.icon, c.description,
            (SELECT COUNT(*) FROM help_articles a WHERE a.category_id = c.id AND a.status = 'published') AS cnt
     FROM help_categories c
     WHERE c.is_visible = 1 AND c.parent_id = 0
     ORDER BY c.sort_order, c.id"
)->fetchAll();

// 热门文章（浏览量 top 8）
$hot = $pdo->query(
    "SELECT id, title, slug, summary, view_count, updated_at
     FROM help_articles WHERE status = 'published'
     ORDER BY view_count DESC, updated_at DESC LIMIT 8"
)->fetchAll();

// 最新文章
$latest = $pdo->query(
    "SELECT id, title, slug, summary, updated_at
     FROM help_articles WHERE status = 'published'
     ORDER BY updated_at DESC LIMIT 6"
)->fetchAll();

// 热门 FAQ
$hotFaq = $pdo->query(
    "SELECT id, question, answer FROM help_faq WHERE status = 'published'
     ORDER BY sort_order, id LIMIT 5"
)->fetchAll();

$aiEnabled = help_setting('ai_assistant_enabled', '1') === '1';
$popularQs = ['怎么认领地块', 'BCT 是什么', '怎么开店卖货', '如何提现', 'NFT 头像怎么获得'];

require __DIR__ . '/../_layout.php';
help_header(['active' => 'home']);
?>

<div class="hc-hero">
  <h1><i class="fa-solid fa-circle-question"></i> 有问题？这里都有答案</h1>
  <p>图文教程 · 常见问题 · 术语解释<?= $aiEnabled ? ' · AI 在线答疑' : '' ?></p>
  <form class="hc-hero-search" action="<?= e(help_url('search')) ?>" method="get">
    <input type="text" name="q" placeholder="输入你的问题，如：怎么认领地块？" value="<?= e($q = isset($_GET['q']) ? trim((string)$_GET['q']) : '') ?>">
    <button type="submit">搜索</button>
  </form>
  <div class="hc-hero-tags">热门：
    <?php foreach ($popularQs as $pq): ?>
      <a href="<?= e(help_url('search') . '?q=' . urlencode($pq)) ?>"><?= e($pq) ?></a>
    <?php endforeach; ?>
  </div>
</div>

<div class="hc-section-title"><i class="fa-solid fa-folder-open"></i> 浏览分类</div>
<div class="hc-grid">
  <?php foreach ($cats as $c): ?>
  <a class="hc-cat-card" href="<?= e(category_url($c['slug'])) ?>">
    <i class="fa-solid fa-<?= e($c['icon']) ?>"></i>
    <div>
      <div class="t"><?= e($c['name']) ?></div>
      <div class="d"><?= e($c['description']) ?></div>
    </div>
    <span class="n"><?= (int)$c['cnt'] ?> 篇</span>
  </a>
  <?php endforeach; ?>
</div>

<?php if ($aiEnabled): ?>
<div class="hc-section-title"><i class="fa-solid fa-robot"></i> AI 助手</div>
<div class="hc-list">
  <a class="hc-list-item" href="<?= e(help_url('ask')) ?>">
    <i class="fa-solid fa-robot" style="color:var(--brand)"></i>
    <div class="t">没找到答案？直接问 AI 助手 —— 7×24 小时在线，基于官方教程回答并附引用来源</div>
    <span class="m hc-ask-btn" style="color:var(--brand);font-weight:600">去提问 →</span>
  </a>
</div>
<?php endif; ?>

<?php if ($hot): ?>
<div class="hc-section-title"><i class="fa-solid fa-fire"></i> 热门教程
  <a class="more" href="<?= e(help_url('faq')) ?>">更多常见问题 →</a>
</div>
<div class="hc-list">
  <?php foreach ($hot as $i => $a): ?>
  <a class="hc-list-item" href="<?= e(article_url($a['slug'])) ?>">
    <span class="idx"><?= $i + 1 ?></span>
    <div class="t"><?= e($a['title']) ?></div>
    <?php if ($i === 0): ?><span class="hot"><i class="fa-solid fa-fire"></i> HOT</span><?php endif; ?>
    <span class="m"><?= (int)$a['view_count'] ?> 次浏览</span>
  </a>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($latest): ?>
<div class="hc-section-title"><i class="fa-solid fa-clock"></i> 最近更新</div>
<div class="hc-articles">
  <?php foreach ($latest as $a): ?>
  <a class="hc-art-card" href="<?= e(article_url($a['slug'])) ?>">
    <h3><?= e($a['title']) ?> <span class="new">NEW</span></h3>
    <p><?= e($a['summary']) ?></p>
    <div class="meta"><span>更新于 <?= e(date('Y-m-d', strtotime($a['updated_at']))) ?></span></div>
  </a>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($hotFaq): ?>
<div class="hc-section-title"><i class="fa-solid fa-comments"></i> 大家都在问</div>
<?php foreach ($hotFaq as $f): ?>
<div class="hc-faq-item">
  <div class="hc-faq-q"><i class="fa-solid fa-circle-question"></i><?= e($f['question']) ?><i class="fa-solid fa-chevron-down"></i></div>
  <div class="hc-faq-a"><?= nl2br(e($f['answer'])) ?></div>
</div>
<?php endforeach; ?>
<?php endif; ?>

<?php help_footer(); ?>
