<?php
/**
 * 访客城市定位（服务端 IP 定位）
 *
 * GET /api/city_geo.php
 *   → {"ok":true,"city":"深圳市","citycode":"0755","adcode":"440300","from":"amap|cache|session"}
 *   → {"ok":false,"reason":"no_key|rate_limited|lookup_failed","city":"","citycode":""}
 *
 * 设计要点（change: city-locate-server-side-ip）
 *  1. 三级复用：会话缓存 → IP 文件缓存(24h) → 高德 v3/ip；
 *     城市→电话区号单独缓存 30 天（长期稳定映射）
 *  2. 配额保护：改造前 2 次外部调用/PV，改造后约 1 次/IP/天
 *  3. 严格校验：高德 status !== '1' 或 city 为空一律视为失败
 *     （配额超限时高德仍返回 200、status=0，旧前端会当成成功直接崩进降级）
 *  4. 失败绝不返回"需要定位"信号：前端显示「未知城市」，不再调用
 *     navigator.geolocation，从根上消除浏览器定位授权弹窗
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');

// ---------- CORS：仅允许 58.tl 各子站跨域调用 ----------
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '') {
    $host = parse_url($origin, PHP_URL_HOST);
    if (is_string($host) && preg_match('#^(?:[a-z0-9-]+\.)*58\.tl$#i', $host)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Access-Control-Allow-Credentials: true');
        header('Vary: Origin');
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'reason' => 'method_not_allowed']);
    exit;
}

// ---------- 常量 ----------
const GEO_IP_TTL      = 86400;      // IP 定位结果缓存 24h
const GEO_CODE_TTL    = 2592000;    // 城市→电话区号缓存 30 天
const GEO_HTTP_TIMEOUT = 3;         // 单次外部请求超时（秒）
const GEO_RATE_LIMIT  = 30;         // 同 IP 每分钟最多外部查询次数

$cacheDir = dirname(__DIR__) . '/city/cache/geo';
$cacheWritable = (is_dir($cacheDir) || @mkdir($cacheDir, 0775, true)) && is_writable($cacheDir);

// 诊断信息：仅当请求带 ?diag=1 时随 JSON 返回，便于线上直接定位失败环节
// （服务器 PHP 日志常不可见/未开启，靠肉眼排查太慢）
$diag = ['cache_dir' => $cacheDir, 'cache_writable' => $cacheWritable];

/**
 * 取访客 IP。
 * 优先公网 REMOTE_ADDR；反代/CDN 后面（REMOTE_ADDR 为内网）时从
 * X-Real-IP / X-Forwarded-For 中取真实 IP。不盲信转发头，防伪造。
 */
function geo_is_public_ip($ip) {
    return (bool)filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
}

function geo_client_ip() {
    global $diag;
    $remote = $_SERVER['REMOTE_ADDR'] ?? '';
    $diag['remote_addr'] = $remote;
    $diag['x_real_ip']   = $_SERVER['HTTP_X_REAL_IP'] ?? null;
    $diag['x_forwarded'] = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? null;
    $diag['cf_ip']       = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? null;
    if ($remote !== '' && geo_is_public_ip($remote)) {
        $diag['ip_source'] = 'REMOTE_ADDR';
        return $remote;
    }
    foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP'] as $key) {
        if (!empty($_SERVER[$key])) {
            $ip = trim(explode(',', $_SERVER[$key])[0]);
            if ($ip !== '' && geo_is_public_ip($ip)) {
                $diag['ip_source'] = $key;
                return $ip;
            }
        }
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        // 反代会把真实 IP 追加在链尾，从后往前找第一个公网 IP
        $parts = array_map('trim', explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']));
        for ($i = count($parts) - 1; $i >= 0; $i--) {
            if ($parts[$i] !== '' && geo_is_public_ip($parts[$i])) {
                $diag['ip_source'] = 'X-Forwarded-For';
                return $parts[$i];
            }
        }
    }
    $diag['ip_source'] = 'none(非公网)';
    return $remote !== '' ? $remote : '0.0.0.0';
}

function geo_cache_path($dir, $key) {
    return $dir . '/' . $key . '.json';
}

function geo_cache_read($dir, $key, $ttl) {
    $path = geo_cache_path($dir, $key);
    if (!is_file($path)) return null;
    if (time() - filemtime($path) > $ttl) return null;
    $raw = @file_get_contents($path);
    if ($raw === false) return null;
    $data = json_decode($raw, true);
    return is_array($data) ? $data : null;
}

function geo_cache_write($dir, $key, array $data) {
    if ($dir === null) return;
    @file_put_contents(geo_cache_path($dir, $key), json_encode($data, JSON_UNESCAPED_UNICODE), LOCK_EX);
}

function geo_http_json($url) {
    global $diag;
    // 全项目其它外呼均走 cURL（部分主机 allow_url_fopen 被禁，
    // file_get_contents 请求 https 会直接失败），这里保持一致
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => GEO_HTTP_TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_USERAGENT      => '58.tl city-geo',
            // 与项目其它外呼（AiProvider/HermesClient 等）保持一致：
            // 服务器未配置 CA 证书包，验证会直接失败（curl #60）
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
        ]);
        $raw = curl_exec($ch);
        $diag['curl_errno'] = curl_errno($ch);
        $diag['curl_error'] = curl_error($ch);
        $diag['http_code']  = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($raw === false) {
            error_log('[city_geo] curl failed: #' . curl_errno($ch) . ' ' . curl_error($ch));
            curl_close($ch);
            return null;
        }
        curl_close($ch);
    } else {
        $ctx = stream_context_create([
            'http' => [
                'timeout' => GEO_HTTP_TIMEOUT,
                'ignore_errors' => true,
                'header' => "User-Agent: 58.tl city-geo\r\n",
            ],
        ]);
        $raw = @file_get_contents($url, false, $ctx);
        if ($raw === false || $raw === '') {
            error_log('[city_geo] file_get_contents failed (allow_url_fopen off?): ' . $url);
            return null;
        }
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : null;
}

/** 同 IP 频控：返回 true 表示允许查询外部接口 */
function geo_rate_allow($dir, $ipHash) {
    if ($dir === null) return true; // 无缓存目录时不做频控（会话缓存仍在）
    $path = geo_cache_path($dir, 'rl_' . $ipHash);
    $minute = (int)floor(time() / 60);
    $count = 0;
    if (is_file($path)) {
        $raw = json_decode((string)@file_get_contents($path), true);
        if (is_array($raw) && (int)($raw['minute'] ?? 0) === $minute) {
            $count = (int)($raw['count'] ?? 0);
        }
    }
    $count++;
    @file_put_contents($path, json_encode(['minute' => $minute, 'count' => $count]), LOCK_EX);
    return $count <= GEO_RATE_LIMIT;
}

function geo_respond(array $payload) {
    global $diag;
    // ?diag=1 时附带诊断信息（服务器日志常不可见，线上可直接看 JSON 定位）
    if (isset($_GET['diag']) && $_GET['diag'] !== '0' && $diag) {
        $payload['diag'] = $diag;
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

// ---------- 1. 会话缓存 ----------
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '.58.tl',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'None',
    ]);
    @session_start();
}
// ?nocache=1 跳过会话/文件缓存，强制重新查询（排查时用）
$bypassCache = isset($_GET['nocache']) && $_GET['nocache'] !== '0';
if ($bypassCache) {
    unset($_SESSION['city_geo']);
}

if (!$bypassCache && !empty($_SESSION['city_geo']) && is_array($_SESSION['city_geo'])) {
    $s = $_SESSION['city_geo'];
    if (!empty($s['city']) && time() - (int)($s['ts'] ?? 0) < GEO_IP_TTL) {
        geo_respond(['ok' => true, 'city' => $s['city'], 'citycode' => $s['citycode'] ?? '',
                     'adcode' => $s['adcode'] ?? '', 'from' => 'session']);
    }
}

$ip = geo_client_ip();
$ipHash = md5($ip);
$fsCache = $cacheWritable ? $cacheDir : null;

// ---------- 2. IP 文件缓存 ----------
$cached = (!$bypassCache && $fsCache !== null) ? geo_cache_read($fsCache, $ipHash, GEO_IP_TTL) : null;
if ($cached !== null && !empty($cached['city'])) {
    $_SESSION['city_geo'] = ['city' => $cached['city'], 'citycode' => $cached['citycode'] ?? '',
                             'adcode' => $cached['adcode'] ?? '', 'ts' => time()];
    geo_respond(['ok' => true, 'city' => $cached['city'], 'citycode' => $cached['citycode'] ?? '',
                 'adcode' => $cached['adcode'] ?? '', 'from' => 'cache']);
}

// ---------- 3. 配置检查 ----------
// 该 key 历史上明文写在前端 JS 中（可被任意盗用、日配额易被打满），
// 仅作为未配置 config/amap.php 时的临时回退，保证功能不中断。
// 请尽快在高德控制台新建 key 并写入 config/amap.php（务必绑定域名白名单）。
const GEO_AMAP_FALLBACK_KEY = 'c7ebabae441606f172364f6d644a9ce4';

$configFile = dirname(__DIR__) . '/config/amap.php';
$key = '';
if (is_file($configFile)) {
    require_once $configFile;
    if (defined('AMAP_WEB_KEY')) {
        $key = AMAP_WEB_KEY;
    }
}
if ($key === '') {
    error_log('[city_geo] config/amap.php 未配置，正在回退旧 key（曾明文暴露于前端），建议尽快配置新 key');
    $key = GEO_AMAP_FALLBACK_KEY;
    $diag['key_source'] = 'fallback(未配置 config/amap.php)';
} else {
    $diag['key_source'] = 'config/amap.php';
}

// ---------- 4. 频控 ----------
if (!geo_rate_allow($fsCache, $ipHash)) {
    geo_respond(['ok' => false, 'city' => '', 'citycode' => '', 'reason' => 'rate_limited']);
}

// ---------- 5. 高德 IP 定位 ----------
$diag['resolved_ip'] = $ip;
$url = 'https://restapi.amap.com/v3/ip?ip=' . rawurlencode($ip) . '&key=' . rawurlencode($key);
$ipData = geo_http_json($url);
$diag['amap_url'] = preg_replace('/key=[^&]+/', 'key=***', $url);
if (is_array($ipData)) {
    $diag['amap_status']   = $ipData['status'] ?? null;
    $diag['amap_info']     = $ipData['info'] ?? null;
    $diag['amap_infocode'] = $ipData['infocode'] ?? null;
    $diag['amap_province'] = $ipData['province'] ?? null;
    $diag['amap_city']     = $ipData['city'] ?? null;
}
if (!is_array($ipData) || ($ipData['status'] ?? '') !== '1') {
    error_log('[city_geo] v3/ip bad response: ip=' . $ip
        . ' status=' . var_export($ipData['status'] ?? null, true)
        . ' info=' . ($ipData['info'] ?? '-')
        . ' infocode=' . ($ipData['infocode'] ?? '-'));
    geo_respond(['ok' => false, 'city' => '', 'citycode' => '', 'reason' => 'lookup_failed']);
}
$city = $ipData['city'] ?? '';
$adcode = $ipData['adcode'] ?? '';
// 定位失败（局域网/境外/配额）时高德可能返回空字符串或空数组
if (!is_string($city) || trim($city) === '') {
    error_log('[city_geo] v3/ip no city: ip=' . $ip . ' province=' . var_export($ipData['province'] ?? null, true));
    geo_respond(['ok' => false, 'city' => '', 'citycode' => '', 'reason' => 'lookup_failed']);
}
$city = trim($city);
$adcode = is_string($adcode) ? $adcode : '';

// ---------- 6. 电话区号（城市级缓存，长 TTL） ----------
$codeKey = 'code_' . md5($city);
$citycode = '';
$codeCached = $fsCache !== null ? geo_cache_read($fsCache, $codeKey, GEO_CODE_TTL) : null;
if ($codeCached !== null) {
    $citycode = (string)($codeCached['citycode'] ?? '');
} else {
    $geoData = geo_http_json('https://restapi.amap.com/v3/geocode/geo?address=' . rawurlencode($city)
        . '&city=' . rawurlencode($city) . '&key=' . rawurlencode($key));
    if (is_array($geoData) && ($geoData['status'] ?? '') === '1'
        && !empty($geoData['geocodes'][0]['citycode'])) {
        $citycode = (string)$geoData['geocodes'][0]['citycode'];
    }
    if ($fsCache !== null) {
        geo_cache_write($fsCache, $codeKey, ['city' => $city, 'citycode' => $citycode, 'ts' => time()]);
    }
}

// ---------- 7. 回写缓存并输出 ----------
$result = ['city' => $city, 'citycode' => $citycode, 'adcode' => $adcode, 'ts' => time()];
$_SESSION['city_geo'] = $result;
if ($fsCache !== null) {
    geo_cache_write($fsCache, $ipHash, $result);
}
geo_respond(['ok' => true, 'city' => $city, 'citycode' => $citycode, 'adcode' => $adcode, 'from' => 'amap']);
