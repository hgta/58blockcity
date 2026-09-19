<?php
/**
 * 帮助中心公共布局
 * change: help-center-ai-assistant (task 2.1)
 */

function help_header(array $opts = []) {
    $title = isset($opts['title']) && $opts['title'] !== ''
        ? $opts['title'] . ' - 58区块城市帮助中心'
        : '58区块城市帮助中心 - 新手上路、玩法教程、常见问题';
    $desc  = isset($opts['description']) && $opts['description'] !== ''
        ? $opts['description']
        : '58区块城市图文帮助中心：区块认领、BCT人气值、NFT头像、人气商城、互访圈、拍卖等玩法教程与常见问题解答，另有AI助手在线答疑。';
    $active = isset($opts['active']) ? $opts['active'] : '';
    $q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
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
<meta property="og:type" content="website">
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

.hc-hero { background: var(--brand-grad); border-radius: 18px; padding: 40px 24px; text-align: center; color: #fff; margin-bottom: 28px; }
.hc-hero h1 { font-size: 26px; margin-bottom: 8px; }
.hc-hero p { opacity: .92; font-size: 14px; margin-bottom: 20px; }
.hc-hero-search { display: flex; max-width: 560px; margin: 0 auto; }
.hc-hero-search input { flex: 1; border: none; border-radius: 24px 0 0 24px; padding: 12px 20px; font-size: 15px; outline: none; }
.hc-hero-search button { border: none; background: #1f2937; color: #fff; padding: 0 24px; border-radius: 0 24px 24px 0; cursor: pointer; font-size: 15px; }
.hc-hero-tags { margin-top: 14px; font-size: 13px; opacity: .95; }
.hc-hero-tags a { color: #fff; margin: 0 6px; border-bottom: 1px dashed rgba(255,255,255,.5); }

.hc-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 14px; margin-bottom: 28px; }
.hc-cat-card { background: var(--card); border: 1px solid var(--line); border-radius: var(--radius); padding: 18px; display: flex; align-items: center; gap: 12px; transition: box-shadow .15s, transform .15s; }
.hc-cat-card:hover { box-shadow: 0 6px 18px rgba(255,107,0,.12); transform: translateY(-2px); text-decoration: none; }
.hc-cat-card i { width: 42px; height: 42px; border-radius: 10px; background: #fff3e8; color: var(--brand); display: flex; align-items: center; justify-content: center; font-size: 18px; flex: none; }
.hc-cat-card .t { font-weight: 600; font-size: 15px; color: var(--ink); }
.hc-cat-card .d { font-size: 12px; color: var(--muted); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.hc-cat-card .n { margin-left: auto; font-size: 12px; color: #c0c4cc; white-space: nowrap; }

.hc-section-title { display: flex; align-items: center; gap: 8px; font-size: 18px; font-weight: 700; margin: 26px 0 14px; }
.hc-section-title i { color: var(--brand); }
.hc-section-title a.more { margin-left: auto; font-size: 13px; font-weight: 400; }

.hc-list { background: var(--card); border: 1px solid var(--line); border-radius: var(--radius); overflow: hidden; }
.hc-list-item { display: flex; align-items: center; gap: 12px; padding: 14px 18px; border-bottom: 1px solid var(--line); color: var(--ink); }
.hc-list-item:last-child { border-bottom: none; }
.hc-list-item:hover { background: #fffaf5; text-decoration: none; }
.hc-list-item .idx { width: 22px; height: 22px; border-radius: 6px; background: #fff3e8; color: var(--brand); font-size: 12px; font-weight: 700; display: flex; align-items: center; justify-content: center; flex: none; }
.hc-list-item .t { font-weight: 500; font-size: 15px; flex: 1; }
.hc-list-item .m { font-size: 12px; color: var(--muted); white-space: nowrap; }
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

.hc-article { background: var(--card); border: 1px solid var(--line); border-radius: var(--radius); padding: 28px 32px; }
.hc-article h1 { font-size: 24px; margin-bottom: 6px; }
.hc-article .meta { font-size: 13px; color: var(--muted); margin-bottom: 18px; padding-bottom: 14px; border-bottom: 1px solid var(--line); }
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
.hc-faq-item { background: var(--card); border: 1px solid var(--line); border-radius: var(--radius); margin-bottom: 10px; overflow: hidden; }
.hc-faq-q { padding: 14px 18px; cursor: pointer; font-weight: 600; font-size: 15px; display: flex; align-items: center; gap: 10px; }
.hc-faq-q i { color: var(--brand); margin-left: auto; transition: transform .2s; }
.hc-faq-item.open .hc-faq-q i { transform: rotate(180deg); }
.hc-faq-a { display: none; padding: 0 18px 14px; color: var(--muted); font-size: 14px; }
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
  .hc-nav { display: none; position: absolute; top: 60px; left: 0; right: 0; background: #fff; flex-direction: column; align-items: stretch; padding: 10px 16px; border-bottom: 1px solid var(--line); gap: 2px; }
  .hc-nav.open { display: flex; }
  .hc-menu-btn { display: block; }
  .hc-search { display: none; }
  .hc-hero h1 { font-size: 20px; }
  .hc-article { padding: 20px 16px; }
}
</style>
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
      <a class="hc-ask-btn" href="<?= e(help_url('ask')) ?>" style="background:linear-gradient(135deg,#ff8a3d,#ff6b00);color:#fff"><i class="fa-solid fa-robot"></i> 问AI助手</a>
      <?php endif; ?>
      <a href="<?= e($mainSite) ?>">返回主站</a>
    </nav>
    <button class="hc-menu-btn" id="hcMenuBtn" aria-label="菜单"><i class="fa-solid fa-bars"></i></button>
  </div>
</header>
<div class="hc-container">
<?php
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
