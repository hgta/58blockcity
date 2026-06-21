<?php
/**
 * 全站 SEO 配置文件
 *
 * 请在百度搜索资源平台获取 token 后填入 baidu_token。
 * 配置变更后无需重启，PHP 每次请求都会读取。
 */
return [
    // 百度搜索资源平台主动推送 token
    'baidu_token' => 'YOUR_BAIDU_TOKEN',

    // 百度推送绑定的主域名
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
    ],

    // 是否开启自动推送（发布/更新内容时自动调用百度接口）
    'auto_push_enabled' => true,

    // 默认 og 图片（当页面没有专属图片时使用）
    'default_og_image' => 'https://58.tl/assets/images/og-main.jpg',
];
