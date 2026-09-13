<?php
/**
 * 模特公开留言板
 *
 * 与原 Message 类的区别（重要）：
 *   Message        私信系统：双向会话，只有收发双方能看到
 *   ModelMessage   公开留言板：某模特主页下所有人可见，支持回复
 *
 * 规则：
 *   - 未登录可浏览；发表与回复需登录（由调用方校验）
 *   - 先发后审：发布即显示，管理员可软删除（status = deleted）
 *   - 回复为单层结构：回复挂在主留言的 parent_id 上，不无限嵌套
 */
class ModelMessage
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * 发表留言或回复
     *
     * @param int    $modelId       被留言的模特
     * @param int    $userId        留言者
     * @param string $content       内容
     * @param int    $parentId      回复的主留言 ID（0 = 主留言）
     * @param int    $replyToUserId 被回复者（回复时用）
     * @return int 新留言 ID，失败返回 0
     */
    public function create($modelId, $userId, $content, $parentId = 0, $replyToUserId = 0)
    {
        $modelId = intval($modelId);
        $userId  = intval($userId);
        $content = trim((string)$content);

        if ($modelId <= 0 || $userId <= 0 || $content === '') {
            return 0;
        }
        if (mb_strlen($content) > 500) {
            $content = mb_substr($content, 0, 500);
        }

        $parentId = intval($parentId);
        if ($parentId > 0) {
            // 校验回复目标：必须是本模特下的有效主留言（防跨模特串楼）
            $stmt = $this->pdo->prepare(
                "SELECT id, user_id FROM model_messages
                 WHERE id = ? AND model_id = ? AND status = 'active' AND parent_id IS NULL"
            );
            $stmt->execute([$parentId, $modelId]);
            $parent = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$parent) {
                return 0;
            }
            if (intval($replyToUserId) <= 0) {
                $replyToUserId = intval($parent['user_id']);
            }
        } else {
            $parentId = null;
            $replyToUserId = null;
        }

        $stmt = $this->pdo->prepare(
            "INSERT INTO model_messages (model_id, user_id, message, parent_id, reply_to_user_id, status)
             VALUES (?, ?, ?, ?, ?, 'active')"
        );
        $ok = $stmt->execute([
            $modelId,
            $userId,
            $content,
            $parentId,
            $replyToUserId ?: null,
        ]);
        return $ok ? (int)$this->pdo->lastInsertId() : 0;
    }

    /**
     * 取某模特的主留言（分页），每条附带回复列表
     *
     * @return array ['list'=>[...], 'total'=>int, 'pages'=>int, 'page'=>int]
     */
    public function getByModel($modelId, $page = 1, $perPage = 20, $replyLimit = 50)
    {
        $modelId = intval($modelId);
        $page    = max(1, intval($page));
        $perPage = max(1, intval($perPage));
        $offset  = ($page - 1) * $perPage;

        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM model_messages
             WHERE model_id = ? AND status = 'active' AND parent_id IS NULL"
        );
        $stmt->execute([$modelId]);
        $total = (int)$stmt->fetchColumn();

        $stmt = $this->pdo->prepare(
            "SELECT mm.*, u.username, u.avatar AS user_avatar
             FROM model_messages mm
             LEFT JOIN users u ON mm.user_id = u.id
             WHERE mm.model_id = ? AND mm.status = 'active' AND mm.parent_id IS NULL
             ORDER BY mm.created_at DESC, mm.id DESC
             LIMIT " . $perPage . " OFFSET " . $offset
        );
        $stmt->execute([$modelId]);
        $list = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 批量取回复，避免 N+1
        $ids     = array_column($list, 'id');
        $replies = [];
        if ($ids) {
            $ph  = implode(',', array_map('intval', $ids));
            $lim = max(1, intval($replyLimit));
            $stmt = $this->pdo->prepare(
                "SELECT mm.*, u.username, u.avatar AS user_avatar
                 FROM model_messages mm
                 LEFT JOIN users u ON mm.user_id = u.id
                 WHERE mm.parent_id IN ($ph) AND mm.status = 'active'
                 ORDER BY mm.created_at ASC, mm.id ASC"
            );
            $stmt->execute();
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $pid = intval($r['parent_id']);
                if (!isset($replies[$pid])) {
                    $replies[$pid] = [];
                }
                if (count($replies[$pid]) < $lim) {
                    $replies[$pid][] = $r;
                }
            }
        }

        foreach ($list as &$item) {
            $item['replies'] = $replies[intval($item['id'])] ?? [];
        }
        unset($item);

        return [
            'list'  => $list,
            'total' => $total,
            'pages' => (int)ceil($total / $perPage),
            'page'  => $page,
        ];
    }

    /** 某模特的留言总数（含回复） */
    public function countByModel($modelId)
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM model_messages WHERE model_id = ? AND status = 'active'"
        );
        $stmt->execute([intval($modelId)]);
        return (int)$stmt->fetchColumn();
    }

    /** 取单条 */
    public function getById($id)
    {
        $stmt = $this->pdo->prepare(
            "SELECT mm.*, u.username FROM model_messages mm
             LEFT JOIN users u ON mm.user_id = u.id
             WHERE mm.id = ?"
        );
        $stmt->execute([intval($id)]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * 软删除（删除主留言时其下回复一并隐藏，避免孤儿回复）
     *
     * @param int  $operatorId   操作者用户 ID
     * @param int  $modelOwnerId 该模特归属的用户 ID（模特本人可删）
     * @param bool $isAdmin      管理员可删任何留言
     */
    public function softDelete($messageId, $operatorId, $modelOwnerId = 0, $isAdmin = false)
    {
        $messageId = intval($messageId);
        $row = $this->getById($messageId);
        if (!$row) {
            return false;
        }
        $canDelete = $isAdmin
                  || (intval($row['user_id']) === intval($operatorId))
                  || ($modelOwnerId > 0 && intval($modelOwnerId) === intval($operatorId));
        if (!$canDelete) {
            return false;
        }

        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare("UPDATE model_messages SET status = 'deleted' WHERE id = ?")
                     ->execute([$messageId]);
            if (empty($row['parent_id'])) {
                $this->pdo->prepare("UPDATE model_messages SET status = 'deleted' WHERE parent_id = ?")
                         ->execute([$messageId]);
            }
            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /** 点赞（轻量累加，不做防重复） */
    public function like($messageId)
    {
        $stmt = $this->pdo->prepare(
            "UPDATE model_messages SET like_count = like_count + 1
             WHERE id = ? AND status = 'active'"
        );
        $stmt->execute([intval($messageId)]);
        return $stmt->rowCount() > 0;
    }
}
