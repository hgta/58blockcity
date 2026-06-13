<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>全部城市 - 58区块城市</title>
    <meta name="description" content="58区块城市全部城市列表，按字母分组展示所有开通城市">
    <meta name="keywords" content="58,区块城市,全部城市,城市列表,元宇宙,BlockCity">
    <link rel="icon" type="image/png" href="/favicon-96x96.png" sizes="96x96" />
    <link rel="icon" type="image/svg+xml" href="/favicon.svg" />
    <link rel="shortcut icon" href="/favicon.ico" />
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png" />
    <link rel="manifest" href="/site.webmanifest" />
    <script src="/city/city.js"></script>
<?php
session_start();
require_once 'config/database.php';
require_once 'classes/City.php';
$city = new City($pdo);
$allCities = $city->getAllCities();
$letters = range('A', 'Z');
$citiesByLetter = [];
foreach ($allCities as $c) {
    $first = strtoupper(substr($c['pinyin'], 0, 1));
    if (!isset($citiesByLetter[$first])) $citiesByLetter[$first] = [];
    $citiesByLetter[$first][] = $c;
}
?>
    <style>
        :root {
            --bg: #f0f2f5;
            --card: #fff;
            --text: #1a1a2e;
            --muted: #6b7280;
            --primary: #2563eb;
            --accent: #f59e0b;
            --shadow: 0 1px 3px rgba(0,0,0,.06), 0 1px 2px rgba(0,0,0,.04);
            --shadow-md: 0 4px 6px rgba(0,0,0,.07), 0 2px 4px rgba(0,0,0,.04);
            --radius: 12px;
        }
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family: 'PingFang SC','Microsoft YaHei','Helvetica Neue',sans-serif; background:var(--bg); color:var(--text); line-height:1.6; }
        a { text-decoration:none; color:inherit; }
        .container { max-width:1200px; margin:0 auto; padding:0 20px; }

        /* Header */
        .site-header { background:#fff; border-bottom:1px solid #e5e7eb; padding:12px 0; position:sticky; top:0; z-index:100; }
        .header-inner { display:flex; align-items:center; justify-content:space-between; }
        .logo { display:flex; align-items:center; gap:8px; font-size:20px; font-weight:700; color:var(--text); }
        .logo-img { width:40px; height:40px; background:linear-gradient(135deg,#ff6b00,#ff8c00); border-radius:8px; display:flex; align-items:center; justify-content:center; color:#fff; font-weight:700; font-size:18px; }
        .logo span { font-size:13px; color:var(--muted); font-weight:400; margin-left:4px; }
        .header-back { padding:6px 16px; background:var(--bg); border-radius:6px; font-size:14px; color:var(--muted); transition:.2s; }
        .header-back:hover { background:#e5e7eb; color:var(--text); }

        /* Page */
        .page-title { padding:32px 0 8px; }
        .page-title h1 { font-size:28px; margin-bottom:4px; }
        .page-title p { color:var(--muted); font-size:15px; }
        .total-count { color:var(--muted); font-size:14px; margin-bottom:20px; }

        /* Letter nav */
        .letter-nav { background:#fff; padding:12px 0; border-bottom:1px solid #e5e7eb; position:sticky; top:65px; z-index:99; }
        .letter-nav-container { display:flex; flex-wrap:wrap; gap:6px; justify-content:center; }
        .letter-link { width:32px; height:32px; display:flex; align-items:center; justify-content:center; border-radius:6px; font-size:13px; font-weight:600; color:var(--muted); background:var(--bg); transition:.2s; }
        .letter-link:hover { background:var(--primary); color:#fff; }

        /* City sections */
        .city-list-container { padding:24px 0 40px; }
        .city-section { margin-bottom:24px; }
        .city-letter { font-size:22px; font-weight:700; color:var(--primary); margin-bottom:12px; padding-bottom:8px; border-bottom:2px solid #e5e7eb; }
        .city-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(100px, 1fr)); gap:10px; }
        .city-item { padding:10px 14px; background:#fff; border-radius:var(--radius); text-align:center; font-size:14px; color:var(--text); box-shadow:var(--shadow); transition:.2s; border:1px solid transparent; }
        .city-item:hover { border-color:var(--primary); transform:translateY(-2px); box-shadow:var(--shadow-md); }
        .city-item.hot-city { background:linear-gradient(135deg, #fff8f5, #fff); border-color:#ff6b0020; color:#ff6b00; font-weight:600; }

        @media (max-width: 768px) {
            .city-grid { grid-template-columns:repeat(auto-fill, minmax(80px, 1fr)); gap:8px; }
            .city-item { padding:8px 10px; font-size:13px; }
            .page-title h1 { font-size:22px; }
        }
    </style>
</head>
<body>

<header class="site-header">
    <div class="container header-inner">
        <a href="/" class="logo">
            <div class="logo-img">58</div>
            区块城市 <span>全部城市</span>
        </a>
        <a href="/" class="header-back">← 返回首页</a>
    </div>
</header>

<nav class="letter-nav">
    <div class="container letter-nav-container">
        <?php foreach ($letters as $letter): ?>
            <?php if (!empty($citiesByLetter[$letter])): ?>
                <a href="#<?= $letter ?>" class="letter-link"><?= $letter ?></a>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
</nav>

<main class="container">
    <div class="page-title">
        <h1>全部城市列表</h1>
        <p>按首字母分组，共 <?= count($allCities) ?> 个城市</p>
    </div>

    <div class="city-list-container">
        <?php foreach ($letters as $letter):
            if (empty($citiesByLetter[$letter])) continue;
        ?>
        <section id="<?= $letter ?>" class="city-section">
            <div class="city-letter"><?= $letter ?></div>
            <div class="city-grid">
                <?php foreach ($citiesByLetter[$letter] as $c): ?>
                    <a href="/city/<?= urlencode($c['pinyin']) ?>.html" class="city-item <?= $c['is_hot'] ? 'hot-city' : '' ?>">
                        <?= htmlspecialchars($c['name']) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endforeach; ?>
    </div>
</main>

</body>
</html>
