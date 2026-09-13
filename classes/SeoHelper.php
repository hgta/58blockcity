<?php
/**
 * SEO 辅助类
 * 提供全站 SEO 相关的 URL 生成、摘要、百度推送等通用方法。
 */
class SeoHelper
{
    /**
     * 将任意字符串转换为 SEO 友好的 URL slug（保留中文、英文、数字）
     */
    public static function slug($str)
    {
        $str = preg_replace('/[^\x{4e00}-\x{9fa5}a-zA-Z0-9]+/u', '-', (string)$str);
        $str = trim($str, '-');
        $str = preg_replace('/-+/', '-', $str);
        return mb_substr($str, 0, 60);
    }

    /**
     * 生成纯文本摘要，默认 120 字符
     */
    public static function excerpt($text, $len = 120)
    {
        $text = strip_tags((string)$text);
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);
        return mb_substr($text, 0, $len);
    }

    /**
     * 根据当前请求协议生成完整 URL
     */
    public static function currentUrl()
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? 'www.58.tl';
        $uri    = $_SERVER['REQUEST_URI'] ?? '/';
        return $scheme . '://' . $host . $uri;
    }

    /**
     * 生成绝对 URL
     */
    public static function fullUrl($path)
    {
        $host = $_SERVER['HTTP_HOST'] ?? 'www.58.tl';
        return 'https://' . $host . '/' . ltrim($path, '/');
    }

    /* ========== 各子站规范 URL 生成 ========== */

    public static function productUrl($id, $name)
    {
        return 'https://mall.58.tl/product/' . intval($id) . '-' . self::slug($name) . '.html';
    }

    public static function shopUrl($id, $name)
    {
        return 'https://mall.58.tl/shop/' . intval($id) . '-' . self::slug($name) . '.html';
    }

    public static function productListUrl($categoryId = 0, $categoryName = '', $page = 1)
    {
        $url = 'https://mall.58.tl/product/list.php';
        $params = [];
        if ($categoryId) {
            $params[] = 'category=' . intval($categoryId);
        }
        if ($page > 1) {
            $params[] = 'page=' . intval($page);
        }
        if (!empty($params)) {
            $url .= '?' . implode('&', $params);
        }
        return $url;
    }

    public static function cityUrl($pinyin)
    {
        return 'https://www.58.tl/city/' . preg_replace('/[^a-zA-Z0-9_-]/', '', $pinyin) . '.html';
    }

    public static function circleUrl($id, $name)
    {
        return 'https://v.58.tl/hufang/circles/view.php?id=' . intval($id);
    }

    public static function nftUrl($id, $name)
    {
        $slug = self::slug($name);
        return 'https://nft.58.tl/nft/' . intval($id) . ($slug ? '-' . $slug : '') . '.html';
    }

    public static function postUrl($id, $title)
    {
        // nginx 规则为 ^/post/([0-9]+)-.*\.html$，slug 必须非空（moment 标题为空时兜底）
        $slug = self::slug($title);
        if ($slug === '') $slug = 'post';
        return 'https://club.58.tl/post/' . intval($id) . '-' . $slug . '.html';
    }

    /**
     * 模特详情页规范 URL（已迁移至独立子站 model.58.tl）
     * 旧格式 https://mall.58.tl/model/{id}-{slug}.html 由 nginx 301 到此处
     */
    public static function modelUrl($id, $nickname)
    {
        return 'https://model.58.tl/m/' . intval($id) . '-' . self::slug($nickname) . '.html';
    }

    /**
     * 模特库列表页 URL（带可选筛选参数）
     */
    public static function modelListUrl($params = [])
    {
        $url = 'https://model.58.tl/list.php';
        $params = array_filter((array)$params, function ($v) {
            return $v !== '' && $v !== null && $v !== 0;
        });
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        return $url;
    }

    /**
     * 模特子站首页 URL
     */
    public static function modelHomeUrl()
    {
        return 'https://model.58.tl/';
    }

    /**
     * 短剧详情页规范 URL
     */
    public static function dramaUrl($id, $title)
    {
        $slug = self::slug($title);
        return 'https://model.58.tl/drama/' . intval($id) . ($slug ? '-' . $slug : '') . '.html';
    }

    /**
     * 短剧列表页 URL
     */
    public static function dramaListUrl($tag = '')
    {
        $url = 'https://model.58.tl/dramas.php';
        if ($tag !== '' && $tag !== null) {
            $url .= '?tag=' . urlencode($tag);
        }
        return $url;
    }

    /**
     * 模特排行榜 URL
     */
    public static function modelRankingUrl($type = 'follower')
    {
        $url = 'https://model.58.tl/rankings.php';
        if ($type !== '' && $type !== null) {
            $url .= '?type=' . urlencode($type);
        }
        return $url;
    }

    public static function authorUrl($id, $nickname)
    {
        return 'https://mall.58.tl/author/' . intval($id) . '-' . self::slug($nickname) . '.html';
    }

    /**
     * 生成 title，自动截断到合理长度
     */
    public static function title($main, $suffix = '58区块城市')
    {
        $main = trim((string)$main);
        $full = $main . ' - ' . $suffix;
        if (mb_strlen($full) > 60) {
            $main = mb_substr($main, 0, 45);
            $full = $main . ' - ' . $suffix;
        }
        return $full;
    }

    /**
     * 生成 description，自动拼接后缀
     */
    public static function description($text, $suffix = '58区块城市')
    {
        $text = self::excerpt($text, 100);
        if (empty($text)) {
            return $suffix . '，基于元宇宙技术的下一代同城生活服务平台。';
        }
        $full = $text . ' - ' . $suffix;
        if (mb_strlen($full) > 160) {
            $text = mb_substr($text, 0, 140 - mb_strlen($suffix));
            $full = $text . ' - ' . $suffix;
        }
        return $full;
    }

    /**
     * 安全地设置 $site_config 的 SEO 字段
     */
    public static function setSiteConfig(&$siteConfig, $title, $description, $keywords, $canonical, $ogImage = '', $ogType = 'website')
    {
        $siteConfig['title']       = $title;
        $siteConfig['description'] = $description;
        $siteConfig['keywords']    = $keywords;
        $siteConfig['canonical_url'] = $canonical;
        $siteConfig['og_title']    = $siteConfig['og_title'] ?? $title;
        $siteConfig['og_description'] = $siteConfig['og_description'] ?? $description;
        $siteConfig['og_type']     = $ogType;
        $siteConfig['og_url']      = $canonical;
        if (!empty($ogImage)) {
            $siteConfig['og_image'] = $ogImage;
        }
    }

    /**
     * 读取 SEO 配置（带静态缓存，单次请求内只读一次文件）
     *
     * @return array
     */
    private static function seoConfig()
    {
        static $config = null;
        if ($config !== null) {
            return $config;
        }
        $configFile = __DIR__ . '/../config/seo.php';
        if (!file_exists($configFile)) {
            $config = [];
            return $config;
        }
        $loaded = require $configFile;
        $config = is_array($loaded) ? $loaded : [];
        return $config;
    }

    /**
     * 解析某 host 对应的推送凭据
     *
     * 优先读取 sites 映射；无映射时回退到顶层 baidu_token / baidu_site。
     * token 为占位符或空视为未配置。
     *
     * @param string $host 不带协议的域名，如 www.58.tl
     * @return array{token:string,site:string,enabled:bool,reason:string}
     */
    public static function resolvePushCredentials($host)
    {
        $config = self::seoConfig();
        $host   = strtolower(trim((string)$host));

        $token   = '';
        $site    = $host;
        $enabled = false;
        $reason  = '';

        if (!empty($config['sites']) && is_array($config['sites'])) {
            $entry = $config['sites'][$host] ?? null;
            if ($entry === null) {
                $reason = '该子域未在 sites 中配置';
            } else {
                $token   = (string)($entry['token'] ?? '');
                $site    = (string)($entry['site'] ?? $host);
                $enabled = !empty($entry['enabled']);
                if (!$enabled) {
                    $reason = '该子域已配置但未启用（enabled=false）';
                }
            }
        } else {
            // 无 sites 配置：回退到顶层字段（视为 www）
            $token   = (string)($config['baidu_token'] ?? '');
            $site    = (string)($config['baidu_site'] ?? 'www.58.tl');
            $enabled = true;
        }

        if ($enabled && ($token === '' || $token === 'YOUR_BAIDU_TOKEN')) {
            $enabled = false;
            $reason  = 'token 未配置或仍为占位符';
        }

        return [
            'token'   => $token,
            'site'    => $site,
            'enabled' => $enabled,
            'reason'  => $reason,
        ];
    }

    /**
     * 百度主动推送（底层执行：发送 HTTP 请求 + 记录结果日志）
     *
     * 注意：业务代码请使用 pushContentUrl()，它会自动按 URL 归属选择 token。
     * 本方法供 pushContentUrl() 与命令行批量推送（site.php）使用。
     *
     * @param string|array $urls 要推送的 URL
     * @param string       $token 推送 token
     * @param string       $site  站点域名（不带协议），如 www.58.tl
     * @return string|false API 原始返回
     */
    public static function baiduPush($urls, $token = '', $site = '')
    {
        $urls = (array)$urls;
        $urls = array_values(array_filter(array_unique(array_map('trim', $urls))));

        // 未显式传 token 时，按第一个 URL 的 host 解析
        if ($token === '' || $site === '') {
            $first = $urls[0] ?? '';
            $host  = parse_url($first, PHP_URL_HOST);
            if (!$host) {
                $cfg  = self::seoConfig();
                $host = $cfg['baidu_site'] ?? 'www.58.tl';
            }
            $cred = self::resolvePushCredentials($host);
            if ($token === '') $token = $cred['token'];
            if ($site === '')  $site  = $cred['site'];
        }

        if ($token === '' || $token === 'YOUR_BAIDU_TOKEN') {
            self::log('[推送跳过] token 未配置 site=' . $site . ' urls=' . count($urls));
            return false;
        }
        if (empty($urls)) {
            self::log('[推送跳过] URL 为空 site=' . $site);
            return false;
        }

        $api = 'http://data.zz.baidu.com/urls?site=https://' . $site . '&token=' . urlencode($token);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $api,
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS     => implode("\n", $urls),
            CURLOPT_HTTPHEADER     => ['Content-Type: text/plain'],
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $result    = curl_exec($ch);
        $curlErrNo = curl_errno($ch);
        $curlError = curl_error($ch);
        $httpCode  = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // 网络层错误
        if ($result === false || $curlErrNo !== 0) {
            self::log('[推送失败·网络] site=' . $site
                . ' errno=' . $curlErrNo . ' error=' . $curlError
                . ' urls=' . count($urls));
            return false;
        }

        // HTTP 层错误
        if ($httpCode !== 200) {
            self::log('[推送失败·HTTP] site=' . $site
                . ' http=' . $httpCode . ' body=' . substr((string)$result, 0, 500));
            return $result;
        }

        // 业务层结果（解析百度返回，提取关键字段）
        $parsed = json_decode((string)$result, true);
        if (is_array($parsed)) {
            $parts = [];
            foreach (['success', 'remain', 'not_same_site', 'not_valid', 'over_quota', 'message', 'error'] as $k) {
                if (array_key_exists($k, $parsed)) {
                    $v = $parsed[$k];
                    $parts[] = $k . '=' . (is_scalar($v) ? $v : json_encode($v, JSON_UNESCAPED_UNICODE));
                }
            }
            self::log('[推送结果] site=' . $site
                . ' sent=' . count($urls)
                . ' ' . implode(' ', $parts));
        } else {
            self::log('[推送结果·无法解析] site=' . $site
                . ' http=' . $httpCode . ' body=' . substr((string)$result, 0, 500));
        }

        return $result;
    }

    /**
     * 记录 SEO 日志
     */
    public static function log($message)
    {
        error_log('[SEO] ' . $message);
    }

    /**
     * 发布内容时自动推送 URL 到百度（业务侧唯一入口）
     *
     * 守卫：auto_push_enabled 开启 + 该 URL 所属 host 已配置 token 且启用。
     * 按 URL 的 host 选择对应子域 token，匹配不到则跳过（不误推给主站）。
     */
    public static function pushContentUrl($url)
    {
        $config = self::seoConfig();
        if (empty($config['auto_push_enabled'])) {
            return;
        }

        $url = trim((string)$url);
        if ($url === '') {
            return;
        }

        $host = parse_url($url, PHP_URL_HOST);
        if (!$host) {
            self::log('[推送跳过] 无法解析 URL host: ' . $url);
            return;
        }

        $cred = self::resolvePushCredentials($host);
        if (!$cred['enabled']) {
            self::log('[推送跳过] host=' . $host . ' 原因=' . $cred['reason'] . ' url=' . $url);
            return;
        }

        self::baiduPush($url, $cred['token'], $cred['site']);
    }

    /**
     * 通知 Google sitemap 已更新（百度无 sitemap ping 接口，故不发送）
     *
     * 受 config/seo.php 的 sitemap_ping_enabled 控制，默认关闭。
     * 百度发现 sitemap 依靠 robots.txt 的 Sitemap 声明与平台手动提交。
     */
    public static function pingSitemap($sitemapUrl)
    {
        $config = self::seoConfig();
        if (empty($config['sitemap_ping_enabled'])) {
            return;
        }

        // Google sitemap ping
        $googlePing = 'https://www.google.com/ping?sitemap=' . urlencode($sitemapUrl);
        @file_get_contents($googlePing);
    }

    /**
     * 如果当前 URL 不是规范 URL，执行 301 跳转
     */
    public static function redirectIfNotCanonical($canonicalUrl)
    {
        if (empty($canonicalUrl)) {
            return;
        }
        $current = self::currentUrl();
        $currentParts = parse_url($current);
        $canonicalParts = parse_url($canonicalUrl);
        if (!$currentParts || !$canonicalParts) {
            return;
        }

        // 对 path/query 做 URL 解码，避免编码差异导致误判
        $currentKey = strtolower($currentParts['host'] ?? '')
            . rawurldecode($currentParts['path'] ?? '')
            . rawurldecode($currentParts['query'] ?? '');
        $canonicalKey = strtolower($canonicalParts['host'] ?? '')
            . rawurldecode($canonicalParts['path'] ?? '')
            . rawurldecode($canonicalParts['query'] ?? '');

        if ($currentKey !== $canonicalKey) {
            header('HTTP/1.1 301 Moved Permanently');
            // 对外跳转时确保 URL 编码安全，避免中文直接出现在 HTTP header 中
            header('Location: ' . self::encodeUrl($canonicalUrl));
            exit;
        }
    }

    /* ========== 结构化数据 (JSON-LD) ========== */

    /**
     * 生成 BreadcrumbList JSON-LD 结构化数据
     *
     * @param array $items [['name'=>'首页','url'=>'/'], ['name'=>'商品详情','url'=>null]]
     * @return string JSON-LD 脚本标签
     */
    public static function breadcrumbList(array $items)
    {
        if (empty($items)) return '';
        $list = [];
        $pos = 1;
        foreach ($items as $item) {
            $el = [
                '@type' => 'ListItem',
                'position' => $pos++,
                'name' => $item['name'],
            ];
            if (!empty($item['url'])) {
                $el['item'] = $item['url'];
            }
            $list[] = $el;
        }
        return '<script type="application/ld+json">' . json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $list,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>';
    }

    /**
     * 生成 ItemList JSON-LD 结构化数据（用于列表页）
     *
     * @param array  $items    [['url'=>'...','name'=>'...'], ...]
     * @param string $listName 列表名称
     * @return string JSON-LD 脚本标签
     */
    public static function itemListSchema(array $items, $listName = '商品列表')
    {
        if (empty($items)) return '';
        $elements = [];
        $pos = 1;
        foreach ($items as $item) {
            $elements[] = [
                '@type' => 'ListItem',
                'position' => $pos++,
                'url' => $item['url'],
                'name' => $item['name'],
            ];
        }
        return '<script type="application/ld+json">' . json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'ItemList',
            'name' => $listName,
            'itemListElement' => $elements,
            'numberOfItems' => count($elements),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>';
    }

    /* ========== 生成式引擎结构化数据（GEO） ========== */

    /**
     * 统一输出 JSON-LD 脚本标签（内部工具方法）
     */
    private static function jsonLd(array $data)
    {
        if (empty($data)) {
            return '';
        }
        return '<script type="application/ld+json">'
            . json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            . '</script>';
    }

    /** 全局组织实体 @id（与 shared/organization.php 保持一致） */
    const ORG_ID = 'https://www.58.tl/#organization';

    /**
     * 短剧 / 影视作品结构化数据（TVSeries）
     *
     * @param array $d ['title'=>, 'url'=>, 'image'=>, 'description'=>, 'episodes'=>,
     *                 'genre'=>[], 'actors'=>[['name'=>,'url'=>], ...]]
     */
    public static function tvSeriesSchema(array $d)
    {
        if (empty($d['title'])) {
            return '';
        }
        $data = [
            '@context'    => 'https://schema.org',
            '@type'       => 'TVSeries',
            'name'        => $d['title'],
        ];
        if (!empty($d['url']))         $data['url'] = $d['url'];
        if (!empty($d['image']))       $data['image'] = $d['image'];
        if (!empty($d['description'])) $data['description'] = $d['description'];
        if (!empty($d['episodes']))    $data['numberOfEpisodes'] = intval($d['episodes']);
        if (!empty($d['genre']) && is_array($d['genre'])) {
            $data['genre'] = array_values($d['genre']);
        }
        if (!empty($d['actors']) && is_array($d['actors'])) {
            $actors = [];
            foreach ($d['actors'] as $a) {
                if (empty($a['name'])) {
                    continue;
                }
                $actor = ['@type' => 'Person', 'name' => $a['name']];
                if (!empty($a['url'])) {
                    $actor['url'] = $a['url'];
                }
                $actors[] = $actor;
            }
            if ($actors) {
                $data['actor'] = $actors;
            }
        }
        return self::jsonLd($data);
    }

    /**
     * Organization 结构化数据（通用构造，一般由 shared/organization.php 提供）
     */
    public static function organizationSchema(array $org)
    {
        $data = array_merge([
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
        ], $org);
        return self::jsonLd($data);
    }

    /**
     * WebSite 结构化数据（首页 / 站点级）
     *
     * @param array $site ['name'=>, 'url'=>, 'description'=>, 'alternateName'=>, 'search'=>, 'publisher_id'=>]
     */
    public static function webSiteSchema(array $site)
    {
        $url = $site['url'] ?? 'https://www.58.tl/';
        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            '@id' => $site['id'] ?? (rtrim($url, '/') . '/#website'),
            'name' => $site['name'] ?? '58区块城市',
            'url' => $url,
        ];
        if (!empty($site['alternateName'])) {
            $data['alternateName'] = $site['alternateName'];
        }
        if (!empty($site['description'])) {
            $data['description'] = $site['description'];
        }
        if (!empty($site['search'])) {
            $data['potentialAction'] = [
                '@type' => 'SearchAction',
                'target' => $site['search'],
                'query-input' => 'required name=search_term_string',
            ];
        }
        $data['publisher'] = $site['publisher_id']
            ? ['@id' => $site['publisher_id']]
            : ['@id' => self::ORG_ID];
        return self::jsonLd($data);
    }

    /**
     * Article / NewsArticle 结构化数据
     *
     * @param array $a ['headline'=>, 'description'=>, 'url'=>, 'image'=>, 'datePublished'=>,
     *                  'dateModified'=>, 'author'=>, 'section'=>, 'keywords'=>, 'type'=>, 'publisher'=>]
     */
    public static function articleSchema(array $a)
    {
        $data = [
            '@context' => 'https://schema.org',
            '@type' => $a['type'] ?? 'Article',
            'headline' => mb_substr(trim((string)($a['headline'] ?? '')), 0, 110),
        ];
        if (!empty($a['description'])) {
            $data['description'] = (string)$a['description'];
        }
        if (!empty($a['url'])) {
            $data['mainEntityOfPage'] = ['@type' => 'WebPage', '@id' => $a['url']];
            $data['url'] = $a['url'];
        }
        if (!empty($a['image'])) {
            $data['image'] = $a['image'];
        }
        if (!empty($a['datePublished'])) {
            $data['datePublished'] = $a['datePublished'];
        }
        if (!empty($a['dateModified'])) {
            $data['dateModified'] = $a['dateModified'];
        }
        if (!empty($a['author'])) {
            $data['author'] = is_array($a['author'])
                ? $a['author']
                : ['@type' => 'Person', 'name' => (string)$a['author']];
        }
        $data['publisher'] = $a['publisher'] ?? ['@id' => self::ORG_ID];
        if (!empty($a['section'])) {
            $data['articleSection'] = $a['section'];
        }
        if (!empty($a['keywords'])) {
            $data['keywords'] = $a['keywords'];
        }
        if (!empty($a['inLanguage'])) {
            $data['inLanguage'] = $a['inLanguage'];
        } else {
            $data['inLanguage'] = 'zh-CN';
        }
        return self::jsonLd($data);
    }

    /**
     * FAQPage 结构化数据
     *
     * @param array $qa [['question'=>'', 'answer'=>''], ...]
     */
    public static function faqPageSchema(array $qa)
    {
        $items = [];
        foreach ($qa as $q) {
            $question = trim((string)($q['question'] ?? ''));
            $answer   = trim((string)($q['answer'] ?? ''));
            if ($question === '' || $answer === '') {
                continue;
            }
            $items[] = [
                '@type' => 'Question',
                'name' => $question,
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $answer],
            ];
        }
        if (empty($items)) {
            return '';
        }
        return self::jsonLd([
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => $items,
        ]);
    }

    /**
     * HowTo 结构化数据
     *
     * @param array $how ['name'=>, 'description'=>, 'image'=>, 'totalTime'=>,
     *                    'steps'=>[['name'=>, 'text'=>, 'url'=>, 'image'=>], ...]]
     */
    public static function howToSchema(array $how)
    {
        $steps = [];
        $pos = 1;
        foreach (($how['steps'] ?? []) as $s) {
            $name = trim((string)($s['name'] ?? ''));
            $text = trim((string)($s['text'] ?? ''));
            if ($name === '' && $text === '') {
                continue;
            }
            $step = [
                '@type' => 'HowToStep',
                'position' => $pos++,
                'name' => $name !== '' ? $name : $text,
                'text' => $text !== '' ? $text : $name,
            ];
            if (!empty($s['url'])) $step['url'] = $s['url'];
            if (!empty($s['image'])) $step['image'] = $s['image'];
            $steps[] = $step;
        }
        if (empty($steps)) {
            return '';
        }
        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'HowTo',
            'name' => $how['name'] ?? '',
            'step' => $steps,
        ];
        if (!empty($how['description'])) $data['description'] = $how['description'];
        if (!empty($how['image'])) $data['image'] = $how['image'];
        if (!empty($how['totalTime'])) $data['totalTime'] = $how['totalTime'];
        return self::jsonLd($data);
    }

    /**
     * Product + Offer 结构化数据
     *
     * @param array $p ['name'=>, 'url'=>, 'image'=>, 'description'=>, 'sku'=>, 'category'=>,
     *                  'brand'=>, 'price'=>, 'currency'=>, 'availability'=>, 'seller'=>,
     *                  'rating'=>, 'reviewCount'=>]
     */
    public static function productSchema(array $p)
    {
        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => (string)($p['name'] ?? ''),
        ];
        if (!empty($p['url'])) $data['url'] = $p['url'];
        if (!empty($p['image'])) $data['image'] = $p['image'];
        if (!empty($p['description'])) $data['description'] = (string)$p['description'];
        if (!empty($p['sku'])) $data['sku'] = (string)$p['sku'];
        if (!empty($p['category'])) $data['category'] = (string)$p['category'];
        if (!empty($p['brand'])) {
            $data['brand'] = ['@type' => 'Brand', 'name' => (string)$p['brand']];
        }
        $offer = [
            '@type' => 'Offer',
            'price' => (string)($p['price'] ?? ''),
            'priceCurrency' => $p['currency'] ?? 'CNY',
            'availability' => $p['availability'] ?? 'https://schema.org/InStock',
        ];
        if (!empty($p['url'])) $offer['url'] = $p['url'];
        if (!empty($p['seller'])) {
            $offer['seller'] = ['@type' => 'Organization', 'name' => (string)$p['seller']];
        }
        $data['offers'] = $offer;
        if (!empty($p['rating']) && !empty($p['reviewCount'])) {
            $data['aggregateRating'] = [
                '@type' => 'AggregateRating',
                'ratingValue' => (string)$p['rating'],
                'reviewCount' => (int)$p['reviewCount'],
            ];
        }
        return self::jsonLd($data);
    }

    /**
     * Store 结构化数据
     *
     * @param array $s ['name'=>, 'url'=>, 'image'=>, 'description'=>, 'telephone'=>,
     *                  'addressRegion'=>, 'addressLocality'=>, 'streetAddress'=>,
     *                  'rating'=>, 'reviewCount'=>]
     */
    public static function storeSchema(array $s)
    {
        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'Store',
            'name' => (string)($s['name'] ?? ''),
        ];
        if (!empty($s['url'])) $data['url'] = $s['url'];
        if (!empty($s['image'])) $data['image'] = $s['image'];
        if (!empty($s['description'])) $data['description'] = (string)$s['description'];
        if (!empty($s['telephone'])) $data['telephone'] = (string)$s['telephone'];
        $addr = [];
        if (!empty($s['streetAddress']))   $addr['streetAddress']   = (string)$s['streetAddress'];
        if (!empty($s['addressLocality'])) $addr['addressLocality'] = (string)$s['addressLocality'];
        if (!empty($s['addressRegion']))   $addr['addressRegion']   = (string)$s['addressRegion'];
        if (!empty($addr)) {
            $addr['@type'] = 'PostalAddress';
            $addr['addressCountry'] = $s['addressCountry'] ?? 'CN';
            $data['address'] = $addr;
        }
        if (!empty($s['rating']) && !empty($s['reviewCount'])) {
            $data['aggregateRating'] = [
                '@type' => 'AggregateRating',
                'ratingValue' => (string)$s['rating'],
                'reviewCount' => (int)$s['reviewCount'],
            ];
        }
        $data['parentOrganization'] = ['@id' => self::ORG_ID];
        return self::jsonLd($data);
    }

    /**
     * Person 结构化数据（模特 / 作者 / 用户）
     *
     * @param array $p ['name'=>, 'url'=>, 'image'=>, 'description'=>, 'jobTitle'=>, 'worksFor'=>, 'sameAs'=>]
     */
    public static function personSchema(array $p)
    {
        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'Person',
            'name' => (string)($p['name'] ?? ''),
        ];
        if (!empty($p['url'])) $data['url'] = $p['url'];
        if (!empty($p['image'])) $data['image'] = $p['image'];
        if (!empty($p['description'])) $data['description'] = (string)$p['description'];
        if (!empty($p['jobTitle'])) $data['jobTitle'] = (string)$p['jobTitle'];
        $data['worksFor'] = $p['worksFor'] ?? ['@id' => self::ORG_ID];
        if (!empty($p['sameAs'])) $data['sameAs'] = (array)$p['sameAs'];
        if (!empty($p['extra']) && is_array($p['extra'])) {
            foreach ($p['extra'] as $k => $v) {
                if ($v === '' || $v === null || $v === []) continue;
                $data[$k] = $v;
            }
        }
        return self::jsonLd($data);
    }

    /**
     * Place / AdministrativeArea 结构化数据（城市）
     *
     * @param array $pl ['name'=>, 'url'=>, 'description'=>, 'image'=>, 'type'=>,
     *                   'addressRegion'=>, 'addressCountry'=>, 'containedInPlace'=>,
     *                   'geo'=>['lat'=>,'lng'=>], 'additionalProperties'=>['名称'=>'值']]
     */
    public static function placeSchema(array $pl)
    {
        $data = [
            '@context' => 'https://schema.org',
            '@type' => $pl['type'] ?? 'Place',
            'name' => (string)($pl['name'] ?? ''),
        ];
        if (!empty($pl['url'])) $data['url'] = $pl['url'];
        if (!empty($pl['description'])) $data['description'] = (string)$pl['description'];
        if (!empty($pl['image'])) $data['image'] = $pl['image'];
        if (!empty($pl['addressRegion']) || !empty($pl['addressCountry'])) {
            $addr = ['@type' => 'PostalAddress'];
            if (!empty($pl['addressRegion'])) $addr['addressRegion'] = (string)$pl['addressRegion'];
            $addr['addressCountry'] = $pl['addressCountry'] ?? 'CN';
            $data['address'] = $addr;
        }
        if (!empty($pl['containedInPlace'])) {
            $data['containedInPlace'] = $pl['containedInPlace'];
        }
        if (!empty($pl['geo']['lat']) && !empty($pl['geo']['lng'])) {
            $data['geo'] = [
                '@type' => 'GeoCoordinates',
                'latitude' => $pl['geo']['lat'],
                'longitude' => $pl['geo']['lng'],
            ];
        }
        if (!empty($pl['additionalProperties']) && is_array($pl['additionalProperties'])) {
            $props = [];
            foreach ($pl['additionalProperties'] as $k => $v) {
                if ($v === '' || $v === null) continue;
                $props[] = ['@type' => 'PropertyValue', 'name' => (string)$k, 'value' => (string)$v];
            }
            if ($props) $data['additionalProperty'] = $props;
        }
        return self::jsonLd($data);
    }

    /**
     * DefinedTermSet 结构化数据（术语表）
     *
     * @param array $set ['name'=>, 'description'=>, 'url'=>,
     *                    'terms'=>[['name'=>, 'description'=>, 'url'=>], ...]]
     */
    public static function definedTermSetSchema(array $set)
    {
        $terms = [];
        foreach (($set['terms'] ?? []) as $t) {
            $name = trim((string)($t['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $term = ['@type' => 'DefinedTerm', 'name' => $name];
            if (!empty($t['description'])) $term['description'] = (string)$t['description'];
            if (!empty($t['url'])) $term['url'] = $t['url'];
            if (!empty($t['inDefinedTermSet'])) $term['inDefinedTermSet'] = $t['inDefinedTermSet'];
            $terms[] = $term;
        }
        if (empty($terms)) {
            return '';
        }
        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'DefinedTermSet',
            'name' => $set['name'] ?? '术语表',
            'hasDefinedTerm' => $terms,
        ];
        if (!empty($set['description'])) $data['description'] = (string)$set['description'];
        if (!empty($set['url'])) $data['url'] = $set['url'];
        return self::jsonLd($data);
    }

    /**
     * VisualArtwork 结构化数据（NFT）
     *
     * @param array $n ['name'=>, 'url'=>, 'image'=>, 'description'=>, 'artMedium'=>,
     *                  'dateCreated'=>, 'creator'=>, 'identifier'=>,
     *                  'price'=>, 'currency'=>, 'availability'=>]
     */
    public static function visualArtworkSchema(array $n)
    {
        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'VisualArtwork',
            'name' => (string)($n['name'] ?? ''),
        ];
        if (!empty($n['url'])) $data['url'] = $n['url'];
        if (!empty($n['image'])) $data['image'] = $n['image'];
        if (!empty($n['description'])) $data['description'] = (string)$n['description'];
        if (!empty($n['artMedium'])) $data['artMedium'] = (string)$n['artMedium'];
        if (!empty($n['dateCreated'])) $data['dateCreated'] = $n['dateCreated'];
        if (!empty($n['identifier'])) $data['identifier'] = (string)$n['identifier'];
        if (!empty($n['creator'])) {
            $data['creator'] = is_array($n['creator'])
                ? $n['creator']
                : ['@type' => 'Person', 'name' => (string)$n['creator']];
        }
        if (!empty($n['price'])) {
            $offer = [
                '@type' => 'Offer',
                'price' => (string)$n['price'],
                'priceCurrency' => $n['currency'] ?? 'CNY',
                'availability' => $n['availability'] ?? 'https://schema.org/InStock',
            ];
            if (!empty($n['url'])) $offer['url'] = $n['url'];
            $data['offers'] = $offer;
        }
        return self::jsonLd($data);
    }

    /**
     * 对 URL 的中文/特殊字符进行安全编码，同时保留 :// / ? & = 等 URL 结构字符
     */
    private static function encodeUrl($url)
    {
        $parts = parse_url($url);
        if (!$parts) {
            return $url;
        }
        $out = ($parts['scheme'] ?? 'https') . '://';
        if (!empty($parts['user'])) {
            $out .= $parts['user'];
            if (!empty($parts['pass'])) {
                $out .= ':' . $parts['pass'];
            }
            $out .= '@';
        }
        $out .= $parts['host'] ?? '';
        if (!empty($parts['port'])) {
            $out .= ':' . $parts['port'];
        }
        if (!empty($parts['path'])) {
            $out .= implode('/', array_map('rawurlencode', explode('/', $parts['path'])));
        }
        if (!empty($parts['query'])) {
            $out .= '?' . $parts['query'];
        }
        if (!empty($parts['fragment'])) {
            $out .= '#' . $parts['fragment'];
        }
        return $out;
    }
}
