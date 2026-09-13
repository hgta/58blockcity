<?php
/**
 * 全站 SEO 配置示例文件
 *
 * 使用方式：
 *   复制本文件为 config/seo.php，然后填入真实配置。
 *   config/seo.php 已被加入 .gitignore，不会提交到仓库。
 *
 * 请在百度搜索资源平台获取 token 后填入对应站点的 token 字段。
 * 配置变更后无需重启，PHP 每次请求都会读取。
 */
return [
    // ============ 多子域推送配置（按 host 键控）============
    // 每个子域独立验证、独立 token、独立开关：
    //   - 完成百度搜索资源平台验证后，填入该子域 token
    //   - 将 enabled 置为 true 才会推送；未启用时推送被跳过并记录原因
    'sites' => [
        'www.58.tl'   => ['token' => 'YOUR_BAIDU_TOKEN', 'enabled' => false],
        'mall.58.tl'  => ['token' => '', 'enabled' => false],
        'block.58.tl' => ['token' => '', 'enabled' => false],
        'bct.58.tl'   => ['token' => '', 'enabled' => false],
        'nft.58.tl'   => ['token' => '', 'enabled' => false],
        'v.58.tl'     => ['token' => '', 'enabled' => false],
        'bid.58.tl'   => ['token' => '', 'enabled' => false],
        'club.58.tl'  => ['token' => '', 'enabled' => false],
        'model.58.tl' => ['token' => '', 'enabled' => false],
        'task.58.tl'  => ['token' => '', 'enabled' => false],

        // 一级域名（主收录目标；见下方 primary_domains）
        // 完成百度验证后填入各自 token 并置 enabled=true
        'renqizhi.com'   => ['token' => '', 'enabled' => false],
        'hufangquan.com' => ['token' => '', 'enabled' => false],
    ],

    // ============ 一级域名映射（主域路由）============
    // 某些业务同时绑定子域与一级域名（内容相同）时，用本表声明主收录目标。
    //   primary    —— 主收录目标，canonical 指向它
    //   aliases    —— 别名域，nginx 层 301 到 primary
    //   subdomain  —— 收口子域，canonical 指向 primary（保留可访问，不 301）
    // 未在表中列出的 host 保持原有行为（canonical 指向自身）。
    'primary_domains' => [
        'bct' => [
            'primary'   => 'renqizhi.com',
            'aliases'   => ['renqizhi.cn'],
            'subdomain' => 'bct.58.tl',
        ],
        'hufang' => [
            'primary'   => 'hufangquan.com',
            'aliases'   => ['hufangquan.cn'],
            'subdomain' => 'v.58.tl',
        ],
    ],

    // ============ 兼容字段 ============
    // 无 sites 配置时的回退 token / site（等同 www.58.tl）
    'baidu_token' => 'YOUR_BAIDU_TOKEN',
    'baidu_site'  => 'www.58.tl',

    // 站点主域名（用于生成 canonical）
    'site_domain' => '58.tl',

    // 公开的子域名列表（用于 sitemap 生成）
    'subdomains'  => [
        'www'   => 'https://www.58.tl',
        'mall'  => 'https://mall.58.tl',
        'block' => 'https://block.58.tl',
        'bct'   => 'https://bct.58.tl',
        'nft'   => 'https://nft.58.tl',
        'v'     => 'https://v.58.tl',
        'bid'   => 'https://bid.58.tl',
        'club'  => 'https://club.58.tl',
        'model' => 'https://model.58.tl',
        'task'  => 'https://task.58.tl',
    ],

    // 是否开启自动推送（发布/更新内容时自动调用百度接口）
    'auto_push_enabled' => true,

    // 是否开启 sitemap ping（仅 Google 有该接口；百度无 ping，不受此项影响）
    'sitemap_ping_enabled' => false,

    // 默认 og 图片（当页面没有专属图片时使用）
    'default_og_image' => 'https://58.tl/assets/images/og-main.jpg',
];
