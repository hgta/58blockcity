<?php
/**
 * 统一站内信 - Message 类
 */
class Message
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * 发送站内信
     */
    public function send($fromUserId, $toUserId, $message)
    {
        $msg = trim((string)$message);
        if (empty($msg) || mb_strlen($msg) < 1) return false;
        $stmt = $this->pdo->prepare(
            "INSERT INTO user_messages (from_user_id, to_user_id, message) VALUES (?, ?, ?)"
        );
        return $stmt->execute([intval($fromUserId), intval($toUserId), $msg]);
    }

    /**
     * 获取某用户的会话列表（按最新消息排序）
     */
    public function getConversations($userId, $limit = 20)
    {
        $uid = intval($userId);
        $sql = "
            SELECT 
                CASE WHEN m.from_user_id = {$uid} THEN m.to_user_id ELSE m.from_user_id END AS partner_id,
                u.username, u.avatar as user_avatar,
                MAX(m.created_at) AS last_time,
                SUM(CASE WHEN m.to_user_id = {$uid} AND m.is_read = 0 THEN 1 ELSE 0 END) AS unread_count
            FROM user_messages m
            LEFT JOIN users u ON u.id = CASE WHEN m.from_user_id = {$uid} THEN m.to_user_id ELSE m.from_user_id END
            WHERE m.from_user_id = {$uid} OR m.to_user_id = {$uid}
            GROUP BY partner_id
            ORDER BY last_time DESC
            LIMIT " . intval($limit);
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 获取两人之间的消息列表
     */
    public function getMessages($userA, $userB, $page = 1, $perPage = 30)
    {
        $a = intval($userA); $b = intval($userB);
        $offset = (max(1, intval($page)) - 1) * $perPage;
        $sql = "SELECT m.*, u.username, u.avatar as user_avatar 
                FROM user_messages m 
                LEFT JOIN users u ON m.from_user_id = u.id 
                WHERE (m.from_user_id = {$a} AND m.to_user_id = {$b}) 
                   OR (m.from_user_id = {$b} AND m.to_user_id = {$a})
                ORDER BY m.created_at DESC 
                LIMIT {$perPage} OFFSET {$offset}";
        $stmt = $this->pdo->query($sql);
        return array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * 获取未读消息总数
     */
    public function getUnreadCount($userId)
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM user_messages WHERE to_user_id = ? AND is_read = 0"
        );
        $stmt->execute([intval($userId)]);
        return intval($stmt->fetchColumn());
    }

    /**
     * 标记某联系人发来的所有消息为已读
     */
    public function markRead($userId, $fromUserId)
    {
        $stmt = $this->pdo->prepare(
            "UPDATE user_messages SET is_read = 1 WHERE to_user_id = ? AND from_user_id = ? AND is_read = 0"
        );
        return $stmt->execute([intval($userId), intval($fromUserId)]);
    }

    /**
     * 获取两人之间最新几条消息（模态框用）
     */
    public function getRecentMessages($userA, $userB, $limit = 6)
    {
        $a = intval($userA); $b = intval($userB);
        $sql = "SELECT m.*, u.username 
                FROM user_messages m 
                LEFT JOIN users u ON m.from_user_id = u.id 
                WHERE (m.from_user_id = {$a} AND m.to_user_id = {$b}) 
                   OR (m.from_user_id = {$b} AND m.to_user_id = {$a})
                ORDER BY m.created_at DESC 
                LIMIT " . intval($limit);
        $stmt = $this->pdo->query($sql);
        return array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));
    }
}
