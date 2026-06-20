<?php
class Notification {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    public function create($userId, $type, $relatedId, $content) {
        $stmt = $this->pdo->prepare("INSERT INTO notifications 
                                    (user_id, type, related_id, content) 
                                    VALUES (?, ?, ?, ?)");
        return $stmt->execute([$userId, $type, $relatedId, $content]);
    }
    
    public function getUserNotifications($userId, $limit = 10) {
        $stmt = $this->pdo->prepare("SELECT * FROM notifications
                                    WHERE user_id = ?
                                    ORDER BY created_at DESC
                                    LIMIT ?");
        $stmt->execute([$userId, $limit]);
        return $stmt->fetchAll();
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