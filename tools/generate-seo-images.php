<?php
/**
 * generate-seo-images.php — 生成 SEO 图片资产
 *
 * 产出：
 *   1. assets/images/og-{main,mall,block,bct,nft,hufang,bid,club,model,task}.jpg
 *      （1200×630，og:image 分享卡；主图 og-main.jpg 右侧嵌数字地块图）
 *   2. assets/images/logo/{site}-logo-{121x75,200x133}.png
 *      （百度搜索资源平台「站点属性-LOGO」提交素材）
 *
 * 用法：php tools/generate-seo-images.php
 * 文案/配色改这里后重跑即可，文件名保持不变，线上引用无需改动。
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

$ROOT     = dirname(__DIR__);
$OUT_OG   = $ROOT . '/assets/images';
$OUT_LOGO = $ROOT . '/assets/images/logo';
if (!is_dir($OUT_LOGO)) { mkdir($OUT_LOGO, 0755, true); }

$FONT_B = 'C:/Windows/Fonts/msyhbd.ttc';   // 微软雅黑 Bold
$FONT_R = 'C:/Windows/Fonts/msyh.ttc';      // 微软雅黑 Regular
foreach ([$FONT_B, $FONT_R] as $f) {
    if (!file_exists($f)) { fwrite(STDERR, "缺少字体: $f\n"); exit(1); }
}

/** 对角渐变画布 */
function grad($w, $h, $hex1, $hex2) {
    $im = imagecreatetruecolor($w, $h);
    $r1 = hexdec(substr($hex1, 1, 2)); $g1 = hexdec(substr($hex1, 3, 2)); $b1 = hexdec(substr($hex1, 5, 2));
    $r2 = hexdec(substr($hex2, 1, 2)); $g2 = hexdec(substr($hex2, 3, 2)); $b2 = hexdec(substr($hex2, 5, 2));
    for ($x = 0; $x < $w; $x++) {
        $t = $x / $w;
        // 垂直方向再混入少量变化，形成对角感
        for ($y = 0; $y < $h; $y++) {
            $tt = min(1, $t + ($y / $h) * 0.35);
            $r = (int)round($r1 + ($r2 - $r1) * $tt);
            $g = (int)round($g1 + ($g2 - $g1) * $tt);
            $b = (int)round($b1 + ($b2 - $b1) * $tt);
            imagesetpixel($im, $x, $y, ($r << 16) | ($g << 8) | $b);
        }
    }
    return $im;
}

/** 圆角矩形 */
function rr($im, $x, $y, $w, $h, $r, $color) {
    $x = (int)round($x); $y = (int)round($y);
    $w = (int)round($w); $h = (int)round($h); $r = (int)round($r);
    imagefilledrectangle($im, $x + $r, $y, $x + $w - $r, $y + $h, $color);
    imagefilledrectangle($im, $x, $y + $r, $x + $w, $y + $h - $r, $color);
    imagefilledellipse($im, $x + $r,     $y + $r,     $r * 2, $r * 2, $color);
    imagefilledellipse($im, $x + $w - $r, $y + $r,     $r * 2, $r * 2, $color);
    imagefilledellipse($im, $x + $r,     $y + $h - $r, $r * 2, $r * 2, $color);
    imagefilledellipse($im, $x + $w - $r, $y + $h - $r, $r * 2, $r * 2, $color);
}

/** 文本宽度 */
function tw($size, $font, $text) {
    $b = imagettfbbox($size, 0, $font, $text);
    return $b[2] - $b[0];
}

/** 居中写入（$cx 中心 x，$cy 中心 y） */
function tc($im, $size, $cx, $cy, $color, $font, $text) {
    $b = imagettfbbox($size, 0, $font, $text);
    $h = $b[1] - $b[7];
    imagettftext($im, $size, 0, (int)($cx - tw($size, $font, $text) / 2), (int)($cy + $h / 2), $color, $font, $text);
}

/** 左对齐写入（$y 为视觉顶部） */
function tl($im, $size, $x, $y, $color, $font, $text) {
    $b = imagettfbbox($size, 0, $font, $text);
    imagettftext($im, $size, 0, (int)$x, (int)($y - $b[7]), $color, $font, $text);
}

/** 十六进制色 → GD 色（可带透明度 0-127） */
function col($im, $hex, $alpha = 0) {
    return imagecolorallocatealpha(
        $im,
        hexdec(substr($hex, 1, 2)),
        hexdec(substr($hex, 3, 2)),
        hexdec(substr($hex, 5, 2)),
        $alpha
    );
}

/**
 * 子站配置
 * og:   [文件名, 标题, 副标题, 域名, 渐变起色, 渐变止色]
 * logo: [目录名, LOGO 名称, LOGO 小标签, 主题色]
 */
$sites = [
    'main'   => ['og-main',    '58区块城市',        '元宇宙同城生活服务平台',        'www.58.tl',  '#c2410c', '#ff9e4d', 'www',   '区块城市', '元宇宙同城', '#ff6b00'],
    'mall'   => ['og-mall',    '58人气值商城',      'BCT 商城交易平台 · 免费开店',   'mall.58.tl', '#c2410c', '#ff8f5e', 'mall',  '商城',     '人气值商城', '#ee5a24'],
    'block'  => ['og-block',   'BlockCity 区块市场', '区块认领 · 买卖 · 相邻合并',    'block.58.tl','#1e3a5f', '#3b82f6', 'block', '区块',     'BlockCity 区块市场', '#2563eb'],
    'bct'    => ['og-bct',     '人气值 BCT',        '区块城市数字权益 · 流通交易',   'bct.58.tl',  '#92400e', '#f59e0b', 'bct',   'BCT',      '人气值数字权益', '#f59e0b'],
    'nft'    => ['og-nft',     '58 NFT 数字藏品',   '城市文化纪念 · 链上发行',       'nft.58.tl',  '#4c1d95', '#a78bfa', 'nft',   'NFT',      '数字藏品', '#7c4dff'],
    'hufang' => ['og-hufang',  '互访圈',            '同城互访 · 圈层社交',           'v.58.tl',    '#065f46', '#34d399', 'v',     '互访圈',   '同城社交', '#10b981'],
    'bid'    => ['og-bid',     '58拍卖',            'NFT · 商品在线拍卖',            'bid.58.tl',  '#991b1b', '#f87171', 'bid',   '拍卖',     'NFT · 商品在线拍卖', '#dc2626'],
    'club'   => ['og-club',    '58俱乐部',          '同城兴趣社区 · 圈子动态',       'club.58.tl', '#155e75', '#22d3ee', 'club',  '俱乐部',   '同城兴趣社区', '#0891b2'],
    'model'  => ['og-model',   '58模特',            '人气模特 · 作品与关注',         'model.58.tl','#9d174d', '#f472b6', 'model', '模特',     '人气演艺', '#e8467c'],
    'task'   => ['og-task',    '58任务广场',        '悬赏众包 · 接单赚人气值',       'task.58.tl', '#312e81', '#818cf8', 'task',  '任务',     '悬赏众包 · 任务广场', '#4f46e5'],
];

/* ---------------- 1. og:image 分享卡 1200×630 ---------------- */

foreach ($sites as $key => $s) {
    [$file, $title, $sub, $domain, $c1, $c2] = $s;
    $W = 1200; $H = 630;
    $im = grad($W, $H, $c1, $c2);

    // 装饰：右上/左下柔光大圆
    imagefilledellipse($im, 1120, 60, 620, 620, col($im, '#ffffff', 112));
    imagefilledellipse($im, -80, 640, 460, 460, col($im, '#ffffff', 116));

    // 左上品牌区：58 徽标 + 品牌名
    $badge = 54;
    rr($im, 72, 58, $badge, $badge, 14, col($im, '#ffffff'));
    tc($im, 28, 72 + $badge / 2, 58 + $badge / 2 + 1, col($im, '#ff6b00'), $FONT_B, '58');
    tl($im, 21, 140, 66, col($im, '#ffffff'), $FONT_B, '区块城市 · 58 BlockCity');
    tl($im, 15, 141, 96, col($im, '#ffffff', 40), $FONT_R, '元宇宙同城生活服务生态');

    // 主标题与副标题
    tl($im, 74, 70, 236, col($im, '#ffffff'), $FONT_B, $title);
    tl($im, 30, 74, 340, col($im, '#ffffff', 26), $FONT_R, $sub);

    // 底部域名条
    $domW = tw(24, $FONT_B, $domain) + 44;
    rr($im, 72, 508, $domW, 48, 24, col($im, '#ffffff', 96));
    tc($im, 24, 72 + $domW / 2, 532, col($im, '#ffffff'), $FONT_B, $domain);

    // 主站：右侧嵌数字地块图（品牌视觉锚点）
    if ($key === 'main') {
        $src = $ROOT . '/assets/images/default-block.png';
        if (file_exists($src)) {
            $img = imagecreatefrompng($src);
            $side = 330; $sx = 800; $sy = 150;
            rr($im, $sx - 14, $sy - 14, $side + 28, $side + 28, 26, col($im, '#ffffff', 60));
            imagecopyresampled($im, $img, $sx, $sy, 0, 0, $side, $side, imagesx($img), imagesy($img));
            imagedestroy($img);
        }
    }

    $out = "{$OUT_OG}/{$file}.jpg";
    imagejpeg($im, $out, 88);
    imagedestroy($im);
    printf("og    %-28s %s\n", basename($out), $out);
}

/* ---------------- 2. 百度站点 LOGO 121×75 / 200×133 ---------------- */

/** 在给定最大宽度内自适应字号（从 $max 递减到最小 10px） */
function fit_size($text, $maxW, $max, $font) {
    for ($s = $max; $s > 10; $s--) {
        if (tw($s, $font, $text) <= $maxW) { return $s; }
    }
    return 10;
}

foreach ($sites as $key => $s) {
    [, , , , , , $dir, $name, $tag, $accent] = $s;

    // ---- 121×75 ----
    $im = imagecreatetruecolor(121, 75);
    imagefill($im, 0, 0, col($im, '#ffffff'));
    $b = 38; $bx = 8; $by = (75 - $b) / 2;
    rr($im, $bx, $by, $b, $b, 9, col($im, $accent));
    tc($im, 19, $bx + $b / 2, $by + $b / 2 + 1, col($im, '#ffffff'), $FONT_B, '58');
    $mx = 121 - ($bx + $b + 7) - 5;              // 名称可用宽度
    $fs = fit_size($name, $mx, 21, $FONT_B);
    tl($im, $fs, $bx + $b + 7, (75 - $fs) / 2 - 1, col($im, '#1f2937'), $FONT_B, $name);
    $out = "{$OUT_LOGO}/{$dir}-logo-121x75.png";
    imagepng($im, $out, 6); imagedestroy($im);
    printf("logo  %-28s %s\n", basename($out), $out);

    // ---- 200×133 ----
    $im = imagecreatetruecolor(200, 133);
    imagefill($im, 0, 0, col($im, '#ffffff'));
    $b = 66; $bx = 15; $by = (133 - $b) / 2;
    rr($im, $bx, $by, $b, $b, 15, col($im, $accent));
    tc($im, 32, $bx + $b / 2, $by + $b / 2 + 2, col($im, '#ffffff'), $FONT_B, '58');
    $tx = $bx + $b + 12;
    $mx = 200 - $tx - 8;
    $fs = fit_size($name, $mx, 30, $FONT_B);
    tl($im, $fs, $tx, $by + 4, col($im, '#1f2937'), $FONT_B, $name);
    $fsTag = fit_size($tag, $mx, 15, $FONT_R);
    tl($im, $fsTag, $tx + 1, $by + 12 + $fs, col($im, '#6b7280'), $FONT_R, $tag);
    $out = "{$OUT_LOGO}/{$dir}-logo-200x133.png";
    imagepng($im, $out, 6); imagedestroy($im);
    printf("logo  %-28s %s\n", basename($out), $out);
}

/* ---------------- 3. 主站 hero 地块图（256×256，供首页首屏展示） ---------------- */

$heroSrc = $ROOT . '/assets/images/default-block.png';
$heroOut = $ROOT . '/assets/images/hero-block.png';
if (file_exists($heroSrc)) {
    $src = imagecreatefrompng($heroSrc);
    $side = 256;
    $dst = imagecreatetruecolor($side, $side);
    imagesavealpha($dst, true);
    imagealphablending($dst, false);
    imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
    imagealphablending($dst, true);
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $side, $side, imagesx($src), imagesy($src));
    imagedestroy($src);
    imagepng($dst, $heroOut, 9);
    imagedestroy($dst);
    printf("hero  %-28s %s (%.1f KB)\n", basename($heroOut), $heroOut, filesize($heroOut) / 1024);
} else {
    echo "跳过 hero-block.png：缺少 {$heroSrc}\n";
}

/* ---------------- 4. 校验输出 ---------------- */
echo "\n=== 校验 ===\n";
$fail = 0;
$check = [];
foreach ($sites as $s) { $check[] = "{$OUT_OG}/{$s[0]}.jpg"; }
foreach ($sites as $s) {
    $check[] = "{$OUT_LOGO}/{$s[6]}-logo-121x75.png";
    $check[] = "{$OUT_LOGO}/{$s[6]}-logo-200x133.png";
}
foreach ($check as $f) {
    $i = @getimagesize($f);
    if (!$i) { echo "FAIL $f\n"; $fail++; continue; }
    printf("%-52s %sx%s %6.1f KB\n", basename($f), $i[0], $i[1], filesize($f) / 1024);
}
echo $fail ? "有 {$fail} 个文件生成失败\n" : "全部 30 个文件生成成功\n";
exit($fail ? 1 : 0);
