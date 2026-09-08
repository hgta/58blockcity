<?php
/**
 * 任务双向评价（task_reviews）
 * 一份认领 completed 后，雇主↔接单人各可评价一次（星级+文字），不可修改/删除。
 */
require_once __DIR__ . '/../includes/functions.php';

class TaskReview {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    /**
     * 新增评价
     * @param int $taskId    tasks.id
     * @param int $claimId   task_claims.id
     * @param int $fromUserId 评价方
     * @param int $toUserId   被评价方
     * @return array [bool, message]
     */
    public function add($taskId, $claimId, $fromUserId, $toUserId, $rating, $content) {
        $taskId    = (int)$taskId;
        $claimId   = (int)$claimId;
        $fromUserId = (int)$fromUserId;
        $toUserId   = (int)$toUserId;
        $rating     = (int)$rating;
        $content    = trim((string)$content);

        if (!in_array($rating, [1, 2, 3, 4, 5], true)) return [false, '星级需为 1-5'];
        if ($content === '') return [false, '请填写评价内容'];
        if (mb_strlen($content) > 500) return [false, '评价内容不超过 500 字'];
        if ($toUserId === $fromUserId) return [false, '不能评价自己'];

        // 认领必须已完成，且评价方必须是该认领参与方（雇主或接单人）
        $stmt = $this->pdo->prepare(
            "SELECT c.status, c.worker_id, t.employer_id, t.status AS task_status
             FROM task_claims c JOIN tasks t ON c.task_id = t.id
             WHERE c.id = ? AND c.task_id = ?"
        );
        $stmt->execute([$claimId, $taskId]);
        $claim = $stmt->fetch();
        if (!$claim) return [false, '认领不存在'];
        if ($claim['status'] !== 'completed') return [false, '认领尚未完成结算，不可评价'];
        $isEmployer = (int)$claim['employer_id'] === $fromUserId;
        $isWorker   = (int)$claim['worker_id'] === $fromUserId;
        if (!$isEmployer && !$isWorker) return [false, '只有该认领的雇主或接单人可评价'];

        try {
            $stmt = $this->pdo->prepare(
                "INSERT INTO task_reviews (task_id, claim_id, from_user_id, to_user_id, rating, content)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([$taskId, $claimId, $fromUserId, $toUserId, $rating, $content]);
            return [true, '评价已提交'];
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                return [false, '你已评价过该认领，评价不可重复/修改'];
            }
            error_log('TaskReview::add 失败: ' . $e->getMessage());
            return [false, '评价提交失败，请稍后再试'];
        }
    }

    /** 某认领的评价 */
    public function listByClaim($claimId) {
        $stmt = $this->pdo->prepare(
            "SELECT r.*, u.username AS from_name
             FROM task_reviews r
             LEFT JOIN users u ON r.from_user_id = u.id
             WHERE r.claim_id = ?
             ORDER BY r.id ASC"
        );
        $stmt->execute([(int)$claimId]);
        return $stmt->fetchAll();
    }

    /** 某用户收到的评价（含任务名/评价方/时间），分页 */
    public function listReceived($userId, $page = 1, $perPage = 10) {
        $page    = max(1, (int)$page);
        $perPage = min(50, max(5, (int)$perPage));

        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM task_reviews WHERE to_user_id = ?");
        $stmt->execute([(int)$userId]);
        $total = (int)$stmt->fetchColumn();
        $pages = max(1, (int)ceil($total / $perPage));

        $stmt = $this->pdo->prepare(
            "SELECT r.*, t.title AS task_title, u.username AS from_name
             FROM task_reviews r
             JOIN tasks t ON r.task_id = t.id
             LEFT JOIN users u ON r.from_user_id = u.id
             WHERE r.to_user_id = ?
             ORDER BY r.id DESC
             LIMIT :lim OFFSET :off"
        );
        $stmt->bindValue(1, (int)$userId);
        $stmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':off', ($page - 1) * $perPage, PDO::PARAM_INT);
        $stmt->execute();

        return ['list' => $stmt->fetchAll(), 'total' => $total, 'pages' => $pages];
    }

    /** 用户收到的评价摘要（信誉参考） */
    public function summary($userId) {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) AS cnt, COALESCE(AVG(rating), 0) AS avg_rating
             FROM task_reviews WHERE to_user_id = ?"
        );
        $stmt->execute([(int)$userId]);
        $row = $stmt->fetch();
        return [
            'count' => (int)$row['cnt'],
            'avg'   => round((float)$row['avg_rating'], 1),
        ];
    }
}
