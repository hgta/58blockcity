<?php
/**
 * E2E 模拟：卖家输入 → 详情页渲染
 * 验证 8.1 任务的关键安全 + 渲染场景
 */
require_once __DIR__ . '/../classes/MarkdownSafe.php';

$md = <<<MD
# 拍品详情

**全新未拆封**，*限量首发*

- 材质：陶瓷
- 编号：A-001

> 此商品不退不换

[更多图集](https://github.com/xxx)

[恶意链接](javascript:alert(1))

<script>alert(1)</script>

![坏图](https://evil.com/x.png)

![好图](https://github.com/bid/avatar/1.png)

![站内图](https://bid.58.tl/avatar/1.png)

表格示例：

| 列 1 | 列 2 |
|-----|-----|
| A   | B   |

代码示例：

\`\`\`
SELECT * FROM auctions
WHERE status = 'active'
\`\`\`
MD;

$html = MarkdownSafe::render($md);

echo "--- HTML OUTPUT (length=" . strlen($html) . ") ---" . PHP_EOL;
echo $html . PHP_EOL;
echo PHP_EOL . "--- SAFETY CHECKS ---" . PHP_EOL;

$checks = [
    // 必须不出现（攻击 payload）
    'must_not' => ['<script', 'javascript:', 'evil.com', 'onerror', '<iframe'],
    // 必须出现（合法渲染产物）
    'must_have' => ['github.com/xxx', 'bid.58.tl/avatar', '<h1>', '<table>', '<strong>', 'target="_blank"', 'rel="noopener noreferrer"'],
];

$failures = 0;
foreach ($checks['must_not'] as $needle) {
    if (strpos($html, $needle) === false) {
        echo "  PASS  must_not  " . $needle . PHP_EOL;
    } else {
        echo "  FAIL  must_not  " . $needle . " (FOUND)" . PHP_EOL;
        $failures++;
    }
}
foreach ($checks['must_have'] as $needle) {
    if (strpos($html, $needle) !== false) {
        echo "  PASS  must_have " . $needle . PHP_EOL;
    } else {
        echo "  FAIL  must_have " . $needle . " (MISSING)" . PHP_EOL;
        $failures++;
    }
}

echo PHP_EOL . "=== E2E: " . ($failures === 0 ? 'PASS' : "FAIL ($failures)") . " ===" . PHP_EOL;
exit($failures === 0 ? 0 : 1);