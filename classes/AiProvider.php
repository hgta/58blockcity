<?php
/**
 * AI Provider 抽象层（OpenAI 兼容协议）
 * change: help-center-ai-assistant (task 3.1)
 *
 * - 全部渠道走 /chat/completions 协议（DeepSeek/通义/Kimi/智谱/OpenAI/Hermes Agent 均兼容）
 * - 故障切换：按 is_default 优先、再按 sort_order，失败自动尝试下一渠道
 * - 流式：chatStream() 以回调逐块返回；非流式：chatOnce()（测试连接/草稿生成用）
 * - 每日限额：call_count_today 按日重置，达到 daily_limit(>0) 跳过该渠道
 */

class AiProvider
{
    /** @var array 渠道行 */
    private $row;
    /** @var string 解密后的 API Key */
    private $apiKey;

    const TIMEOUT_CONNECT = 5;
    const TIMEOUT_TOTAL   = 60;

    public function __construct(array $row)
    {
        $this->row = $row;
        $this->apiKey = $this->row['api_key_cipher'] !== '' ? SecureCrypto::decrypt($this->row['api_key_cipher']) : '';
    }

    public function id()      { return (int)$this->row['id']; }
    public function name()    { return $this->row['name']; }
    public function model()   { return $this->row['model']; }

    /** 当日额度是否已用尽（daily_limit=0 表示不限） */
    public function quotaExceeded()
    {
        $this->rolloverCounter();
        return $this->row['daily_limit'] > 0 && $this->row['call_count_today'] >= $this->row['daily_limit'];
    }

    /** 跨天重置计数 */
    private function rolloverCounter()
    {
        $today = date('Y-m-d');
        if ($this->row['count_date'] !== $today) {
            $this->row['count_date'] = $today;
            $this->row['call_count_today'] = 0;
        }
    }

    private function baseUrl()
    {
        $u = rtrim(trim($this->row['endpoint']), '/');
        // 容错：用户填了完整路径则先剥离，避免拼出 .../chat/completions/v1/chat/completions
        if (preg_match('#/chat/completions$#', $u)) $u = preg_replace('#/chat/completions$#', '', $u);
        // 允许填到域名或 /v1，统一补全到 /v1
        if (!preg_match('#/v\d+$#', $u)) $u .= '/v1';
        return $u;
    }

    /** 归一化后的完整请求 URL（后台测试展示用） */
    public function endpointUrl()
    {
        return $this->baseUrl() . '/chat/completions';
    }

    /**
     * 非流式对话（一次拿全）
     * @return array{ok:bool, answer:string, error:string}
     */
    public function chatOnce(array $messages, float $timeout = 30.0)
    {
        return $this->request($messages, false, null, $timeout);
    }

    /**
     * 流式对话
     * @param callable $onChunk function(string $delta)
     * @return array{ok:bool, answer:string, error:string}
     */
    public function chatStream(array $messages, callable $onChunk)
    {
        return $this->request($messages, true, $onChunk, self::TIMEOUT_TOTAL);
    }

    private function request(array $messages, $stream, $onChunk, $timeout)
    {
        $url = $this->baseUrl() . '/chat/completions';
        $payload = json_encode([
            'model'    => $this->row['model'],
            'messages' => $messages,
            'stream'   => $stream,
        ], JSON_UNESCAPED_UNICODE);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey,
            ],
            CURLOPT_CONNECTTIMEOUT => self::TIMEOUT_CONNECT,
            CURLOPT_TIMEOUT        => (int)$timeout,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);

        $answer = '';
        $errBuf = '';
        $self = $this;

        $write = function ($ch, $data) use ($stream, $onChunk, &$answer, &$errBuf, $self) {
            $len = strlen($data);
            if (!$stream) { $answer .= $data; return $len; }

            // SSE: 多行 "data: {...}"
            foreach (preg_split('/\r?\n/', $data) as $line) {
                $line = trim($line);
                if ($line === '' || strpos($line, 'data:') !== 0) continue;
                $json = trim(substr($line, 5));
                if ($json === '[DONE]') continue;
                $obj = json_decode($json, true);
                if (!is_array($obj)) continue;
                if (isset($obj['error'])) {
                    $errBuf .= is_string($obj['error']) ? $obj['error'] : json_encode($obj['error'], JSON_UNESCAPED_UNICODE);
                    continue;
                }
                $delta = $obj['choices'][0]['delta']['content']
                      ?? $obj['choices'][0]['message']['content']
                      ?? '';
                if ($delta !== '') {
                    $answer .= $delta;
                    if ($onChunk) call_user_func($onChunk, $delta);
                }
            }
            return $len;
        };
        curl_setopt($ch, CURLOPT_WRITEFUNCTION, $write);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);

        curl_exec($ch);
        $errno = curl_errno($ch);
        $errmsg = curl_error($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $this->touch($errno === 0 && $httpCode >= 200 && $httpCode < 300 && $answer !== '');

        if ($errno !== 0) {
            return ['ok' => false, 'answer' => $answer, 'error' => '网络错误(' . $errno . '): ' . $errmsg . ' [' . $url . ']'];
        }
        if ($httpCode >= 400) {
            // 非流式时正文在 $answer（尚未解析），流式时错误摘要在 $errBuf
            $body = !$stream && $answer !== '' ? trim($answer) : $errBuf;
            return ['ok' => false, 'answer' => $answer, 'error' => 'HTTP ' . $httpCode . ' [' . $url . ']'
                . ($body !== '' ? ': ' . mb_substr($body, 0, 200) : '')];
        }
        if ($answer === '') {
            return ['ok' => false, 'answer' => '', 'error' => '空响应(HTTP ' . $httpCode . ') [' . $url . ']'
                . ($errBuf !== '' ? ': ' . mb_substr($errBuf, 0, 200) : '')];
        }

        // 非流式：解析 JSON 提取 answer
        if (!$stream) {
            $obj = json_decode($answer, true);
            $answer = is_array($obj) ? (string)($obj['choices'][0]['message']['content'] ?? '') : '';
            if ($answer === '') return ['ok' => false, 'answer' => '', 'error' => '响应解析失败'];
        }

        return ['ok' => true, 'answer' => $answer, 'error' => ''];
    }

    /** 更新渠道调用计数与失败时间 */
    private function touch($success)
    {
        try {
            $this->rolloverCounter();
            $newCount = $this->row['call_count_today'] + 1;
            $this->row['call_count_today'] = $newCount;
            if ($success) {
                $this->db()->prepare("UPDATE ai_providers SET call_count_today = :c, count_date = :d, failed_at = NULL WHERE id = :id")
                    ->execute([':c' => $newCount, ':d' => $this->row['count_date'], ':id' => $this->row['id']]);
            } else {
                $this->db()->prepare("UPDATE ai_providers SET call_count_today = :c, count_date = :d, failed_at = NOW() WHERE id = :id")
                    ->execute([':c' => $newCount, ':d' => $this->row['count_date'], ':id' => $this->row['id']]);
            }
        } catch (Exception $ex) { /* 计数失败不影响主流程 */ }
    }

    private function db()
    {
        global $pdo;
        return $pdo;
    }

    // ------------------------------------------------------------
    // 静态路由
    // ------------------------------------------------------------

    /** 可用渠道列表：默认渠道优先，其余按 sort_order */
    public static function routeList(PDO $db)
    {
        $rows = $db->query(
            "SELECT * FROM ai_providers WHERE is_enabled = 1 ORDER BY is_default DESC, sort_order, id"
        )->fetchAll();
        $list = [];
        foreach ($rows as $r) {
            $p = new AiProvider($r);
            if ($p->quotaExceeded()) continue;
            $list[] = $p;
        }
        return $list;
    }

    /**
     * 带故障切换的流式对话
     * 注：若某渠道已向客户端输出内容后中断，则不再切换（避免回答重复拼接）
     * @param callable $onChunk
     * @return array{ok:bool, provider:?AiProvider, answer:string, error:string}
     */
    public static function chatWithFailover(PDO $db, array $messages, callable $onChunk)
    {
        $lastErr = '没有可用的 AI 渠道';
        $emitted = false;
        $wrapped = function ($delta) use ($onChunk, &$emitted) {
            $emitted = true;
            call_user_func($onChunk, $delta);
        };
        foreach (self::routeList($db) as $p) {
            $res = $p->chatStream($messages, $wrapped);
            if ($res['ok']) {
                return ['ok' => true, 'provider' => $p, 'answer' => $res['answer'], 'error' => ''];
            }
            $lastErr = '[' . $p->name() . '] ' . $res['error'];
            if ($emitted) {
                return ['ok' => false, 'provider' => $p, 'answer' => $res['answer'],
                        'error' => $lastErr . '（内容已部分输出，未切换渠道）'];
            }
        }
        return ['ok' => false, 'provider' => null, 'answer' => '', 'error' => $lastErr];
    }

    /** 连通性测试（后台用） */
    public static function testConnection(array $row)
    {
        $p = new AiProvider($row);
        $res = $p->chatOnce([
            ['role' => 'user', 'content' => '你好，请回复"连接成功"'],
        ], 15.0);
        return $res;
    }
}
