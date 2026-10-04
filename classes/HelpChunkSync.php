<?php
/**
 * 知识块同步：切块 → hash 比对 → 增量嵌入 → upsert
 * change: help-semantic-rag (task 3.1 / 3.2)
 *
 * - 后台保存钩子（单源同步）与 CLI 全量重建（rebuildAll）共用本类
 * - 仅 published 内容入库为块；非发布态/删除 → 清块
 * - content_hash 未变的块不重嵌（省 API 费）；换嵌入模型后需 CLI 全量重建
 * - 向量以 pack('f*') 序列化 BLOB，dim 冗余存储供读取端校验
 */
class HelpChunkSync
{
    /**
     * 同步单篇文章（非 published 清除其全部块）
     * @return array{total:int, embedded:int, kept:int, dropped:int}
     */
    public static function syncArticle(PDO $db, EmbeddingProvider $emb, $articleId)
    {
        $stmt = $db->prepare("SELECT * FROM help_articles WHERE id = ?");
        $stmt->execute([(int)$articleId]);
        $row = $stmt->fetch();
        if (!$row) {
            self::dropSource($db, 'article', (int)$articleId);
            return ['total' => 0, 'embedded' => 0, 'kept' => 0, 'dropped' => 0];
        }
        if ($row['status'] !== 'published') {
            $dropped = self::dropSource($db, 'article', (int)$articleId);
            return ['total' => 0, 'embedded' => 0, 'kept' => 0, 'dropped' => $dropped];
        }
        return self::writeChunks($db, $emb, 'article', (int)$articleId, HelpChunker::chunkArticle($row));
    }

    /** 同步单条 FAQ（仅 published） */
    public static function syncFaq(PDO $db, EmbeddingProvider $emb, $faqId)
    {
        $stmt = $db->prepare("SELECT * FROM help_faq WHERE id = ?");
        $stmt->execute([(int)$faqId]);
        $row = $stmt->fetch();
        if (!$row || $row['status'] !== 'published') {
            $dropped = self::dropSource($db, 'faq', (int)$faqId);
            return ['total' => 0, 'embedded' => 0, 'kept' => 0, 'dropped' => $dropped];
        }
        return self::writeChunks($db, $emb, 'faq', (int)$faqId, HelpChunker::chunkFaq($row));
    }

    /** 同步单条术语（无状态字段，恒同步） */
    public static function syncGlossary(PDO $db, EmbeddingProvider $emb, $glossaryId)
    {
        $stmt = $db->prepare("SELECT * FROM help_glossary WHERE id = ?");
        $stmt->execute([(int)$glossaryId]);
        $row = $stmt->fetch();
        if (!$row) {
            $dropped = self::dropSource($db, 'glossary', (int)$glossaryId);
            return ['total' => 0, 'embedded' => 0, 'kept' => 0, 'dropped' => $dropped];
        }
        return self::writeChunks($db, $emb, 'glossary', (int)$glossaryId, HelpChunker::chunkGlossary($row));
    }

    /** 删除某源的全部块，返回删除行数 */
    public static function dropSource(PDO $db, $type, $id)
    {
        $stmt = $db->prepare("DELETE FROM help_chunks WHERE source_type = ? AND source_id = ?");
        $stmt->execute([$type, (int)$id]);
        return $stmt->rowCount();
    }

    /**
     * 全量重建（CLI）：published 文章/FAQ + 全部术语，末尾清理孤儿块
     * @return array 汇总统计
     */
    public static function rebuildAll(PDO $db, EmbeddingProvider $emb, callable $log = null)
    {
        $say = function ($m) use ($log) { if ($log) call_user_func($log, $m); };
        $stats = ['sources' => 0, 'chunks' => 0, 'embedded' => 0, 'kept' => 0, 'dropped' => 0, 'failed' => 0];

        foreach ($db->query("SELECT id FROM help_articles WHERE status = 'published' ORDER BY id") as $r) {
            try {
                $s = self::syncArticle($db, $emb, $r['id']);
                $stats['sources']++; $stats['chunks'] += $s['total'];
                $stats['embedded'] += $s['embedded']; $stats['kept'] += $s['kept']; $stats['dropped'] += $s['dropped'];
                $say(sprintf('article #%d: %d 块（新嵌 %d，保留 %d）', $r['id'], $s['total'], $s['embedded'], $s['kept']));
            } catch (Exception $ex) {
                $stats['failed']++;
                $say('article #' . $r['id'] . ' 失败: ' . $ex->getMessage());
            }
        }
        foreach ($db->query("SELECT id FROM help_faq WHERE status = 'published' ORDER BY id") as $r) {
            try {
                $s = self::syncFaq($db, $emb, $r['id']);
                $stats['sources']++; $stats['chunks'] += $s['total'];
                $stats['embedded'] += $s['embedded']; $stats['kept'] += $s['kept']; $stats['dropped'] += $s['dropped'];
            } catch (Exception $ex) {
                $stats['failed']++;
                $say('faq #' . $r['id'] . ' 失败: ' . $ex->getMessage());
            }
        }
        foreach ($db->query("SELECT id FROM help_glossary ORDER BY id") as $r) {
            try {
                $s = self::syncGlossary($db, $emb, $r['id']);
                $stats['sources']++; $stats['chunks'] += $s['total'];
                $stats['embedded'] += $s['embedded']; $stats['kept'] += $s['kept']; $stats['dropped'] += $s['dropped'];
            } catch (Exception $ex) {
                $stats['failed']++;
                $say('glossary #' . $r['id'] . ' 失败: ' . $ex->getMessage());
            }
        }

        // 孤儿块清理：源已不存在/不再是 published
        try {
            $db->exec("DELETE c FROM help_chunks c
                       LEFT JOIN help_articles a ON a.id = c.source_id AND c.source_type = 'article'
                       LEFT JOIN help_faq f ON f.id = c.source_id AND c.source_type = 'faq'
                       LEFT JOIN help_glossary g ON g.id = c.source_id AND c.source_type = 'glossary'
                       WHERE (c.source_type = 'article' AND (a.id IS NULL OR a.status <> 'published'))
                          OR (c.source_type = 'faq' AND (f.id IS NULL OR f.status <> 'published'))
                          OR (c.source_type = 'glossary' AND g.id IS NULL)");
            $orphan = $db->query("SELECT ROW_COUNT()")->fetchColumn();
            $stats['dropped'] += (int)$orphan;
        } catch (Exception $ex) {
            $say('孤儿清理失败: ' . $ex->getMessage());
        }

        return $stats;
    }

    /**
     * 核心：与现有块 hash 比对，变更块批量重嵌后 upsert，多余块删除
     * @param array $chunks HelpChunker 输出（title/text）
     * @throws RuntimeException 嵌入失败
     */
    private static function writeChunks(PDO $db, EmbeddingProvider $emb, $type, $id, array $chunks)
    {
        $id = (int)$id;

        if (!$chunks) { // 切不出内容（如空文章）→ 清块
            return ['total' => 0, 'embedded' => 0, 'kept' => 0, 'dropped' => self::dropSource($db, $type, $id)];
        }

        $stmt = $db->prepare("SELECT chunk_no, content_hash FROM help_chunks WHERE source_type = ? AND source_id = ?");
        $stmt->execute([$type, $id]);
        $existing = [];
        foreach ($stmt->fetchAll() as $r) $existing[(int)$r['chunk_no']] = $r['content_hash'];

        // 待重嵌块（新增或 hash 变更）
        $pending = [];
        foreach ($chunks as $no => $c) {
            $hash = hash('sha256', $c['text']);
            if (!isset($existing[$no]) || $existing[$no] !== $hash) {
                $pending[$no] = ['text' => $c['text'], 'hash' => $hash];
            }
        }

        $vectors = [];
        $dim = 0;
        if ($pending) {
            $res = $emb->embed(array_map(function ($p) { return $p['text']; }, $pending));
            if (!$res['ok']) {
                throw new RuntimeException('嵌入失败: ' . $res['error']);
            }
            $i = 0;
            foreach ($pending as $no => $p) {
                $vectors[$no] = isset($res['vectors'][$i]) ? $res['vectors'][$i] : null;
                $i++;
            }
            $dim = $res['dim'];
        }

        // 兼容 MySQL/SQLite 的 upsert（存在即更新，否则插入）
        $ins = $db->prepare(
            "INSERT INTO help_chunks (source_type, source_id, chunk_no, title, chunk_text, content_hash, embedding, dim)
             VALUES (?,?,?,?,?,?,?,?)"
        );
        $upd = $db->prepare(
            "UPDATE help_chunks SET title=?, chunk_text=?, content_hash=?, embedding=?, dim=?
             WHERE source_type=? AND source_id=? AND chunk_no=?"
        );
        foreach ($chunks as $no => $c) {
            if (!isset($pending[$no])) continue; // 未变更，保留
            $vec = $vectors[$no] ?? null;
            $blob = $vec ? pack('f*', ...$vec) : null;
            $args = [mb_substr($c['title'], 0, 255), $c['text'], $pending[$no]['hash'], $blob, $vec ? count($vec) : 0];
            if (array_key_exists($no, $existing)) {
                $upd->execute(array_merge($args, [$type, $id, $no]));
            } else {
                $ins->execute(array_merge([$type, $id, $no], $args));
            }
        }

        // 多余旧块（新切块数变少）
        $stmt = $db->prepare("DELETE FROM help_chunks WHERE source_type = ? AND source_id = ? AND chunk_no >= ?");
        $stmt->execute([$type, $id, count($chunks)]);
        $dropped = $stmt->rowCount();

        return [
            'total'    => count($chunks),
            'embedded' => count($pending),
            'kept'     => count($chunks) - count($pending),
            'dropped'  => $dropped,
        ];
    }
}
