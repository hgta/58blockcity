<?php
/**
 * 帮助中心统一路由入口
 * change: help-center-ai-assistant (task 2.1)
 *
 * 路由（经 .htaccess 重写，也支持 ?route=xxx 直连）:
 *   /                      → home      首页
 *   /category/{slug}       → category  分类列表
 *   /article/{slug}        → article   文章详情
 *   /search?q=             → search    搜索
 *   /faq                   → faq       常见问题
 *   /glossary              → glossary  术语表
 *   /ask                   → ask       AI 全屏问答
 *   /sitemap.xml           → sitemap
 */

require_once __DIR__ . '/_init.php';

$route = isset($_GET['route']) ? preg_replace('/[^a-z0-9\-]/', '', (string)$_GET['route']) : 'home';

$routes = [
    'home'     => ['file' => 'home.php',     'need_db' => true],
    'category' => ['file' => 'category.php', 'need_db' => true],
    'article'  => ['file' => 'article.php',  'need_db' => true],
    'search'   => ['file' => 'search.php',   'need_db' => true],
    'faq'      => ['file' => 'faq.php',      'need_db' => true],
    'glossary' => ['file' => 'glossary.php', 'need_db' => true],
    'ask'      => ['file' => 'ask.php',      'need_db' => true],
    'sitemap'  => ['file' => 'sitemap.php',  'need_db' => true],
];

if (!isset($routes[$route])) {
    $route = 'home';
}

// 数据表未就绪时给出部署引导（不影响静态展示）
if ($routes[$route]['need_db'] && !help_tables_ready()) {
    http_response_code(503);
    require __DIR__ . '/_layout.php';
    help_header(['title' => '初始化中']);
    echo '<div class="hc-empty"><i class="fa-solid fa-database"></i><div>帮助中心数据尚未初始化，请先在服务器执行 <code>init/migration-help-center-ai.sql</code></div></div>';
    help_footer();
    exit;
}

require __DIR__ . '/pages/' . $routes[$route]['file'];
