<?php

class Comment {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Add a new comment for an NFT
     * 
     * @param int $userId The ID of the user posting the comment
     * @param int $nftId The ID of the NFT being commented on
     * @param string $content The comment content
     * @return bool True on success, false on failure
     */
    public function addComment($userId, $nftId, $content) {
        $content = trim($content);
        if (empty($content)) {
            return false;
        }

        // $sql = "INSERT INTO comments (user_id, nft_id, content, created_at) 
                // VALUES (:user_id, :nft_id, :content, NOW())";
				
		$sql = "INSERT INTO comments (user_id, nft_id, content, created_at) 
                VALUES (?, ?, ?, NOW())";
        
        try {
            $stmt = $this->pdo->prepare($sql);
            // $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            // $stmt->bindParam(':nft_id', $nftId, PDO::PARAM_INT);
            // $stmt->bindParam(':content', $content, PDO::PARAM_STR);
			
			$stmt->bindParam(1, $userId, PDO::PARAM_INT);
            $stmt->bindParam(2, $nftId, PDO::PARAM_INT);
            $stmt->bindParam(3, $content, PDO::PARAM_STR);
			//echo "INSERT INTO comments (user_id, nft_id, content, created_at) 
            //    VALUES (".$userId.", ".$nftId.", ". $content .", NOW())";die();
            return $stmt->execute();
        } catch (PDOException $e) {
            // Log error or handle as needed
            error_log("Error adding comment: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all comments for a specific NFT
     * 
     * @param int $nftId The ID of the NFT
     * @return array Array of comment data with user information
     */
    public function getCommentsByNft($nftId) {
        $sql = "SELECT c.*, u.username 
                FROM comments c
                JOIN users u ON c.user_id = u.id
                WHERE c.nft_id = :nft_id
                ORDER BY c.created_at DESC";
        
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':nft_id', $nftId, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            // Log error or handle as needed
            error_log("Error fetching comments: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Delete a comment (only allowed by admin or comment owner)
     * 
     * @param int $commentId The ID of the comment to delete
     * @param int $userId The ID of the user attempting deletion
     * @param bool $isAdmin Whether the user is an admin
     * @return bool True on success, false on failure
     */
    public function deleteComment($commentId, $userId, $isAdmin = false) {
        $sql = "DELETE FROM comments WHERE id = :comment_id";
        
        if (!$isAdmin) {
            $sql .= " AND user_id = :user_id";
        }
        
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':comment_id', $commentId, PDO::PARAM_INT);
            
            if (!$isAdmin) {
                $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            }
            
            return $stmt->execute();
        } catch (PDOException $e) {
            // Log error or handle as needed
            error_log("Error deleting comment: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get comment count for a specific NFT
     * 
     * @param int $nftId The ID of the NFT
     * @return int Number of comments
     */
    public function getCommentCount($nftId) {
        $sql = "SELECT COUNT(*) as count FROM comments WHERE nft_id = :nft_id";
        
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':nft_id', $nftId, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['count'] ?? 0;
        } catch (PDOException $e) {
            // Log error or handle as needed
            error_log("Error counting comments: " . $e->getMessage());
            return 0;
        }
    }
}