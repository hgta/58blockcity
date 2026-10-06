<?php
/**
 * 帮助中心公共布局
 * change: help-center-ai-assistant (task 2.1)
 * change: help-seo-foundation (canonical / og:url)
 * change: help-structured-data (实体锚点 + JSON-LD 统一输出位)
 */

// 全站品牌实体单一来源 + SEO 结构化数据工具（change: help-structured-data D1）
require_once __DIR__ . '/../shared/organization.php';
require_once __DIR__ . '/../classes/SeoHelper.php';

/** help 子站默认 OG 图（品牌图，文章无 cover_image 时回退） */
if (!defined('HELP_OG_IMAGE')) {
    define('HELP_OG_IMAGE', 'https://www.58.tl/assets/images/og-main.jpg');
}

/** 相对图片路径补成绝对 URL（og:image / schema image 必须绝对） */
function help_abs_image($u) {
    $u = trim((string)$u);
    if ($u === '') return '';
    if (preg_match('#^https?://#i', $u)) return $u;
    if (strpos($u, '//') === 0) return 'https:' . $u;
    return 'https://www.58.tl/' . ltrim($u, '/');
}

function help_header(array $opts = []) {
    $title = isset($opts['title']) && $opts['title'] !== ''
        ? $opts['title'] . ' - 58区块城市帮助中心'
        : '58区块城市帮助中心 - 新手上路、玩法教程、常见问题';
    $desc  = isset($opts['description']) && $opts['description'] !== ''
        ? $opts['description']
        : '58区块城市图文帮助中心：区块认领、BCT人气值、NFT头像、人气商城、互访圈、拍卖等玩法教程与常见问题解答，另有AI助手在线答疑。';
    $active = isset($opts['active']) ? $opts['active'] : '';
    // 权威域 canonical：页面可显式传，未传则按当前请求自动推导（change: help-seo-foundation D1）
    $canonical = isset($opts['canonical']) && $opts['canonical'] !== ''
        ? $opts['canonical']
        : help_current_canonical();
    $q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
    // OG（change: help-structured-data D6）：文章页为 article + 封面图，其余为 website + 品牌图
    // robots 指令（默认不输出；搜索页等薄内容页传 noindex,follow —— change: help-content-seo D4）
    $robots  = isset($opts['robots']) ? trim((string)$opts['robots']) : '';
    $ogType  = isset($opts['og_type']) && $opts['og_type'] !== '' ? $opts['og_type'] : 'website';
    $ogImage = help_abs_image(isset($opts['og_image']) ? $opts['og_image'] : '') ?: HELP_OG_IMAGE;
    $mainSite = 'https://www.58.tl/';
    $aiEnabled = help_setting('ai_assistant_enabled', '1') === '1';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($title) ?></title>
<meta name="description" content="<?= e($desc) ?>">
<meta property="og:title" content="<?= e($title) ?>">
<meta property="og:description" content="<?= e($desc) ?>">
<meta property="og:type" content="<?= e($ogType) ?>">
<link rel="canonical" href="<?= e($canonical) ?>">
<meta property="og:url" content="<?= e($canonical) ?>">
<meta property="og:image" content="<?= e($ogImage) ?>">
<meta property="og:site_name" content="58区块城市">
<?php if ($robots !== ''): ?><meta name="robots" content="<?= e($robots) ?>"><?php endif; ?>
<link rel="icon" href="https://www.58.tl/favicon.ico">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root {
  --brand: #ff6b00; --brand-dark: #e05e00; --brand-grad: linear-gradient(135deg, #ff8a3d, #ff6b00);
  --ink: #1f2937; --muted: #6b7280; --line: #e5e7eb; --bg: #f7f8fa; --card: #ffffff;
  --ok: #16a34a; --radius: 14px;
}
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: -apple-system, BlinkMacSystemFont, "PingFang SC", "Hiragino Sans GB", "Microsoft YaHei", sans-serif; background: var(--bg); color: var(--ink); line-height: 1.7; }
a { color: var(--brand); text-decoration: none; }
a:hover { text-decoration: underline; }
img { max-width: 100%; }

.hc-header { background: #fff; border-bottom: 1px solid var(--line); position: sticky; top: 0; z-index: 100; }
.hc-header-inner { max-width: 1080px; margin: 0 auto; padding: 0 16px; display: flex; align-items: center; gap: 20px; height: 60px; }
.hc-logo { display: flex; align-items: center; gap: 8px; font-size: 18px; font-weight: 700; color: var(--ink); white-space: nowrap; }
.hc-logo i { color: var(--brand); }
.hc-logo:hover { text-decoration: none; }
.hc-search { flex: 1; max-width: 420px; display: flex; }
.hc-search input { flex: 1; border: 2px solid var(--brand); border-right: none; border-radius: 22px 0 0 22px; padding: 7px 16px; font-size: 14px; outline: none; background: #fff; }
.hc-search button { border: none; background: var(--brand-grad); color: #fff; padding: 0 18px; border-radius: 0 22px 22px 0; cursor: pointer; font-size: 14px; }
.hc-nav { display: flex; align-items: center; gap: 4px; margin-left: auto; }
.hc-nav a { color: var(--muted); padding: 6px 10px; border-radius: 8px; font-size: 14px; }
.hc-nav a:hover { color: var(--brand); background: #fff3e8; text-decoration: none; }
.hc-nav a.on { color: var(--brand); font-weight: 600; background: #fff3e8; }
.hc-nav .hc-ask-btn { background: var(--brand-grad); color: #fff; font-weight: 600; padding: 8px 16px; border-radius: 20px; }
.hc-nav .hc-ask-btn:hover { color: #fff; opacity: .92; }
.hc-menu-btn { display: none; background: none; border: none; font-size: 20px; color: var(--ink); cursor: pointer; margin-left: auto; }

.hc-container { max-width: 1080px; margin: 0 auto; padding: 24px 16px 48px; }
.hc-breadcrumb { font-size: 13px; color: var(--muted); margin-bottom: 14px; }
.hc-breadcrumb a { color: var(--muted); }

/* 紧凑标题条（取代原大色块 hero：首屏高度 ~250px → ~90px）
   桌面端不重复头部搜索；≤860px 显示搜索，作为手机端唯一搜索入口（见移动端媒体查询） */
.hc-hero { background: #fff; border: 1px solid var(--line); border-left: 4px solid var(--brand); border-radius: var(--radius); padding: 14px 18px; margin-bottom: 18px; }
.hc-hero h1 { font-size: 19px; margin: 0 0 2px; }
.hc-hero h1 i { color: var(--brand); margin-right: 6px; }
.hc-hero p { font-size: 13px; color: var(--muted); margin: 0; }
.hc-hero-search { display: none; }
.hc-hero.is-search .hc-hero-search { display: flex; max-width: 560px; margin-top: 12px; }
.hc-hero-search input { flex: 1; border: 1px solid var(--line); border-right: none; border-radius: 22px 0 0 22px; padding: 9px 16px; font-size: 14px; outline: none; }
.hc-hero-search button { border: none; background: var(--brand-grad); color: #fff; padding: 0 20px; border-radius: 0 22px 22px 0; cursor: pointer; font-size: 14px; }
.hc-hero-tags { margin-top: 10px; font-size: 13px; color: var(--muted); }
.hc-hero-tags a { color: var(--brand); margin-right: 10px; }
.hc-hero-tags a:hover { text-decoration: underline; }

/* 统一页面标题区（替换原有的 3 种标题写法与内联 margin 覆盖） */
.hc-pagehead { display: flex; align-items: center; gap: 12px; margin: 2px 0 18px; flex-wrap: wrap; }
.hc-pagehead h1 { font-size: 20px; margin: 0; flex: none; }
.hc-pagehead h1 i { color: var(--brand); margin-right: 6px; }
.hc-pagehead .sub { font-size: 13px; color: var(--muted); min-width: 0; overflow: hidden; text-overflow: ellipsis; }
.hc-pagehead .acts { margin-left: auto; font-size: 13px; flex: none; }
.hc-pagehead + .hc-section-title { margin-top: 14px; }

/* 首页两栏：DOM 顺序为 分类 → AI → 热门 → 最近更新 → 大家问（移动端单列顺序即此，符合预期）
   桌面端用 grid-areas 把 AI 卡与"大家都在问"排到右列 */
.hc-home { display: grid; grid-template-columns: minmax(0, 1fr) 320px; gap: 22px; align-items: start; grid-template-areas: "cats ai" "hot asks" "latest asks"; }
.hc-home > .a-cats { grid-area: cats; }
.hc-home > .a-ai { grid-area: ai; }
.hc-home > .a-hot { grid-area: hot; }
.hc-home > .a-latest { grid-area: latest; }
.hc-home > .a-asks { grid-area: asks; }
.hc-home .hc-section-title { margin-top: 0; }
.hc-home .a-ai .hc-side-card { padding: 14px 16px; }
.hc-home .a-ai .hc-side-card p { font-size: 13px; color: var(--muted); margin: 6px 0 10px; }
.hc-list-item.alt .idx { background: none; color: var(--muted); }
.hc-list-item.alt .m.date { color: #9ca3af; }

.hc-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 14px; margin-bottom: 28px; }
.hc-cat-card { background: var(--card); border: 1px solid var(--line); border-radius: var(--radius); padding: 16px 18px; display: flex; align-items: center; gap: 12px; transition: box-shadow .15s, transform .15s; }
.hc-cat-card:hover { box-shadow: 0 6px 18px rgba(255,107,0,.12); transform: translateY(-2px); text-decoration: none; }
.hc-cat-card i { width: 42px; height: 42px; border-radius: 10px; background: #fff3e8; color: var(--brand); display: flex; align-items: center; justify-content: center; font-size: 18px; flex: none; }
/* 关键：flex 子项必须 min-width:0，否则内部文本既不换行也不省略，会撑破卡片并把右侧计数挤出边界 */
.hc-cat-card > div { flex: 1 1 auto; min-width: 0; }
.hc-cat-card .t { font-weight: 600; font-size: 15px; color: var(--ink); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.hc-cat-card .d { font-size: 12px; color: var(--muted); line-height: 1.5; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.hc-cat-card .n { margin-left: auto; flex: none; font-size: 12px; color: #c0c4cc; white-space: nowrap; }

.hc-section-title { display: flex; align-items: center; gap: 8px; font-size: 18px; font-weight: 700; margin: 26px 0 14px; }
.hc-section-title i { color: var(--brand); }
.hc-section-title a.more { margin-left: auto; font-size: 13px; font-weight: 400; }

.hc-list { background: var(--card); border: 1px solid var(--line); border-radius: var(--radius); overflow: hidden; }
.hc-list-item { display: flex; align-items: center; gap: 12px; padding: 14px 18px; border-bottom: 1px solid var(--line); color: var(--ink); }
.hc-list-item:last-child { border-bottom: none; }
.hc-list-item:hover { background: #fffaf5; text-decoration: none; }
.hc-list-item .idx { width: 22px; height: 22px; border-radius: 6px; background: #fff3e8; color: var(--brand); font-size: 12px; font-weight: 700; display: flex; align-items: center; justify-content: center; flex: none; }
.hc-list-item .t { font-weight: 500; font-size: 15px; flex: 1 1 auto; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.hc-list-item .m { font-size: 12px; color: var(--muted); white-space: nowrap; flex: none; }
.hc-list-item .hot { color: #ef4444; }
.hc-list-item .new { background: #fee2e2; color: #ef4444; border-radius: 4px; padding: 0 6px; font-size: 11px; }

.hc-articles { display: grid; grid-template-columns: 1fr; gap: 10px; }
.hc-art-card { background: var(--card); border: 1px solid var(--line); border-radius: var(--radius); padding: 16px 18px; display: block; color: var(--ink); }
.hc-art-card:hover { border-color: var(--brand); text-decoration: none; box-shadow: 0 4px 12px rgba(255,107,0,.08); }
.hc-art-card h3 { font-size: 16px; margin-bottom: 6px; }
.hc-art-card p { font-size: 13px; color: var(--muted); }
.hc-art-card .meta { font-size: 12px; color: #9ca3af; margin-top: 8px; display: flex; gap: 12px; }

.hc-layout { display: grid; grid-template-columns: 220px 1fr; gap: 20px; }
.hc-side { position: sticky; top: 76px; align-self: start; }
.hc-side-card { background: var(--card); border: 1px solid var(--line); border-radius: var(--radius); padding: 10px; margin-bottom: 14px; }
.hc-side-card h4 { font-size: 13px; color: var(--muted); padding: 8px 10px 4px; font-weight: 600; }
.hc-side-card a { display: flex; align-items: center; gap: 8px; padding: 8px 10px; border-radius: 8px; color: var(--ink); font-size: 14px; }
.hc-side-card a:hover { background: #fff3e8; color: var(--brand); text-decoration: none; }
.hc-side-card a.on { background: #fff3e8; color: var(--brand); font-weight: 600; }
.hc-ai-btn { display: inline-flex; align-items: center; gap: 6px; background: var(--brand-grad); color: #fff !important; font-weight: 600; font-size: 13px; padding: 8px 16px; border-radius: 20px; }
.hc-ai-btn:hover { opacity: .92; text-decoration: none; }

.hc-article { background: var(--card); border: 1px solid var(--line); border-radius: var(--radius); padding: 28px 32px; }
.hc-article h1 { font-size: 20px; margin-bottom: 6px; } /* 与 .hc-pagehead h1 同字号，保持各页标题观感一致 */
.hc-article .meta { font-size: 13px; color: var(--muted); margin-bottom: 18px; padding-bottom: 14px; border-bottom: 1px solid var(--line); }
/* 直答段落：位于标题/元信息之后、正文之前，语义上属正文一部分（答案前置，change: help-content-seo D2） */
.hc-article .hc-lead { font-size: 16px; font-weight: 500; color: var(--ink); line-height: 1.85; margin: 0 0 20px; }
/* 跨子站入口（change: help-content-seo D5） */
.hc-links { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 10px; }
.hc-links a { display: flex; align-items: center; gap: 8px; background: var(--card); border: 1px solid var(--line); border-radius: 10px; padding: 12px 14px; font-size: 14px; color: var(--ink); }
.hc-links a:hover { border-color: var(--brand); color: var(--brand); text-decoration: none; }
.hc-links a i { color: var(--brand); }
.hc-article .body { font-size: 15px; }
.hc-article .body img { border-radius: 8px; border: 1px solid var(--line); margin: 8px 0; }
.hc-article .body h2 { font-size: 19px; margin: 22px 0 10px; }
.hc-article .body h3 { font-size: 16px; margin: 18px 0 8px; }
.hc-article .body p { margin: 10px 0; }
.hc-article .body ul, .hc-article .body ol { padding-left: 22px; margin: 10px 0; }
.hc-article .body table { border-collapse: collapse; width: 100%; margin: 12px 0; }
.hc-article .body th, .hc-article .body td { border: 1px solid var(--line); padding: 8px 12px; font-size: 14px; text-align: left; }
.hc-article .body th { background: #fff7f0; }
.hc-article .body mark { background: #ffe4c7; color: inherit; padding: 0 2px; border-radius: 3px; }

.hc-steps { counter-reset: step; }
.hc-step { position: relative; padding: 0 0 26px 52px; border-left: 2px dashed #ffd9b3; margin-left: 20px; }
.hc-step:last-child { border-left-color: transparent; padding-bottom: 4px; }
.hc-step::before { counter-increment: step; content: counter(step); position: absolute; left: -19px; top: 0; width: 36px; height: 36px; border-radius: 50%; background: var(--brand-grad); color: #fff; font-weight: 700; display: flex; align-items: center; justify-content: center; }
.hc-step h3 { font-size: 16px; margin-bottom: 6px; }
.hc-step p { font-size: 14px; color: var(--ink); }
.hc-step img { margin-top: 10px; }

.hc-feedback { margin-top: 26px; padding: 16px 20px; border-radius: var(--radius); background: #fff7f0; display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.hc-feedback span { font-size: 14px; }
.hc-feedback button { border: 1px solid var(--brand); background: #fff; color: var(--brand); padding: 6px 16px; border-radius: 18px; cursor: pointer; font-size: 13px; }
.hc-feedback button:hover { background: var(--brand); color: #fff; }
.hc-feedback button.done { background: #f0fdf4; border-color: var(--ok); color: var(--ok); cursor: default; }

.hc-faq-cat { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 18px; }
.hc-faq-cat a { padding: 7px 16px; border-radius: 18px; background: #fff; border: 1px solid var(--line); color: var(--muted); font-size: 13px; }
.hc-faq-cat a:hover { text-decoration: none; border-color: var(--brand); color: var(--brand); }
.hc-faq-cat a.on { background: var(--brand-grad); border-color: transparent; color: #fff; font-weight: 600; }
.hc-faq-item { background: var(--card); border: 1px solid var(--line); border-radius: var(--radius); margin-bottom: 10px; overflow: hidden; transition: border-color .15s, box-shadow .15s; }
.hc-faq-item:hover { border-color: #f3c9a5; }
.hc-faq-item.open { border-color: var(--brand); box-shadow: 0 2px 10px rgba(255, 102, 0, .08); }
.hc-faq-q { padding: 14px 18px; cursor: pointer; font-weight: 600; font-size: 15px; display: flex; align-items: flex-start; gap: 10px; line-height: 1.5; }
.hc-faq-q > i:first-child { color: var(--brand); margin-top: 3px; }
.hc-faq-q > i:last-child { margin-left: auto; color: #b8c0cc; font-size: 12px; margin-top: 4px; transition: transform .2s; flex-shrink: 0; }
.hc-faq-item.open .hc-faq-q > i:last-child { transform: rotate(180deg); color: var(--brand); }
.hc-faq-a { display: none; padding: 0 18px 16px 46px; color: var(--muted); font-size: 14px; line-height: 1.7; }
.hc-faq-item.open .hc-faq-a { display: block; }
.hc-faq-a a { margin-top: 8px; display: inline-block; font-size: 13px; }

.hc-term-group h3 { font-size: 15px; color: var(--brand); margin: 20px 0 8px; }
.hc-term-item { background: var(--card); border: 1px solid var(--line); border-radius: 10px; padding: 12px 16px; margin-bottom: 8px; }
.hc-term-item .term { font-weight: 700; }
.hc-term-item .def { font-size: 13px; color: var(--muted); margin-top: 4px; }

.hc-empty { text-align: center; padding: 60px 20px; color: var(--muted); }
.hc-empty i { font-size: 44px; color: #d1d5db; margin-bottom: 12px; }
.hc-empty .btn { display: inline-block; margin-top: 16px; background: var(--brand-grad); color: #fff; padding: 9px 22px; border-radius: 20px; font-weight: 600; }
.hc-empty .btn:hover { opacity: .92; text-decoration: none; color: #fff; }

.hc-pager { display: flex; gap: 6px; justify-content: center; margin-top: 18px; flex-wrap: wrap; }
.hc-pager a, .hc-pager span { min-width: 34px; height: 34px; display: inline-flex; align-items: center; justify-content: center; border-radius: 8px; background: #fff; border: 1px solid var(--line); font-size: 13px; color: var(--ink); padding: 0 8px; }
.hc-pager span.cur { background: var(--brand-grad); border-color: transparent; color: #fff; font-weight: 600; }

.hc-footer { border-top: 1px solid var(--line); background: #fff; padding: 22px 16px; text-align: center; color: var(--muted); font-size: 13px; }
.hc-footer a { color: var(--muted); margin: 0 8px; }

@media (max-width: 860px) {
  .hc-layout { grid-template-columns: 1fr; }
  .hc-side { position: static; }
  /* 头部改两行：第一行 logo + 菜单按钮，第二行搜索框
     —— 关键修复：原先此处 .hc-search{display:none} 导致分类/文章/FAQ/术语表/问答页在手机上无法搜索 */
  .hc-header-inner { flex-wrap: wrap; height: auto; padding: 8px 16px; row-gap: 8px; }
  .hc-search { display: flex; order: 3; flex: 1 0 100%; max-width: none; }
  .hc-menu-btn { order: 2; }
  .hc-nav { display: none; position: absolute; top: 100%; left: 0; right: 0; background: #fff; flex-direction: column; align-items: stretch; padding: 10px 16px; border-bottom: 1px solid var(--line); gap: 2px; }
  .hc-nav.open { display: flex; }
  .hc-menu-btn { display: block; }
  .hc-hero-search { display: flex; margin-top: 10px; }   /* 手机端唯一搜索入口 */
  .hc-hero { padding: 12px 14px; }
  .hc-hero h1 { font-size: 18px; }
  .hc-home { grid-template-columns: 1fr; grid-template-areas: none; gap: 0; }
  .hc-home > div { margin-bottom: 8px; }
  .hc-article { padding: 20px 16px; }
}
</style>
<?php
// 结构化数据统一输出位（change: help-structured-data D5）
// 顺序：canonical/og → Organization（实体锚点）→ 本页主 schema → BreadcrumbList
echo organization_json_ld();
$ld = isset($opts['jsonld']) ? $opts['jsonld'] : '';
if (is_array($ld)) {
    $ld = implode("\n", array_filter(array_map('strval', $ld), function ($s) { return $s !== ''; }));
}
if ($ld !== '') echo "\n" . $ld;
$bc = isset($opts['breadcrumb']) ? $opts['breadcrumb'] : [];
if ($bc) echo "\n" . SeoHelper::breadcrumbList($bc);
?>
</head>
<body>
<header class="hc-header">
  <div class="hc-header-inner">
    <a class="hc-logo" href="<?= e(help_url()) ?>"><i class="fa-solid fa-circle-question"></i>帮助中心</a>
    <form class="hc-search" action="<?= e(help_url('search')) ?>" method="get">
      <input type="text" name="q" placeholder="搜索教程、问题、术语…" value="<?= e($q) ?>">
      <button type="submit"><i class="fa-solid fa-magnifying-glass"></i></button>
    </form>
    <nav class="hc-nav" id="hcNav">
      <a href="<?= e(help_url()) ?>" class="<?= $active === 'home' ? 'on' : '' ?>">首页</a>
      <a href="<?= e(help_url('faq')) ?>" class="<?= $active === 'faq' ? 'on' : '' ?>">常见问题</a>
      <a href="<?= e(help_url('glossary')) ?>" class="<?= $active === 'glossary' ? 'on' : '' ?>">术语表</a>
      <?php if ($aiEnabled): ?>
      <a class="hc-ask-btn" href="<?= e(help_url('ask')) ?>"><i class="fa-solid fa-robot"></i> 问AI助手</a>
      <?php endif; ?>
      <a href="<?= e($mainSite) ?>">返回主站</a>
    </nav>
    <button class="hc-menu-btn" id="hcMenuBtn" aria-label="菜单"><i class="fa-solid fa-bars"></i></button>
  </div>
</header>
<div class="hc-container">
<?php
}

/**
 * FAQ 折叠项（公共组件：原先在 home/article/search/faq 四处重复同一段 markup）
 * @param string $q 问题
 * @param string $a 答案
 * @param string $moreUrl 可选：附加链接地址
 * @param string $moreLabel 可选：附加链接文案（默认"查看详情 →"）
 */
function help_faq_item($q, $a, $moreUrl = '', $moreLabel = '查看详情 →') {
?>
<div class="hc-faq-item">
  <div class="hc-faq-q"><i class="fa-solid fa-circle-question"></i><?= e($q) ?><i class="fa-solid fa-chevron-down"></i></div>
  <div class="hc-faq-a"><?= nl2br(e($a)) ?><?php if ($moreUrl !== ''): ?><br><a href="<?= e($moreUrl) ?>"><?= e($moreLabel) ?></a><?php endif; ?></div>
</div>
<?php
}

/**
 * AI 助手入口
 * @param string $mode 'cta'=推广条（列表项样式，用于搜索/FAQ 等页）；'card'=侧列卡片（首页用）
 * @param string $text 可选：自定义文案（cta 模式）
 */
function help_ai_cta($mode = 'cta', $text = '') {
    if (help_setting('ai_assistant_enabled', '1') !== '1') return;
    if ($text === '') $text = '没找到答案？直接问 AI 助手 —— 7×24 小时在线，基于官方教程回答并附引用来源';
    $url = e(help_url('ask'));
    if ($mode === 'card'):
?>
  <div class="hc-side-card">
    <h4><i class="fa-solid fa-robot"></i> AI 助手</h4>
    <p>没找到答案？直接问 —— 7×24 小时在线，基于官方教程回答并附引用来源。</p>
    <a class="hc-ai-btn" href="<?= $url ?>"><i class="fa-solid fa-comment-dots"></i> 去提问 →</a>
  </div>
<?php else: ?>
<div class="hc-list">
  <a class="hc-list-item" href="<?= $url ?>">
    <i class="fa-solid fa-robot" style="color:var(--brand)"></i>
    <div class="t"><?= e($text) ?></div>
    <span class="m" style="color:var(--brand);font-weight:600">去提问 →</span>
  </a>
</div>
<?php
    endif;
}

/**
 * 页面标题区（统一骨架：标题 + 可选副标题 + 可选右侧操作）
 */
function help_pagehead($title, $sub = '', $acts = '') {
?>
<div class="hc-pagehead">
  <h1><?= e($title) ?></h1>
  <?php if ($sub !== ''): ?><span class="sub"><?= e($sub) ?></span><?php endif; ?>
  <?php if ($acts !== ''): ?><span class="acts"><?= $acts ?></span><?php endif; ?>
</div>
<?php
}

/**
 * 统一 404 呈现（软 404 修复：未知路径与内容不存在都返回真 404，不再回落首页）
 * change: help-seo-foundation (task 2.2)
 *
 * @param string $msg 提示文案
 * @param string $backUrl 返回链接，默认帮助中心首页
 * @param string $backLabel 返回链接文案
 * @param int $status HTTP 状态码（下架内容可传 410）
 */
function help_404($msg = '页面不存在', $backUrl = '', $backLabel = '', $status = 404) {
    http_response_code($status);
    if ($backUrl === '') $backUrl = help_url();
    if ($backLabel === '') $backLabel = '返回帮助中心首页';
    help_header(['title' => '页面不存在']);
    echo '<div class="hc-empty"><i class="fa-solid fa-circle-exclamation"></i>'
       . '<div>' . e($msg) . '</div>'
       . '<a class="btn" href="' . e($backUrl) . '">' . e($backLabel) . '</a></div>';
    help_footer();
    exit;
}

function help_footer() {
    $mainSite = 'https://www.58.tl/';
?>
</div>
<footer class="hc-footer">
  <div>
    <a href="<?= e($mainSite) ?>">58区块城市</a>·
    <a href="<?= e(help_url()) ?>">帮助中心</a>·
    <a href="<?= e(help_url('faq')) ?>">常见问题</a>·
    <a href="<?= e(help_url('glossary')) ?>">术语表</a>·
    <a href="<?= e(help_url('sitemap.xml')) ?>">站点地图</a>
  </div>
  <div style="margin-top:6px">© <?= date('Y') ?> 58区块城市 · 让每一块虚拟土地都有价值</div>
</footer>
<script>
document.getElementById('hcMenuBtn') && document.getElementById('hcMenuBtn').addEventListener('click', function () {
  document.getElementById('hcNav').classList.toggle('open');
});
// FAQ 折叠交互（事件委托，动态内容也生效）
document.addEventListener('click', function (ev) {
  var q = ev.target.closest('.hc-faq-q');
  if (q) { q.parentNode.classList.toggle('open'); }
});
</script>
</body>
</html>
<?php
}
