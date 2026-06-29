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
        return 'https://v.58.tl/circle/' . intval($id) . '-' . self::slug($name) . '.html';
    }

    public static function nftUrl($id, $name)
    {
        $slug = self::slug($name);
        return 'https://nft.58.tl/nft/' . intval($id) . ($slug ? '-' . $slug : '') . '.html';
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
     * 百度主动推送（实时推送）
     *
     * @param string|array $urls 要推送的 URL
     * @return string|false API 返回结果
     */
    public static function baiduPush($urls)
    {
        $configFile = __DIR__ . '/../config/seo.php';
        if (!file_exists($configFile)) {
            return false;
        }
        $config = require $configFile;
        $token  = $config['baidu_token'] ?? '';
        $site   = $config['baidu_site'] ?? 'www.58.tl';

        if (empty($token) || empty($urls)) {
            return false;
        }

        $urls = (array)$urls;
        $urls = array_filter(array_unique($urls));
        if (empty($urls)) {
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
        ]);
        $result = curl_exec($ch);
        curl_close($ch);

        return $result;
    }

    /**
     * 记录 SEO 日志（方便调试百度推送结果）
     */
    public static function log($message)
    {
        error_log('[SEO] ' . $message);
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
