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

    /**
     * 流式调用的"首字节超时"：建立连接后多久还没吐出第一个字就判定该渠道失败并切换。
     * 实测背景（2026-10-05）：某些渠道/模型（如带深度思考的 ark-code-latest）会先"思考"
     * 数十秒才输出第一个字，前台用户会干等一分半。与其让用户等，不如快速切到下一个渠道。
     * 注意：只作用于"首字节之前"，已开始输出后不再打断（避免截断正常回答）。
     * 设为 0 表示关闭此保护。
     */
    public static $firstByteTimeout = 8.0;

    public function __construct(array $row)
    {
        $this->row = $row;
        $this->apiKey = $this->row['api_key_cipher'] !== '' ? SecureCrypto::decrypt($this->row['api_key_cipher']) : '';
    }

    public function id()      { return (int)$this->row['id']; }
    public function name()    { return $this->row['name']; }
    public function model()   { return $this->row['model']; }
    public function preset()  { return (string)($this->row['preset'] ?? ''); }

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
    /**
     * 非流式对话（一次拿全）
     * @param string|null $modelOverride 临时覆盖渠道默认模型（后台小任务可指定快模型）
     */
    public function chatOnce(array $messages, float $timeout = 30.0, $modelOverride = null)
    {
        return $this->request($messages, false, null, $timeout, $modelOverride);
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

    private function request(array $messages, $stream, $onChunk, $timeout, $modelOverride = null)
    {
        $url = $this->baseUrl() . '/chat/completions';
        $payload = json_encode([
            'model'    => $modelOverride ?: $this->row['model'],
            'messages' => $messages,
            'stream'   => $stream,
        ], JSON_UNESCAPED_UNICODE);

        $ch = curl_init($url);
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey,
        ];
        // Hermes 渠道：记忆分区隔离（前台小帮共享 assistant 区），配合 api/ai/chat.php 的记忆禁写令
        if (($this->row['preset'] ?? '') === 'hermes') {
            $headers[] = 'X-Hermes-Session-Key: web:58tl:assistant';
        }
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_CONNECTTIMEOUT => self::TIMEOUT_CONNECT,
            CURLOPT_TIMEOUT        => (int)$timeout,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        // 流式：首字节保护——长时间一个字都不吐（如深度思考模型先想 90s）就中止，
        // 让上层能快速切到下一个渠道，而不是让用户干等
        if ($stream && self::$firstByteTimeout > 0) {
            curl_setopt($ch, CURLOPT_LOW_SPEED_LIMIT, 1);
            curl_setopt($ch, CURLOPT_LOW_SPEED_TIME, (int)ceil(self::$firstByteTimeout));
        }

        $answer = '';
        $errBuf = '';
        $self = $this;
        $startAt = microtime(true);
        // 首字节（正文首字）保护：思考型模型可能先流式吐 reasoning_content，
        // 用户屏幕仍是空白——所以这里以"解析出正文 content"为界，而不是"收到任何字节"
        $contentLimit = ($stream && self::$firstByteTimeout > 0) ? self::$firstByteTimeout : 0;

        $write = function ($ch, $data) use ($stream, $onChunk, &$answer, &$errBuf, $self, $startAt, $contentLimit) {
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
                // 只取正文；reasoning_content 是思考过程，不计入回答、也不解除保护
                $delta = $obj['choices'][0]['delta']['content']
                      ?? $obj['choices'][0]['message']['content']
                      ?? '';
                if ($delta !== '') {
                    $answer .= $delta;
                    if ($onChunk) call_user_func($onChunk, $delta);
                }
            }

            // 仍在等正文且超过限制 → 中断传输（返回非长度值即让 curl 以 WRITE_ERROR 结束）
            if ($contentLimit > 0 && $answer === '' && (microtime(true) - $startAt) > $contentLimit) {
                return 0;
            }
            return $len;
        };
        curl_setopt($ch, CURLOPT_WRITEFUNCTION, $write);
        // 注意：不要在此设置 CURLOPT_RETURNTRANSFER=false——PHP curl 中它会将 write method
        // 重置为 STDOUT，导致上面的 WRITEFUNCTION 回调被丢弃、响应体直接漏出到输出
        // （默认即 false，无需显式设置）

        curl_exec($ch);
        $errno = curl_errno($ch);
        $errmsg = curl_error($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $this->touch($errno === 0 && $httpCode >= 200 && $httpCode < 300 && $answer !== '');

        if ($errno !== 0) {
            // 流式且正文一个字都没出现 → 首字节（正文）超时：模型在长时间思考或只吐思考过程
            $isFirstByteTimeout = $stream && $answer === '' && self::$firstByteTimeout > 0
                && ($errno === CURLE_OPERATION_TIMEOUTED || $errno === CURLE_WRITE_ERROR);
            if ($isFirstByteTimeout) {
                return ['ok' => false, 'answer' => '', 'error' => '正文首字节超时（' . self::$firstByteTimeout
                    . 's 内未输出正文，模型可能在长时间思考）[' . $url . ']'];
            }
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
        // 无 DB 上下文（CLI 探针/测试）直接跳过，避免 PDO 为 null 产生致命错误
        if (!($this->db() instanceof PDO)) return;
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

    /** 可用渠道列表：默认渠道优先，其余按 sort_order（仅聊天渠道，嵌入渠道见 EmbeddingProvider） */
    public static function routeList(PDO $db)
    {
        $rows = $db->query(
            "SELECT * FROM ai_providers WHERE is_enabled = 1 AND (purpose = 'chat' OR purpose IS NULL) ORDER BY is_default DESC, sort_order, id"
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
    public static function chatWithFailover(PDO $db, array $messages, callable $onChunk, $firstByteTimeout = null)
    {
        // 首字节超时可通过 system_settings: ai_chat_first_byte_timeout 调整（0=关闭保护）
        if ($firstByteTimeout === null) {
            try {
                $v = $db->query("SELECT setting_value FROM system_settings WHERE setting_key = 'ai_chat_first_byte_timeout'")->fetchColumn();
                if ($v !== false && $v !== null && $v !== '') $firstByteTimeout = (float)$v;
            } catch (Exception $ex) { /* 读不到用默认 */ }
        }
        $oldFirstByte = self::$firstByteTimeout;
        if ($firstByteTimeout !== null) self::$firstByteTimeout = max(0, $firstByteTimeout);

        $lastErr = '没有可用的 AI 渠道';
        $emitted = false;
        $wrapped = function ($delta) use ($onChunk, &$emitted) {
            $emitted = true;
            call_user_func($onChunk, $delta);
        };
        foreach (self::routeList($db) as $p) {
            $res = $p->chatStream($messages, $wrapped);
            if ($res['ok']) {
                self::$firstByteTimeout = $oldFirstByte;
                return ['ok' => true, 'provider' => $p, 'answer' => $res['answer'], 'error' => ''];
            }
            $lastErr = '[' . $p->name() . '] ' . $res['error'];
            if ($emitted) {
                self::$firstByteTimeout = $oldFirstByte;
                return ['ok' => false, 'provider' => $p, 'answer' => $res['answer'],
                        'error' => $lastErr . '（内容已部分输出，未切换渠道）'];
            }
        }
        self::$firstByteTimeout = $oldFirstByte;
        return ['ok' => false, 'provider' => null, 'answer' => '', 'error' => $lastErr];
    }

    /**
     * 非流式 + 故障切换（后台短任务用：摘要/slug 生成等）
     *
     * 与 chatWithFailover（流式、单渠道 60s）的区别：
     * - 每个渠道单独计时、单独超时，某个渠道"卡住不返回"时立即判定失败并切下一个，
     *   不会把整个请求拖到 PHP max_execution_time（避免"网络异常"的黑盒失败）
     * - $preferDirect=true 时把本机 Hermes（完整 agent 循环，这类短任务很慢）排到最后，
     *   优先用直连模型渠道，显著降低等待时间
     * - 返回每个渠道的尝试明细，便于后台提示"用了哪个渠道/为什么切换"
     *
     * @return array{ok:bool, answer:string, provider:?AiProvider, error:string, attempts:array}
     */
    public static function chatOnceWithFailover(PDO $db, array $messages, float $timeout = 30.0, $preferDirect = false, $modelOverride = null)
    {
        $list = self::routeList($db);
        if ($preferDirect) {
            // 稳定排序：非 hermes 渠道在前，hermes 殿后（保持各自原有相对顺序）
            $direct = [];
            $local  = [];
            foreach ($list as $p) {
                if ($p->preset() === 'hermes') $local[] = $p;
                else $direct[] = $p;
            }
            $list = array_merge($direct, $local);
        }

        $attempts = [];
        $lastErr = '没有可用的 AI 渠道';
        foreach ($list as $p) {
            $t0 = microtime(true);
            $res = $p->chatOnce($messages, $timeout, $modelOverride);
            $ms = (int)round((microtime(true) - $t0) * 1000);
            $attempts[] = [
                'name' => $p->name(),
                'ok' => $res['ok'],
                'ms' => $ms,
                'error' => $res['error'],
            ];
            if ($res['ok']) {
                return ['ok' => true, 'answer' => $res['answer'], 'provider' => $p, 'error' => '', 'attempts' => $attempts];
            }
            $lastErr = '[' . $p->name() . '] ' . $res['error'];
        }
        return ['ok' => false, 'answer' => '', 'provider' => null, 'error' => $lastErr, 'attempts' => $attempts];
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
