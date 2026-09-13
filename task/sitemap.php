<?php
/**
 * 任务子站 · 站点地图（task.58.tl）
 *
 * 仅输出本域 URL。nginx: rewrite ^/sitemap\.xml$ /sitemap.php last;
 */

$__root = dirname(__DIR__);   // .../58blockcity

require_once $__root . '/config/database.php';
require_once $__root . '/classes/SeoHelper.php';

header('Content-Type: application/xml; charset=utf-8');

define('TASK_BASE', 'https://task.58.tl');
$now = date('Y-m-d');

$urls = [];

function task_node(&$urls, $loc, $priority, $freq, $lastmod)
{
    $urls[] = ['loc' => $loc, 'priority' => $priority, 'freq' => $freq, 'lastmod' => $lastmod];
}

// 首页与公开列表
task_node($urls, TASK_BASE . '/', '1.0', 'daily', $now);
task_node($urls, TASK_BASE . '/find.php', '0.8', 'daily', $now);

// 任务详情（tasks.status 为 enum('open','closed')）
try {
    $stmt = $pdo->query("SELECT id, title, updated_at FROM tasks WHERE status = 'open' ORDER BY id DESC LIMIT 2000");
    while ($t = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $lastmod = !empty($t['updated_at']) ? date('Y-m-d', strtotime($t['updated_at'])) : $now;
        task_node($urls, TASK_BASE . '/view.php?id=' . intval($t['id']), '0.7', 'weekly', $lastmod);
    }
} catch (Exception $e) {
    // tasks 表不存在或字段不符时跳过
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
<?php foreach ($urls as $u): ?>
  <url>
    <loc><?= htmlspecialchars($u['loc']) ?></loc>
    <lastmod><?= htmlspecialchars($u['lastmod']) ?></lastmod>
    <changefreq><?= htmlspecialchars($u['freq']) ?></changefreq>
    <priority><?= htmlspecialchars($u['priority']) ?></priority>
  </url>
<?php endforeach; ?>
</urlset>
