<?php
/**
 * 任务认领（task_claims）状态机
 *
 * 领取 accepted → 提交凭证 submitted → 验收通过 settling → completed
 *                                     └→ 驳回 rejected ─(补交)→ submitted
 *   任意未完结态可发起争议 disputed → admin 裁决 settle / cancel
 * 人气值为用户自管记录，结算仅推进状态、不做扣减/划转；
 * 现金结算同样仅状态推进（线下付款）。
 */
require_once __DIR__ . '/../includes/functions.php';

class TaskClaim {
    const S_ACCEPTED  = 'accepted';   // 已领取待交付
    const S_SUBMITTED = 'submitted';  // 已提交待验收
    const S_REJECTED  = 'rejected';   // 被驳回可补交
    const S_SETTLING  = 'settling';   // 验收通过待结算
    const S_COMPLETED = 'completed';  // 已结算
    const S_CANCELLED = 'cancelled';  // 取消
    const S_DISPUTED  = 'disputed';   // 争议中

    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    // ---------- 领取 ----------

    /**
     * 用户领取任务名额（众包：同任务每人一份）
     * @return array [bool, message]
     */
    public function claim($taskId, $workerId) {
        $taskId   = (int)$taskId;
        $workerId = (int)$workerId;
        if ($workerId <= 0) return [false, '请先登录'];

        try {
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare(
                "SELECT * FROM tasks WHERE id = ? FOR UPDATE"
            );
            $stmt->execute([$taskId]);
            $task = $stmt->fetch();
            if (!$task) { $this->pdo->rollBack(); return [false, '任务不存在']; }
            if ((int)$task['employer_id'] === $workerId) {
                $this->pdo->rollBack(); return [false, '不能领取自己发布的任务'];
            }
            if ($task['status'] !== 'open') {
                $this->pdo->rollBack(); return [false, '任务已关闭，不可领取'];
            }
            if (!empty($task['expire_at']) && strtotime($task['expire_at']) <= time()) {
                $this->pdo->rollBack(); return [false, '任务已到期，不可领取'];
            }
            if ((int)$task['claimed_count'] >= (int)$task['quota']) {
                $this->pdo->rollBack(); return [false, '任务名额已满'];
            }

            // 已领取过则拒绝
            $stmt = $this->pdo->prepare(
                "SELECT id FROM task_claims WHERE task_id = ? AND worker_id = ?"
            );
            $stmt->execute([$taskId, $workerId]);
            if ($stmt->fetch()) { $this->pdo->rollBack(); return [false, '你已领取过该任务']; }

            $stmt = $this->pdo->prepare(
                "INSERT INTO task_claims (task_id, worker_id, status) VALUES (?, ?, 'accepted')"
            );
            $stmt->execute([$taskId, $workerId]);

            $stmt = $this->pdo->prepare(
                "UPDATE tasks SET claimed_count = claimed_count + 1, updated_at = NOW() WHERE id = ?"
            );
            $stmt->execute([$taskId]);

            $this->pdo->commit();
        } catch (Exception $e) {
            $this->pdo->rollBack();
            if ($e->getCode() == 23000) {
                return [false, '你已领取过该任务'];
            }
            error_log('TaskClaim::claim 失败: ' . $e->getMessage());
            return [false, '领取失败，请稍后再试'];
        }

        // 通知雇主
        $stmt = $this->pdo->prepare("SELECT username FROM users WHERE id = ?");
        $stmt->execute([$workerId]);
        $uname = $stmt->fetchColumn() ?: ('#' . $workerId);
        $this->notify((int)$task['employer_id'], 'task_claimed', $taskId,
            '用户 ' . $uname . ' 领取了你的任务《' . $task['title'] . '》');

        return [true, '领取成功，请尽快完成任务并提交凭证'];
    }

    // ---------- 查询 ----------

    public function getClaim($claimId) {
        $stmt = $this->pdo->prepare(
            "SELECT c.*, t.title AS task_title, t.reward_type, t.reward_amount,
                    t.quota, t.review_days, t.expire_at, t.status AS task_status,
                    t.city AS task_city, t.employer_id,
                    t.accept_desc AS task_accept_desc,
                    cat.name AS category_name,
                    e.username AS employer_name, e.avatar AS employer_avatar,
                    w.username AS worker_name, w.avatar AS worker_avatar
             FROM task_claims c
             JOIN tasks t ON c.task_id = t.id
             LEFT JOIN task_categories cat ON t.category_id = cat.id
             LEFT JOIN users e ON t.employer_id = e.id
             LEFT JOIN users w ON c.worker_id = w.id
             WHERE c.id = ?"
        );
        $stmt->execute([(int)$claimId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** 当前用户在某任务上的认领状态（用于页面按钮） */
    public function userClaimOn($taskId, $workerId) {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM task_claims WHERE task_id = ? AND worker_id = ?"
        );
        $stmt->execute([(int)$taskId, (int)$workerId]);
        return $stmt->fetch() ?: null;
    }

    /** 某任务的全部认领（雇主视角：按领取时间正序） */
    public function claimsByTask($taskId) {
        $stmt = $this->pdo->prepare(
            "SELECT c.*, w.username AS worker_name, w.avatar AS worker_avatar
             FROM task_claims c
             LEFT JOIN users w ON c.worker_id = w.id
             WHERE c.task_id = ?
             ORDER BY c.claimed_at ASC, c.id ASC"
        );
        $stmt->execute([(int)$taskId]);
        return $stmt->fetchAll();
    }

    /** 我的承接（含任务标题/类别/雇主名），可按状态筛 */
    public function myClaims($workerId, $status = '') {
        $sql =
            "SELECT c.*, t.title AS task_title, t.reward_type, t.reward_amount,
                    t.quota, t.city AS task_city, t.status AS task_status,
                    t.employer_id, cat.name AS category_name,
                    e.username AS employer_name
             FROM task_claims c
             JOIN tasks t ON c.task_id = t.id
             LEFT JOIN task_categories cat ON t.category_id = cat.id
             LEFT JOIN users e ON t.employer_id = e.id
             WHERE c.worker_id = ?";
        $params = [(int)$workerId];
        if ($status !== '' && in_array($status, [self::S_ACCEPTED, self::S_SUBMITTED,
                self::S_REJECTED, self::S_SETTLING, self::S_COMPLETED, self::S_CANCELLED, self::S_DISPUTED], true)) {
            $sql .= " AND c.status = ?";
            $params[] = $status;
        }
        $sql .= " ORDER BY c.claimed_at DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function countByWorker($workerId) {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM task_claims WHERE worker_id = ?");
        $stmt->execute([(int)$workerId]);
        return (int)$stmt->fetchColumn();
    }

    // ---------- 交付与验收 ----------

    /**
     * 接单人提交凭证（文字必填 + 可附图）
     * 仅 accepted/rejected 可提交；任务关闭后不再受理新交付（可走争议）。
     */
    public function submitProof($claimId, $workerId, $proofText, $proofImage = '') {
        $claimId  = (int)$claimId;
        $proofText = trim((string)$proofText);
        if ($proofText === '') return [false, '请填写交付凭证的文字说明'];

        try {
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare(
                "SELECT c.*, t.status AS task_status, t.review_days
                 FROM task_claims c JOIN tasks t ON c.task_id = t.id
                 WHERE c.id = ? FOR UPDATE"
            );
            $stmt->execute([$claimId]);
            $claim = $stmt->fetch();
            if (!$claim) { $this->pdo->rollBack(); return [false, '认领不存在']; }
            if ((int)$claim['worker_id'] !== (int)$workerId) {
                $this->pdo->rollBack(); return [false, '只能为自己领取的认领提交凭证'];
            }
            if (!in_array($claim['status'], [self::S_ACCEPTED, self::S_REJECTED], true)) {
                $this->pdo->rollBack(); return [false, '该认领当前不能提交凭证'];
            }
            if ($claim['task_status'] !== 'open') {
                $this->pdo->rollBack();
                return [false, '任务已关闭，不再受理新交付；如有异议请发起争议'];
            }

            $stmt = $this->pdo->prepare(
                "UPDATE task_claims c JOIN tasks t ON c.task_id = t.id
                 SET c.status = 'submitted',
                     c.proof_text = ?, c.proof_image = ?,
                     c.employer_note = NULL,
                     c.submitted_at = NOW(), c.reviewed_at = NULL,
                     c.review_due_at = DATE_ADD(NOW(), INTERVAL t.review_days DAY)
                 WHERE c.id = ? AND c.worker_id = ?"
            );
            $stmt->execute([$proofText, $proofImage !== '' ? $proofImage : null, $claimId, (int)$workerId]);

            $this->pdo->commit();
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log('TaskClaim::submitProof 失败: ' . $e->getMessage());
            return [false, '提交失败，请稍后再试'];
        }

        $this->notify((int)$claim['employer_id'], 'task_submitted', (int)$claim['task_id'],
            '任务《' . ($claim['task_title'] ?? '') . '》收到一份交付凭证，请验收');
        return [true, '交付凭证已提交，等待雇主验收'];
    }

    /**
     * 雇主验收：通过(review pass) 或 驳回(reject)
     * pass → settling 并立即尝试结算；reject → rejected 并记录原因（可补交）
     */
    public function review($claimId, $employerId, $decision, $note = '') {
        $claimId = (int)$claimId;
        $note    = trim((string)$note);
        $stmt = $this->pdo->prepare(
            "SELECT c.*, t.title AS task_title, t.employer_id
             FROM task_claims c JOIN tasks t ON c.task_id = t.id
             WHERE c.id = ?"
        );
        $stmt->execute([$claimId]);
        $claim = $stmt->fetch();
        if (!$claim) return [false, '认领不存在'];
        if ((int)$claim['employer_id'] !== (int)$employerId) return [false, '只能验收自己任务下的认领'];
        if ($claim['status'] !== self::S_SUBMITTED) return [false, '该认领当前不可验收（仅待验收状态可操作）'];

        if ($decision === 'reject') {
            if ($note === '') return [false, '驳回必须填写原因'];
            $stmt = $this->pdo->prepare(
                "UPDATE task_claims SET status = 'rejected', employer_note = ?, reviewed_at = NOW()
                 WHERE id = ? AND status = 'submitted'"
            );
            $stmt->execute([$note, $claimId]);
            $this->notify((int)$claim['worker_id'], 'task_rejected', (int)$claim['task_id'],
                '你的交付被驳回：《' . $claim['task_title'] . '》。原因：' . $note . '。可补充凭证后重新提交');
            return [true, '已驳回并通知接单人'];
        }

        if ($decision === 'pass') {
            $stmt = $this->pdo->prepare(
                "UPDATE task_claims SET status = 'settling',
                        employer_note = ?, reviewed_at = NOW()
                 WHERE id = ? AND status = 'submitted'"
            );
            $stmt->execute([$note !== '' ? $note : null, $claimId]);
            $this->notify((int)$claim['worker_id'], 'task_passed', (int)$claim['task_id'],
                '你的交付已验收通过，进入结算');
            return $this->settleOnce($claimId);
        }

        return [false, '未知的验收动作'];
    }

    // ---------- 结算 ----------

    /**
     * 尝试结算一份 settling 认领：
     * 人气值 → 人气值为用户自管记录，仅把状态推进为 completed，不做扣减/划转，
     *          也不再因雇主人气值不足而停留在 settling。
     * 现金 → 仅状态推进为 completed（线下付款）。
     */
    public function settleOnce($claimId) {
        // 事务化：认领行 FOR UPDATE 必须持锁到状态推进完成，防止并发“重试结算”重复推进。
        // 若调用方已处于事务（如仲裁 settle 流程内），则加入现有事务由调用方统一提交。
        $outer = $this->pdo->inTransaction();
        if (!$outer) {
            try {
                $this->pdo->beginTransaction();
            } catch (Exception $e) {
                error_log('TaskClaim::settleOnce 开启事务失败: ' . $e->getMessage());
                return [false, '结算失败，请稍后再试'];
            }
        }

        try {
            $stmt = $this->pdo->prepare(
                "SELECT c.*, t.title AS task_title, t.employer_id, t.city AS task_city,
                        t.reward_type, t.reward_amount
                 FROM task_claims c JOIN tasks t ON c.task_id = t.id
                 WHERE c.id = ? AND c.status = 'settling' FOR UPDATE"
            );
            $stmt->execute([(int)$claimId]);
            $claim = $stmt->fetch();
            if (!$claim) {
                if (!$outer) $this->pdo->rollBack();
                return [false, '该认领不在待结算状态'];
            }

            // 现金任务：仅状态推进
            if ($claim['reward_type'] === 'cash') {
                $stmt = $this->pdo->prepare(
                    "UPDATE task_claims SET status = 'completed', settled_at = NOW()
                     WHERE id = ? AND status = 'settling'"
                );
                $stmt->execute([(int)$claimId]);
                if ($stmt->rowCount() === 0) {
                    if (!$outer) $this->pdo->rollBack();
                    return [false, '该认领不在待结算状态'];
                }
                if (!$outer) $this->pdo->commit();
                $this->notify((int)$claim['worker_id'], 'task_settled', (int)$claim['task_id'],
                    '任务《' . $claim['task_title'] . '》验收通过。本单为线下现金结算，请与雇主完成付款（平台不托管）');
                return [true, '已完成（现金任务线下结算，请与雇主完成付款）'];
            }

            // 人气值任务：人气值为用户自管记录，结算仅推进状态，不做划转
            if ($claim['reward_type'] === 'popularity') {
                $stmt = $this->pdo->prepare(
                    "UPDATE task_claims SET status = 'completed', settled_at = NOW()
                     WHERE id = ? AND status = 'settling'"
                );
                $stmt->execute([(int)$claimId]);
                if ($stmt->rowCount() === 0) {
                    if (!$outer) $this->pdo->rollBack();
                    return [false, '该认领不在待结算状态'];
                }
                if (!$outer) $this->pdo->commit();
                $this->notify((int)$claim['worker_id'], 'task_settled', (int)$claim['task_id'],
                    '任务《' . $claim['task_title'] . '》已结算');
                return [true, '结算成功'];
            }

            if (!$outer) $this->pdo->rollBack();
            return [false, '未知赏金类型'];
        } catch (Exception $e) {
            if (!$outer) $this->pdo->rollBack();
            error_log('TaskClaim::settleOnce 失败: ' . $e->getMessage());
            return [false, '结算失败，请稍后再试'];
        }
    }

    // ---------- 争议 ----------

    /**
     * 任一方发起争议（认领须处于未完结：accepted/submitted/rejected）
     */
    public function openDispute($claimId, $initiatorId, $reason) {
        $reason = trim((string)$reason);
        if ($reason === '') return [false, '请填写争议事由'];
        $stmt = $this->pdo->prepare(
            "SELECT c.*, t.title AS task_title, t.employer_id
             FROM task_claims c JOIN tasks t ON c.task_id = t.id
             WHERE c.id = ? FOR UPDATE"
        );
        $stmt->execute([(int)$claimId]);
        $claim = $stmt->fetch();
        if (!$claim) return [false, '认领不存在'];
        $isParty = (int)$claim['worker_id'] === (int)$initiatorId || (int)$claim['employer_id'] === (int)$initiatorId;
        if (!$isParty) return [false, '只有该认领的雇主或接单人可发起争议'];
        if (!in_array($claim['status'], [self::S_ACCEPTED, self::S_SUBMITTED, self::S_REJECTED], true)) {
            return [false, '该认领当前状态不可发起争议'];
        }

        try {
            $this->pdo->beginTransaction();
            $stmt = $this->pdo->prepare(
                "SELECT id FROM task_disputes WHERE claim_id = ? AND status = 'open'"
            );
            $stmt->execute([(int)$claimId]);
            if ($stmt->fetch()) { $this->pdo->rollBack(); return [false, '该认领已存在进行中的争议']; }

            $stmt = $this->pdo->prepare(
                "UPDATE task_claims SET status = 'disputed' WHERE id = ? AND status IN ('accepted','submitted','rejected')"
            );
            $stmt->execute([(int)$claimId]);
            if ($stmt->rowCount() === 0) { $this->pdo->rollBack(); return [false, '认领状态已变化，无法发起争议']; }

            $stmt = $this->pdo->prepare(
                "INSERT INTO task_disputes (claim_id, initiator_id, reason) VALUES (?, ?, ?)"
            );
            $stmt->execute([(int)$claimId, (int)$initiatorId, mb_substr($reason, 0, 500)]);

            $this->pdo->commit();
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log('TaskClaim::openDispute 失败: ' . $e->getMessage());
            return [false, '发起争议失败，请稍后再试'];
        }

        // 通知对方
        $other = (int)$claim['worker_id'] === (int)$initiatorId
            ? (int)$claim['employer_id']
            : (int)$claim['worker_id'];
        $this->notify($other, 'task_disputed', (int)$claim['task_id'],
            '任务《' . $claim['task_title'] . '》有一份认领已进入争议，等待平台仲裁');
        return [true, '争议已提交，等待平台仲裁'];
    }

    /** 站内通知 */
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
            error_log('TaskClaim::notify 失败: ' . $e->getMessage());
        }
    }
}
