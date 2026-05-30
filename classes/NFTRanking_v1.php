<?php
class NFTRanking {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * 城市排行榜
     */
    
    /**
     * 认领最多城市榜
     */
    public function getTopCitiesByClaims($limit = 10, $period = 'all') {
        $periodCondition = $this->getPeriodCondition($period, 'ncu.created_at');
        
        $sql = "SELECT 
                    c.id,
                    c.name,
                    COUNT(DISTINCT ncu.id) as claim_count,
                    COUNT(DISTINCT ncu.nft_id) as unique_nft_count
                FROM cities c
                JOIN nft_city_user ncu ON c.id = ncu.city_id
                WHERE ncu.is_current = 1
                {$periodCondition}
                GROUP BY c.id
                ORDER BY claim_count DESC
                LIMIT ?";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    /**
     * 挂售最多城市榜
     */
    public function getTopCitiesByListings($limit = 10, $period = 'all') {
        $periodCondition = $this->getPeriodCondition($period, 't.created_at');
        
        $sql = "SELECT 
                    c.id,
                    c.name,
                    COUNT(DISTINCT t.id) as listing_count,
                    SUM(t.price) as total_volume
                FROM cities c
                JOIN nft_transactions t ON c.id = t.city_id
                WHERE t.status = 'listed'
                {$periodCondition}
                GROUP BY c.id
                ORDER BY listing_count DESC
                LIMIT ?";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    /**
     * 求购最多城市榜
     */
    public function getTopCitiesByPurchaseRequests($limit = 10, $period = 'all') {
        $periodCondition = $this->getPeriodCondition($period, 'pr.created_at');
        
        $sql = "SELECT 
                    c.id,
                    c.name,
                    COUNT(DISTINCT pr.id) as request_count,
                    SUM(pr.amount) as total_amount
                FROM cities c
                JOIN nft_purchase_requests pr ON c.id = pr.city_id
                WHERE pr.expires_at > NOW()
                {$periodCondition}
                GROUP BY c.id
                ORDER BY request_count DESC
                LIMIT ?";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    /**
     * 成交最多城市榜
     */
    public function getTopCitiesByTransactions($limit = 10, $period = 'all') {
        $periodCondition = $this->getPeriodCondition($period, 't.created_at');
        
        $sql = "SELECT 
                    c.id,
                    c.name,
                    COUNT(DISTINCT t.id) as transaction_count,
                    SUM(t.price) as total_volume
                FROM cities c
                JOIN nft_transactions t ON c.id = t.city_id
                WHERE t.status = 'completed'
                {$periodCondition}
                GROUP BY c.id
                ORDER BY transaction_count DESC
                LIMIT ?";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    /**
     * 头像排行榜
     */
    
    /**
     * 认领最多头像榜
     */
    public function getTopNftsByClaims($limit = 10, $period = 'all') {
        $periodCondition = $this->getPeriodCondition($period, 'ncu.created_at');
        
        $sql = "SELECT 
                    n.id,
                    n.code,
                    n.base_image,
                    COUNT(DISTINCT ncu.id) as claim_count,
                    COUNT(DISTINCT ncu.city_id) as city_count
                FROM nft_avatars n
                JOIN nft_city_user ncu ON n.id = ncu.nft_id
                WHERE ncu.is_current = 1
                {$periodCondition}
                GROUP BY n.id
                ORDER BY claim_count DESC
                LIMIT ?";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    /**
     * 挂售最多头像榜
     */
    public function getTopNftsByListings($limit = 10, $period = 'all') {
        $periodCondition = $this->getPeriodCondition($period, 't.created_at');
        
        $sql = "SELECT 
                    n.id,
                    n.code,
                    n.base_image,
                    COUNT(DISTINCT t.id) as listing_count,
                    AVG(t.price) as avg_price
                FROM nft_avatars n
                JOIN nft_transactions t ON n.id = t.nft_id
                WHERE t.status = 'listed'
                {$periodCondition}
                GROUP BY n.id
                ORDER BY listing_count DESC
                LIMIT ?";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    /**
     * 求购最多头像榜
     */
    public function getTopNftsByPurchaseRequests($limit = 10, $period = 'all') {
        $periodCondition = $this->getPeriodCondition($period, 'pr.created_at');
        
        $sql = "SELECT 
                    n.id,
                    n.code,
                    n.base_image,
                    COUNT(DISTINCT pr.id) as request_count,
                    AVG(pr.amount) as avg_amount
                FROM nft_avatars n
                JOIN nft_purchase_requests pr ON n.id = pr.nft_id
                WHERE pr.expires_at > NOW()
                {$periodCondition}
                GROUP BY n.id
                ORDER BY request_count DESC
                LIMIT ?";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    /**
     * 成交最多头像榜
     */
    public function getTopNftsByTransactions($limit = 10, $period = 'all') {
        $periodCondition = $this->getPeriodCondition($period, 't.created_at');
        
        $sql = "SELECT 
                    n.id,
                    n.code,
                    n.base_image,
                    COUNT(DISTINCT t.id) as transaction_count,
                    SUM(t.price) as total_volume,
                    AVG(t.price) as avg_price
                FROM nft_avatars n
                JOIN nft_transactions t ON n.id = t.nft_id
                WHERE t.status = 'completed'
                {$periodCondition}
                GROUP BY n.id
                ORDER BY transaction_count DESC
                LIMIT ?";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    /**
     * 用户排行榜
     */
    
    /**
     * 认领最多用户榜
     */
    public function getTopUsersByClaims($limit = 10, $period = 'all') {
        $periodCondition = $this->getPeriodCondition($period, 'ncu.created_at');
        
        $sql = "SELECT 
                    u.id,
                    u.username,
                    u.avatar,
                    COUNT(DISTINCT ncu.id) as claim_count,
                    COUNT(DISTINCT ncu.nft_id) as unique_nft_count,
                    COUNT(DISTINCT ncu.city_id) as city_count
                FROM users u
                JOIN nft_city_user ncu ON u.id = ncu.user_id
                WHERE ncu.is_current = 1
                {$periodCondition}
                GROUP BY u.id
                ORDER BY claim_count DESC
                LIMIT ?";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    /**
     * 挂售最多用户榜
     */
    public function getTopUsersByListings($limit = 10, $period = 'all') {
        $periodCondition = $this->getPeriodCondition($period, 't.created_at');
        
        $sql = "SELECT 
                    u.id,
                    u.username,
                    u.avatar,
                    COUNT(DISTINCT t.id) as listing_count,
                    SUM(t.price) as total_volume,
                    AVG(t.price) as avg_price
                FROM users u
                JOIN nft_transactions t ON u.id = t.seller_id
                WHERE t.status = 'listed'
                {$periodCondition}
                GROUP BY u.id
                ORDER BY listing_count DESC
                LIMIT ?";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    /**
     * 求购最多用户榜
     */
    public function getTopUsersByPurchaseRequests($limit = 10, $period = 'all') {
        $periodCondition = $this->getPeriodCondition($period, 'pr.created_at');
        
        $sql = "SELECT 
                    u.id,
                    u.username,
                    u.avatar,
                    COUNT(DISTINCT pr.id) as request_count,
                    SUM(pr.amount) as total_amount,
                    AVG(pr.amount) as avg_amount
                FROM users u
                JOIN nft_purchase_requests pr ON u.id = pr.user_id
                WHERE pr.expires_at > NOW()
                {$periodCondition}
                GROUP BY u.id
                ORDER BY request_count DESC
                LIMIT ?";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    /**
     * 成交最多用户榜
     */
    public function getTopUsersByTransactions($limit = 10, $period = 'all') {
        $periodCondition = $this->getPeriodCondition($period, 't.created_at');
        
        $sql = "SELECT 
                    u.id,
                    u.username,
                    u.avatar,
                    COUNT(DISTINCT t.id) as transaction_count,
                    SUM(t.price) as total_volume,
                    AVG(t.price) as avg_price
                FROM users u
                JOIN nft_transactions t ON u.id = t.seller_id
                WHERE t.status = 'completed'
                {$periodCondition}
                GROUP BY u.id
                ORDER BY transaction_count DESC
                LIMIT ?";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    /**
     * 获取时间周期条件
     */
    private function getPeriodCondition($period, $dateField) {
        switch ($period) {
            case 'day':
                return "AND {$dateField} >= DATE_SUB(NOW(), INTERVAL 1 DAY)";
            case 'week':
                return "AND {$dateField} >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
            case 'month':
                return "AND {$dateField} >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
            case 'year':
                return "AND {$dateField} >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
            default:
                return "";
        }
    }
    
    /**
     * 获取综合排行榜数据
     */
    public function getComprehensiveRankings($type = 'all', $limit = 10, $period = 'all') {
        $rankings = [];
        
        if ($type === 'all' || $type === 'city') {
            $rankings['city_claims'] = $this->getTopCitiesByClaims($limit, $period);
            $rankings['city_listings'] = $this->getTopCitiesByListings($limit, $period);
            $rankings['city_purchase_requests'] = $this->getTopCitiesByPurchaseRequests($limit, $period);
            $rankings['city_transactions'] = $this->getTopCitiesByTransactions($limit, $period);
        }
        
        if ($type === 'all' || $type === 'nft') {
            $rankings['nft_claims'] = $this->getTopNftsByClaims($limit, $period);
            $rankings['nft_listings'] = $this->getTopNftsByListings($limit, $period);
            $rankings['nft_purchase_requests'] = $this->getTopNftsByPurchaseRequests($limit, $period);
            $rankings['nft_transactions'] = $this->getTopNftsByTransactions($limit, $period);
        }
        
        if ($type === 'all' || $type === 'user') {
            $rankings['user_claims'] = $this->getTopUsersByClaims($limit, $period);
            $rankings['user_listings'] = $this->getTopUsersByListings($limit, $period);
            $rankings['user_purchase_requests'] = $this->getTopUsersByPurchaseRequests($limit, $period);
            $rankings['user_transactions'] = $this->getTopUsersByTransactions($limit, $period);
        }
        
        return $rankings;
    }
    
    /**
     * 获取用户个人排名
     */
    public function getUserRankings($userId, $period = 'all') {
        $userRankings = [];
        
        // 用户在各榜单的排名
        $userRankings['claims_rank'] = $this->getUserRankInClaims($userId, $period);
        $userRankings['listings_rank'] = $this->getUserRankInListings($userId, $period);
        $userRankings['purchase_requests_rank'] = $this->getUserRankInPurchaseRequests($userId, $period);
        $userRankings['transactions_rank'] = $this->getUserRankInTransactions($userId, $period);
        
        return $userRankings;
    }
    
    /**
     * 获取用户在认领榜的排名
     */
    private function getUserRankInClaims($userId, $period) {
        $periodCondition = $this->getPeriodCondition($period, 'ncu.created_at');
        
        $sql = "SELECT position FROM (
                    SELECT 
                        u.id,
                        ROW_NUMBER() OVER (ORDER BY COUNT(DISTINCT ncu.id) DESC) as position
                    FROM users u
                    JOIN nft_city_user ncu ON u.id = ncu.user_id
                    WHERE ncu.is_current = 1
                    {$periodCondition}
                    GROUP BY u.id
                ) as ranks
                WHERE id = ?";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$userId]);
        return $stmt->fetchColumn();
    }
    
    /**
     * 获取用户在挂售榜的排名
     */
    private function getUserRankInListings($userId, $period) {
        $periodCondition = $this->getPeriodCondition($period, 't.created_at');
        
        $sql = "SELECT position FROM (
                    SELECT 
                        u.id,
                        ROW_NUMBER() OVER (ORDER BY COUNT(DISTINCT t.id) DESC) as position
                    FROM users u
                    JOIN nft_transactions t ON u.id = t.seller_id
                    WHERE t.status = 'listed'
                    {$periodCondition}
                    GROUP BY u.id
                ) as ranks
                WHERE id = ?";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$userId]);
        return $stmt->fetchColumn();
    }
    
    // 其他排名方法的实现类似...
}
?>