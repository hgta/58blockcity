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
}
?>