<?php
/**
 * 全局品牌实体 Organization JSON-LD —— 单一来源（Single Source of Truth）
 *
 * 用途：把 www / block / bct / mall / model / nft / v / bid / club / task 十个子域在结构化数据层面
 *      聚合为「同一个组织实体」，供生成式引擎做实体归因。
 *
 * 用法：
 *   require_once __DIR__ . '/organization.php';
 *   echo organization_json_ld();              // 输出 <script type="application/ld+json">…</script>
 *   // 或仅取 @id 用于跨页引用
 *   $orgId = ORG_ENTITY_ID;                   // "https://www.58.tl/#organization"
 *
 * 维护约定（见 design.md D3 / D6）：
 *   - 第三方独立平台 BlockCity.vip 用 isRelatedTo（是「我服务/关联它」），
 *     避免生成式引擎把两个组织错误合并、转嫁信誉风险。
 *   - 微信号 BitPFP / 公众号 www58tl 都不是 URL，用 ContactPoint 的 identifier/name 承载，
 *     不写入 sameAs（schema.org 要求 sameAs 为可解析 URL）。
 *   - 当前无其他可引用的自有平台主页，故 sameAs 暂不声明；后续如有官方站外主页再加。
 *   - 静态 HTML 页需手工注入同一份 JSON-LD（无模板可 include）；变更本文件时同步静态页。
 */

if (!defined('ORG_ENTITY_ID')) {
    define('ORG_ENTITY_ID', 'https://www.58.tl/#organization');
}

/**
 * 品牌名 —— 全站单一来源
 *
 * 出现位置：<title>、og:title、og:site_name、application-name、
 *          Organization.name、WebSite.name。
 * 改品牌只改这里，避免「标题里一个名、结构化数据里另一个名」的漂移。
 *
 * 命名取舍（见 docs/baidu-brand-display.md）：
 *   沿用「58区块城市」——用户认知最强。含「58」，提交百度品牌展现时
 *   存在被判为第三方品牌的审核风险，故不在本文件内做任何规避性改写，
 *   待资质核实后再决定是否调整。
 */
if (!defined('BRAND_NAME')) {
    define('BRAND_NAME', '58区块城市');
}

// 品牌别名：仅用于 structured data 的 alternateName。
// 不擅自新增品牌别名，避免与生态方（BlockCity.vip）品牌混淆。
if (!defined('BRAND_ALT_NAMES')) {
    define('BRAND_ALT_NAMES', ['58 BlockCity', 'BlockCity 58', 'BlockCity DAO']);
}

if (!function_exists('organization_json_ld')) {
    /**
     * 生成全局 Organization JSON-LD 脚本标签
     *
     * @param array $override 可选覆盖字段（合并进实体，用于特殊页面微调）
     * @return string
     */
    function organization_json_ld(array $override = [])
    {
        $org = [
            '@context'    => 'https://schema.org',
            '@type'       => 'Organization',
            '@id'         => ORG_ENTITY_ID,
            'name'        => BRAND_NAME,
            'alternateName' => BRAND_ALT_NAMES,
            'url'         => 'https://www.58.tl/',
            'logo'        => [
                '@type'  => 'ImageObject',
                'url'    => 'https://www.58.tl/apple-touch-icon.png',
                'width'  => 180,
                'height' => 180,
            ],
            'description' => '58区块城市是基于元宇宙技术的下一代同城生活服务平台，整合 BlockCity DAO 社区治理，提供区块认领、人气值（BCT）交易、NFT 头像、同城社交与本地生活服务。本站为基于 BlockCity.vip 生态的独立第三方工具站集群，涵盖 www、block、bct、mall、model、nft、v、bid、club、task 十个子域。',
            // 联系入口：微信号/公众号 ID 非 URL，用 identifier/name 承载
            'contactPoint' => [
                [
                    '@type'        => 'ContactPoint',
                    'contactType'  => 'customer service',
                    'email'        => 'support@58.tl',
                    'identifier'   => 'BitPFP',
                    'name'         => '微信客服（BitPFP）',
                    'availableLanguage' => ['zh-CN'],
                ],
                [
                    '@type'        => 'ContactPoint',
                    'contactType'  => 'customer service',
                    'identifier'   => 'www58tl',
                    'name'         => '微信公众号（www58tl）',
                    'availableLanguage' => ['zh-CN'],
                ],
            ],
            // 第三方独立平台：弱关系声明（不并入实体）
            'isRelatedTo' => [
                [
                    '@type' => 'Organization',
                    'name'  => 'BlockCity.vip',
                    'url'   => 'https://www.blockcity.vip/',
                    'description' => '本站所服务的独立第三方平台生态',
                ],
            ],
        ];

        if (!empty($override)) {
            $org = array_replace($org, $override);
        }

        return '<script type="application/ld+json">'
            . json_encode($org, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            . '</script>';
    }
}
