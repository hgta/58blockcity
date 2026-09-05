<?php
/**
 * 统一城市门户 — 渲染器
 *
 * 单一入口渲染「58 生态城市门户」完整 HTML，供 city.php（web）与
 * city/build-static.php（缓存预生成）复用，返回字符串便于落缓存。
 *
 * ctx 约定（由调用方构造）：
 *   [
 *     'city'    => cities 表一行 (array),
 *     'profile' => city_profiles 一行 (array|null),
 *     'portal'  => CityPortal::assemble() 结果 (array),
 *     'pinyin'  => string,
 *     'meta'    => ['title'=>, 'desc'=>, 'keywords'=>],
 *   ]
 *
 * 页面结构（模块空态自动降级，页面始终完整输出）：
 *   hero(4 指标) → 城市资料卡 → 区块街景(+9区) → BCT → NFT → 同城动态
 *   → 互访圈 → 城市好店 → 详细介绍 → footer
 */

require_once __DIR__ . '/../classes/SeoHelper.php';

if (!function_exists('city_portal_build_ctx')) {
    /**
     * 构建渲染上下文 ctx（city/profile/portal/meta）。
     * city.php 与 city/build-static.php 复用；依赖类惰性加载。
     */
    function city_portal_build_ctx($pdo, array $city, $pinyin) {
        foreach (['City', 'CityPortal'] as $c) {
            if (!class_exists($c)) {
                $f = dirname(__DIR__) . '/classes/' . $c . '.php';
                if (is_file($f)) {
                    require_once $f;
                }
            }
        }
        $portal   = new CityPortal($pdo);
        $cityName = trim((string)($city['name'] ?? ''));
        return [
            'city'    => $city,
            'profile' => $portal->profile((int)($city['id'] ?? 0)),
            'portal'  => $portal->assemble($city),
            'pinyin'  => $pinyin,
            'meta'    => [
                'title'    => $cityName . '区块城市 - 58区块城市 | 元宇宙同城平台',
                'desc'     => $cityName . '区块城市详情页，展示' . $cityName . '城市排名、居民信息、基金余额等关键数据，是了解' . $cityName . '数字经济与元宇宙发展的重要门户。',
                'keywords' => $cityName . '区块城市,' . $cityName . '元宇宙,58同城' . $cityName . ',BlockCity,DAO',
            ],
        ];
    }
}

if (!function_exists('cp_e')) {
    function cp_e($s) {
        return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    }
}
if (!function_exists('cp_clip')) {
    function cp_clip($s, $len = 56) {
        $s = trim(preg_replace('/\s+/u', ' ', (string)$s));
        if (function_exists('mb_strlen') && mb_strlen($s, 'UTF-8') > $len) {
            return mb_substr($s, 0, $len, 'UTF-8') . '…';
        }
        return $s;
    }
}
if (!function_exists('cp_time')) {
    function cp_time($dt) {
        $ts = strtotime((string)$dt);
        return $ts ? date('m-d H:i', $ts) : '';
    }
}
if (!function_exists('cp_bignum')) {
    /** 100000000 → 1亿 缩写；保留 2 位小数 */
    function cp_bignum($n) {
        $n = (float)$n;
        if ($n >= 100000000) return rtrim(rtrim(number_format($n / 100000000, 2), '0'), '.') . '亿';
        if ($n >= 10000) return rtrim(rtrim(number_format($n / 10000, 1), '0'), '.') . '万';
        return number_format($n);
    }
}
if (!function_exists('cp_json_arr')) {
    /** 安全 decode 为数组；解析失败/非数组返回 null */
    function cp_json_arr($json) {
        if (empty($json)) return null;
        $v = json_decode($json, true);
        return is_array($v) ? $v : null;
    }
}
if (!function_exists('cp_money')) {
    function cp_money($price, $currency = '') {
        if ($currency === 'popularity') {
            return cp_bignum($price) . ' 人气值';
        }
        return '¥' . number_format((float)$price, 2);
    }
}
if (!function_exists('cp_avatar_svg')) {
    /** 城市首字 SVG 头像 data URI（原静态页风格） */
    function cp_avatar_svg($text) {
        $ch = cp_e(mb_substr((string)$text, 0, 1, 'UTF-8'));
        return "data:image/svg+xml;charset=UTF-8," .
            rawurlencode("<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect width='100' height='100' fill='%23fff8f0'/><text x='50' y='60' font-size='40' text-anchor='middle' fill='%23cc0000'>{$ch}</text></svg>");
    }
}

if (!function_exists('city_portal_render')) {
    function city_portal_render(array $ctx): string {
        $city    = $ctx['city'];
        $profile = $ctx['profile'] ?? null;
        $portal  = $ctx['portal'];
        $pinyin  = $ctx['pinyin'];
        $meta    = $ctx['meta'];

        $cityName  = $portal['city_name'] ?: ($city['name'] ?? '');
        // Schema.org addressRegion：原值已带行政后缀则原样，否则补「市」
        $addrRegion = $cityName;
        if ($addrRegion !== '') {
            $suffixes = ['特别行政区', '自治区', '自治州', '盟', '省', '市'];
            $hasSuffix = false;
            foreach ($suffixes as $suf) {
                if (mb_strlen($addrRegion) > mb_strlen($suf) && mb_substr($addrRegion, -mb_strlen($suf)) === $suf) {
                    $hasSuffix = true;
                    break;
                }
            }
            if (!$hasSuffix) {
                $addrRegion .= '市';
            }
        }
        $rank      = $city['rank'] ?? '—';
        $residents = number_format((int)($city['resident_count'] ?? 0));
        $blocksCnt = number_format((int)($city['activated_blocks'] ?? 0));
        $fund      = number_format((float)($city['current_balance'] ?? ($city['total_fund'] ?? 0)), 1);
        $areaCode  = (string)($city['area_code'] ?? '');
        $enterUrl  = $areaCode !== '' ? "https://www.blockcity.pub/{$areaCode}?iclc" : 'https://www.blockcity.pub/?iclc';
        $pageUrl   = 'https://58.tl/city/' . $pinyin . '.html';

        $cityEnc = rawurlencode($cityName);

        // ===== 深链映射（集中配置，一处维护）=====
        $L = [
            'blocks'  => 'https://block.58.tl/city.php?name=' . $pinyin,
            'bct'     => 'https://bct.58.tl/market.php',
            'nft'     => 'https://nft.58.tl/',
            'club'    => 'https://club.58.tl/index.php?city=' . $cityEnc,
            'circles' => 'https://v.58.tl/?city=' . $cityEnc,
            'mall'    => 'https://mall.58.tl/model/list.php?city=' . $cityEnc,
        ];

        ob_start();
        ?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= cp_e($meta['title']) ?></title>
    <meta name="description" content="<?= cp_e($meta['desc']) ?>">
    <meta name="keywords" content="<?= cp_e($meta['keywords']) ?>">
    <link rel="canonical" href="<?= cp_e($pageUrl) ?>" />
    <meta property="og:title" content="<?= cp_e($meta['title']) ?>">
    <meta property="og:description" content="<?= cp_e($meta['desc']) ?>">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= cp_e($pageUrl) ?>">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= cp_e($meta['title']) ?>">
    <meta name="twitter:description" content="<?= cp_e($meta['desc']) ?>">
    <script type="application/ld+json">
    {"@context":"https://schema.org","@type":"City","name":"<?= cp_e($cityName) ?>区块城市","url":"<?= cp_e($pageUrl) ?>","address":{"@type":"PostalAddress","addressRegion":"<?= cp_e($addrRegion) ?>","addressCountry":"CN"}}
    </script>
    <script type="application/ld+json">
    {"@context":"https://schema.org","@type":"BreadcrumbList","itemListElement":[{"@type":"ListItem","position":1,"name":"58区块城市","item":"https://www.58.tl/"},{"@type":"ListItem","position":2,"name":"城市列表","item":"https://www.58.tl/all-cities.php"},{"@type":"ListItem","position":3,"name":"<?= cp_e($cityName) ?>区块城市"}]}
    </script>
    <script charset="UTF-8" id="LA_COLLECT" src="//sdk.51.la/js-sdk-pro.min.js"></script>
    <script>LA.init({id:"Km945dEjfme2S7Eg",ck:"Km945dEjfme2S7Eg"})</script>
    <link rel="icon" type="image/png" href="/favicon-96x96.png" sizes="96x96" />
    <link rel="icon" type="image/svg+xml" href="/favicon.svg" />
    <link rel="shortcut icon" href="/favicon.ico" />
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png" />
    <link rel="stylesheet" href="/city/city.css" type="text/css" media="all" />
    <link rel="stylesheet" href="/assets/css/city-portal.css" type="text/css" media="all" />
    <script>
    var _hmt=_hmt||[];
    (function(){var hm=document.createElement("script");hm.src="https://hm.baidu.com/hm.js?5949e57aa9d2303fbf9451b06d4df471";var s=document.getElementsByTagName("script")[0];s.parentNode.insertBefore(hm,s);})();
    </script>
</head>
<body>
    <div class="container breadcrumb">
        <a href="/index.php">首页</a> &gt;
        <a href="/top200city.php">TOP200城市</a> &gt;
        <span><?= cp_e($cityName) ?>区块城市</span>
    </div>
    <header>
        <div class="container header-container">
            <div class="logo">
                <div class="logo-img">58</div>
                <div class="logo-text">区块城市<span>元宇宙同城生活服务平台</span></div>
            </div>
            <div class="user-actions">
                <a href="/index.php" class="nav-button">返回首页</a>
                <a href="https://nft.58.tl/" class="nav-button">NFT交易</a>
                <a href="https://v.58.tl/" class="nav-button">互访圈</a>
                <a href="/top200city.php" class="nav-button">TOP200城市</a>
                <a href="https://www.blockcity.vip/pages/user/user/?iclc" class="nav-button">我的区块</a>
            </div>
        </div>
    </header>
    <div class="city-location-bar" id="cityLocationBar">
        欢迎您,来自于<span id="userCity">未知城市</span>的朋友，<a href="https://www.blockcity.pub/?iclc" id="cityLink">点击进入您所在城市的区块</a>
    </div>

    <div class="cp-wrap container">
        <?php /* ============ hero ============ */ ?>
        <section class="cp-hero">
            <div class="cp-hero-avatar"><img src="<?= cp_e(cp_avatar_svg($cityName)) ?>" alt="<?= cp_e($cityName) ?>城市"></div>
            <div class="cp-hero-main">
                <h1 class="cp-city-name"><?= cp_e($cityName) ?>区块城市</h1>
                <p class="cp-slogan"><?= cp_e(trim((string)($profile['slogan'] ?? '')) ?: ('共建' . $cityName . '元宇宙')) ?></p>
                <div class="cp-statgrid">
                    <div class="cp-stat"><span class="cp-stat-label">全国排名</span><span class="cp-stat-value">第<?= cp_e($rank) ?>名</span></div>
                    <div class="cp-stat"><span class="cp-stat-label">现有居民</span><span class="cp-stat-value"><?= cp_e($residents) ?>人</span></div>
                    <div class="cp-stat"><span class="cp-stat-label">开启区块数</span><span class="cp-stat-value"><?= cp_e($blocksCnt) ?></span></div>
                    <div class="cp-stat"><span class="cp-stat-label">基金余额</span><span class="cp-stat-value">¥<?= cp_e($fund) ?></span></div>
                </div>
                <a class="cp-enter-btn" href="<?= cp_e($enterUrl) ?>" rel="nofollow">进入<?= cp_e($cityName) ?>区块城市 →</a>
            </div>
        </section>

        <?php /* ============ 城市资料卡 ============ */ ?>
        <?php if ($profile): $facts = [
                ['行政面积', $profile['admin_area'] ?? ''],
                ['常住人口', $profile['population'] ?? ''],
                ['GDP', $profile['gdp'] ?? ''],
                ['人均GDP', $profile['gdp_per_capita'] ?? ''],
                ['城镇化率', $profile['urbanization_rate'] ?? ''],
                ['高校数量', $profile['universities'] ?? ''],
            ];
            $tags = cp_json_arr($profile['feature_tags'] ?? '');
            $highlights = [
                ['定位', $profile['position'] ?? '', '📍'],
                ['地标', $profile['landmarks'] ?? '', '🏛'],
                ['美食', $profile['food'] ?? '', '🍜'],
                ['潜力', $profile['potential'] ?? '', '📈'],
            ];
            $year = trim((string)($profile['data_year'] ?? ''));
        ?>
        <section class="cp-card" id="city-profile">
            <h2 class="cp-card-title"><span class="cp-dot"></span>走进<?= cp_e($cityName) ?><?= $year !== '' ? '<em class="cp-year">数据年份：' . cp_e($year) . '</em>' : '' ?></h2>
            <div class="cp-factgrid">
                <?php foreach ($facts as $f): if (($f[1] ?? '') === '') continue; ?>
                    <div class="cp-fact"><span class="cp-fact-label"><?= cp_e($f[0]) ?></span><span class="cp-fact-value"><?= cp_e($f[1]) ?></span></div>
                <?php endforeach; ?>
            </div>
            <?php if ($tags): ?>
                <div class="cp-tags">
                    <?php foreach ($tags as $t): $t = trim((string)$t); if ($t === '') continue; ?>
                        <span class="cp-tag"><?= cp_e($t) ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php $hasHl = false; foreach ($highlights as $h) { if (trim((string)$h[1]) !== '') { $hasHl = true; break; } } ?>
            <?php if ($hasHl): ?>
                <div class="cp-hlgrid">
                    <?php foreach ($highlights as $h): if (trim((string)$h[1]) === '') continue; ?>
                        <div class="cp-hl"><div class="cp-hl-head"><?= $h[2] ?> <?= cp_e($h[0]) ?></div><div class="cp-hl-body"><?= cp_e(cp_clip($h[1], 90)) ?></div></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
        <?php endif; ?>

        <?php /* ============ 🏘 区块街景 ============ */ ?>
        <?php $blocksMod = $portal['blocks'] ?? ['ok' => false, 'count' => 0, 'items' => []]; ?>
        <section class="cp-card" id="city-blocks">
            <h2 class="cp-card-title"><span class="cp-dot"></span>🏘 区块街景
                <a class="cp-more" href="<?= cp_e($L['blocks']) ?>" rel="nofollow">进入区块地图 →</a></h2>
            <?php if ($blocksMod['count'] > 0): ?>
                <div class="cp-bgrid">
                    <?php foreach ($blocksMod['items'] as $b):
                        $label = trim((string)$b['name']);
                        $skinText = trim((string)$b['display_text']);
                        $skinImg = trim((string)$b['img']);
                    ?>
                    <div class="cp-bcard">
                        <div class="cp-bhead"><span class="cp-bzone"><?= cp_e($b['zone']) ?>区</span><span class="cp-bno">#<?= cp_e($b['number']) ?></span></div>
                        <div class="cp-bname"><?= cp_e($label) ?></div>
                        <?php if ($skinImg !== ''): ?>
                            <div class="cp-bimg"><img src="<?= cp_e($skinImg) ?>" alt="<?= cp_e($label) ?>" loading="lazy"></div>
                        <?php elseif ($skinText !== ''): ?>
                            <div class="cp-btext cp-skin-<?= cp_e($b['display_color'] ?: 'red') ?>"><?= cp_e(cp_clip($skinText, 8)) ?></div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="cp-empty">该城暂无已命名的区块，<a href="<?= cp_e($L['blocks']) ?>" rel="nofollow">去区块地图认领你的第 1 个区块 →</a></div>
            <?php endif; ?>
            <?php
                // 9 区 mini 卡（现实区名若 city_profiles.districts 提供，否则仅 A~Z 占位）
                $districts = cp_json_arr($profile['districts'] ?? '');
                $byZone = [];
                if ($districts) { foreach ($districts as $d) { $byZone[strtoupper((string)($d['zone'] ?? ''))] = (string)($d['area'] ?? ''); } }
                $zones = ['A','B','C','D','E','F','G','H','Z'];
            ?>
            <div class="cp-mapstrip" aria-label="区块分区示意">
                <?php foreach ($zones as $z): ?>
                    <span class="cp-minizone"><b><?= $z ?></b><?= !empty($byZone[$z]) ? cp_e('·' . $byZone[$z]) : '区' ?></span>
                <?php endforeach; ?>
            </div>
        </section>

        <?php /* ============ 💰 BCT 行情 ============ */ ?>
        <?php $bctMod = $portal['bct'] ?? ['ok' => false, 'count' => 0, 'items' => []]; ?>
        <section class="cp-card" id="city-bct">
            <h2 class="cp-card-title"><span class="cp-dot"></span>💰 BCT 行情
                <a class="cp-more" href="<?= cp_e($L['bct']) ?>" rel="nofollow">行情市场 →</a></h2>
            <?php if ($bctMod['count'] > 0): $b = $bctMod['items'][0]; ?>
                <div class="cp-bctrow">
                    <div class="cp-fact"><span class="cp-fact-label">BCT 现价</span><span class="cp-fact-value">¥<?= number_format((float)$b['current_price'], 4) ?></span></div>
                    <div class="cp-fact"><span class="cp-fact-label">基础价</span><span class="cp-fact-value">¥<?= number_format((float)$b['base_price'], 4) ?></span></div>
                    <div class="cp-fact"><span class="cp-fact-label">流通量</span><span class="cp-fact-value"><?= cp_e(cp_bignum($b['circulating_supply'])) ?></span></div>
                    <div class="cp-fact"><span class="cp-fact-label">总供给</span><span class="cp-fact-value"><?= cp_e(cp_bignum($b['total_supply'])) ?></span></div>
                </div>
            <?php else: ?>
                <div class="cp-empty">该城暂无 BCT 行情，<a href="<?= cp_e($L['bct']) ?>" rel="nofollow">去行情市场看看 →</a></div>
            <?php endif; ?>
        </section>

        <?php /* ============ 🖼 NFT 热卖 ============ */ ?>
        <?php $nftMod = $portal['nft'] ?? ['ok' => false, 'count' => 0, 'items' => []]; ?>
        <section class="cp-card" id="city-nft">
            <h2 class="cp-card-title"><span class="cp-dot"></span>🖼 <?= cp_e($cityName) ?>NFT 挂售
                <a class="cp-more" href="<?= cp_e($L['nft']) ?>" rel="nofollow">NFT 广场 →</a></h2>
            <?php if ($nftMod['count'] > 0): ?>
                <div class="cp-nftstrip">
                    <?php foreach ($nftMod['items'] as $n):
                        $nftHref = SeoHelper::nftUrl($n['nft_id'], $n['code']);
                    ?>
                    <a class="cp-nft" href="<?= cp_e($nftHref) ?>">
                        <?php if ($n['img'] !== ''): ?>
                            <img src="<?= cp_e($n['img']) ?>" alt="NFT <?= cp_e($n['code']) ?>" loading="lazy">
                        <?php else: ?>
                            <span class="cp-nft-ph"><?= cp_e(cp_clip($n['code'] ?: 'NFT', 6)) ?></span>
                        <?php endif; ?>
                        <span class="cp-nft-name"><?= cp_e($n['code']) ?></span>
                        <span class="cp-nft-price"><?= cp_e(cp_money($n['price'], $n['currency'])) ?></span>
                    </a>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="cp-empty">该城暂无 NFT 挂售，<a href="<?= cp_e($L['nft']) ?>" rel="nofollow">去 NFT 广场逛逛 →</a></div>
            <?php endif; ?>
        </section>

        <?php /* ============ 💬 同城动态 ============ */ ?>
        <?php $clubMod = $portal['club'] ?? ['ok' => false, 'count' => 0, 'items' => []]; ?>
        <section class="cp-card" id="city-feed">
            <h2 class="cp-card-title"><span class="cp-dot"></span>💬 <?= cp_e($cityName) ?>同城动态
                <a class="cp-more" href="<?= cp_e($L['club']) ?>" rel="nofollow">去发帖 →</a></h2>
            <?php if ($clubMod['count'] > 0): ?>
                <ul class="cp-feed">
                    <?php foreach ($clubMod['items'] as $p):
                        $postTitle = trim((string)$p['title']);
                        $display   = $postTitle !== '' ? $postTitle : cp_clip($p['content'], 40);
                        $isMoment  = (($p['type'] ?? 'post') === 'moment');
                    ?>
                    <li>
                        <a class="cp-feed-main" href="<?= cp_e(SeoHelper::postUrl($p['id'], $postTitle !== '' ? $postTitle : $p['content'])) ?>">
                            <span class="cp-feed-type"><?= $isMoment ? '心情' : '帖子' ?></span>
                            <span class="cp-feed-text"><?= cp_e($display) ?></span>
                        </a>
                        <span class="cp-feed-meta"><?= cp_e($p['username'] ?: '匿名') ?> · <?= cp_e(cp_time($p['created_at'])) ?> · 👍<?= (int)$p['like_count'] ?> · 💬<?= (int)$p['comment_count'] ?></span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <div class="cp-empty">该城暂无同城动态，<a href="<?= cp_e($L['club']) ?>" rel="nofollow">抢发第 1 条 →</a></div>
            <?php endif; ?>
        </section>

        <?php /* ============ 🔄 互访圈 ============ */ ?>
        <?php $circlesMod = $portal['circles'] ?? ['ok' => false, 'count' => 0, 'items' => [], 'stat' => []]; ?>
        <section class="cp-card" id="city-circles">
            <h2 class="cp-card-title"><span class="cp-dot"></span>🔄 <?= cp_e($cityName) ?>互访圈
                <a class="cp-more" href="<?= cp_e($L['circles']) ?>" rel="nofollow">更多圈子 →</a></h2>
            <?php $cstat = $circlesMod['stat'] ?? [];
                $cstatChips = [];
                if (!empty($cstat['circle_count']) && $cstat['circle_count'] !== null) $cstatChips[] = $cstat['circle_count'] . ' 个圈子';
                if (!empty($cstat['user_count']) && $cstat['user_count'] !== null) $cstatChips[] = $cstat['user_count'] . ' 位圈友';
                if (!empty($cstat['visit_count']) && $cstat['visit_count'] !== null) $cstatChips[] = $cstat['visit_count'] . ' 次互访';
            ?>
            <?php if ($cstatChips): ?>
                <div class="cp-cstat"><?= cp_e(implode(' · ', $cstatChips)) ?></div>
            <?php endif; ?>
            <?php if ($circlesMod['count'] > 0): ?>
                <div class="cp-cgrid">
                    <?php foreach ($circlesMod['items'] as $c): ?>
                        <a class="cp-ccard" href="<?= cp_e(SeoHelper::circleUrl($c['id'], $c['name'])) ?>">
                            <span class="cp-ccard-name"><?= cp_e($c['name']) ?></span>
                            <span class="cp-ccard-desc"><?= cp_e(cp_clip($c['description'] ?: '暂无简介', 40)) ?></span>
                            <span class="cp-ccard-meta">圈主 <?= cp_e($c['username'] ?: '匿名') ?><?= $c['category'] ? ' · ' . cp_e($c['category']) : '' ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="cp-empty">该城暂无互访圈，<a href="<?= cp_e($L['circles']) ?>" rel="nofollow">去发起你的圈子 →</a></div>
            <?php endif; ?>
        </section>

        <?php /* ============ 🛍 城市好店 ============ */ ?>
        <?php $mallMod = $portal['mall'] ?? ['ok' => false, 'count' => 0, 'items' => []]; ?>
        <section class="cp-card" id="city-mall">
            <h2 class="cp-card-title"><span class="cp-dot"></span>🛍 <?= cp_e($cityName) ?>好店·人物
                <a class="cp-more" href="<?= cp_e($L['mall']) ?>" rel="nofollow">逛逛商城 →</a></h2>
            <?php if ($mallMod['count'] > 0): ?>
                <div class="cp-sgrid">
                    <?php foreach ($mallMod['items'] as $m):
                        $href = $m['kind'] === 'author'
                            ? SeoHelper::authorUrl($m['id'], $m['nickname'])
                            : SeoHelper::modelUrl($m['id'], $m['nickname']);
                    ?>
                        <a class="cp-scard" href="<?= cp_e($href) ?>">
                            <?php if ($m['img'] !== ''): ?>
                                <img class="cp-savatar" src="<?= cp_e($m['img']) ?>" alt="<?= cp_e($m['nickname']) ?>" loading="lazy">
                            <?php else: ?>
                                <span class="cp-savatar cp-savatar-ph"><?= cp_e(mb_substr((string)$m['nickname'], 0, 1, 'UTF-8')) ?></span>
                            <?php endif; ?>
                            <span class="cp-sname"><?= cp_e($m['nickname']) ?></span>
                            <span class="cp-stag"><?= $m['kind'] === 'author' ? '作者' : '模特' ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="cp-empty">该城暂无好店入驻，<a href="<?= cp_e($L['mall']) ?>" rel="nofollow">去商城看看 →</a></div>
            <?php endif; ?>
        </section>

        <?php /* ============ 📖 详细介绍 ============ */ ?>
        <?php if ($profile):
            $intro = cp_json_arr($profile['intro'] ?? '');
            if ($intro): ?>
                <section class="cp-card" id="city-intro">
                    <h2 class="cp-card-title"><span class="cp-dot"></span>📖 走进<?= cp_e($cityName) ?></h2>
                    <?php foreach ($intro as $seg): if (!is_array($seg)) continue; ?>
                        <h3 class="cp-intro-h"><?= cp_e($seg['h'] ?? '') ?></h3>
                        <p class="cp-intro-p"><?= nl2br(cp_e(trim((string)($seg['p'] ?? '')))) ?></p>
                    <?php endforeach; ?>
                </section>
            <?php endif;
        endif; ?>
    </div>

    <footer>
        <div class="container">
            <div class="footer-container">
                <div class="footer-column"><h3>关于58区块城市</h3><ul>
                    <li><a href="https://www.blockcity.vip/pages/index/company/?iclc=1">公司简介</a></li>
                    <li><a href="https://www.blockcity.vip/zt/pages/invest/plan/?iclc=1">元宇宙愿景</a></li>
                    <li><a href="https://www.blockcity.vip/pages/index/help3?iclc=1&id=72&type=7">产品介绍</a></li>
                    <li><a href="https://www.blockcity.pub/pages/index/book/?iclc=1">元宇宙白皮书</a></li>
                </ul></div>
                <div class="footer-column"><h3>帮助中心</h3><ul>
                    <li><a href="/help/help.html">新手指南</a></li>
                    <li><a href="#">元宇宙入门</a></li>
                    <li><a href="https://mp.weixin.qq.com/s/KWoNXzeldh3GxI9uS2O80g">用户答疑</a></li>
                    <li><a href="https://www.blockcity.vip/pages/index/help/?iclc=1">常见问题</a></li>
                </ul></div>
                <div class="footer-column"><h3>商家服务</h3><ul>
                    <li><a href="/news.html">区块新闻</a></li>
                    <li><a href="https://www.blockcity.biz/naquba/">元宇宙店铺</a></li>
                    <li><a href="https://www.blockcity.pub/pages/index/block/?iclc=1">9区价格表</a></li>
                    <li><a href="http://blockcity.pub/zc/?iclc">营销推广</a></li>
                </ul></div>
                <div class="footer-column"><h3>关注我们</h3><ul>
                    <li><a href="#">BlockCity微信公众号</a></li>
                    <li><a href="#">BlockCity微博</a></li>
                    <li><a href="#">BlockCity小红书</a></li>
                    <li><a href="https://work.weixin.qq.com/kfid/kfc5e3b38b343460881">BlockCity在线客服</a></li>
                </ul></div>
            </div>
            <div class="copyright">© 2025 58区块城市 | BlockCity DAO 版权所有 | 基于元宇宙技术的下一代同城服务平台</div>
        </div>
    </footer>
    <div class="promotion-floating" id="promotionFloating">
        <div class="promotion-close" onclick="document.getElementById('promotionFloating').style.display='none'">×</div>
        <div class="promotion-header"><i>🎉</i> 限时优惠</div>
        <div class="promotion-content">凡通过本站购买各城市新区块，一律享<strong style="color:#ff6b00;">7.5折优惠</strong>！<br>详情请扫描下方二维码添加客服微信咨询。</div>
        <div class="promotion-qrcode"><img src="/qr.jpg" alt="<?= cp_e($cityName) ?>区块城市客服微信二维码" loading="lazy"></div>
        <div style="text-align:center;font-size:12px;color:#999;">扫码添加客服微信</div>
    </div>
    <script src="/city/city.js"></script>
    <script>
        window.onload=getCityInfo;
        setTimeout(function(){document.getElementById('promotionFloating').style.display='block';},3000);
    </script>
</body>
</html>
        <?php
        return ob_get_clean();
    }
}
