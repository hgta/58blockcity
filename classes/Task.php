<?php
/**
 * 悬赏任务（tasks）
 * 雇主发布悬赏（可关联城市/区块/互访圈对象），广场分页浏览与筛选，状态机由 TaskClaim 推进。
 * 资金口径：人气值任务 reward_amount=整数个并按任务 city 结算；现金任务 reward_amount=分，站内无资金变动。
 */
require_once __DIR__ . '/../includes/functions.php';

class Task {
    const STATUS_OPEN   = 'open';
    const STATUS_CLOSED = 'closed';

    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    /**
     * 惰性到期关闭：过期 open 任务置 closed（广场页每次调用）
     */
    public function expireOverdue() {
        try {
            $this->pdo->prepare(
                "UPDATE tasks SET status = 'closed', updated_at = NOW()
                 WHERE status = 'open' AND expire_at IS NOT NULL AND expire_at <= NOW()"
            )->execute();
        } catch (Exception $e) {
            error_log('Task::expireOverdue 失败: ' . $e->getMessage());
        }
    }

    /**
     * 创建任务
     * @param array $d employer_id/category_id/title/description/accept_desc/city/
     *                 target_type/target_id/reward_type/reward_amount/quota/review_days/expire_at
     * @return array [bool, message|taskId]
     */
    public function create($d) {
        $employerId = (int)($d['employer_id'] ?? 0);
        $categoryId = (int)($d['category_id'] ?? 0);
        $title      = trim((string)($d['title'] ?? ''));
        $desc       = trim((string)($d['description'] ?? ''));
        $acceptDesc = trim((string)($d['accept_desc'] ?? ''));
        $city       = trim((string)($d['city'] ?? ''));
        $rewardType = (string)($d['reward_type'] ?? '');
        $rewardAmt  = (int)($d['reward_amount'] ?? 0);
        $quota      = (int)($d['quota'] ?? 1);
        $reviewDays = (int)($d['review_days'] ?? 3);
        $expireAt   = trim((string)($d['expire_at'] ?? ''));

        // 基础校验
        if ($employerId <= 0) return [false, '请先登录'];
        if ($title === '' || mb_strlen($title) > 120) return [false, '请填写标题（不超过 120 字）'];
        if ($desc === '') return [false, '请填写任务说明'];
        if ($acceptDesc === '') return [false, '请填写验收说明（接单人交付标准）'];
        if (!in_array($rewardType, ['popularity', 'cash'], true)) return [false, '赏金类型不正确'];
        if ($quota < 1 || $quota > 999) return [false, '名额需为 1-999 的正整数'];
        if ($reviewDays < 1 || $reviewDays > 30) return [false, '验收期限需为 1-30 天'];

        // 有效期必须为未来时间
        if ($expireAt === '') return [false, '请选择任务有效期'];
        $ts = strtotime($expireAt);
        if ($ts === false) return [false, '有效期格式不正确'];
        if ($ts <= time()) return [false, '有效期需晚于当前时间'];
        $expireSql = date('Y-m-d H:i:s', $ts);

        // 赏金校验
        if ($rewardType === 'popularity') {
            if ($rewardAmt <= 0) return [false, '人气值赏金需为正整数'];
            if ($city === '') return [false, '人气值任务必须关联城市（作为结算账本城市）'];
        } else {
            if ($rewardAmt <= 0) return [false, '现金赏金需大于 0（以元为单位填写，站内线下结算）'];
            // cash 任务城市可为空
        }

        // 类别必须启用中
        $stmt = $this->pdo->prepare(
            "SELECT id FROM task_categories WHERE id = ? AND status = 'active'"
        );
        $stmt->execute([$categoryId]);
        if (!$stmt->fetch()) return [false, '请选择启用中的任务类别'];

        // 关联对象（可选，成对）
        $targetType = '';
        $targetId   = '';
        if (!empty($d['target_type']) && $d['target_type'] !== '') {
            $targetType = $d['target_type'];
            $targetId   = trim((string)($d['target_id'] ?? ''));
            if (!in_array($targetType, ['block', 'circle'], true) || $targetId === '') {
                return [false, '关联对象不正确'];
            }
        }

        try {
            $stmt = $this->pdo->prepare(
                "INSERT INTO tasks
                   (employer_id, category_id, title, description, accept_desc, city,
                    target_type, target_id, reward_type, reward_amount,
                    quota, claimed_count, review_days, expire_at, status)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,0,?,?,'open')"
            );
            $stmt->execute([
                $employerId, $categoryId, $title, $desc, $acceptDesc,
                $city !== '' ? $city : null,
                $targetType !== '' ? $targetType : null,
                $targetId !== '' ? $targetId : null,
                $rewardType, $rewardAmt, $quota, $reviewDays, $expireSql,
            ]);
            return [true, (int)$this->pdo->lastInsertId()];
        } catch (Exception $e) {
            error_log('Task::create 失败: ' . $e->getMessage());
            return [false, '任务创建失败，请稍后再试'];
        }
    }

    /** 取任务详情（含类别名 / 发布者信息） */
    public function getById($id) {
        $stmt = $this->pdo->prepare(
            "SELECT t.*, c.name AS category_name,
                    u.username AS employer_name, u.avatar AS employer_avatar,
                    (t.quota - t.claimed_count) AS remaining
             FROM tasks t
             LEFT JOIN task_categories c ON t.category_id = c.id
             LEFT JOIN users u ON t.employer_id = u.id
             WHERE t.id = ?"
        );
        $stmt->execute([(int)$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** 判断任务是否仍接受新领取（open 且未到期且未满额） */
    public function isClaimable($task) {
        if (!$task) return false;
        $notExpired = empty($task['expire_at']) || strtotime($task['expire_at']) > time();
        return $task['status'] === self::STATUS_OPEN
            && $notExpired
            && (int)$task['claimed_count'] < (int)$task['quota'];
    }

    /** 广场任务列表 */
    public function listPlaza($filters = [], $page = 1, $perPage = 12) {
        $page   = max(1, (int)$page);
        $perPage = min(50, max(6, (int)$perPage));
        $tab    = $filters['tab'] ?? 'open';   // open=进行中 full=已满 ended=已结束
        $catId  = (int)($filters['category'] ?? 0);
        $city   = trim((string)($filters['city'] ?? ''));
        $rtype  = (string)($filters['reward_type'] ?? '');
        $sort   = (string)($filters['sort'] ?? 'new'); // new|reward

        $where  = ['1=1'];
        $params = [];

        if ($tab === 'ended') {
            $where[] = "t.status = 'closed'";
        } else {
            $where[] = "t.status = 'open'";
            $where[] = "(t.expire_at IS NULL OR t.expire_at > NOW())";
            if ($tab === 'full') {
                $where[] = "t.claimed_count >= t.quota";
            } else {
                $where[] = "t.claimed_count < t.quota";
            }
        }
        if ($catId > 0)      { $where[] = 't.category_id = ?';       $params[] = $catId; }
        if ($city !== '')    { $where[] = 't.city = ?';              $params[] = $city; }
        if (in_array($rtype, ['popularity', 'cash'], true)) { $where[] = 't.reward_type = ?'; $params[] = $rtype; }

        $order = $sort === 'reward'
            ? 't.reward_amount DESC, t.id DESC'
            : 't.id DESC';

        $countSql = "SELECT COUNT(*) FROM tasks t WHERE " . implode(' AND ', $where);
        $stmt = $this->pdo->prepare($countSql);
        $stmt->execute($params);
        $total = (int)$stmt->fetchColumn();
        $pages = max(1, (int)ceil($total / $perPage));
        if ($page > $pages) $page = $pages;

        $sql = "SELECT t.*, c.name AS category_name,
                       u.username AS employer_name,
                       (t.quota - t.claimed_count) AS remaining
                FROM tasks t
                LEFT JOIN task_categories c ON t.category_id = c.id
                LEFT JOIN users u ON t.employer_id = u.id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY {$order}
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

    /** 某雇主发布的全部任务（我的发布） */
    public function listByEmployer($employerId) {
        $stmt = $this->pdo->prepare(
            "SELECT t.*, c.name AS category_name,
                    (t.quota - t.claimed_count) AS remaining
             FROM tasks t
             LEFT JOIN task_categories c ON t.category_id = c.id
             WHERE t.employer_id = ?
             ORDER BY t.id DESC"
        );
        $stmt->execute([(int)$employerId]);
        return $stmt->fetchAll();
    }

    public function countByEmployer($employerId) {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM tasks WHERE employer_id = ?");
        $stmt->execute([(int)$employerId]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * 雇主关闭进行中任务：
     *  - 任务置 closed（open→closed）
     *  - 尚未交付的认领（accepted）一并取消并通知接单人
     *  - 已提交(submitted)/被驳回(rejected) 保留：已提交仍可验收，被驳回可发起争议
     * @return array [bool, message]
     */
    public function closeByEmployer($taskId, $employerId) {
        $task = $this->getById($taskId);
        if (!$task) return [false, '任务不存在'];
        if ((int)$task['employer_id'] !== (int)$employerId) return [false, '只能关闭自己发布的任务'];
        if ($task['status'] !== self::STATUS_OPEN) return [false, '任务已关闭'];

        try {
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare(
                "UPDATE tasks SET status = 'closed', updated_at = NOW() WHERE id = ? AND status = 'open'"
            );
            $stmt->execute([(int)$taskId]);
            if ($stmt->rowCount() === 0) {
                $this->pdo->rollBack();
                return [false, '任务已关闭或不存在'];
            }

            // 取消尚未交付的认领
            $stmt = $this->pdo->prepare(
                "UPDATE task_claims SET status = 'cancelled'
                 WHERE task_id = ? AND status = 'accepted'"
            );
            $stmt->execute([(int)$taskId]);
            $cancelled = $stmt->rowCount();

            $this->pdo->commit();

            // 通知被取消的接单人
            if ($cancelled > 0) {
                $stmt = $this->pdo->prepare(
                    "SELECT worker_id FROM task_claims WHERE task_id = ? AND status = 'cancelled'"
                );
                $stmt->execute([(int)$taskId]);
                foreach ($stmt->fetchAll() as $c) {
                    $this->notify((int)$c['worker_id'], 'task_cancelled', (int)$taskId,
                        '雇主已关闭任务《' . $task['title'] . '》，你未交付的认领已被取消');
                }
            }
            $this->notify((int)$employerId, 'task_closed', (int)$taskId,
                '任务《' . $task['title'] . '》已关闭');

            return [true, '任务已关闭' . ($cancelled > 0 ? '，取消了 ' . $cancelled . ' 份未交付认领' : '')];
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log('Task::closeByEmployer 失败: ' . $e->getMessage());
            return [false, '关闭失败，请稍后再试'];
        }
    }

    /** 发送站内通知 */
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
            error_log('Task::notify 失败: ' . $e->getMessage());
        }
    }
}
