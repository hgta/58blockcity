<?php
class Notification {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    /**
     * 创建系统通知（兼容旧接口 + 扩展参数）
     */
    public function create($userId, $type, $relatedId, $content) {
        $stmt = $this->pdo->prepare("INSERT INTO notifications 
                                    (user_id, type, related_id, content) 
                                    VALUES (?, ?, ?, ?)");
        return $stmt->execute([$userId, $type, $relatedId, $content]);
    }

    /**
     * 创建系统通知（带跳转链接）
     */
    public function sendSystemNotify($userId, $type, $relatedId, $content, $relatedUrl = '') {
        try {
            $stmt = $this->pdo->prepare("INSERT INTO notifications 
                                        (user_id, type, related_id, content, related_url, created_at) 
                                        VALUES (?, ?, ?, ?, ?, NOW())");
            return $stmt->execute([$userId, $type, $relatedId, $content, $relatedUrl]);
        } catch (Exception $e) {
            error_log("sendSystemNotify 失败: " . $e->getMessage());
            return false;
        }
    }

    public function getUserNotifications($userId, $limit = 10) {
        $stmt = $this->pdo->prepare("SELECT * FROM notifications
                                    WHERE user_id = ?
                                    ORDER BY created_at DESC
                                    LIMIT " . intval($limit));
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    /**
     * 获取用户通知列表（分页）
     */
    public function getUserNotificationsAll($userId, $page = 1, $perPage = 20) {
        $offset = ($page - 1) * $perPage;
        $stmt = $this->pdo->prepare("SELECT * FROM notifications
                                    WHERE user_id = ?
                                    ORDER BY created_at DESC
                                    LIMIT $perPage OFFSET $offset");
        $stmt->execute([$userId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $cntStmt = $this->pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ?");
        $cntStmt->execute([$userId]);
        $total = (int)$cntStmt->fetchColumn();

        return ['rows' => $rows, 'total' => $total];
    }

    /**
     * 标记所有通知为已读
     */
    public function markAllAsRead($userId) {
        $stmt = $this->pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
        return $stmt->execute([$userId]);
    }

    public function getUnreadCount($userId) {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM notifications
                                    WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$userId]);
        return (int) $stmt->fetchColumn();
    }

    public function markAsRead($notificationId, $userId = null) {
        if ($userId === null) {
            $stmt = $this->pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?");
            return $stmt->execute([$notificationId]);
        }
        $stmt = $this->pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
        return $stmt->execute([$notificationId, $userId]);
    }
}
?>