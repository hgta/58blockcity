<?php
/**
 * Hermes 原生 API 客户端（训练台专用）
 * change: admin-ai-training-console (task 1.3)
 *
 * - 直连本机 Hermes（/api/sessions/* 原生会话），与 AiProvider（OpenAI 兼容层）分工：
 *   前台小帮走 AiProvider 渠道抽象；训练台调教的就是这台 Hermes 本体
 * - 端点与 Key 取自 ai_providers 中 preset='hermes' 的渠道行（SecureCrypto 解密）
 * - 记忆分区：X-Hermes-Session-Key 头（admin=训练试验区 / assistant=前台小帮共享区）
 * - 浏览器永不接触 Hermes 地址与 Key（仅经 api/ai/console.php 服务端代理调用）
 */

class HermesClient
{
    const SESSION_KEY_ADMIN     = 'web:58tl:admin';
    const SESSION_KEY_ASSISTANT = 'web:58tl:assistant';

    const TIMEOUT_CONNECT = 5;
    const TIMEOUT_HTTP    = 30;

    /** @var string|null 错误信息（上次请求失败时非空） */
    public $lastError = '';
    /** @var int 上次请求 HTTP 状态码 */
    public $lastHttpCode = 0;

    private $baseUrl;
    private $apiKey;

    /** @var callable|null 流式回调 function(string $jsonLine) —— SSE 透传用 */
    private $streamSink = null;

    /**
     * 从数据库装载 Hermes 渠道
     * 识别规则：preset='hermes' 优先；否则按端点特征兜底（127.0.0.1/localhost + 8642 等本机 Hermes 部署）
     * @return HermesClient|null 失败返回 null（无渠道/Hermes 未启用），错误写回 $err
     */
    public static function load(PDO $db, &$err = '')
    {
        // 1) 按 preset 精确匹配
        $stmt = $db->prepare("SELECT * FROM ai_providers WHERE preset = 'hermes' AND is_enabled = 1 ORDER BY is_default DESC, id LIMIT 1");
        $stmt->execute();
        $row = $stmt->fetch();

        // 2) 兜底：老渠道 preset 可能是 custom，按端点特征识别本机 Hermes
        if (!$row) {
            $rows = $db->query("SELECT * FROM ai_providers WHERE is_enabled = 1 ORDER BY is_default DESC, sort_order, id")->fetchAll();
            foreach ($rows as $r) {
                $ep = (string)$r['endpoint'];
                if (preg_match('#^https?://(127\.0\.0\.1|localhost)(:\d+)?/?#i', $ep)
                    && (strpos($ep, '8642') !== false || stripos($r['name'], 'hermes') !== false)) {
                    $row = $r; break;
                }
            }
        }
        if (!$row) { $err = '未找到启用的 Hermes 渠道（后台 AI 渠道配置中新增，选择 Hermes 预置模板）'; return null; }
        $key = $row['api_key_cipher'] !== '' ? SecureCrypto::decrypt($row['api_key_cipher']) : '';
        if ($key === null || $key === '') { $err = 'Hermes 渠道 API Key 解密失败，请在后台重新保存该渠道'; return null; }
        $c = new self(rtrim($row['endpoint'], '/'), $key);
        return $c;
    }

    public function __construct(string $baseUrl, string $apiKey)
    {
        $this->baseUrl = $baseUrl;
        $this->apiKey = $apiKey;
    }

    // ------------------------------------------------------------
    // 会话管理
    // ------------------------------------------------------------

    /** 会话列表 */
    public function listSessions(string $sessionKey = self::SESSION_KEY_ADMIN)
    {
        return $this->json('GET', '/api/sessions', null, $sessionKey);
    }

    /**
     * 创建会话
     * @param array $meta 可选元数据（title / system_prompt 等，字段名以探测结论为准，多余字段 Hermes 会忽略）
     */
    public function createSession(array $meta = [], string $sessionKey = self::SESSION_KEY_ADMIN)
    {
        return $this->json('POST', '/api/sessions', $meta, $sessionKey);
    }

    /** 会话详情 */
    public function getSession(string $id, string $sessionKey = self::SESSION_KEY_ADMIN)
    {
        return $this->json('GET', '/api/sessions/' . rawurlencode($id), null, $sessionKey);
    }

    /** 更新会话（title 等） */
    public function updateSession(string $id, array $patch, string $sessionKey = self::SESSION_KEY_ADMIN)
    {
        return $this->json('PATCH', '/api/sessions/' . rawurlencode($id), $patch, $sessionKey);
    }

    /** 删除会话 */
    public function deleteSession(string $id, string $sessionKey = self::SESSION_KEY_ADMIN)
    {
        return $this->json('DELETE', '/api/sessions/' . rawurlencode($id), null, $sessionKey);
    }

    /** 会话消息历史 */
    public function sessionMessages(string $id, string $sessionKey = self::SESSION_KEY_ADMIN)
    {
        return $this->json('GET', '/api/sessions/' . rawurlencode($id) . '/messages', null, $sessionKey);
    }

    /** 分叉会话 */
    public function forkSession(string $id, string $sessionKey = self::SESSION_KEY_ADMIN)
    {
        return $this->json('POST', '/api/sessions/' . rawurlencode($id) . '/fork', null, $sessionKey);
    }

    // ------------------------------------------------------------
    // 对话
    // ------------------------------------------------------------

    /**
     * 会话内流式对话：透传 Hermes SSE，每条原始 data 行回调 $onLine
     * @return array{ok:bool, error:string}
     */
    public function chatStream(string $sessionId, string $message, callable $onLine, string $sessionKey = self::SESSION_KEY_ADMIN, float $timeout = 120.0)
    {
        $this->streamSink = $onLine;
        $res = $this->request('POST', '/api/sessions/' . rawurlencode($sessionId) . '/chat/stream',
            ['message' => $message], $sessionKey, true, $timeout);
        $this->streamSink = null;
        return $res;
    }

    /**
     * 会话内非流式对话（记忆写入/验证用）
     * @return array{ok:bool, data:array|null, answer:string, error:string}
     */
    public function chatOnce(string $sessionId, string $message, string $sessionKey = self::SESSION_KEY_ADMIN, float $timeout = 60.0)
    {
        $raw = '';
        $this->streamSink = function ($line) use (&$raw) { $raw .= $line . "\n"; };
        $res = $this->request('POST', '/api/sessions/' . rawurlencode($sessionId) . '/chat',
            ['message' => $message], $sessionKey, true, $timeout);
        $this->streamSink = null;
        if (!$res['ok']) return ['ok' => false, 'data' => null, 'answer' => '', 'error' => $res['error']];

        // 优先按 JSON 解析；失败则从 SSE 行里聚合文本
        $data = json_decode($raw, true);
        if (is_array($data)) {
            $answer = (string)($data['content'] ?? $data['message'] ?? $data['response'] ?? $data['choices'][0]['message']['content'] ?? '');
            return ['ok' => true, 'data' => $data, 'answer' => $answer, 'error' => ''];
        }
        $answer = '';
        foreach (preg_split('/\r?\n/', $raw) as $line) {
            $line = trim($line);
            if (strpos($line, 'data:') !== 0) continue;
            $json = trim(substr($line, 5));
            if ($json === '' || $json === '[DONE]') continue;
            $obj = json_decode($json, true);
            if (!is_array($obj)) continue;
            $answer .= (string)($obj['content'] ?? $obj['delta'] ?? $obj['text']
                    ?? $obj['choices'][0]['delta']['content'] ?? $obj['choices'][0]['message']['content'] ?? '');
        }
        if (trim($answer) === '') {
            return ['ok' => false, 'data' => null, 'answer' => '', 'error' => '响应解析失败: ' . mb_substr($raw, 0, 200)];
        }
        return ['ok' => true, 'data' => null, 'answer' => $answer, 'error' => ''];
    }

    // ------------------------------------------------------------
    // HTTP 基础
    // ------------------------------------------------------------

    private function json(string $method, string $path, ?array $body, string $sessionKey, float $timeout = self::TIMEOUT_HTTP)
    {
        $res = $this->request($method, $path, $body, $sessionKey, false, $timeout);
        if (!$res['ok']) return null;
        $data = json_decode($res['body'], true);
        if (!is_array($data)) { $this->lastError = '响应非 JSON: ' . mb_substr($res['body'], 0, 200); return null; }
        return $data;
    }

    /** @return array{ok:bool, body:string, error:string} */
    private function request(string $method, string $path, ?array $body, string $sessionKey, bool $stream, float $timeout)
    {
        $url = $this->baseUrl . $path;
        $this->lastError = '';
        $this->lastHttpCode = 0;
        $headers = ['Content-Type: application/json', 'Authorization: Bearer ' . $this->apiKey];
        if ($sessionKey !== '') $headers[] = 'X-Hermes-Session-Key: ' . $sessionKey;

        $ch = curl_init($url);
        $opts = [
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_CONNECTTIMEOUT => self::TIMEOUT_CONNECT,
            CURLOPT_TIMEOUT        => (int)$timeout,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_SSL_VERIFYPEER => false,
        ];
        if ($body !== null) $opts[CURLOPT_POSTFIELDS] = json_encode($body, JSON_UNESCAPED_UNICODE);
        if ($stream && $this->streamSink !== null) {
            // 注意：设置 WRITEFUNCTION 后不可再设 CURLOPT_RETURNTRANSFER=false（会重置 write method 导致回调失效）
            $sink = $this->streamSink;
            $opts[CURLOPT_WRITEFUNCTION] = function ($ch, $data) use ($sink) {
                call_user_func($sink, $data);
                return strlen($data);
            };
        } else {
            $opts[CURLOPT_RETURNTRANSFER] = true;
        }
        curl_setopt_array($ch, $opts);
        $resp = curl_exec($ch);
        $errno = curl_errno($ch);
        $errmsg = curl_error($ch);
        $this->lastHttpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno !== 0) {
            $this->lastError = 'Hermes 网络错误(' . $errno . '): ' . $errmsg . ' [' . $url . ']';
            return ['ok' => false, 'body' => '', 'error' => $this->lastError];
        }
        if ($this->lastHttpCode >= 400) {
            $snippet = is_string($resp) ? mb_substr($resp, 0, 200) : '';
            $this->lastError = 'HTTP ' . $this->lastHttpCode . ' [' . $url . ']' . ($snippet !== '' ? ': ' . $snippet : '');
            return ['ok' => false, 'body' => (string)$resp, 'error' => $this->lastError];
        }
        return ['ok' => true, 'body' => is_string($resp) ? $resp : '', 'error' => ''];
    }
}
