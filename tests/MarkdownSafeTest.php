<?php
/**
 * MarkdownSafe 单元用例（手工逐条跑通）
 * 用法：php tests/MarkdownSafeTest.php
 */
require_once __DIR__ . '/../classes/MarkdownSafe.php';

$cases = [
    ['name' => 'plain text',         'in' => "# 标题\n段落内容",                        'expect_contains' => '<h1>标题</h1>'],
    ['name' => 'safe link w/ target', 'in' => '[GH](https://github.com)',             'expect_contains' => '<a href="https://github.com"'],
    ['name' => 'evil scheme',        'in' => '[点我](javascript:alert(1))',           'expect_not_contains' => 'javascript:'],
    ['name' => 'script tag',         'in' => '<script>alert(1)</script>',             'expect_not_contains' => '<script>'],
    ['name' => 'evil img host',      'in' => '![x](https://evil.com/x.png)',          'expect_not_contains' => 'evil.com'],
    ['name' => 'onerror (safemode escapes)', 'in' => '<img src="x" onerror="alert(1)">', 'expect_contains' => '&lt;img', 'expect_not_contains' => '<img src='],
    ['name' => 'external link default target', 'in' => '[GH](https://github.com)', 'expect_contains' => 'target="_blank"', 'expect_also_contains' => 'rel="noopener noreferrer"'],
    ['name' => 'absolute 58.tl link w/ target', 'in' => '[站内](https://bid.58.tl/foo)', 'expect_contains' => 'target="_blank"', 'expect_not_contains' => '<a href="https://bid.58.tl/foo">'],
    ['name' => 'data url',           'in' => '![x](data:image/png;base64,xxx)',       'expect_not_contains' => 'data:'],
    ['name' => 'empty',              'in' => '',                                      'expect_eq' => ''],
    ['name' => 'null',               'in' => null,                                    'expect_eq' => ''],
    ['name' => 'relative anchor',    'in' => '[跳](#section)',                        'expect_contains' => 'href="#section"'],
    ['name' => 'relative path',      'in' => '[链接](/foo/bar)',                      'expect_contains' => 'href="/foo/bar"'],
    ['name' => 'vbscript',           'in' => '[点](vbscript:msgbox(1))',              'expect_not_contains' => 'vbscript:'],
    ['name' => 'safe 58.tl img',     'in' => '![x](https://bid.58.tl/avatar/1.png)', 'expect_contains' => 'src="https://bid.58.tl/avatar/1.png"'],
    ['name' => 'safe subdomain img', 'in' => '![x](https://api.github.com/x.png)',   'expect_contains' => 'src="https://api.github.com/x.png"'],
    ['name' => 'iframe tag',         'in' => '<iframe src="https://evil.com"></iframe>', 'expect_not_contains' => '<iframe'],
];

$pass = 0; $fail = 0;
foreach ($cases as $c) {
    $out = MarkdownSafe::render($c['in']);
    $ok = true;
    if (isset($c['expect_eq']) && $out !== $c['expect_eq']) $ok = false;
    if (isset($c['expect_contains']) && strpos($out, $c['expect_contains']) === false) $ok = false;
    if (isset($c['expect_not_contains']) && strpos($out, $c['expect_not_contains']) !== false) $ok = false;
    if (isset($c['expect_also_contains']) && strpos($out, $c['expect_also_contains']) === false) $ok = false;
    if ($ok) {
        $pass++;
        echo "  PASS  " . $c['name'] . PHP_EOL;
    } else {
        $fail++;
        echo "  FAIL  " . $c['name'] . PHP_EOL;
        echo "    in : " . substr(var_export($c['in'], true), 0, 80) . PHP_EOL;
        echo "    out: " . $out . PHP_EOL;
    }
}

echo PHP_EOL . "=== RESULT: PASS=$pass  FAIL=$fail ===" . PHP_EOL;
exit($fail === 0 ? 0 : 1);