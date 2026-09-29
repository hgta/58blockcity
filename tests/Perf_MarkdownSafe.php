<?php
/**
 * MarkdownSafe 性能基准（单进程，PHP 7+ microtime）
 * 用途：验证单次渲染耗时增量 < 5ms（任务 8.2）
 */
require_once __DIR__ . '/../classes/MarkdownSafe.php';

// 模拟一段中等长度的拍品描述（~2KB）
$md = str_repeat("**全新首发** 拍品来源：[link](https://github.com/xxx)\n\n> 不退不换\n\n- 项目\n- 项目\n\n", 50);

$iterations = 1000;

// 热身
for ($i = 0; $i < 100; $i++) MarkdownSafe::render($md);

// 基准
$start = microtime(true);
for ($i = 0; $i < $iterations; $i++) {
    MarkdownSafe::render($md);
}
$elapsed = microtime(true) - $start;
$perRender = $elapsed / $iterations * 1000; // ms

printf("Iterations: %d\n", $iterations);
printf("Total:      %.2f ms\n", $elapsed * 1000);
printf("Per render: %.3f ms\n", $perRender);
printf("Target:     < 5.000 ms\n");

exit($perRender < 5.0 ? 0 : 1);