<?php
/**
 * MarkdownSafe 渲染时允许的 host 白名单（可覆盖 MarkdownSafe.php 的内嵌默认值）
 *
 * 使用场景：
 *   - 拍品描述中允许引用外链图、参考链接
 *   - 默认白名单覆盖 58.tl 全域 + 通用参考域
 *   - 如需新增白名单域，仅修改本文件
 *
 * 格式：每个元素为一个完整 host 或通配后缀。
 *   'github.com'       仅匹配 github.com
 *   'example.com'      匹配 *.example.com（子域通配）
 *
 * 注意：本文件由 MarkdownSafe::loadAllowedHosts() 读取并与内置默认值合并。
 *
 * 安全要求：
 *   - 仅加入你信任的域（避免 XSS 经白名单域落地）
 *   - 不要加 *.ru / *.cn / 已知广告/追踪域
 */

return [
    // 平台所有子站（与现有 mall.58.tl / block.58.tl / nft.58.tl / bct.58.tl / bid.58.tl / hufang.58.tl 等互通）
    '58.tl',

    // 通用参考域（社区常用）
    'github.com',
    'githubusercontent.com',
    'wikipedia.org',
    'wikimedia.org',
];