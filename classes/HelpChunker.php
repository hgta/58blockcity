<?php
/**
 * 知识切块器：help_articles / help_faq / help_glossary → 检索块
 * change: help-semantic-rag (task 2.2)
 *
 * 规则（design.md D4）：
 * - richtext：按 <h2>/<h3> 小节切分（无小节则整篇），段超长滑窗（500 字 / 80 字重叠）
 * - steps：每个 step {title,text} 天然一块
 * - faq：一问一答一块；glossary：术语+定义一块
 * - 每块前置"【文章标题】"上下文头，缓解切块丢失主题
 * - 返回块文本可直接用于嵌入；content_hash 由调用方计算（sha256(块文本)）
 */
class HelpChunker
{
    const MAX_LEN  = 500; // 单块正文字数上限
    const OVERLAP  = 80;  // 滑窗重叠

    /**
     * 文章切块
     * @param array $a help_articles 行（title/content_type/content_richtext/content_steps）
     * @return array [ ['title' => '文章标题（小节标题）', 'text' => '【标题】…正文…'], … ]
     */
    public static function chunkArticle(array $a)
    {
        $title = trim((string)($a['title'] ?? ''));
        $chunks = [];

        if (($a['content_type'] ?? 'richtext') === 'steps') {
            $steps = json_decode((string)($a['content_steps'] ?? ''), true);
            if (is_array($steps)) {
                foreach ($steps as $i => $s) {
                    $secTitle = trim((string)($s['title'] ?? ('步骤' . ($i + 1))));
                    $body = trim((string)($s['text'] ?? ''));
                    if ($body === '') continue;
                    foreach (self::sliding($body) as $win) {
                        $chunks[] = ['title' => $title . '（' . $secTitle . '）', 'text' => '【' . $title . '】' . $secTitle . ' ' . $win];
                    }
                }
            }
            // steps 为空时回退富文本（防脏数据丢内容）
            if ($chunks) return $chunks;
        }

        $html = (string)($a['content_richtext'] ?? '');
        // 去脚本样式
        $html = preg_replace('#<(script|style)[^>]*>.*?</\1>#is', '', $html);

        // 按 h2/h3 分节（捕获分隔符，标题段与正文段交替）
        $parts = preg_split('#(<h[23][^>]*>.*?</h[23]>)#is', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false) $parts = [$html];

        $sections = []; // [secTitle, body]
        $pendingTitle = '';
        foreach ($parts as $i => $part) {
            if ($part === '') continue;
            if ($i % 2 === 1) { // 分隔符捕获组：标题
                $pendingTitle = trim(preg_replace('/\s+/u', ' ', strip_tags($part)));
            } else {
                $body = self::cleanText($part);
                if ($body !== '') $sections[] = [$pendingTitle, $body];
                $pendingTitle = '';
            }
        }
        if ($pendingTitle !== '') $sections[] = [$pendingTitle, '']; // 只有标题的尾巴

        foreach ($sections as $sec) {
            list($secTitle, $body) = $sec;
            $text = ($secTitle !== '' ? $secTitle . ' ' : '') . $body;
            $displayTitle = $title . ($secTitle !== '' ? '（' . $secTitle . '）' : '');
            foreach (self::sliding($text) as $win) {
                $chunks[] = ['title' => $displayTitle, 'text' => '【' . $title . '】' . $win];
            }
        }
        return $chunks;
    }

    /** FAQ：一问一答一块 */
    public static function chunkFaq(array $f)
    {
        $q = trim((string)($f['question'] ?? ''));
        $ans = self::cleanText((string)($f['answer'] ?? ''));
        if ($q === '' || $ans === '') return [];
        return [['title' => mb_substr($q, 0, 100), 'text' => '【常见问题：' . $q . '】' . mb_substr($ans, 0, 600)]];
    }

    /** 术语表：术语+定义一块 */
    public static function chunkGlossary(array $g)
    {
        $term = trim((string)($g['term'] ?? ''));
        $def = self::cleanText((string)($g['definition'] ?? ''));
        if ($term === '' || $def === '') return [];
        return [['title' => mb_substr($term, 0, 100), 'text' => '【术语：' . $term . '】' . mb_substr($def, 0, 600)]];
    }

    /** 富文本 → 干净文本 */
    private static function cleanText($html)
    {
        $t = preg_replace('#<(script|style)[^>]*>.*?</\1>#is', '', (string)$html);
        $t = preg_replace('#<br\s*/?>#i', ' ', $t);
        $t = preg_replace('#</(p|div|li|h[1-6]|tr)>#i', ' ', $t);
        $t = strip_tags($t);
        $t = html_entity_decode($t, ENT_QUOTES, 'UTF-8');
        $t = preg_replace('/\s+/u', ' ', $t);
        return trim($t);
    }

    /** 滑窗：超长文本按 MAX_LEN/OVERLAP 切，短文本原样返回 */
    private static function sliding($text)
    {
        $len = mb_strlen($text);
        if ($len <= self::MAX_LEN) return [$text];

        $windows = [];
        $step = self::MAX_LEN - self::OVERLAP;
        for ($off = 0; $off < $len; $off += $step) {
            $win = mb_substr($text, $off, self::MAX_LEN);
            if (trim($win) !== '') $windows[] = trim($win);
            if ($off + self::MAX_LEN >= $len) break;
        }
        return $windows ?: [mb_substr($text, 0, self::MAX_LEN)];
    }
}
