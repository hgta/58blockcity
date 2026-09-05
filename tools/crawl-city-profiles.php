<?php
/**
 * 城市真实资料离线采集器（一次/低频运行，不做随请求在线爬取）
 *
 * 用法:
 *   php tools/crawl-city-profiles.php                 # 全量采集
 *   php tools/crawl-city-profiles.php beijing         # 单城
 *   php tools/crawl-city-profiles.php --force         # 忽略断点，强制重采
 *   php tools/crawl-city-profiles.php --limit=5       # 只采前 5 城（试跑）
 *   php tools/crawl-city-profiles.php --no-wiki       # 关闭中文维基简介
 *
 * 主源 Wikidata SPARQL（免费无 key）：
 *   面积 P2046 / 人口 P1082 / 名义GDP P2132（按 statement 的 P585 时间戳选最新）
 * 备选（配置 CRAWL_WIKI_SUMMARY=1，默认开）中文维基 REST 摘要 → intro 首段。
 *
 * 产出：data/city-profiles/{pinyin}.json
 *   断点续跑：文件已存在即跳过（--force 覆盖）；失败重试 1 次后记入失败清单继续。
 * 入库请执行 tools/sync-city-profiles.php
 */

require_once __DIR__ . '/../config/database.php';

// ---- DB 配置兜底（config/database.php 被运维替换后无此分支）----
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

// ================= 配置（可按需调） =================
define('SPARQL_ENDPOINT', 'https://query.wikidata.org/sparql');
define('WIKI_SUMMARY_API', 'https://zh.wikipedia.org/api/rest_v1/page/summary/%s');
define('CRAWL_WIKI_SUMMARY', true);          // 采集中文维基摘要补 intro 首段
define('OUT_DIR', __DIR__ . '/../data/city-profiles');
define('HTTP_TIMEOUT', 20);                   // 秒
define('HTTP_RETRY', 1);                      // 失败重试次数
define('REQUEST_DELAY', 1.0);                 // 每城基础间隔秒
define('NAME_CHUNK', 30);                     // SPARQL 单批匹配城市数
define('QID_CHUNK', 20);                      // SPARQL 单批属性查询 Q 数

// ================= 命令行参数 =================
$args = [];
$onlyPinyin = '';
foreach (array_slice($argv, 1) as $a) {
    if (preg_match('/^--?force$/i', $a)) {
        $args['force'] = true;
    } elseif (preg_match('/^--?no[-_]?wiki$/i', $a)) {
        $args['wiki'] = false;
    } elseif (preg_match('/^--?delay=([\d.]+)$/i', $a, $m)) {
        $args['delay'] = (float)$m[1];
    } elseif (preg_match('/^--?timeout=(\d+)$/i', $a, $m)) {
        $args['timeout'] = (int)$m[1];
    } elseif (preg_match('/^--?limit=(\d+)$/i', $a, $m)) {
        $args['limit'] = (int)$m[1];
    } elseif (preg_match('/^[a-z]+$/', $a)) {
        $onlyPinyin = $a;
    } else {
        fwrite(STDERR, "未知参数: {$a}\n用法: php crawl-city-profiles.php [pinyin] [--force] [--no-wiki] [--limit=N] [--delay=S]\n");
        exit(2);
    }
}
$force  = !empty($args['force']);
$useWiki = !($args['wiki'] === false);
$delay  = $args['delay'] ?? REQUEST_DELAY;
$timeout = $args['timeout'] ?? HTTP_TIMEOUT;
$limit  = $args['limit'] ?? 0;

if (!is_dir(OUT_DIR)) {
    @mkdir(OUT_DIR, 0775, true);
}
if (!is_dir(OUT_DIR) || !is_writable(OUT_DIR)) {
    fwrite(STDERR, '输出目录不可写: ' . OUT_DIR . "\n");
    exit(1);
}

// ================= 小工具 =================

function http_get($url, $timeout) {
    $ua = 'Mozilla/5.0 (compatible; BlockCityProfileCrawler/1.0; +https://www.58.tl)';
    $ctx = stream_context_create(['http' => [
        'method' => 'GET',
        'timeout' => $timeout,
        'header' => "User-Agent: {$ua}\r\nAccept: application/sparql-results+json, application/json\r\n",
    ]]);
    $t0 = microtime(true);
    $body = @file_get_contents($url, false, $ctx);
    return ['ok' => ($body !== false), 'body' => $body, 'ms' => (int)((microtime(true) - $t0) * 1000)];
}

/** SPARQL 查询 → assoc 数组；失败返回 null */
function sparql($query, $timeout) {
    $url = SPARQL_ENDPOINT . '?query=' . rawurlencode($query) . '&format=json';
    for ($i = 0; $i <= HTTP_RETRY; $i++) {
        $res = http_get($url, $timeout);
        if ($res['ok']) {
            $j = json_decode($res['body'], true);
            if (isset($j['results']['bindings']) && is_array($j['results']['bindings'])) {
                $rows = [];
                foreach ($j['results']['bindings'] as $b) {
                    $row = [];
                    foreach ($b as $k => $v) {
                        $row[$k] = $v['value'] ?? null;
                    }
                    $rows[] = $row;
                }
                return $rows;
            }
        }
        if ($i < HTTP_RETRY) {
            usleep(500000 * ($i + 1)); // 0.5s/1s 退避
        }
    }
    return null;
}

/** SPARQL literal 数值解析（兼容 xsd:int/decimal/double 与 plain） */
function numval($v) {
    if ($v === null || $v === '') {
        return null;
    }
    $v = preg_replace('/\s+/', '', (string)$v);
    if (!is_numeric($v)) {
        return null;
    }
    return (float)$v;
}

/** 时间戳取年份（ISO，可能带 T）；无年份返回 null */
function yearOf($dt) {
    if (!$dt) {
        return null;
    }
    return preg_match('/^(\d{4})/', (string)$dt, $m) ? (int)$m[1] : null;
}

/** 数字千分位；万亿→亿/万亿 转换（展示口径） */
function fmtBig($v, $dec = 0) {
    $v = (float)$v;
    if ($v >= 1e12) {
        return rtrim(rtrim(number_format($v / 1e12, $dec + 1), '0'), '.') . '万亿';
    }
    if ($v >= 1e8) {
        return rtrim(rtrim(number_format($v / 1e8, $dec), '0'), '.') . '亿';
    }
    if ($v >= 1e4) {
        return rtrim(rtrim(number_format($v / 1e4, $dec), '0'), '.') . '万';
    }
    return number_format($v);
}

/** 去掉「市/省/自治区」等后缀的标准短名 */
function shortName($name) {
    $s = trim((string)$name);
    $suffixes = ['特别行政区', '自治区', '自治州', '盟', '省', '市'];
    foreach ($suffixes as $suf) {
        if (mb_strlen($s) > mb_strlen($suf) && mb_substr($s, -mb_strlen($suf)) === $suf) {
            return mb_substr($s, 0, mb_strlen($s) - mb_strlen($suf));
        }
    }
    return $s;
}

/** 候选维基词条标题 / Wikidata label 列表（含 短名、原名、原名+市 形态） */
function candidateNames($name) {
    $s = trim((string)$name);
    $out = [$s];
    $short = shortName($s);
    if ($short !== '' && $short !== $s) {
        $out[] = $short;
    }
    if (mb_substr($s, -1) !== '市') {
        $out[] = $s . '市';
    }
    return array_values(array_unique($out));
}

// ================= 主流程 =================

$stmt = $pdo->query("SELECT id, name, pinyin FROM cities WHERE status = 'active' ORDER BY rank ASC, id ASC");
$cities = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (!$cities) {
    fwrite(STDERR, "cities 表无数据。\n");
    exit(1);
}
if ($onlyPinyin !== '') {
    $cities = array_values(array_filter($cities, function ($c) use ($onlyPinyin) {
        return $c['pinyin'] === $onlyPinyin;
    }));
    if (!$cities) {
        fwrite(STDERR, "未找到城市: {$onlyPinyin}\n");
        exit(1);
    }
}
if ($limit > 0) {
    $cities = array_slice($cities, 0, $limit);
}
echo sprintf("[采集] 目标 %d 城 | force=%s | wiki=%s | delay=%ss\n", count($cities), $force ? 'Y' : 'N', $useWiki ? 'Y' : 'N', $delay);

// 待采列表（断点续跑：已有文件跳过）
$todo = [];
foreach ($cities as $c) {
    $f = OUT_DIR . '/' . $c['pinyin'] . '.json';
    if (!$force && is_file($f)) {
        echo "  跳过(已存在) {$c['pinyin']}\n";
        continue;
    }
    $todo[] = $c;
}

// ---- Phase A：按 label 分块匹配 Wikidata 城市实体（Q515） ----
$label2q = [];   // label → qid（每个 label 只保留一个城市实体）
for ($i = 0; $i < count($todo); $i += NAME_CHUNK) {
    $chunk = array_slice($todo, $i, NAME_CHUNK);
    $names = [];
    foreach ($chunk as $c) {
        foreach (candidateNames($c['name']) as $n) {
            $names[] = $n;
        }
    }
    $names = array_values(array_unique($names));
    $values = implode(' ', array_map(function ($n) {
        return '"' . addcslashes($n, "\\\"") . '"';
    }, $names));
    // 无法用 VALUES ?name{...} 时改用 FILTER(STR(?l) IN (...))
    $q = "SELECT DISTINCT ?item ?itemLabel WHERE {
      ?item wdt:P31/wdt:P279* wd:Q515 .
      ?item rdfs:label ?l . FILTER(lang(?l) = 'zh')
      BIND(STR(?l) AS ?itemLabel)
      FILTER(?itemLabel IN ($values))
    }";
    $rows = sparql($q, $timeout);
    if ($rows === null) {
        echo "  [warn] label 匹配批次失败（跳过 " . count($chunk) . " 城），重试留给下一轮手动运行\n";
        continue;
    }
    foreach ($rows as $r) {
        $qid = preg_replace('/^.*\/(Q\d+)$/', '$1', $r['item']);
        if (!isset($label2q[$r['itemLabel']])) {
            $label2q[$r['itemLabel']] = $qid;
        }
    }
    usleep((int)($delay * 1000000));
}
echo "[采集] label 匹配完成，命中 " . count($label2q) . " 个实体\n";

// ---- Phase B：分块取 P2046/P1082/P2132（带时间戳，PHP 端选最新） ----
// key: qid → [ 'area'=>[[v,time]...], 'pop'=>..., 'gdp'=>... ]
$facts = [];
$propQueries = [
    'area' => ['P2046', 'ps:P2046'],
    'pop'  => ['P1082', 'ps:P1082'],
    'gdp'  => ['P2132', 'ps:P2132'],
];
$qids = array_values(array_unique($label2q));
foreach (array_chunk($qids, QID_CHUNK) as $chunk) {
    $values = implode(' ', array_map(function ($q) {
        return 'wd:' . $q;
    }, $chunk));
    foreach ($propQueries as $key => $def) {
        list($p, $ps) = $def;
        $q = "SELECT ?item ?value ?time WHERE {
          VALUES ?item { $values }
          ?item p:$p ?s . ?s $ps ?value .
          OPTIONAL { ?s pq:P585 ?time }
        }";
        $rows = sparql($q, $timeout);
        if ($rows === null) {
            echo "  [warn] 属性 $p 批次查询失败\n";
            continue;
        }
        foreach ($rows as $r) {
            $qid = preg_replace('/^.*\/(Q\d+)$/', '', $r['item']);
            $v = numval($r['value']);
            if ($v === null) {
                continue;
            }
            $facts[$qid][$key][] = ['v' => $v, 'y' => yearOf($r['time'] ?? null)];
        }
        usleep((int)($delay * 1000000));
    }
}

// ---- 汇总各城 JSON ----
$ok = 0; $empty = 0; $fail = [];
foreach ($todo as $c) {
    $pinyin = $c['pinyin'];
    $qid = null;
    foreach (candidateNames($c['name']) as $n) {
        if (isset($label2q[$n])) {
            $qid = $label2q[$n];
            break;
        }
    }
    if (!$qid) {
        $empty++;
        echo "  [empty] {$pinyin}: 未匹配到 Wikidata 城市实体\n";
        file_put_contents(OUT_DIR . '/' . $pinyin . '.json', json_encode([
            'pinyin' => $pinyin, 'city_id' => (int)$c['id'], 'name' => $c['name'],
            'status' => 0, 'source' => 'wikidata:unmatched',
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        continue;
    }

    // 每属性选最新时间戳；无时间戳候选兜底
    $pick = function ($list) {
        if (!$list) {
            return null;
        }
        $best = null;
        foreach ($list as $it) {
            if ($best === null || ($it['y'] !== null && ($best['y'] === null || $it['y'] > $best['y']))) {
                $best = $it;
            }
        }
        return $best;
    };
    $f = $facts[$qid] ?? [];
    $area = $pick($f['area'] ?? []);
    $pop  = $pick($f['pop'] ?? []);
    $gdp  = $pick($f['gdp'] ?? []);

    $profile = [
        'pinyin'    => $pinyin,
        'city_id'   => (int)$c['id'],
        'name'      => $c['name'],
        'admin_area' => $area ? '约 ' . number_format(round($area['v'])) . ' 平方公里' : '',
        'population' => $pop ? (($pop['y'] ? $pop['y'] . '年 ' : '') . fmtBig($pop['v'], 0) . ' 人') : '',
        'gdp'       => $gdp ? (($gdp['y'] ? $gdp['y'] . '年 ' : '') . '约 ' . fmtBig($gdp['v'], 0) . ' 元') : '',
        'gdp_per_capita' => '',
        'urbanization_rate' => '',
        'universities' => '',
        'feature_tags' => '',
        'slogan'    => '',
        'position'  => '',
        'landmarks' => '',
        'food'      => '',
        'potential' => '',
        'districts' => '',
        'intro'     => '',
        'data_year' => max(array_filter([$area['y'] ?? null, $pop['y'] ?? null, $gdp['y'] ?? null])),
        'status'    => ($area || $pop || $gdp) ? 1 : 0,
        'source'    => [
            'area' => $area ? "Wikidata P2046(km², {$qid})" : '',
            'population' => $pop ? "Wikidata P1082({$qid})" : '',
            'gdp' => $gdp ? "Wikidata P2132({$qid})" : '',
        ],
    ];

    // 备选源：中文维基摘要 → intro 首段（请求超时/失败仅警告不中断）
    if ($useWiki) {
        $title = null;
        foreach (candidateNames($c['name']) as $n) {
            $res = http_get(sprintf(WIKI_SUMMARY_API, rawurlencode($n)), $timeout);
            if ($res['ok']) {
                $j = json_decode($res['body'], true);
                if (isset($j['type']) && $j['type'] === 'standard' && !empty($j['extract'])) {
                    $title = $j['title'] ?? $n;
                    $extract = trim(preg_replace('/\s+/u', ' ', (string)$j['extract']));
                    if ($extract !== '') {
                        $profile['intro'] = json_encode([
                            ['h' => '城市概览', 'p' => mb_substr($extract, 0, 500, 'UTF-8')],
                        ], JSON_UNESCAPED_UNICODE);
                    }
                    break;
                }
            }
            usleep((int)($delay * 1000000));
        }
        $profile['source']['intro'] = $title ? "中文维基百科《{$title}》摘要" : '';
    }

    $profile['status'] = (($profile['admin_area'] || $profile['population'] || $profile['gdp']) ? 1 : 0);
    file_put_contents(
        OUT_DIR . '/' . $pinyin . '.json',
        json_encode($profile, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
    );
    if ($profile['status']) {
        $ok++;
        echo "  [ok] {$pinyin}(" . ($qid ?: '-') . "): 面积={$profile['admin_area']} 人口={$profile['population']} GDP={$profile['gdp']}\n";
    } else {
        $empty++;
        echo "  [empty] {$pinyin}: 命中实体但无有效数据\n";
    }
    usleep((int)($delay * 1000000));
}

// ---- 结尾统计 ----
echo "\n=== 完成 ===\n";
echo "  成功(有数值): {$ok}\n";
echo "  空结果: {$empty}\n";
echo "  失败(记录于 data/city-profiles/*.json source 标注): " . count($fail) . "\n";
if ($fail) {
    echo "  失败清单: " . implode(', ', $fail) . "\n";
}
echo "  输出目录: " . OUT_DIR . "\n";
echo "  下一步: php tools/sync-city-profiles.php\n";
