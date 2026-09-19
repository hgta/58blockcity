<?php
/**
 * 帮助中心 sitemap（仅 published 内容）
 * change: help-center-ai-assistant (task 2.5)
 */

require_once __DIR__ . '/../_init.php';

header('Content-Type: application/xml; charset=utf-8');

$base = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'help.58.tl') . help_base();

$urls = [];
$urls[] = ['loc' => $base, 'priority' => '1.0'];
$urls[] = ['loc' => $base . 'faq', 'priority' => '0.8'];
$urls[] = ['loc' => $base . 'glossary', 'priority' => '0.6'];

foreach ($pdo->query("SELECT slug, updated_at FROM help_categories WHERE is_visible = 1") as $c) {
    $urls[] = ['loc' => $base . 'category/' . $c['slug'], 'priority' => '0.7'];
}
foreach ($pdo->query("SELECT slug, updated_at FROM help_articles WHERE status = 'published'") as $a) {
    $urls[] = ['loc' => $base . 'article/' . $a['slug'], 'priority' => '0.8', 'lastmod' => date('Y-m-d', strtotime($a['updated_at']))];
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
<?php foreach ($urls as $u): ?>
<url>
<loc><?= htmlspecialchars($u['loc'], ENT_XML1) ?></loc>
<?php if (!empty($u['lastmod'])): ?><lastmod><?= $u['lastmod'] ?></lastmod><?php endif; ?>
<priority><?= $u['priority'] ?></priority>
</url>
<?php endforeach; ?>
</urlset>
