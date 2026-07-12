<?php
/**
 * 统一区域配置
 * 
 * 定义了 A-H 和 Z 共 9 个区域的区块号范围、列号范围和基准价格。
 * 是整个项目区域配置的唯一来源。
 * 
 * 使用方式：
 *   $zoneConfig = require __DIR__ . '/zones.php';
 *   $zoneConfig['A']['block_start']; // 101
 */

return [
    'A' => [
        'block_start' => 101,
        'block_end'   => 1299,
        'col_start'   => 1,
        'col_end'     => 12,
        'base_price'  => 1286,
    ],
    'B' => [
        'block_start' => 1301,
        'block_end'   => 2499,
        'col_start'   => 13,
        'col_end'     => 24,
        'base_price'  => 1690,
    ],
    'C' => [
        'block_start' => 2501,
        'block_end'   => 3699,
        'col_start'   => 25,
        'col_end'     => 36,
        'base_price'  => 2220,
    ],
    'D' => [
        'block_start' => 3701,
        'block_end'   => 4899,
        'col_start'   => 37,
        'col_end'     => 48,
        'base_price'  => 2918,
    ],
    'E' => [
        'block_start' => 4901,
        'block_end'   => 6099,
        'col_start'   => 49,
        'col_end'     => 60,
        'base_price'  => 3834,
    ],
    'F' => [
        'block_start' => 6101,
        'block_end'   => 7299,
        'col_start'   => 61,
        'col_end'     => 72,
        'base_price'  => 5038,
    ],
    'G' => [
        'block_start' => 7301,
        'block_end'   => 8499,
        'col_start'   => 73,
        'col_end'     => 84,
        'base_price'  => 6619,
    ],
    'H' => [
        'block_start' => 8501,
        'block_end'   => 9699,
        'col_start'   => 85,
        'col_end'     => 96,
        'base_price'  => 8698,
    ],
    'Z' => [
        'block_start' => 9701,
        'block_end'   => 9999,
        'col_start'   => 97,
        'col_end'     => 99,
        'base_price'  => 11429,
        'parts'       => [
            ['start' => 9701, 'end' => 9999, 'base' => 11429],
            ['start' => 1,    'end' => 99,   'base' => 34101],
            ['start' => 100,  'end' => 9900, 'base' => 34020],
        ],
    ],
];
