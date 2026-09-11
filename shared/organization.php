<?php
/**
 * 全局品牌实体 Organization JSON-LD —— 单一来源（Single Source of Truth）
 *
 * 用途：把 www / block / bct / mall / nft / v / bid / club 八个子域在结构化数据层面
 *      聚合为「同一个组织实体」，供生成式引擎做实体归因。
 *
 * 用法：
 *   require_once __DIR__ . '/organization.php';
 *   echo organization_json_ld();              // 输出 <script type="application/ld+json">…</script>
 *   // 或仅取 @id 用于跨页引用
 *   $orgId = ORG_ENTITY_ID;                   // "https://www.58.tl/#organization"
 *
 * 维护约定（见 design.md D3 / D6）：
 *   - sameAs  仅放「本站自有的跨平台主页」（是「我是它」）→ 当前仅 GitHub 仓库。
 *   - 第三方独立平台 BlockCity.vip 用 isRelatedTo（是「我服务/关联它」），绝不写入 sameAs，
 *     避免生成式引擎把两个组织错误合并、转嫁信誉风险。
 *   - 微信号 BitPFP 不是 URL，用 ContactPoint 的 identifier/name 承载，不写入 sameAs。
 *   - 静态 HTML 页需手工注入同一份 JSON-LD（无模板可 include）；变更本文件时同步静态页。
 */

if (!defined('ORG_ENTITY_ID')) {
    define('ORG_ENTITY_ID', 'https://www.58.tl/#organization');
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
            'name'        => '58区块城市',
            'alternateName' => ['58 BlockCity', 'BlockCity 58', 'BlockCity DAO'],
            'url'         => 'https://www.58.tl/',
            'logo'        => [
                '@type'  => 'ImageObject',
                'url'    => 'https://58.tl/apple-touch-icon.png',
                'width'  => 180,
                'height' => 180,
            ],
            'description' => '58区块城市是基于元宇宙技术的下一代同城生活服务平台，整合 BlockCity DAO 社区治理，提供区块认领、人气值（BCT）交易、NFT 头像、同城社交与本地生活服务。本站为基于 BlockCity.vip 生态的独立第三方工具站集群，涵盖 www、block、bct、mall、nft、v、bid、club 八个子域。',
            // 自有跨平台主页（同一实体的另一 URL）
            'sameAs'      => [
                'https://github.com/hgta/58blockcity',
            ],
            // 联系入口：微信号非 URL，用 identifier/name 承载
            'contactPoint' => [
                [
                    '@type'        => 'ContactPoint',
                    'contactType'  => 'customer service',
                    'email'        => 'support@58.tl',
                    'identifier'   => 'BitPFP',
                    'name'         => '微信客服（BitPFP）',
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
