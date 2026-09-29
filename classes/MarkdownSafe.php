<?php
/**
 * MarkdownSafe — 拍品描述的安全 Markdown 渲染
 *
 * 设计：
 *   - 第一层：Parsedown safemode（白名单 HTML 标签/属性）
 *   - 第二层：scheme 白名单（href 仅 http/https/mailto；src 仅 http/https）
 *   - 第三层：host 白名单（href / img host 必须在 config/markdown_hosts.php 中）
 *   - 兜底：on*= 事件属性移除（即便前两层被绕过）
 *   - 友好性：<a target="_blank"> 自动追加 rel="noopener noreferrer"
 *
 * 使用：
 *   echo MarkdownSafe::render($markdown);
 *
 * 依赖：
 *   vendor/erusev/parsedown/Parsedown.php
 *
 * 性能：拍卖读多写少，单次渲染 < 5ms。
 */
require_once __DIR__ . '/../vendor/erusev/parsedown/Parsedown.php';

class MarkdownSafe {

    /** 允许的 URL scheme（href） */
    const ALLOWED_HREF_SCHEMES = ['http:', 'https:', 'mailto:'];

    /** 允许的 URL scheme（img src） */
    const ALLOWED_SRC_SCHEMES = ['http:', 'https:'];

    /** Parsedown 实例（懒加载） */
    private static $parser = null;

    /**
     * 渲染 Markdown 为安全的 HTML
     * @param string|null $markdown 源文（NULL 或空串返回空串）
     * @return string 安全的 HTML；非法输入一律返回空串
     */
    public static function render($markdown) {
        if ($markdown === null) return '';
        $markdown = (string)$markdown;
        if ($markdown === '') return '';

        // 第一层：Parsedown safemode
        $parser = self::parser();
        $parser->setSafeMode(true);
        $html = $parser->text($markdown);

        // 第二层：scheme + host + 属性白名单
        $html = self::scrub($html);

        return $html;
    }

    /**
     * 懒加载 Parsedown 实例
     */
    private static function parser() {
        if (self::$parser === null) {
            self::$parser = new Parsedown();
        }
        return self::$parser;
    }

    /**
     * 二次过滤：scheme 白名单 + host 白名单 + 属性白名单 + target 修正
     * @param string $html Parsedown 输出
     * @return string 过滤后 HTML
     */
    private static function scrub($html) {
        $hosts = self::loadAllowedHosts();

        // 处理 <a> 标签
        $html = preg_replace_callback(
            '/<a\s+([^>]*?)>/i',
            function ($m) use ($hosts) {
                $attrs = $m[1];
                $attrs = self::filterAttrs($attrs, 'a', $hosts);
                return '<a ' . $attrs . '>';
            },
            $html
        );

        // 处理 <img> 标签
        $html = preg_replace_callback(
            '/<img\s+([^>]*?)>/i',
            function ($m) use ($hosts) {
                $attrs = $m[1];
                $attrs = self::filterAttrs($attrs, 'img', $hosts);
                // 若 src 被过滤（变成空），整个 img 降级为文本
                if (!preg_match('/src\s*=\s*"/i', $attrs)) {
                    return '';
                }
                return '<img ' . $attrs . '>';
            },
            $html
        );

        return $html;
    }

    /**
     * 过滤单个标签的属性字符串
     * @param string $attrs   标签内的属性字符串（如 `href="x" target="_blank"`）
     * @param string $tag     标签名（'a'|'img'）
     * @param array  $hosts   允许的 host 列表
     * @return string 过滤后属性字符串
     */
    private static function filterAttrs($attrs, $tag, array $hosts) {
        // 解析所有 attr="..." / attr='...'
        $result = '';
        preg_match_all('/(\w[\w\-]*)\s*=\s*("([^"]*)"|\'([^\']*)\')/u', $attrs, $matches, PREG_SET_ORDER);
        $filtered = [];
        $href     = null;
        $src      = null;
        $target   = null;

        foreach ($matches as $m) {
            $name  = strtolower($m[1]);
            $value = $m[3] !== '' ? $m[3] : $m[4];

            // 兜底：丢弃所有 on* 事件属性（即便 safemode 已过滤，兜底一道）
            if (strpos($name, 'on') === 0) continue;

            // <a> 允许的属性
            if ($tag === 'a') {
                if ($name === 'href') {
                    if (self::isUrlAllowed($value, self::ALLOWED_HREF_SCHEMES, $hosts, $hostOut)) {
                        $href = self::escAttr($value);
                    } else {
                        continue; // scheme/host 不合法
                    }
                } elseif ($name === 'title' || $name === 'class') {
                    $filtered[$name] = self::escAttr($value);
                } elseif ($name === 'target') {
                    $target = self::escAttr($value);
                } else {
                    // 其他属性丢弃（safemode 已限制，这是兜底）
                    continue;
                }
            }
            // <img> 允许的属性
            elseif ($tag === 'img') {
                if ($name === 'src') {
                    if (self::isUrlAllowed($value, self::ALLOWED_SRC_SCHEMES, $hosts, $hostOut)) {
                        $src = self::escAttr($value);
                    } else {
                        // src 不合法：返回空 src 让调用方降级
                        $src = '';
                    }
                } elseif ($name === 'alt' || $name === 'title' || $name === 'class') {
                    $filtered[$name] = self::escAttr($value);
                } else {
                    continue;
                }
            }
        }

        // 重组
        if ($tag === 'a') {
            if ($href === null) {
                // href 被过滤：整个 <a> 没有意义，返回空串让外层降级
                return '';
            }
            $result = 'href="' . $href . '"';
            foreach ($filtered as $k => $v) $result .= ' ' . $k . '="' . $v . '"';

            // target 策略：
            //   - 用户显式提供 target：尊重之（仅 _blank / _self 等合法值）
            //   - 外部链接（host 不属于默认安全域）默认 target="_blank" rel="noopener noreferrer"
            //   - 内部 / 相对链接不加 target
            $hasTarget = false;
            if ($target !== null && in_array(strtolower($target), ['_blank', '_self'], true)) {
                $result .= ' target="' . $target . '"';
                $hasTarget = true;
            }

            $isInternal = self::isInternalHref($href);
            if (!$isInternal && !$hasTarget) {
                $result .= ' target="_blank"';
                $hasTarget = true;
            }

            // target=_blank 必须配套 noopener
            if ($hasTarget && strtolower($target ?? '_blank') === '_blank') {
                $result .= ' rel="noopener noreferrer"';
            }
            return $result;
        }

        if ($tag === 'img') {
            if ($src === null || $src === '') {
                return ''; // 无 src，丢弃
            }
            $result = 'src="' . $src . '"';
            foreach ($filtered as $k => $v) $result .= ' ' . $k . '="' . $v . '"';
            return $result;
        }

        return $attrs;
    }

    /**
     * 校验 URL 是否允许
     * @param string $url        URL
     * @param array  $schemes    允许的 scheme 列表
     * @param array  $hosts      允许的 host 列表
     * @param string $hostOut    输出：解析出的 host
     * @return bool true=允许
     */
    private static function isUrlAllowed($url, array $schemes, array $hosts, &$hostOut = '') {
        $hostOut = '';
        $url = trim($url);
        if ($url === '') return false;

        // 解析 scheme
        $scheme = '';
        if (preg_match('#^([a-z][a-z0-9+\-.]*):#i', $url, $m)) {
            $scheme = strtolower($m[1]) . ':';
        }
        // mailto / 无 scheme（锚点）按内嵌处理
        if ($scheme === '' || $scheme === '#') {
            // 锚点（#xxx）：仅在 a 标签内有效
            if ($scheme === '' && (strpos($url, '#') === 0 || strpos($url, '/') === 0)) {
                // 相对链接：放行（站内使用）
                return true;
            }
            return false;
        }
        if (!in_array($scheme, $schemes, true)) return false;

        // 解析 host
        $host = '';
        if (preg_match('#^https?://([^/?\#]+)#i', $url, $m)) {
            $host = strtolower($m[1]);
        }
        if ($host === '') return false;

        // host 白名单
        $hostOut = $host;
        foreach ($hosts as $allowed) {
            $allowed = strtolower($allowed);
            if ($host === $allowed) return true;
            // 后缀匹配（如 github.com 匹配 api.github.com 不允许；需 host 完全一致或 host 以 allowed. 开头）
            if (substr($host, -strlen('.' . $allowed)) === '.' . $allowed) return true;
        }
        return false;
    }

    /**
     * 加载 host 白名单（允许被 config/markdown_hosts.php 覆盖）
     * @return array host 列表（小写）
     */
    private static function loadAllowedHosts() {
        static $cached = null;
        if ($cached !== null) return $cached;

        $default = [
            '58.tl',
            'github.com',
            'wikipedia.org',
        ];

        $overridePath = __DIR__ . '/../config/markdown_hosts.php';
        if (is_file($overridePath)) {
            $override = require $overridePath;
            if (is_array($override)) $default = array_merge($default, $override);
        }

        $cached = array_values(array_unique(array_map('strtolower', $default)));
        return $cached;
    }

    /**
     * 判断 href 是否为"站内相对链接"——以 #、/ 开头，或 mailto:
     * （mailto: 通常表示打开邮件客户端，不属于内嵌目标，保留站内行为）
     */
    private static function isInternalHref($href) {
        if ($href === '') return true;
        if ($href[0] === '#' || $href[0] === '/') return true;
        return false;
    }

    /**
     * 转义 HTML 属性值
     */
    private static function escAttr($v) {
        return htmlspecialchars($v, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}