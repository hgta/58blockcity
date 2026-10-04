<?php
/**
 * 方舟 Responses API 联网搜索（内置 web_search 工具）
 * change: help-semantic-rag (task 5.1)
 *
 * 请求格式（官方文档《联网内容插件功能说明》）：
 *   POST {base}/responses
 *   {"model": "...", "input":[{"role":"user","content":"..."}], "tools":[{"type":"web_search"}]}
 *
 * - 凭据复用「嵌入」用途渠道行（同一方舟账号），端点用其 baseUrl
 * - 若渠道端点为套餐地址（如 /api/plan/v3）且不支持 /responses，
 *   自动回退普通 https://ark.cn-beijing.volces.com/api/v3/responses 再试一次
 * - 任何失败都返回 ok=false，由调用方降级为现状文案（spec：搜索失败不报错）
 */
class ArkWebSearch
{
    const FALLBACK_BASE = 'https://ark.cn-beijing.volces.com/api/v3';
    const TIMEOUT = 25;

    /**
     * 联网搜索并让模型归纳
     * @param EmbeddingProvider $emb 凭据来源（嵌入渠道行）
     * @param string $model 搜索归纳用模型（system_settings: ai_search_model）
     * @param string $query 用户问题
     * @return array{ok:bool, answer:string, error:string}
     */
    public static function search(EmbeddingProvider $emb, $model, $query, $hint = '')
    {
        $model = trim((string)$model);
        $query = trim((string)$query);
        if ($model === '' || $query === '') {
            return ['ok' => false, 'answer' => '', 'error' => '未配置搜索模型（ai_search_model）'];
        }

        $prompt = '请联网搜索以下问题，用简体中文简要归纳要点（300字以内），并在末尾以列表列出主要来源网站名称：'
            . $query . ($hint !== '' ? "\n（背景：" . $hint . '）' : '');

        $payload = json_encode([
            'model' => $model,
            'input' => [['role' => 'user', 'content' => $prompt]],
            'tools' => [['type' => 'web_search']],
        ], JSON_UNESCAPED_UNICODE);

        // 先试渠道端点，失败再回退普通 api/v3
        $urls = array_unique([
            self::baseOf($emb->endpointUrl()),
            self::FALLBACK_BASE,
        ]);

        $lastErr = '';
        foreach ($urls as $base) {
            $res = self::request($base . '/responses', $payload, $emb);
            if ($res['ok']) return $res;
            $lastErr = $lastErr === '' ? $res['error'] : $lastErr . ' | ' . $res['error'];
        }
        return ['ok' => false, 'answer' => '', 'error' => $lastErr];
    }

    /** @return array{ok:bool, answer:string, error:string} */
    private static function request($url, $payload, EmbeddingProvider $emb)
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . self::apiKeyOf($emb),
            ],
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT        => self::TIMEOUT,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_RETURNTRANSFER => true,
        ]);
        $body = curl_exec($ch);
        $errno = curl_errno($ch);
        $errmsg = curl_error($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno !== 0) return ['ok' => false, 'answer' => '', 'error' => '网络错误(' . $errno . '): ' . $errmsg];
        if ($httpCode >= 400) return ['ok' => false, 'answer' => '', 'error' => 'HTTP ' . $httpCode . ': ' . mb_substr((string)$body, 0, 200)];

        $obj = json_decode((string)$body, true);
        if (!is_array($obj)) return ['ok' => false, 'answer' => '', 'error' => '响应解析失败'];

        $text = self::extractText($obj);
        if ($text === '') return ['ok' => false, 'answer' => '', 'error' => '响应无文本内容'];
        return ['ok' => true, 'answer' => $text, 'error' => ''];
    }

    /** Responses API 输出结构解析：output[].content[].text / 顶层 output_text */
    private static function extractText(array $obj)
    {
        if (!empty($obj['output_text']) && is_string($obj['output_text'])) return $obj['output_text'];
        $texts = [];
        if (isset($obj['output']) && is_array($obj['output'])) {
            foreach ($obj['output'] as $item) {
                if (!is_array($item) || !isset($item['content']) || !is_array($item['content'])) continue;
                foreach ($item['content'] as $c) {
                    if (isset($c['text']) && is_string($c['text'])) $texts[] = $c['text'];
                }
            }
        }
        return trim(implode("\n", $texts));
    }

    /** 嵌入渠道的 /embeddings URL → 基地址（去掉尾部 /embeddings 或 /v1） */
    private static function baseOf($endpointUrl)
    {
        $u = rtrim((string)$endpointUrl, '/');
        $u = preg_replace('#/embeddings$#', '', $u);
        return $u;
    }

    /** 渠道明文 Key（服务端同进程使用） */
    private static function apiKeyOf(EmbeddingProvider $emb)
    {
        return $emb->apiKey();
    }
}
