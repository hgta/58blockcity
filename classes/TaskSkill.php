<?php
/**
 * 技能卡（task_skills + task_skill_categories）
 * 求职侧：用户声明「我能承接」的类别/说明/参考价；每人一张；可上下架。
 */
require_once __DIR__ . '/../includes/functions.php';

class TaskSkill {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function getByUser($userId) {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM task_skills WHERE user_id = ?"
        );
        $stmt->execute([(int)$userId]);
        $skill = $stmt->fetch();
        if (!$skill) return null;

        // 附带类别
        $stmt = $this->pdo->prepare(
            "SELECT c.id, c.name FROM task_skill_categories sc
             JOIN task_categories c ON sc.category_id = c.id
             WHERE sc.skill_id = ?
             ORDER BY c.sort_order ASC, c.id ASC"
        );
        $stmt->execute([(int)$skill['id']]);
        $skill['categories'] = $stmt->fetchAll();
        return $skill;
    }

    /**
     * 保存技能卡（新建/编辑）：整卡覆盖，可接类别仅允许启用中集合
     * @return array [bool, message|skillId]
     */
    public function save($userId, $description, $categoryIds, $refPrice = '', $active = true) {
        $description = trim((string)$description);
        $refPrice    = trim((string)$refPrice);
        $userId      = (int)$userId;

        if ($description === '' || mb_strlen($description) > 500) {
            return [false, '请填写「我能承接」说明（不超过 500 字）'];
        }
        $categoryIds = array_values(array_unique(array_map('intval', (array)$categoryIds)));
        if (empty($categoryIds)) {
            return [false, '请至少选择一个可承接类别'];
        }
        if (count($categoryIds) > 20) {
            return [false, '可承接类别最多选择 20 个'];
        }
        if (mb_strlen($refPrice) > 50) {
            return [false, '参考价请控制在 50 字以内'];
        }

        // 仅允许启用中的类别
        $holders = implode(',', array_fill(0, count($categoryIds), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT id FROM task_categories WHERE id IN ($holders) AND status = 'active'"
        );
        $stmt->execute($categoryIds);
        $allowed = $stmt->fetchAll(PDO::FETCH_COLUMN);
        if (count($allowed) !== count($categoryIds)) {
            return [false, '存在已停用或无效的类别，请重新选择'];
        }

        $status = $active ? 'active' : 'inactive';
        try {
            $this->pdo->beginTransaction();

            // upsert 技能卡
            $stmt = $this->pdo->prepare("SELECT id FROM task_skills WHERE user_id = ? FOR UPDATE");
            $stmt->execute([$userId]);
            $existing = $stmt->fetch();

            if ($existing) {
                $skillId = (int)$existing['id'];
                $stmt = $this->pdo->prepare(
                    "UPDATE task_skills SET description = ?, ref_price = ?, status = ?,
                            updated_at = NOW() WHERE id = ?"
                );
                $stmt->execute([$description, $refPrice !== '' ? $refPrice : null, $status, $skillId]);
            } else {
                $stmt = $this->pdo->prepare(
                    "INSERT INTO task_skills (user_id, description, ref_price, status)
                     VALUES (?, ?, ?, ?)"
                );
                $stmt->execute([$userId, $description, $refPrice !== '' ? $refPrice : null, $status]);
                $skillId = (int)$this->pdo->lastInsertId();
            }

            // 重建类别关联
            $stmt = $this->pdo->prepare("DELETE FROM task_skill_categories WHERE skill_id = ?");
            $stmt->execute([$skillId]);
            $ins = $this->pdo->prepare(
                "INSERT INTO task_skill_categories (skill_id, category_id) VALUES (?, ?)"
            );
            foreach ($categoryIds as $cid) {
                $ins->execute([$skillId, $cid]);
            }

            $this->pdo->commit();
            return [true, $skillId];
        } catch (Exception $e) {
            $this->pdo->rollBack();
            if ($e->getCode() == 23000) {
                return [false, '技能卡已存在，请直接编辑'];
            }
            error_log('TaskSkill::save 失败: ' . $e->getMessage());
            return [false, '保存失败，请稍后再试'];
        }
    }

    /** 上架/下架 */
    public function setStatus($userId, $status) {
        if (!in_array($status, ['active', 'inactive'], true)) return false;
        $stmt = $this->pdo->prepare(
            "UPDATE task_skills SET status = ?, updated_at = NOW() WHERE user_id = ?"
        );
        return $stmt->execute([$status, (int)$userId]);
    }

    /**
     * 展示中的技能卡列表（公开「找承接人」）
     * @param int $categoryId 可选类别筛选
     */
    public function listActive($categoryId = 0, $page = 1, $perPage = 12) {
        $page    = max(1, (int)$page);
        $perPage = min(50, max(6, (int)$perPage));
        $catId   = (int)$categoryId;

        $where  = ["s.status = 'active'"];
        $params = [];
        if ($catId > 0) {
            $where[] = "EXISTS (SELECT 1 FROM task_skill_categories x
                       WHERE x.skill_id = s.id AND x.category_id = ?)";
            $params[] = $catId;
        }
        $w = implode(' AND ', $where);

        $stmt = $this->pdo->prepare("SELECT COUNT(DISTINCT s.id) FROM task_skills s WHERE $w");
        $stmt->execute($params);
        $total = (int)$stmt->fetchColumn();
        $pages = max(1, (int)ceil($total / $perPage));
        if ($page > $pages) $page = $pages;

        $sql =
            "SELECT s.*, u.username, u.avatar,
                    GROUP_CONCAT(c.name ORDER BY c.sort_order ASC SEPARATOR '、') AS cat_names
             FROM task_skills s
             JOIN users u ON s.user_id = u.id
             LEFT JOIN task_skill_categories sc ON s.id = sc.skill_id
             LEFT JOIN task_categories c ON sc.category_id = c.id
             WHERE $w
             GROUP BY s.id
             ORDER BY s.id DESC
             LIMIT :lim OFFSET :off";
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $i => $p) {
            $stmt->bindValue($i + 1, $p);
        }
        $stmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':off', ($page - 1) * $perPage, PDO::PARAM_INT);
        $stmt->execute();

        return ['list' => $stmt->fetchAll(), 'total' => $total, 'pages' => $pages, 'page' => $page];
    }

    /** 某类别下展示中技能卡数量（发布页供给提示用） */
    public function countActiveByCategory($categoryId) {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(DISTINCT s.id)
             FROM task_skills s
             JOIN task_skill_categories sc ON s.id = sc.skill_id
             WHERE s.status = 'active' AND sc.category_id = ?"
        );
        $stmt->execute([(int)$categoryId]);
        return (int)$stmt->fetchColumn();
    }
}
