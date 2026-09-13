<?php
/**
 * 百度主动推送工具
 *
 * 使用方式：
 *   1. 命令行：php site.php                  （推送默认入口页列表）
 *   2. 命令行：php site.php <url1> <url2> ...（推送指定 URL）
 *
 * 未指定 URL 时，按 URL 归属自动选择对应子域的 token；
 * 未在 config/seo.php 的 sites 中启用 token 的子域会被跳过并提示。
 *
 * 退出码：0=至少一条成功，1=全部失败或未配置。
 */
require_once __DIR__ . '/classes/SeoHelper.php';

$urls = [];

if ($argc > 1) {
    $urls = array_slice($argv, 1);
} else {
    // 默认入口页：按 host 分组，仅推送已在 sites 中启用 token 的域
    $urls = [
        'https://www.58.tl/',
        'https://www.58.tl/top200city.php',
        'https://www.58.tl/all-cities.php',
        'https://www.58.tl/news.php',
        'https://www.58.tl/help/help.html',
        'https://www.58.tl/rankings/rankings.html',
    ];
}

// 按 host 分组（token 与 site 由 host 决定）
$groups = [];
foreach ($urls as $u) {
    $host = parse_url($u, PHP_URL_HOST);
    if (!$host) {
        fwrite(STDERR, "跳过无法解析的 URL: {$u}\n");
        continue;
    }
    $groups[strtolower($host)][] = $u;
}

if (empty($groups)) {
    fwrite(STDERR, "没有可推送的 URL。\n");
    exit(1);
}

$anySuccess = false;

foreach ($groups as $host => $hostUrls) {
    $cred = SeoHelper::resolvePushCredentials($host);

    echo "── {$host} ──────────────────────────────\n";

    if (!$cred['enabled']) {
        echo "  跳过：{$cred['reason']}\n";
        echo "  （完成百度验证后，在 config/seo.php 的 sites.{$host} 填入 token 并置 enabled=true）\n\n";
        continue;
    }

    echo "  site={$cred['site']}  待推送 " . count($hostUrls) . " 条\n";

    $result = SeoHelper::baiduPush($hostUrls, $cred['token'], $cred['site']);

    if ($result === false) {
        echo "  ❌ 推送失败（网络层错误，详见 error log 的 [SEO] 记录）\n\n";
        continue;
    }

    // 美化输出百度返回
    $decoded = json_decode((string)$result, true);
    if (is_array($decoded)) {
        echo "  返回：\n";
        foreach ($decoded as $k => $v) {
            $sv = is_scalar($v) ? $v : json_encode($v, JSON_UNESCAPED_UNICODE);
            echo "    {$k}: {$sv}\n";
        }
        if (!empty($decoded['success']) && (int)$decoded['success'] > 0) {
            $anySuccess = true;
        }
        if (!empty($decoded['message'])) {
            echo "  ⚠️ 百度提示：{$decoded['message']}\n";
        }
    } else {
        echo "  返回（原始）：" . (string)$result . "\n";
    }
    echo "\n";
}

if (!$anySuccess) {
    echo "⚠️ 没有取得 success > 0 的结果，请检查 token / site 是否与百度后台一致。\n";
    exit(1);
}

echo "✅ 推送完成。\n";
exit(0);
