<?php
require __DIR__ . '/../bct/includes/expiry-display.php';

$cases = [
    ['+29 days',     false, '29天期  '],
    ['+5 days',      false, '5天期   '],
    ['+2 days',      false, '2天期   '],
    ['+10 hours',    false, '10小时期 '],
    ['+30 minutes',  false, '30分钟期'],
    [null,           false, '长期单   '],
    ['-1 day',       false, '已过期   '],
    ['-1 day',       true,  '交易顺延 '],
];

foreach ($cases as $c) {
    $exp = $c[0] === null ? null : date('Y-m-d H:i:s', strtotime($c[0]));
    $r = formatRemainingValidity($exp, $c[1]);
    echo $c[2], ' => ', $r[0], ' / ', $r[1], ($r[2] ? ' / ' . $r[2] : ''), PHP_EOL;
}
