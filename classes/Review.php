<?php
/**
 * 商品评价类
 */
class Review {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * 创建评价
     */
    public function createReview($data) {
        try {
            // 检查是否已评价
            if ($this->hasReviewed($data['order_item_id'], $data['user_id'])) {
                throw new Exception("您已经评价过该商品了");
            }
            
            $sql = "INSERT INTO reviews 
                    (order_id, order_item_id, product_id, user_id, shop_id, rating, content, images, is_anonymous, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $data['order_id'],
                $data['order_item_id'],
                $data['product_id'],
                $data['user_id'],
                $data['shop_id'],
                $data['rating'],
                $data['content'] ?? '',
                !empty($data['images']) ? json_encode($data['images']) : null,
                $data['is_anonymous'] ?? 0
            ]);
            
            $reviewId = $this->pdo->lastInsertId();
            
            // 更新商品和店铺评分统计
            $this->updateProductRating($data['product_id']);
            $this->updateShopRating($data['shop_id']);
            
            return $reviewId;
        } catch (Exception $e) {
            error_log("创建评价失败: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * 获取商品评价列表
     */
    public function getProductReviews($productId, $page = 1, $limit = 10) {
        try {
            $offset = ($page - 1) * $limit;
            
            $sql = "SELECT r.*, 
                           u.nickname, u.avatar,
                           oi.product_name
                    FROM reviews r
                    INNER JOIN users u ON r.user_id = u.id
                    LEFT JOIN order_items oi ON r.order_item_id = oi.id
                    WHERE r.product_id = ? AND r.status = 'approved'
                    ORDER BY r.created_at DESC
                    LIMIT ? OFFSET ?";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$productId, $limit, $offset]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("获取商品评价失败: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * 获取商品评价总数
     */
    public function getProductReviewCount($productId) {
        try {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) as count FROM reviews WHERE product_id = ? AND status = 'approved'");
            $stmt->execute([$productId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['count'] ?? 0;
        } catch (Exception $e) {
            error_log("获取评价数失败: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * 获取商品评价统计
     */
    public function getProductReviewStats($productId) {
        try {
            $sql = "SELECT 
                        COUNT(*) as total_count,
                        AVG(rating) as avg_rating,
                        COUNT(CASE WHEN rating = 5 THEN 1 END) as five_star,
                        COUNT(CASE WHEN rating = 4 THEN 1 END) as four_star,
                        COUNT(CASE WHEN rating = 3 THEN 1 END) as three_star,
                        COUNT(CASE WHEN rating = 2 THEN 1 END) as two_star,
                        COUNT(CASE WHEN rating = 1 THEN 1 END) as one_star
                    FROM reviews 
                    WHERE product_id = ? AND status = 'approved'";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$productId]);
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("获取评价统计失败: " . $e->getMessage());
            return [
                'total_count' => 0,
                'avg_rating' => 0,
                'five_star' => 0,
                'four_star' => 0,
                'three_star' => 0,
                'two_star' => 0,
                'one_star' => 0
            ];
        }
    }
    
    /**
     * 商家回复评价
     */
    public function replyReview($reviewId, $shopOwnerId, $content) {
        try {
            // 验证评价是否属于该商家的店铺
            $stmt = $this->pdo->prepare("SELECT shop_id FROM reviews WHERE id = ?");
            $stmt->execute([$reviewId]);
            $review = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$review) {
                throw new Exception("评价不存在");
            }
            
            // 验证店铺归属
            $stmt = $this->pdo->prepare("SELECT user_id FROM shops WHERE id = ?");
            $stmt->execute([$review['shop_id']]);
            $shop = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$shop || $shop['user_id'] != $shopOwnerId) {
                throw new Exception("无权回复该评价");
            }
            
            $sql = "UPDATE reviews SET reply_content = ?, reply_at = NOW() WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([$content, $reviewId]);
        } catch (Exception $e) {
            error_log("回复评价失败: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * 检查用户是否已评价某订单项
     */
    public function hasReviewed($orderItemId, $userId) {
        try {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) as count FROM reviews WHERE order_item_id = ? AND user_id = ?");
            $stmt->execute([$orderItemId, $userId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['count'] > 0;
        } catch (Exception $e) {
            error_log("检查评价状态失败: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 获取订单中可评价的商品列表
     */
    public function getReviewableItems($orderId, $userId) {
        try {
            $sql = "SELECT oi.*, p.main_image as product_image
                    FROM order_items oi
                    INNER JOIN orders o ON oi.order_id = o.id
                    INNER JOIN products p ON oi.product_id = p.id
                    WHERE oi.order_id = ? AND o.user_id = ? AND o.status = 'completed'
                    AND oi.id NOT IN (SELECT order_item_id FROM reviews WHERE user_id = ?)";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$orderId, $userId, $userId]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("获取可评价商品失败: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * 更新商品评分统计
     */
    private function updateProductRating($productId) {
        try {
            $stats = $this->getProductReviewStats($productId);
            
            $stmt = $this->pdo->prepare("UPDATE products SET rating = ?, review_count = ? WHERE id = ?");
            $stmt->execute([
                round($stats['avg_rating'], 2),
                $stats['total_count'],
                $productId
            ]);
        } catch (Exception $e) {
            error_log("更新商品评分失败: " . $e->getMessage());
        }
    }
    
    /**
     * 更新店铺评分统计
     */
    private function updateShopRating($shopId) {
        try {
            $sql = "SELECT AVG(rating) as avg_rating, COUNT(*) as total_count FROM reviews WHERE shop_id = ? AND status = 'approved'";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$shopId]);
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $stmt = $this->pdo->prepare("UPDATE shops SET rating = ?, review_count = ? WHERE id = ?");
            $stmt->execute([
                round($stats['avg_rating'] ?? 5, 2),
                $stats['total_count'] ?? 0,
                $shopId
            ]);
        } catch (Exception $e) {
            error_log("更新店铺评分失败: " . $e->getMessage());
        }
    }
}
