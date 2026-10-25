<?php
/**
 * 极简 SVG → PNG 光栅化器（纯 PHP + GD，无需 Imagick / rsvg / ImageMagick）
 *
 * 定位：不是完整实现 SVG 规范，而是精确覆盖本站 NFT 头像（Noto Emoji 风格，
 * viewBox 0 0 72 72）用到的子集，让其能被微信 / 百度等只认位图的抓取器使用。
 *
 * 支持范围：
 *   图元：path / rect / circle / ellipse / line / polyline / polygon
 *   path 命令：M m L l H h V v C c S s Q q T t A a Z z
 *   绘制属性：fill / stroke / stroke-width / fill-opacity / stroke-opacity
 *   <g> 上的属性继承（fill / stroke / stroke-width / opacity）
 *
 * 抗锯齿：GD 无原生 AA，采用 3× 超采样后缩小，边缘质量接近矢量渲染。
 */
class SvgRaster
{
    /** @var resource|\GdImage */
    private $im;
    private $w, $h;          // 目标尺寸
    private $ss;             // 超采样倍数
    private $W, $H;          // 实际画布尺寸 = w*ss
    private $scale;          // viewBox → 画布 缩放
    private $ox, $oy;        // 居中偏移

    private $vbX = 0, $vbY = 0, $vbW = 72, $vbH = 72;

    /** @var \SimpleXMLElement */
    private $root;

    /**
     * @param string $svg  SVG 文本
     * @param int    $size 输出边长（正方形）
     * @param array  $bg   背景色 [r,g,b]，null = 透明
     * @return string|false PNG 二进制，失败返回 false
     */
    public static function toPng($svg, $size = 300, $bg = null)
    {
        try {
            $r = new self($svg, $size, $bg);
            return $r->render();
        } catch (Throwable $e) {
            error_log('SvgRaster failed: ' . $e->getMessage());
            return false;
        }
    }

    private function __construct($svg, $size, $bg)
    {
        if (!function_exists('imagecreatetruecolor')) {
            throw new RuntimeException('GD 扩展不可用');
        }
        $size = max(16, min(1024, intval($size)));
        $this->ss = 3;
        $this->w  = $this->h = $size;
        $this->W  = $size * $this->ss;
        $this->H  = $size * $this->ss;

        $this->parseViewBox($svg);

        // 等比缩放并居中（preserveAspectRatio: xMidYMid meet）
        $this->scale = min($this->W / $this->vbW, $this->H / $this->vbH);
        $this->ox = ($this->W - $this->vbW * $this->scale) / 2;
        $this->oy = ($this->H - $this->vbH * $this->scale) / 2;

        $this->im = imagecreatetruecolor($this->W, $this->H);
        imagesavealpha($this->im, true);
        imagealphablending($this->im, true);

        if ($bg === null) {
            imagefill($this->im, 0, 0, imagecolorallocatealpha($this->im, 0, 0, 0, 127));
        } else {
            imagefilledrectangle($this->im, 0, 0, $this->W, $this->H,
                imagecolorallocate($this->im, $bg[0], $bg[1], $bg[2]));
        }

        $this->root = $this->loadXml($svg);
    }

    private function parseViewBox($svg)
    {
        if (preg_match('/viewBox\s*=\s*["\']\s*([\d.\-eE]+)[\s,]+([\d.\-eE]+)[\s,]+([\d.\-eE]+)[\s,]+([\d.\-eE]+)/', $svg, $m)) {
            $this->vbX = floatval($m[1]); $this->vbY = floatval($m[2]);
            $this->vbW = floatval($m[3]); $this->vbH = floatval($m[4]);
        } elseif (preg_match('/width\s*=\s*["\']([\d.]+)/', $svg, $mw) && preg_match('/height\s*=\s*["\']([\d.]+)/', $svg, $mh)) {
            $this->vbW = floatval($mw[1]); $this->vbH = floatval($mh[1]);
        }
        if ($this->vbW <= 0) { $this->vbW = 72; }
        if ($this->vbH <= 0) { $this->vbH = 72; }
    }

    private function loadXml($svg)
    {
        // 去掉 DOCTYPE / 注释，避免 SimpleXML 解析外部实体
        $svg = preg_replace('/<!DOCTYPE[^>]*>/i', '', $svg);
        $svg = preg_replace('/<!--.*?-->/s', '', $svg);
        // 去掉 xmlns 声明：SimpleXML 对「默认命名空间」的 children() 行为不一致，
        // 剥掉后按普通 XML 处理最稳（这批 SVG 不使用任何带前缀的属性）
        $svg = preg_replace('/\s+xmlns(?::[A-Za-z0-9_-]+)?\s*=\s*["\'][^"\']*["\']/i', '', $svg);
        $prev = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($svg, 'SimpleXMLElement', LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        if ($xml === false) {
            throw new RuntimeException('SVG 解析失败');
        }
        return $xml;
    }

    private function render()
    {
        $this->walk($this->root, [
            'fill' => '#000000', 'stroke' => null, 'sw' => 1.0,
            'fo' => 1.0, 'so' => 1.0, 'op' => 1.0,
        ]);

        // 超采样 → 目标尺寸（GD 的缩小重采样自带平滑，等效抗锯齿）
        $out = imagecreatetruecolor($this->w, $this->h);
        imagesavealpha($out, true);
        imagealphablending($out, false);
        imagefill($out, 0, 0, imagecolorallocatealpha($out, 0, 0, 0, 127));
        imagealphablending($out, true);
        imagecopyresampled($out, $this->im, 0, 0, 0, 0, $this->w, $this->h, $this->W, $this->H);

        ob_start();
        imagepng($out);
        $png = ob_get_clean();
        imagedestroy($out);
        imagedestroy($this->im);
        return $png === '' ? false : $png;
    }

    /** 递归遍历节点 */
    private function walk($node, $st)
    {
        // 继承当前节点上的绘制属性
        $st = $this->inherit($node, $st);
        $name = strtolower($node->getName());

        switch ($name) {
            case 'svg': case 'g': case 'defs': case 'a': case 'clipPath':
                foreach ($node->children() as $child) { $this->walk($child, $st); }
                break;
            case 'path':
                $d = (string)($node['d'] ?? '');
                if ($d !== '') { $this->drawPath($d, $st); }
                break;
            case 'rect':
                $this->drawRect($node, $st); break;
            case 'circle':
                $this->drawCircle($node, $st); break;
            case 'ellipse':
                $this->drawEllipse($node, $st); break;
            case 'line':
                $this->drawLine($node, $st); break;
            case 'polyline':
                $this->drawPoly($node, $st, false); break;
            case 'polygon':
                $this->drawPoly($node, $st, true); break;
        }
    }

    private function inherit($node, $st)
    {
        if (isset($node['fill']))         { $st['fill'] = (string)$node['fill']; }
        if (isset($node['stroke']))       { $st['stroke'] = (string)$node['stroke']; }
        if (isset($node['stroke-width'])) { $st['sw'] = floatval($node['stroke-width']); }
        if (isset($node['fill-opacity'])) { $st['fo'] = floatval($node['fill-opacity']); }
        if (isset($node['stroke-opacity'])) { $st['so'] = floatval($node['stroke-opacity']); }
        if (isset($node['opacity']))      { $st['op'] = floatval($node['opacity']); }
        if (isset($node['style'])) {
            foreach (explode(';', (string)$node['style']) as $decl) {
                $kv = explode(':', $decl, 2);
                if (count($kv) !== 2) continue;
                $k = trim($kv[0]); $v = trim($kv[1]);
                if ($k === 'fill') $st['fill'] = $v;
                if ($k === 'stroke') $st['stroke'] = $v;
                if ($k === 'stroke-width') $st['sw'] = floatval($v);
                if ($k === 'fill-opacity') $st['fo'] = floatval($v);
                if ($k === 'stroke-opacity') $st['so'] = floatval($v);
                if ($k === 'opacity') $st['op'] = floatval($v);
            }
        }
        return $st;
    }

    // ---------- 坐标变换 ----------

    private function tx($x) { return ($x - $this->vbX) * $this->scale + $this->ox; }
    private function ty($y) { return ($y - $this->vbY) * $this->scale + $this->oy; }
    private function ts($v) { return $v * $this->scale; }

    // ---------- 颜色 ----------

    private function color($spec, $opacity, $default)
    {
        if ($spec === null || $spec === '') { $spec = $default; }
        $spec = strtolower(trim($spec));
        if ($spec === 'none' || $spec === 'transparent') return null;

        $a = (int)round(max(0, min(1, $opacity)) * 127);
        $a = 127 - $a;   // GD: 0 = 不透明, 127 = 全透明

        if ($spec[0] === '#') {
            $hex = substr($spec, 1);
            if (strlen($hex) === 3) {
                $r = hexdec($hex[0] . $hex[0]); $g = hexdec($hex[1] . $hex[1]); $b = hexdec($hex[2] . $hex[2]);
            } else {
                $r = hexdec(substr($hex, 0, 2)); $g = hexdec(substr($hex, 2, 2)); $b = hexdec(substr($hex, 4, 2));
            }
            return imagecolorallocatealpha($this->im, $r, $g, $b, $a);
        }
        if (preg_match('/^rgb\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*\)/', $spec, $m)) {
            return imagecolorallocatealpha($this->im, intval($m[1]), intval($m[2]), intval($m[3]), $a);
        }
        static $named = [
            'black' => [0,0,0], 'white' => [255,255,255], 'red' => [255,0,0],
            'green' => [0,128,0], 'blue' => [0,0,255], 'gray' => [128,128,128],
            'grey' => [128,128,128], 'yellow' => [255,255,0], 'orange' => [255,165,0],
            'purple' => [128,0,128], 'pink' => [255,192,203], 'brown' => [165,42,42],
        ];
        if (isset($named[$spec])) {
            return imagecolorallocatealpha($this->im, $named[$spec][0], $named[$spec][1], $named[$spec][2], $a);
        }
        return imagecolorallocatealpha($this->im, 0, 0, 0, $a);
    }

    // ---------- 绘制 ----------

    /** 填充多边形（兼容 PHP 7 / 8 的 imagefilledpolygon 签名差异） */
    private function fillPoly($pts, $col)
    {
        if (count($pts) < 6) return;   // 至少 3 个点
        if (PHP_VERSION_ID >= 80000) {
            imagefilledpolygon($this->im, $pts, $col);
        } else {
            imagefilledpolygon($this->im, $pts, count($pts) / 2, $col);
        }
    }

    /** 描边折线 */
    private function strokePts($pts, $col, $sw, $closed)
    {
        $n = count($pts) / 2;
        if ($n < 2) return;
        imagesetthickness($this->im, max(1, (int)round($sw)));
        for ($i = 1; $i < $n; $i++) {
            imageline($this->im, $pts[($i-1)*2], $pts[($i-1)*2+1], $pts[$i*2], $pts[$i*2+1], $col);
        }
        if ($closed && $n > 2) {
            imageline($this->im, $pts[($n-1)*2], $pts[($n-1)*2+1], $pts[0], $pts[1], $col);
        }
        imagesetthickness($this->im, 1);
    }

    private function paint($subs, $st, $isClosed)
    {
        $fill   = $this->color($st['fill'], $st['fo'] * $st['op'], null);
        $stroke = $st['stroke'] ? $this->color($st['stroke'], $st['so'] * $st['op'], null) : null;
        $sw     = $this->ts(floatval($st['sw']));

        foreach ($subs as $pts) {
            if (count($pts) < 4) continue;
            // 填充：GD 对每个子路径独立填充（不处理 evenodd 挖洞，emoji 场景可接受）
            if ($fill !== null) {
                $closed = array_slice($pts, 0);
                $this->fillPoly($closed, $fill);
            }
            if ($stroke !== null && $sw > 0) {
                $this->strokePts($pts, $stroke, $sw, $isClosed);
            }
        }
    }

    /** 采样点 → 扁平坐标数组 */
    private function flat($points)
    {
        $out = [];
        foreach ($points as $p) { $out[] = $p[0]; $out[] = $p[1]; }
        return $out;
    }

    // ---------- path ----------

    private function drawPath($d, $st)
    {
        $subs = $this->parsePathD($d);
        $flat = [];
        foreach ($subs as $p) {
            $f = $this->flat($p);
            if (count($f) >= 4) $flat[] = $f;
        }
        $this->paint($flat, $st, true);
    }

    /**
     * 解析 path 的 d 属性 → 子路径点集（已换算到画布坐标）
     * @return array
     */
    private function parsePathD($d)
    {
        $subs = [];
        $cur = [];
        $x = 0.0; $y = 0.0;
        $startX = 0.0; $startY = 0.0;
        $lastC2x = null; $lastC2y = null;   // S 平滑用
        $lastQx = null;  $lastQy = null;    // T 平滑用
        $prevCmd = '';

        preg_match_all('/([MmZzLlHhVvCcSsQqTtAa])\s*([^MmZzLlHhVvCcSsQqTtAa]*)/', $d, $m, PREG_SET_ORDER);

        foreach ($m as $tok) {
            $cmd = $tok[1];
            $args = $this->nums($tok[2]);
            $i = 0; $n = count($args);
            $first = true;

            // 单参数命令可能需要重复执行（如 M 后跟多对坐标 → 后续按 L）
            while ($i < $n || $first) {
                $rel = ($cmd >= 'a' && $cmd <= 'z');
                $C = strtoupper($cmd);

                switch ($C) {
                    case 'M':
                        if ($n - $i < 2) break 2;
                        $x = $rel ? $x + $args[$i] : $args[$i];
                        $y = $rel ? $y + $args[$i+1] : $args[$i+1];
                        $i += 2;
                        if (count($cur) >= 2) { $subs[] = $cur; }
                        $cur = [[$this->tx($x), $this->ty($y)]];
                        $startX = $x; $startY = $y;
                        $cmd = $rel ? 'l' : 'L';   // 后续成对参数视为 lineto
                        break;

                    case 'L':
                        if ($n - $i < 2) break 2;
                        $x = $rel ? $x + $args[$i] : $args[$i];
                        $y = $rel ? $y + $args[$i+1] : $args[$i+1];
                        $i += 2;
                        $cur[] = [$this->tx($x), $this->ty($y)];
                        break;

                    case 'H':
                        if ($n - $i < 1) break 2;
                        $x = $rel ? $x + $args[$i] : $args[$i];
                        $i += 1;
                        $cur[] = [$this->tx($x), $this->ty($y)];
                        break;

                    case 'V':
                        if ($n - $i < 1) break 2;
                        $y = $rel ? $y + $args[$i] : $args[$i];
                        $i += 1;
                        $cur[] = [$this->tx($x), $this->ty($y)];
                        break;

                    case 'C':
                        if ($n - $i < 6) break 2;
                        $x1 = $rel ? $x + $args[$i]   : $args[$i];
                        $y1 = $rel ? $y + $args[$i+1] : $args[$i+1];
                        $x2 = $rel ? $x + $args[$i+2] : $args[$i+2];
                        $y2 = $rel ? $y + $args[$i+3] : $args[$i+3];
                        $px = $rel ? $x + $args[$i+4] : $args[$i+4];
                        $py = $rel ? $y + $args[$i+5] : $args[$i+5];
                        $i += 6;
                        $this->addCubic($cur, $x, $y, $x1, $y1, $x2, $y2, $px, $py);
                        $lastC2x = $x2; $lastC2y = $y2;
                        $x = $px; $y = $py;
                        break;

                    case 'S':
                        if ($n - $i < 4) break 2;
                        // 第一控制点 = 上一段第二控制点关于当前点的镜像
                        if ($lastC2x !== null && in_array(strtoupper($prevCmd), ['C', 'S'], true)) {
                            $x1 = 2 * $x - $lastC2x; $y1 = 2 * $y - $lastC2y;
                        } else {
                            $x1 = $x; $y1 = $y;
                        }
                        $x2 = $rel ? $x + $args[$i]   : $args[$i];
                        $y2 = $rel ? $y + $args[$i+1] : $args[$i+1];
                        $px = $rel ? $x + $args[$i+2] : $args[$i+2];
                        $py = $rel ? $y + $args[$i+3] : $args[$i+3];
                        $i += 4;
                        $this->addCubic($cur, $x, $y, $x1, $y1, $x2, $y2, $px, $py);
                        $lastC2x = $x2; $lastC2y = $y2;
                        $x = $px; $y = $py;
                        break;

                    case 'Q':
                        if ($n - $i < 4) break 2;
                        $qx = $rel ? $x + $args[$i]   : $args[$i];
                        $qy = $rel ? $y + $args[$i+1] : $args[$i+1];
                        $px = $rel ? $x + $args[$i+2] : $args[$i+2];
                        $py = $rel ? $y + $args[$i+3] : $args[$i+3];
                        $i += 4;
                        $this->addQuad($cur, $x, $y, $qx, $qy, $px, $py);
                        $lastQx = $qx; $lastQy = $qy;
                        $x = $px; $y = $py;
                        break;

                    case 'T':
                        if ($n - $i < 2) break 2;
                        if ($lastQx !== null && in_array(strtoupper($prevCmd), ['Q', 'T'], true)) {
                            $qx = 2 * $x - $lastQx; $qy = 2 * $y - $lastQy;
                        } else {
                            $qx = $x; $qy = $y;
                        }
                        $px = $rel ? $x + $args[$i]   : $args[$i];
                        $py = $rel ? $y + $args[$i+1] : $args[$i+1];
                        $i += 2;
                        $this->addQuad($cur, $x, $y, $qx, $qy, $px, $py);
                        $lastQx = $qx; $lastQy = $qy;
                        $x = $px; $y = $py;
                        break;

                    case 'A':
                        if ($n - $i < 7) break 2;
                        $rx = abs($args[$i]); $ry = abs($args[$i+1]);
                        $rot = deg2rad($args[$i+2]);
                        $large = $args[$i+3] >= 0.5;
                        $sweep = $args[$i+4] >= 0.5;
                        $px = $rel ? $x + $args[$i+5] : $args[$i+5];
                        $py = $rel ? $y + $args[$i+6] : $args[$i+6];
                        $i += 7;
                        $this->addArc($cur, $x, $y, $rx, $ry, $rot, $large, $sweep, $px, $py);
                        $x = $px; $y = $py;
                        break;

                    case 'Z':
                        if (count($cur) >= 2) {
                            // 闭合：回到子路径起点
                            $cur[] = [$this->tx($startX), $this->ty($startY)];
                            $subs[] = $cur;
                        }
                        $cur = [];
                        $x = $startX; $y = $startY;
                        $i = $n;   // Z 无参数
                        break;

                    default:
                        break 2;
                }
                $first = false;
                if ($i >= $n && !($C === 'Z')) break;
            }
            $prevCmd = $cmd;
        }

        if (count($cur) >= 2) { $subs[] = $cur; }
        return $subs;
    }

    private function nums($s)
    {
        preg_match_all('/[-+]?(?:\d*\.\d+|\d+\.?)(?:[eE][-+]?\d+)?/', $s, $m);
        $out = [];
        foreach ($m[0] as $v) { $out[] = floatval($v); }
        return $out;
    }

    private function addCubic(&$cur, $x0, $y0, $x1, $y1, $x2, $y2, $x3, $y3)
    {
        $steps = 24;
        for ($s = 1; $s <= $steps; $s++) {
            $t = $s / $steps; $u = 1 - $t;
            $px = $u*$u*$u*$x0 + 3*$u*$u*$t*$x1 + 3*$u*$t*$t*$x2 + $t*$t*$t*$x3;
            $py = $u*$u*$u*$y0 + 3*$u*$u*$t*$y1 + 3*$u*$t*$t*$y2 + $t*$t*$t*$y3;
            $cur[] = [$this->tx($px), $this->ty($py)];
        }
    }

    private function addQuad(&$cur, $x0, $y0, $qx, $qy, $x3, $y3)
    {
        $steps = 20;
        for ($s = 1; $s <= $steps; $s++) {
            $t = $s / $steps; $u = 1 - $t;
            $px = $u*$u*$x0 + 2*$u*$t*$qx + $t*$t*$x3;
            $py = $u*$u*$y0 + 2*$u*$t*$qy + $t*$t*$y3;
            $cur[] = [$this->tx($px), $this->ty($py)];
        }
    }

    /** 椭圆弧（SVG endpoint → center 参数化） */
    private function addArc(&$cur, $x0, $y0, $rx, $ry, $rot, $large, $sweep, $x1, $y1)
    {
        if ($rx <= 0 || $ry <= 0) { $cur[] = [$this->tx($x1), $this->ty($y1)]; return; }
        if ($x0 === $x1 && $y0 === $y1) return;

        $cosR = cos($rot); $sinR = sin($rot);
        $dx2 = ($x0 - $x1) / 2; $dy2 = ($y0 - $y1) / 2;
        $x1p =  $cosR * $dx2 + $sinR * $dy2;
        $y1p = -$sinR * $dx2 + $cosR * $dy2;

        // 半径修正（半径过小无法连接两点时按比例放大）
        $lam = ($x1p*$x1p) / ($rx*$rx) + ($y1p*$y1p) / ($ry*$ry);
        if ($lam > 1) { $s = sqrt($lam); $rx *= $s; $ry *= $s; }

        $num = $rx*$rx*$ry*$ry - $rx*$rx*$y1p*$y1p - $ry*$ry*$x1p*$x1p;
        $den = $rx*$rx*$y1p*$y1p + $ry*$ry*$x1p*$x1p;
        $coef = ($den == 0) ? 0 : sqrt(max(0, $num / $den));
        if ($large === $sweep) { $coef = -$coef; }

        $cxp =  $coef * ($rx * $y1p) / $ry;
        $cyp = -$coef * ($ry * $x1p) / $rx;

        $cx = $cosR * $cxp - $sinR * $cyp + ($x0 + $x1) / 2;
        $cy = $sinR * $cxp + $cosR * $cyp + ($y0 + $y1) / 2;

        $theta1 = atan2(($y1p - $cyp) / $ry, ($x1p - $cxp) / $rx);
        $theta0 = atan2((-$y1p - $cyp) / $ry, (-$x1p - $cxp) / $rx);

        $dTheta = $theta1 - $theta0;
        if ($sweep) {
            if ($dTheta < 0) { $dTheta += 2 * M_PI; }
        } else {
            if ($dTheta > 0) { $dTheta -= 2 * M_PI; }
        }

        $steps = max(12, (int)ceil(abs($dTheta) / (M_PI / 24)));
        for ($s = 1; $s <= $steps; $s++) {
            $th = $theta0 + $dTheta * ($s / $steps);
            $px = $cosR * $rx * cos($th) - $sinR * $ry * sin($th) + $cx;
            $py = $sinR * $rx * cos($th) + $cosR * $ry * sin($th) + $cy;
            $cur[] = [$this->tx($px), $this->ty($py)];
        }
    }

    // ---------- 基本图元 ----------

    private function drawRect($n, $st)
    {
        $x = floatval($n['x'] ?? 0); $y = floatval($n['y'] ?? 0);
        $w = floatval($n['width'] ?? 0); $h = floatval($n['height'] ?? 0);
        if ($w <= 0 || $h <= 0) return;
        $pts = [
            [$this->tx($x), $this->ty($y)],
            [$this->tx($x + $w), $this->ty($y)],
            [$this->tx($x + $w), $this->ty($y + $h)],
            [$this->tx($x), $this->ty($y + $h)],
        ];
        $this->paint([$this->flat($pts)], $st, true);
    }

    private function drawCircle($n, $st)
    {
        $cx = floatval($n['cx'] ?? 0); $cy = floatval($n['cy'] ?? 0); $r = floatval($n['r'] ?? 0);
        if ($r <= 0) return;
        $this->drawEllipsePts($cx, $cy, $r, $r, $st);
    }

    private function drawEllipse($n, $st)
    {
        $cx = floatval($n['cx'] ?? 0); $cy = floatval($n['cy'] ?? 0);
        $rx = floatval($n['rx'] ?? 0); $ry = floatval($n['ry'] ?? 0);
        if ($rx <= 0 || $ry <= 0) return;
        $this->drawEllipsePts($cx, $cy, $rx, $ry, $st);
    }

    private function drawEllipsePts($cx, $cy, $rx, $ry, $st)
    {
        $steps = 72;
        $pts = [];
        for ($i = 0; $i < $steps; $i++) {
            $th = 2 * M_PI * $i / $steps;
            $pts[] = [
                $this->tx($cx + $rx * cos($th)),
                $this->ty($cy + $ry * sin($th)),
            ];
        }
        $this->paint([$this->flat($pts)], $st, true);
    }

    private function drawLine($n, $st)
    {
        $pts = [
            [$this->tx(floatval($n['x1'] ?? 0)), $this->ty(floatval($n['y1'] ?? 0))],
            [$this->tx(floatval($n['x2'] ?? 0)), $this->ty(floatval($n['y2'] ?? 0))],
        ];
        $st2 = $st;
        if (!isset($n['fill'])) { $st2['fill'] = 'none'; }   // line 不应被填充
        $this->paint([$this->flat($pts)], $st2, false);
    }

    private function drawPoly($n, $st, $closed)
    {
        $vals = $this->nums((string)($n['points'] ?? ''));
        if (count($vals) < 4) return;
        $pts = [];
        for ($i = 0; $i + 1 < count($vals); $i += 2) {
            $pts[] = [$this->tx($vals[$i]), $this->ty($vals[$i + 1])];
        }
        if ($closed) { $pts[] = $pts[0]; }
        // polyline 是开放折线，SVG 默认不填充；polygon 才填充
        if (!$closed && !isset($n['fill'])) { $st['fill'] = 'none'; }
        $this->paint([$this->flat($pts)], $st, $closed);
    }
}
