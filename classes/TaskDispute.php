<?php
/**
 * 任务争议（task_disputes）与后台仲裁
 * 发起入口见 TaskClaim::openDispute（认领置 disputed + 建单）；本类负责后台查询与裁决。
 * 裁决 settle / cancel；取消不自动回补任务名额槽位（首期口径，见 design D3）。
 */
require_once __DIR__ . '/../includes/functions.php';

class TaskDispute {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    /** 待仲裁列表（含任务/认领/双方信息） */
    public function openList() {
        return $this->pdo->query(
            "SELECT d.*, c.status AS claim_status, c.task_id,
                    t.title AS task_title, t.reward_type, t.reward_amount, t.city AS task_city,
                    e.username AS employer_name, w.username AS worker_name,
                    u.username AS initiator_name, c.proof_text, c.proof_image
             FROM task_disputes d
             JOIN task_claims c ON d.claim_id = c.id
             JOIN tasks t ON c.task_id = t.id
             LEFT JOIN users e ON t.employer_id = e.id
             LEFT JOIN users w ON c.worker_id = w.id
             LEFT JOIN users u ON d.initiator_id = u.id
             WHERE d.status = 'open'
             ORDER BY d.id ASC"
        )->fetchAll();
    }

    /** 全部争议（含已裁决） */
    public function allList() {
        return $this->pdo->query(
            "SELECT d.*, c.status AS claim_status, t.title AS task_title,
                    t.reward_type, t.reward_amount, t.city AS task_city,
                    e.username AS employer_name, w.username AS worker_name,
                    u.username AS initiator_name
             FROM task_disputes d
             JOIN task_claims c ON d.claim_id = c.id
             JOIN tasks t ON c.task_id = t.id
             LEFT JOIN users e ON t.employer_id = e.id
             LEFT JOIN users w ON c.worker_id = w.id
             LEFT JOIN users u ON d.initiator_id = u.id
             ORDER BY d.id DESC"
        )->fetchAll();
    }

    public function getById($id) {
        $stmt = $this->pdo->prepare(
            "SELECT d.*, c.status AS claim_status, c.task_id, c.worker_id,
                    t.title AS task_title, t.employer_id, t.reward_type, t.reward_amount,
                    t.city AS task_city
             FROM task_disputes d
             JOIN task_claims c ON d.claim_id = c.id
             JOIN tasks t ON c.task_id = t.id
             WHERE d.id = ?"
        );
        $stmt->execute([(int)$id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * 后台裁决
     * @param string $resolution settle=完成结算(或转待结算) / cancel=取消认领
     * @return array [bool, message]
     */
    public function resolve($disputeId, $resolution, $adminId, $adminNote) {
        $resolution = (string)$resolution;
        $adminNote  = trim((string)$adminNote);
        if (!in_array($resolution, ['settle', 'cancel'], true)) return [false, '裁决方式不正确'];
        if ($adminNote === '') return [false, '请填写裁决说明（将通知双方）'];

        $dispute = $this->getById($disputeId);
        if (!$dispute) return [false, '争议不存在'];
        if ($dispute['status'] !== 'open') return [false, '该争议已裁决'];
        if ($dispute['claim_status'] !== 'disputed') return [false, '该认领已不在争议状态'];

        $settleNote = '';
        try {
            $this->pdo->beginTransaction();

            if ($resolution === 'cancel') {
                $stmt = $this->pdo->prepare(
                    "UPDATE task_claims SET status = 'cancelled' WHERE id = ? AND status = 'disputed'"
                );
                $stmt->execute([(int)$dispute['claim_id']]);
                if ($stmt->rowCount() === 0) { $this->pdo->rollBack(); return [false, '认领状态已变化']; }
            } else {
                // settle：现金任务直接完成；人气值任务先转 settling 再由结算流程划转
                if ($dispute['reward_type'] === 'cash') {
                    $stmt = $this->pdo->prepare(
                        "UPDATE task_claims SET status = 'completed', settled_at = NOW()
                         WHERE id = ? AND status = 'disputed'"
                    );
                    $stmt->execute([(int)$dispute['claim_id']]);
                    if ($stmt->rowCount() === 0) { $this->pdo->rollBack(); return [false, '认领状态已变化']; }
                } else {
                    $stmt = $this->pdo->prepare(
                        "UPDATE task_claims SET status = 'settling' WHERE id = ? AND status = 'disputed'"
                    );
                    $stmt->execute([(int)$dispute['claim_id']]);
                    if ($stmt->rowCount() === 0) { $this->pdo->rollBack(); return [false, '认领状态已变化']; }
                }
            }

            $stmt = $this->pdo->prepare(
                "UPDATE task_disputes
                 SET status = 'resolved', resolution = ?, admin_id = ?, admin_note = ?, resolved_at = NOW()
                 WHERE id = ? AND status = 'open'"
            );
            $stmt->execute([$resolution, (int)$adminId, mb_substr($adminNote, 0, 500), (int)$disputeId]);

            $this->pdo->commit();
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log('TaskDispute::resolve 失败: ' . $e->getMessage());
            return [false, '裁决失败，请稍后再试'];
        }

        // 通知双方
        $resultText = $resolution === 'cancel' ? '争议被裁定「取消认领」' : '争议被裁定「完成结算」';
        $toEmployer = (int)$dispute['employer_id'];
        $toWorker   = (int)$dispute['worker_id'];
        $this->notify($toEmployer, 'task_dispute_resolved', (int)$dispute['task_id'],
            '任务《' . $dispute['task_title'] . '》仲裁结果：' . $resultText . '。平台说明：' . $adminNote);
        $this->notify($toWorker, 'task_dispute_resolved', (int)$dispute['task_id'],
            '任务《' . $dispute['task_title'] . '》仲裁结果：' . $resultText . '。平台说明：' . $adminNote);

        if ($resolution === 'cancel') {
            return [true, '已裁决取消该认领'];
        }

        // settle 且人气值任务：尝试结算
        if ($dispute['reward_type'] === 'popularity') {
            require_once __DIR__ . '/TaskClaim.php';
            $tc = new TaskClaim($this->pdo);
            return $tc->settleOnce((int)$dispute['claim_id']);
        }
        return [true, '已裁决完成结算（现金任务线下付款）'];
    }

    protected function notify($userId, $type, $relatedId, $content, $relatedUrl = '') {
        if (!class_exists('Notification')) {
            $p = __DIR__ . '/Notification.php';
            if (file_exists($p)) require_once $p;
        }
        if (!class_exists('Notification')) return;
        try {
            if ($relatedUrl === '') {
                $relatedUrl = 'https://task.58.tl/view.php?id=' . (int)$relatedId;
            }
            $n = new Notification($this->pdo);
            $n->sendSystemNotify($userId, $type, $relatedId, $content, $relatedUrl);
        } catch (Exception $e) {
            error_log('TaskDispute::notify 失败: ' . $e->getMessage());
        }
    }
}
