<?php
/**
 * 旧静态帮助页 → 帮助中心动态内容 迁移脚本
 * change: help-center-ai-assistant (task 1.2)
 *
 * 用法: php init/migrate-help-pages.php [--dry-run]
 *
 * - 读取 help/*.html（排除将被动态页替代的入口，见映射表）
 * - 解析标题与正文容器，入库 help_articles（status=published, content_type=richtext）
 * - glossary.html 额外结构化解析：term-card → help_glossary；FAQ → help_faq
 * - upsert by slug/term，幂等可重跑
 * - 旧文件名写入 legacy_file，供 .htaccess 301 映射
 */

require_once __DIR__ . '/../config/database.php';

if (!isset($pdo)) {
    $h = getenv('DB_HOST') ?: 'localhost';
    $n = getenv('DB_NAME');
    $u = getenv('DB_USER');
    $p = getenv('DB_PASS') ?: '';
    if (!$n || !$u) {
        fwrite(STDERR, "缺少数据库配置：请配置 config/database.php 或设置 DB_* 环境变量。\n");
        exit(1);
    }
    $pdo = new PDO("mysql:host={$h};dbname={$n};charset=utf8mb4", $u, $p, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
    ]);
}

$dryRun = in_array('--dry-run', $argv ?? [], true);

/**
 * 旧文件 → 分类slug / 新slug / 正文容器选择器候选
 * 分类slug 对应 help_categories.slug（见 migration-help-center-ai.sql）
 */
$map = [
    'help.html'                        => ['cat' => 'getting-started', 'slug' => 'help-overview',               'selectors' => ['.help-container', '.container', 'body']],
    'glossary.html'                    => ['cat' => 'rules',           'slug' => 'glossary-guide',              'selectors' => ['.help-container', '.container', 'body']],
    'buy-blocks-guide.html'            => ['cat' => 'block',           'slug' => 'buy-blocks-guide',            'selectors' => ['.help-container', '.container', 'body']],
    'create-city.html'                 => ['cat' => 'block',           'slug' => 'create-city-guide',           'selectors' => ['.help-container', '.container', 'body']],
    'why-create-city.html'             => ['cat' => 'block',           'slug' => 'why-create-city',             'selectors' => ['.help-container', '.container', 'body']],
    'why-create-city-business.html'    => ['cat' => 'block',           'slug' => 'why-create-city-business',    'selectors' => ['.help-container', '.container', 'body']],
    'why-create-city-celebrity.html'   => ['cat' => 'block',           'slug' => 'why-create-city-celebrity',   'selectors' => ['.help-container', '.container', 'body']],
    'why-create-city-community.html'   => ['cat' => 'block',           'slug' => 'why-create-city-community',   'selectors' => ['.help-container', '.container', 'body']],
    'why-create-city-influencer.html'  => ['cat' => 'block',           'slug' => 'why-create-city-influencer',  'selectors' => ['.help-container', '.container', 'body']],
    'why-create-city-organization.html'=> ['cat' => 'block',           'slug' => 'why-create-city-organization', 'selectors' => ['.help-container', '.container', 'body']],
];

/** DOM 加载 HTML（容错） */
function loadDom(string $html): DOMDocument {
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
    libxml_clear_errors();
    return $dom;
}

/** 移除 style/script/nav/footer/header */
function stripJunk(DOMDocument $dom): void {
    $xpath = new DOMXPath($dom);
    foreach (['style', 'script', 'nav', 'footer', 'noscript'] as $tag) {
        $nodes = iterator_to_array($xpath->query("//{$tag}"));
        foreach ($nodes as $node) { $node->parentNode->removeChild($node); }
    }
}

/** 按候选 class 选择器取第一个匹配容器的 innerHTML */
function extractContent(DOMDocument $dom, array $selectors): string {
    $xpath = new DOMXPath($dom);
    foreach ($selectors as $sel) {
        if ($sel === 'body') {
            $bodies = $dom->getElementsByTagName('body');
            if ($bodies->length > 0) return innerHtml($dom, $bodies->item(0));
            continue;
        }
        $class = ltrim($sel, '.');
        $nodes = $xpath->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' {$class} ')]");
        if ($nodes->length > 0) return innerHtml($dom, $nodes->item(0));
    }
    return '';
}

function innerHtml(DOMDocument $dom, DOMNode $node): string {
    $html = '';
    foreach ($node->childNodes as $child) {
        $html .= $dom->saveHTML($child);
    }
    return trim($html);
}

function titleFrom(string $file, DOMDocument $dom): string {
    $titles = $dom->getElementsByTagName('title');
    if ($titles->length > 0) {
        $t = trim($titles->item(0)->textContent);
        $t = preg_replace('/\s*[-|–]\s*58区块城市.*$/u', '', $t);
        if ($t !== '') return mb_substr($t, 0, 100);
    }
    return ucfirst(pathinfo($file, PATHINFO_FILENAME));
}

// ---------- 迁移文章 ----------
$catStmt = $pdo->prepare("SELECT id FROM help_categories WHERE slug = ?");
$insArt = $pdo->prepare(
    "INSERT INTO help_articles (category_id, title, slug, summary, content_type, content_richtext, legacy_file, status, is_pinned)
     VALUES (:cat, :title, :slug, :summary, 'richtext', :content, :legacy, 'published', :pinned)
     ON DUPLICATE KEY UPDATE title=VALUES(title), content_richtext=VALUES(content_richtext),
       category_id=VALUES(category_id), summary=VALUES(summary), legacy_file=VALUES(legacy_file), status='published'"
);

$helpDir = __DIR__ . '/../help';
$count = 0;
foreach ($map as $file => $conf) {
    $path = $helpDir . '/' . $file;
    if (!is_file($path)) { echo "[跳过] 文件不存在: {$file}\n"; continue; }

    $catStmt->execute([$conf['cat']]);
    $catId = $catStmt->fetchColumn();
    if (!$catId) { echo "[跳过] 分类不存在: {$conf['cat']}（先执行 migration-help-center-ai.sql）\n"; continue; }

    $html = file_get_contents($path);
    $dom = loadDom($html);
    stripJunk($dom);
    $title = titleFrom($file, $dom);
    $content = extractContent($dom, $conf['selectors']);
    if ($content === '') { echo "[跳过] 未解析到正文: {$file}\n"; continue; }

    // 摘要：正文去标签后前120字
    $plain = trim(preg_replace('/\s+/u', ' ', strip_tags($content)));
    $summary = mb_substr($plain, 0, 120);

    echo sprintf("[文章] %-32s → %-34s (%s)\n", $file, $conf['slug'], mb_substr($title, 0, 30));
    if (!$dryRun) {
        $insArt->execute([
            ':cat' => $catId, ':title' => $title, ':slug' => $conf['slug'],
            ':summary' => $summary, ':content' => $content,
            ':legacy' => $file, ':pinned' => ($file === 'help.html' ? 1 : 0),
        ]);
    }
    $count++;
}

// ---------- glossary.html 结构化解析 ----------
$glossaryPath = $helpDir . '/glossary.html';
if (is_file($glossaryPath)) {
    $dom = loadDom(file_get_contents($glossaryPath));
    $xpath = new DOMXPath($dom);

    // 术语卡片 → help_glossary
    $catStmt->execute(['rules']);
    $rulesCatId = $catStmt->fetchColumn();
    $insTerm = $pdo->prepare(
        "INSERT INTO help_glossary (term, pinyin, definition) VALUES (:term, :pinyin, :def)
         ON DUPLICATE KEY UPDATE definition=VALUES(definition)"
    );
    $termCount = 0;
    foreach ($xpath->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' term-card ')]") as $card) {
        $h2 = $card->getElementsByTagName('h2')->item(0);
        $p = $card->getElementsByTagName('p')->item(0);
        if (!$h2 || !$p) continue;
        // 去掉别名 span
        $alias = $h2->getElementsByTagName('span');
        if ($alias->length > 0) $h2->removeChild($alias->item(0));
        $term = trim($h2->textContent);
        $def = trim($p->textContent);
        if ($term === '' || $def === '') continue;
        $pinyin = preg_match('/^[\x20-\x7e]+$/', $term) ? strtolower($term) : '';
        echo sprintf("[术语] %s\n", $term);
        if (!$dryRun) {
            $insTerm->execute([':term' => $term, ':pinyin' => $pinyin, ':def' => $def]);
        }
        $termCount++;
    }

    // FAQ 区块 → help_faq
    $faqCount = 0;
    if ($rulesCatId) {
        $insFaq = $pdo->prepare(
            "INSERT INTO help_faq (category_id, question, answer, status, sort_order) VALUES (:cat, :q, :a, 'published', :sort)"
        );
        foreach ($xpath->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' faq-section ')]//*[self::h3]") as $h3) {
            $q = trim($h3->textContent);
            $a = '';
            for ($n = $h3->nextSibling; $n; $n = $n->nextSibling) {
                if ($n->nodeType === XML_ELEMENT_NODE && $n->tagName === 'h3') break;
                if ($n->nodeType === XML_ELEMENT_NODE && $n->tagName === 'p') $a .= ' ' . trim($n->textContent);
            }
            $a = trim($a);
            if ($q === '' || $a === '') continue;
            echo sprintf("[FAQ] %s\n", mb_substr($q, 0, 40));
            if (!$dryRun) {
                $insFaq->execute([':cat' => $rulesCatId, ':q' => $q, ':a' => $a, ':sort' => $faqCount]);
            }
            $faqCount++;
        }
    }
    echo sprintf("[汇总] 术语 %d 条, FAQ %d 条\n", $termCount, $faqCount);
}

echo sprintf("%s: 文章 %d 篇入库%s\n", $dryRun ? '[dry-run 完成]' : '[迁移完成]', $count, $dryRun ? '（未写库）' : '');
if ($count < 10) {
    fwrite(STDERR, "警告：迁移文章数少于10篇，请检查上方 [跳过] 记录。\n");
    exit(1);
}
