<?php
/**
 * 死链清单（供百度搜索资源平台「死链提交」登记）
 * change: help-baidu-indexing (task 6.3)
 *
 * 输出：每行一条 URL 的纯文本（text/plain）
 * 数据来源：help_push_log 中 action='dead' 的记录（文章下架 / 改 slug 时写入）
 *
 * 访问路径：https://help.58.tl/deadlinks.php
 * 部署注意：本文件为物理文件，nginx 的 try_files $uri 会直接命中，
 *          不经过 help/index.php 的路由白名单，无需额外 rewrite 规则。
 */

require_once __DIR__ . '/_init.php';

header('Content-Type: text/plain; charset=utf-8');
header('X-Robots-Tag: noindex');

$urls = [];
try {
    $urls = $pdo->query(
        "SELECT url FROM help_push_log WHERE action = 'dead' ORDER BY pushed_at DESC LIMIT 5000"
    )->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $ex) {
    // 表未建（迁移未执行）时输出空清单，不报错
    $urls = [];
}

foreach ($urls as $u) {
    $u = trim((string)$u);
    if ($u !== '' && strpos($u, 'https://') === 0) {
        echo $u, "\n";
    }
}
