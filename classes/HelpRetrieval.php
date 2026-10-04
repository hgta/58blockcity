<?php
/**
 * 小帮混合检索：ngram FULLTEXT（词面）+ 向量 cosine（语义）→ RRF 融合
 * change: help-semantic-rag (task 4.1 / 4.3)
 *
 * - 两路都在 help_chunks 上跑（同一单位），各取 LEG_K，RRF(k=60) 融合取 topN
 * - 命中判定：向量路可用时取融合 top1 的 cosine ≥ 阈值；向量路失败降级为
 *   纯 ngram（ok=true, mode='ngram'），此时命中判定沿用"FULLTEXT 是否有结果"
 * - 规模假设（design D2）：数百~两千块。向量全量扫描 + PHP 余弦，请求内静态缓存；
 *   千块 × 2048 维实测预期 <300ms，超预期再引入 APCu/缓存层（本类为唯一替换点）
 */
class HelpRetrieval
{
    const RRF_K = 60;  // RRF 常数
    const LEG_K = 10;  // 每路取前 K

    /** @var PDO */
    private $db;
    /** @var EmbeddingProvider|null 注入的嵌入渠道（缺省自动 pick；测试注入桩） */
    private $emb;
    /** @var array|null 块向量缓存 [id => ['blob'=>string,'dim'=>int]] */
    private static $vecCache = null;

    public function __construct(PDO $db, EmbeddingProvider $emb = null)
    {
        $this->db = $db;
        $this->emb = $emb;
    }

    /**
     * 混合检索
     * @param string $query 用户问题
     * @param int $topN 融合后取前 N（沿用 ai_rag_topn）
     * @param float $minScore 命中阈值（向量余弦 0~1，ai_semantic_min_score）
     * @return array{
     *   ok: bool,               // false = 完全不可用（两路皆失败）
     *   mode: 'hybrid'|'ngram', // 实际使用的模式（ngram = 向量路降级）
     *   matched: bool,          // 是否命中知识库（兜底/记 unmatched 的依据）
     *   error: string,
     *   chunks: array<array{id,source_type,source_id,title,text,score,cosine}> // 已按融合分排序
     * }
     */
    public function search($query, $topN = 3, $minScore = 0.45)
    {
        $query = trim((string)$query);
        $topN = max(1, min(10, (int)$topN));

        // ---- 词面腿：ngram FULLTEXT ----
        $ngram = $this->ngramSearch($query, self::LEG_K); // [chunkId => rank(1..)]

        // ---- 语义腿：查询向量 + 全量 cosine ----
        $qvec = null;
        $vecErr = '';
        $emb = $this->emb ?: EmbeddingProvider::pick($this->db);
        if ($emb) {
            $r = $emb->embed([$query]);
            if ($r['ok'] && isset($r['vectors'][0])) {
                $qvec = $r['vectors'][0];
            } else {
                $vecErr = $r['error'];
            }
        }

        if ($qvec === null) {
            // ---- 降级：纯 ngram（任务 4.3）----
            if ($vecErr !== '') error_log('[help-semantic-rag] 向量路降级为纯ngram: ' . $vecErr);
            $chunks = $this->materialize(array_slice($ngram, 0, $topN, true), null);
            return [
                'ok' => true, 'mode' => 'ngram',
                'matched' => !empty($ngram),
                'error' => $vecErr,
                'chunks' => $chunks,
            ];
        }

        // ---- 向量腿 ----
        $vector = $this->vectorSearch($qvec, self::LEG_K); // [chunkId => [rank, cosine]]

        // ---- RRF 融合 ----
        $fused = [];
        foreach ($ngram as $cid => $rank) {
            $fused[$cid] = ($fused[$cid] ?? 0) + 1 / (self::RRF_K + $rank);
        }
        foreach ($vector as $cid => $info) {
            $fused[$cid] = ($fused[$cid] ?? 0) + 1 / (self::RRF_K + $info['rank']);
        }
        arsort($fused);
        $top = array_slice($fused, 0, $topN, true);

        // ---- 命中判定：融合 top1 的 cosine ≥ 阈值 ----
        $matched = false;
        $topId = key($top);
        if ($topId !== null) {
            $best = $this->cosineOf($qvec, (int)$topId);
            if ($best !== null) $matched = $best >= $minScore;
        }
        // 向量完全无结果且词面也无结果 → 未命中
        if (empty($top)) $matched = false;

        $cosMap = [];
        foreach ($vector as $cid => $info) $cosMap[$cid] = $info['cosine'];
        $chunks = $this->materialize($top, $cosMap);

        return ['ok' => true, 'mode' => 'hybrid', 'matched' => $matched, 'error' => '', 'chunks' => $chunks];
    }

    /** ngram FULLTEXT 检索，返回 [chunkId => rank]（rank 从 1 起） */
    private function ngramSearch($query, $k)
    {
        if ($query === '') return [];
        try {
            $stmt = $this->db->prepare(
                "SELECT id FROM help_chunks
                 WHERE MATCH(title, chunk_text) AGAINST(? IN NATURAL LANGUAGE MODE)
                 ORDER BY MATCH(title, chunk_text) AGAINST(? IN NATURAL LANGUAGE MODE) DESC
                 LIMIT " . (int)$k
            );
            $stmt->execute([$query, $query]);
            $out = [];
            $rank = 1;
            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $id) {
                $out[(int)$id] = $rank++;
            }
            return $out;
        } catch (Exception $ex) {
            return [];
        }
    }

    /** 全量 cosine，返回 [chunkId => ['rank'=>int,'cosine'=>float]] */
    private function vectorSearch(array $qvec, $k)
    {
        $cache = $this->loadVectorCache();
        $scored = [];
        $qdim = count($qvec);
        foreach ($cache as $id => $c) {
            if ($c['dim'] !== $qdim) continue; // 换模型后维度不符的旧块跳过（等 CLI 重建）
            $cos = self::cosine($qvec, $c['blob']);
            if ($cos !== null) $scored[$id] = $cos;
        }
        arsort($scored);
        $out = [];
        $rank = 1;
        foreach (array_slice($scored, 0, $k, true) as $id => $cos) {
            $out[(int)$id] = ['rank' => $rank++, 'cosine' => $cos];
        }
        return $out;
    }

    /** 单块 cosine（命中判定用） */
    private function cosineOf(array $qvec, $chunkId)
    {
        $cache = $this->loadVectorCache();
        if (!isset($cache[$chunkId]) || $cache[$chunkId]['dim'] !== count($qvec)) return null;
        return self::cosine($qvec, $cache[$chunkId]['blob']);
    }

    /** 重置请求内向量缓存（长跑脚本/测试在数据变更后调用） */
    public static function resetCache()
    {
        self::$vecCache = null;
    }

    /** 请求内静态缓存全量向量（id => blob+dim，不含正文以省内存） */
    private function loadVectorCache()
    {
        if (self::$vecCache !== null) return self::$vecCache;
        self::$vecCache = [];
        try {
            $rows = $this->db->query(
                "SELECT id, dim, embedding FROM help_chunks WHERE embedding IS NOT NULL AND dim > 0"
            );
            foreach ($rows as $r) {
                self::$vecCache[(int)$r['id']] = ['blob' => $r['embedding'], 'dim' => (int)$r['dim']];
            }
        } catch (Exception $ex) {
            self::$vecCache = [];
        }
        return self::$vecCache;
    }

    /**
     * 查询向量(0索引 float[]) × 块向量(pack f* BLOB) 的余弦相似度
     * @return float|null
     */
    public static function cosine(array $qvec, $blob)
    {
        $v = @unpack('f*', $blob);
        if ($v === false || count($v) !== count($qvec)) return null;
        $dot = 0.0; $nq = 0.0; $nv = 0.0;
        foreach ($v as $i => $x) {
            $q = $qvec[$i - 1];
            $dot += $x * $q;
            $nq  += $q * $q;
            $nv  += $x * $x;
        }
        if ($nq <= 0 || $nv <= 0) return null;
        return $dot / (sqrt($nq) * sqrt($nv));
    }

    /** 取块元数据并组装结果（cosMap 可选补 cosine 展示值） */
    private function materialize(array $rankedIds, $cosMap)
    {
        if (!$rankedIds) return [];
        $ids = array_map('intval', array_keys($rankedIds));
        $in = implode(',', $ids);
        $rows = $this->db->query("SELECT id, source_type, source_id, title, chunk_text FROM help_chunks WHERE id IN ({$in})");
        $byId = [];
        foreach ($rows as $r) $byId[(int)$r['id']] = $r;
        $out = [];
        foreach ($rankedIds as $cid => $rrfScore) {
            if (!isset($byId[$cid])) continue;
            $r = $byId[$cid];
            $out[] = [
                'id'          => (int)$r['id'],
                'source_type' => $r['source_type'],
                'source_id'   => (int)$r['source_id'],
                'title'       => $r['title'],
                'text'        => $r['chunk_text'],
                'score'       => round((float)$rrfScore, 6),
                'cosine'      => isset($cosMap[$cid]) ? round((float)$cosMap[$cid], 4) : null,
            ];
        }
        return $out;
    }
}
