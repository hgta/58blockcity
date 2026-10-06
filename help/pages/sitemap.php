<?php
/**
 * 帮助中心 sitemap（仅 published 内容）
 * change: help-center-ai-assistant (task 2.5)
 * change: help-seo-foundation (task 5.1/5.2) —— base 固定为权威域 + 补齐 lastmod
 */

require_once __DIR__ . '/../_init.php';

header('Content-Type: application/xml; charset=utf-8');

// 权威域写死：通过 www.58.tl/help/sitemap.xml 访问时也只输出 help.58.tl 的 URL
$base = help_canonical_base();

/** 取某表最新更新时间（YYYY-MM-DD），异常或无数据时返回 null */
$maxUpdated = function ($sql) use ($pdo) {
    try {
        $v = $pdo->query($sql)->fetchColumn();
        return $v ? date('Y-m-d', strtotime((string)$v)) : null;
    } catch (Exception $ex) {
        return null;
    }
};
$latestArticle = $maxUpdated("SELECT MAX(updated_at) FROM help_articles WHERE status = 'published'");
$latestFaq     = $maxUpdated("SELECT MAX(updated_at) FROM help_faq WHERE status = 'published'");
$latestTerm    = $maxUpdated("SELECT MAX(updated_at) FROM help_glossary");

$urls = [];
$urls[] = ['loc' => $base, 'priority' => '1.0', 'lastmod' => $latestArticle];
$urls[] = ['loc' => $base . 'faq', 'priority' => '0.8', 'lastmod' => $latestFaq];
$urls[] = ['loc' => $base . 'glossary', 'priority' => '0.6', 'lastmod' => $latestTerm];

foreach ($pdo->query("SELECT slug, updated_at FROM help_categories WHERE is_visible = 1") as $c) {
    $urls[] = [
        'loc' => $base . 'category/' . $c['slug'],
        'priority' => '0.7',
        'lastmod' => !empty($c['updated_at']) ? date('Y-m-d', strtotime($c['updated_at'])) : null,
    ];
}
foreach ($pdo->query("SELECT slug, updated_at FROM help_articles WHERE status = 'published'") as $a) {
    $urls[] = [
        'loc' => $base . 'article/' . $a['slug'],
        'priority' => '0.8',
        'lastmod' => date('Y-m-d', strtotime($a['updated_at'])),
    ];
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
