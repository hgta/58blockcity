<?php
/**
 * Embedding 渠道（OpenAI 兼容 /embeddings 协议）
 * change: help-semantic-rag (task 2.3)
 *
 * - 复用 ai_providers 表（purpose='embedding' 行）与 SecureCrypto 加密存储
 * - 与 AiProvider 分工：聊天路由只取 purpose='chat'，嵌入渠道仅供检索层调用
 * - 端点归一化与 AiProvider 相同：结尾非 /vN 时补 /v1，
 *   /api/v3、/api/plan/v3、/api/coding/v3 等以 /v3 结尾的写法原样保留
 * - 批量嵌入：每请求最多 16 条，超出自动分批
 */
class EmbeddingProvider
{
    /** @var array 渠道行 */
    private $row;
    /** @var string 解密后的 API Key */
    private $apiKey;

    const TIMEOUT_CONNECT = 5;
    const TIMEOUT_TOTAL   = 30;
    // 方舟 /embeddings 单请求上限 10 条（实测：超过报 InvalidParameter "max 10, got 11"）
    const BATCH_SIZE      = 10;

    public function __construct(array $row)
    {
        $this->row = $row;
        $this->apiKey = $this->row['api_key_cipher'] !== '' ? SecureCrypto::decrypt($this->row['api_key_cipher']) : '';
    }

    public function id()    { return (int)$this->row['id']; }
    public function name()  { return $this->row['name']; }
    public function model() { return $this->row['model']; }
    /** 明文 Key（仅供服务端同进程调用方使用，勿输出到前端/日志） */
    public function apiKey() { return $this->apiKey; }

    private function baseUrl()
    {
        $u = rtrim(trim($this->row['endpoint']), '/');
        if (preg_match('#/embeddings$#', $u)) $u = preg_replace('#/embeddings$#', '', $u);
        if (!preg_match('#/v\d+$#', $u)) $u .= '/v1';
        return $u;
    }

    /** 归一化后的完整请求 URL（后台测试展示用） */
    public function endpointUrl()
    {
        return $this->baseUrl() . '/embeddings';
    }

    /**
     * 批量嵌入
     * @param string[] $texts
     * @return array{ok:bool, vectors:array<array<float>>, dim:int, error:string}
     */
    public function embed(array $texts)
    {
        $texts = array_values($texts);
        if (!$texts) return ['ok' => true, 'vectors' => [], 'dim' => 0, 'error' => ''];

        $all = [];
        $dim = 0;
        foreach (array_chunk($texts, self::BATCH_SIZE) as $batch) {
            $res = $this->requestBatch($batch);
            if (!$res['ok']) return ['ok' => false, 'vectors' => [], 'dim' => 0, 'error' => $res['error']];
            foreach ($res['vectors'] as $v) $all[] = $v;
            if ($dim === 0) $dim = $res['dim'];
        }
        return ['ok' => true, 'vectors' => $all, 'dim' => $dim, 'error' => ''];
    }

    /** @return array{ok:bool, vectors:array, dim:int, error:string} */
    private function requestBatch(array $texts)
    {
        $url = $this->baseUrl() . '/embeddings';
        $payload = json_encode([
            'model' => $this->row['model'],
            'input' => $texts,
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
            CURLOPT_TIMEOUT        => self::TIMEOUT_TOTAL,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_RETURNTRANSFER => true,
        ]);
        $body = curl_exec($ch);
        $errno = curl_errno($ch);
        $errmsg = curl_error($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $this->touch($errno === 0 && $httpCode >= 200 && $httpCode < 300);

        if ($errno !== 0) {
            return ['ok' => false, 'vectors' => [], 'dim' => 0, 'error' => '网络错误(' . $errno . '): ' . $errmsg . ' [' . $url . ']'];
        }
        if ($httpCode >= 400) {
            return ['ok' => false, 'vectors' => [], 'dim' => 0, 'error' => 'HTTP ' . $httpCode . ' [' . $url . ']: ' . mb_substr((string)$body, 0, 200)];
        }
        $obj = json_decode((string)$body, true);
        if (!is_array($obj) || !isset($obj['data']) || !is_array($obj['data'])) {
            return ['ok' => false, 'vectors' => [], 'dim' => 0, 'error' => '响应解析失败: ' . mb_substr((string)$body, 0, 200)];
        }
        // 按 index 归位（部分网关不保序）
        $indexed = [];
        $dim = 0;
        foreach ($obj['data'] as $d) {
            if (!isset($d['embedding']) || !is_array($d['embedding'])) continue;
            $idx = (int)($d['index'] ?? count($indexed));
            $indexed[$idx] = array_map('floatval', $d['embedding']);
            $dim = max($dim, count($d['embedding']));
        }
        ksort($indexed);
        if (count($indexed) !== count($texts)) {
            return ['ok' => false, 'vectors' => [], 'dim' => 0, 'error' => '返回条数不符（期望 ' . count($texts) . '，实得 ' . count($indexed) . '）'];
        }
        return ['ok' => true, 'vectors' => array_values($indexed), 'dim' => $dim, 'error' => ''];
    }

    /**
     * 当前可用的嵌入渠道（purpose='embedding' 且启用且未超限），无则 null
     * @return EmbeddingProvider|null
     */
    public static function pick(PDO $db)
    {
        try {
            $rows = $db->query(
                "SELECT * FROM ai_providers
                 WHERE is_enabled = 1 AND purpose = 'embedding'
                 ORDER BY is_default DESC, sort_order, id LIMIT 1"
            )->fetchAll();
        } catch (Exception $ex) {
            return null;
        }
        foreach ($rows as $r) {
            $p = new EmbeddingProvider($r);
            if (!$p->quotaExceeded()) return $p;
        }
        return null;
    }

    /** 当日额度是否已用尽 */
    public function quotaExceeded()
    {
        $this->rolloverCounter();
        return $this->row['daily_limit'] > 0 && $this->row['call_count_today'] >= $this->row['daily_limit'];
    }

    private function rolloverCounter()
    {
        $today = date('Y-m-d');
        if ($this->row['count_date'] !== $today) {
            $this->row['count_date'] = $today;
            $this->row['call_count_today'] = 0;
        }
    }

    /** 更新渠道调用计数与失败时间（与 AiProvider 同口径，供后台用量展示） */
    private function touch($success)
    {
        try {
            $this->rolloverCounter();
            $newCount = $this->row['call_count_today'] + 1;
            $this->row['call_count_today'] = $newCount;
            $sql = $success
                ? "UPDATE ai_providers SET call_count_today = :c, count_date = :d, failed_at = NULL WHERE id = :id"
                : "UPDATE ai_providers SET call_count_today = :c, count_date = :d, failed_at = NOW() WHERE id = :id";
            $this->db()->prepare($sql)->execute([':c' => $newCount, ':d' => $this->row['count_date'], ':id' => $this->row['id']]);
        } catch (Exception $ex) { /* 计数失败不影响主流程 */ }
    }

    private function db()
    {
        global $pdo;
        return $pdo;
    }

    /** 连通性测试（后台用）：嵌入一条短文本，返回维度 */
    public static function testConnection(array $row)
    {
        $p = new EmbeddingProvider($row);
        $res = $p->embed(['连接测试']);
        if ($res['ok']) {
            return ['ok' => true, 'answer' => '连接成功，嵌入维度 ' . $res['dim'], 'error' => ''];
        }
        return ['ok' => false, 'answer' => '', 'error' => $res['error']];
    }
}
